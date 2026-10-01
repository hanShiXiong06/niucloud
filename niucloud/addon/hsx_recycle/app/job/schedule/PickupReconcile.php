<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job\schedule;

use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
use addon\hsx_recycle\app\service\core\ExpressOrderService;
use addon\hsx_recycle\app\service\core\express\ExpressOperationLock;
use addon\hsx_recycle\app\service\core\express\ExpressProviderRegistry;
use core\base\BaseJob;
use think\facade\Log;

/** 只核实已登记且声明查询能力的渠道任务；绝不重新叫件或改变回收业务状态。 */
class PickupReconcile extends BaseJob
{
    public const BATCH_SIZE = 20;
    public const MIN_INTERVAL = 300;
    private const TERMINAL_STATES = ['delivered', 'cancelled', 'failed', 'manual', 'not_requested'];

    public function doJob(array $params = []): array
    {
        $counts = ['checked' => 0, 'failed' => 0, 'waiting' => 0, 'skipped' => 0];
        try {
            $rows = $this->candidates($this->now());
        } catch (\Throwable $e) {
            $this->logFailure('读取取件核实任务失败', []);
            $counts['failed']++;
            return $counts;
        }

        foreach (array_slice($rows, 0, self::BATCH_SIZE) as $row) {
            $siteId = (int)($row['site_id'] ?? 0);
            $recordId = (int)($row['id'] ?? 0);
            $thirdOrderNo = trim((string)($row['third_order_no'] ?? ''));
            if ($siteId <= 0 || $recordId <= 0 || $thirdOrderNo === '') {
                $counts['skipped']++;
                continue;
            }
            try {
                $outcome = $this->withLock($siteId, $thirdOrderNo, function () use ($siteId, $recordId, $thirdOrderNo) {
                    // 等锁期间可能已收到回调或人工刷新；必须重新读取后再判断。
                    $current = $this->readRecord($siteId, $recordId);
                    $now = $this->now();
                    if (!$current || (string)($current['third_order_no'] ?? '') !== $thirdOrderNo || !$this->eligible($current, $now)) return 'skipped';
                    $data = $this->decode($current['api_response'] ?? []);
                    $meta = $this->decode($data['pickup_reconcile'] ?? []);
                    $meta['last_checked_at'] = $now;

                    // 标识需求由原渠道声明；缺标识不能推定未下单，更不能换渠道重下。
                    $missing = $this->missingQueryIdentifiers($data);
                    if ($missing) {
                        $meta['next_check_at'] = $now + 86400;
                        $meta['last_error_code'] = 'missing_query_identifier';
                        $meta['last_error'] = '尚未取得原渠道所需的查询标识，等待回调或管理员核实；未重新叫件';
                        $this->saveMeta($siteId, $recordId, $meta);
                        return 'waiting';
                    }

                    // 查询前占位，进程中断也至少间隔五分钟；与人工刷新的 last_query_at 共用限频信息。
                    $meta['next_check_at'] = $now + self::MIN_INTERVAL;
                    $meta['attempt_count'] = (int)($meta['attempt_count'] ?? 0) + 1;
                    $this->saveMeta($siteId, $recordId, $meta, $now);
                    try {
                        $this->queryDetail($siteId, $thirdOrderNo);
                        $meta['failure_count'] = 0;
                        $meta['last_success_at'] = $now;
                        $meta['last_error_code'] = '';
                        $meta['last_error'] = '';
                        $meta['next_check_at'] = $this->now() + self::MIN_INTERVAL;
                        // queryDetail 会同步取件事实；只合并调度元数据，不覆盖查询/回调的新状态。
                        $this->saveMeta($siteId, $recordId, $meta);
                        return 'checked';
                    } catch (\Throwable $e) {
                        $meta = $this->failureMeta($meta, $e, $this->now());
                        $this->saveMeta($siteId, $recordId, $meta);
                        $this->logFailure('取件自动核实未完成，已持久化退避', [
                            'site_id' => $siteId, 'record_id' => $recordId,
                            'error_code' => $meta['last_error_code'], 'next_check_at' => $meta['next_check_at'],
                        ]);
                        return 'failed';
                    }
                });
                $counts[$outcome]++;
            } catch (\Throwable $e) {
                // 单条锁争用/存储异常不阻断后续记录，不输出凭证、客户地址或上游原始错误。
                $counts['failed']++;
                $this->logFailure('取件自动核实记录暂未处理', ['site_id' => $siteId, 'record_id' => $recordId]);
            }
        }
        return $counts;
    }

    protected function candidates(int $now): array
    {
        $providers = array_keys($this->providerRequirements());
        if (!$providers) return [];
        $placeholders = implode(',', array_fill(0, count($providers), '?'));
        // 在 SQL 中排除退避中的记录，避免前二十条长退避任务饿死后面的可查记录。
        $json = "CASE WHEN JSON_VALID(api_response) THEN api_response ELSE '{}' END";
        $number = static function (string $path) use ($json): string {
            return "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT({$json}, '{$path}')) AS UNSIGNED), 0)";
        };
        return ExpressOrderRecord::whereNotIn('order_status', self::TERMINAL_STATES)
            ->field('id,site_id,third_order_no,order_status,create_at,api_response')
            ->where('third_order_no', '<>', '')
            ->where('create_at', '<=', $now - self::MIN_INTERVAL)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$json}, '$.provider')) IN ({$placeholders})", $providers)
            ->whereRaw($number('$.pickup_reconcile.next_check_at') . ' <= ?', [$now])
            ->whereRaw('GREATEST(' . $number('$.last_query_at') . ', ' . $number('$.pickup_reconcile.last_checked_at') . ') <= ?', [$now - self::MIN_INTERVAL])
            ->order('update_at asc,id asc')->limit(self::BATCH_SIZE)->select()->toArray();
    }

    protected function eligible(array $record, int $now): bool
    {
        $data = $this->decode($record['api_response'] ?? []);
        if (!array_key_exists((string)($data['provider'] ?? ''), $this->providerRequirements())) return false;
        foreach ([(string)($data['booking_state'] ?? ''), (string)($record['order_status'] ?? '')] as $state) {
            if (in_array($state, self::TERMINAL_STATES, true)) return false;
        }
        $meta = $this->decode($data['pickup_reconcile'] ?? []);
        $last = max((int)($data['last_query_at'] ?? 0), (int)($meta['last_checked_at'] ?? 0), (int)($record['create_at'] ?? 0));
        return $now - $last >= self::MIN_INTERVAL && $now >= (int)($meta['next_check_at'] ?? 0);
    }

    protected function providerRequirements(): array
    {
        return (new ExpressProviderRegistry())->queryRequirements();
    }

    protected function missingQueryIdentifiers(array $data): array
    {
        $requirements = $this->providerRequirements()[(string)($data['provider'] ?? '')] ?? [];
        return array_values(array_filter($requirements, static function (string $field) use ($data): bool {
            return !isset($data[$field]) || !is_scalar($data[$field]) || trim((string)$data[$field]) === '';
        }));
    }

    protected function failureMeta(array $meta, \Throwable $error, int $now): array
    {
        $failures = max(1, (int)($meta['failure_count'] ?? 0) + 1);
        $configurationError = (bool)preg_match('/账号|账户|凭证|配置|权限|签名|停用|未启用|auth|credential|signature|account|config|disabled|permission|forbidden/i', $error->getMessage());
        $delay = $configurationError
            ? min(86400, 21600 * (2 ** min(2, $failures - 1)))
            : min(21600, self::MIN_INTERVAL * (2 ** min(7, $failures - 1)));
        $meta['failure_count'] = $failures;
        $meta['last_error_at'] = $now;
        $meta['next_check_at'] = $now + $delay;
        $meta['last_error_code'] = $configurationError ? 'provider_configuration' : 'query_unavailable';
        $meta['last_error'] = $configurationError
            ? '原渠道账号或配置无法用于核实，请管理员恢复原账号配置；未换账号查询、未重新叫件'
            : '渠道查询暂未确认成功，将延后核实；未重新叫件';
        return $meta;
    }

    protected function readRecord(int $siteId, int $recordId): ?array
    {
        $record = ExpressOrderRecord::where('site_id', $siteId)->find($recordId);
        return $record ? $record->toArray() : null;
    }

    protected function saveMeta(int $siteId, int $recordId, array $meta, ?int $queryAt = null): void
    {
        $record = ExpressOrderRecord::where('site_id', $siteId)->find($recordId);
        if (!$record) throw new \RuntimeException('record no longer exists');
        $data = $this->decode($record->api_response);
        $data['pickup_reconcile'] = $meta;
        if ($queryAt !== null) $data['last_query_at'] = $queryAt;
        $record->save(['api_response' => $data]);
    }

    protected function queryDetail(int $siteId, string $thirdOrderNo): void
    {
        (new ExpressOrderService())->getOrderDetail($siteId, ['thirdOrderNo' => $thirdOrderNo]);
    }

    protected function withLock(int $siteId, string $key, callable $operation)
    {
        return ExpressOperationLock::run($siteId, $key, $operation);
    }

    protected function decode($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function now(): int { return time(); }
    protected function logFailure(string $message, array $context): void { Log::warning($message, $context); }
}
