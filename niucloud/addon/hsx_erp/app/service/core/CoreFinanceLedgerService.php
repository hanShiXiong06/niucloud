<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\model\FinanceReceivable;
use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 财务-账务核心(事件驱动落库)
 *
 * 由监听器调用: 把回收/销售发来的"应付/应收事实"幂等落库。
 * 不依赖 admin 请求上下文(site_id 等全部由事件 payload 显式传入)。
 * 幂等: 以 (site_id, event_id) 唯一键去重, 重复事件直接返回已存在记录。
 */
class CoreFinanceLedgerService extends BaseCoreService
{
    /**
     * 记一笔应付(我欠往来单位)
     * @param array $payload finance.payable.created.v1 的 payload
     * @return int 应付记录ID(0=被忽略)
     */
    public function recordPayable(array $payload): int
    {
        return $this->record(new FinancePayable(), $payload);
    }

    /**
     * 记一笔应收(往来单位欠我)
     */
    public function recordReceivable(array $payload): int
    {
        return $this->record(new FinanceReceivable(), $payload);
    }

    /**
     * @param FinancePayable|FinanceReceivable $model
     */
    private function record($model, array $payload): int
    {
        $siteId  = (int)($payload['site_id'] ?? 0);
        $eventId = (string)($payload['event_id'] ?? '');
        $amount  = round((float)($payload['amount'] ?? 0), 2);
        $cpId    = (int)($payload['counterparty_id'] ?? 0);

        // 基本校验: 金额必须>0, 必须有往来单位与幂等键, 否则忽略(不抛, 避免打断业务流)
        if ($siteId <= 0 || $eventId === '' || $amount <= 0 || $cpId <= 0) {
            Log::warning('[erp_finance] 忽略非法账务事件: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
            return 0;
        }

        // 幂等
        $exist = $model->where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
        if (!$exist->isEmpty()) {
            return (int)$exist->id;
        }

        $now = time();
        $row = $model->create([
            'site_id'           => $siteId,
            'counterparty_id'   => $cpId,
            'counterparty_name' => (string)($payload['counterparty_name'] ?? ''),
            'amount'            => $amount,
            'settled_amount'    => 0,
            'status'            => FinanceDict::STATUS_PENDING,
            'source_type'       => (string)($payload['source_type'] ?? ''),
            'source_no'         => (string)($payload['source_no'] ?? ''),
            'source_device_id'  => (int)($payload['source_device_id'] ?? 0),
            'event_id'          => $eventId,
            'occurred_at'       => (int)($payload['occurred_at'] ?? $now),
            'remark'            => (string)($payload['remark'] ?? ''),
            'ext_json'          => isset($payload['ext']) ? json_encode($payload['ext'], JSON_UNESCAPED_UNICODE) : null,
            'create_time'       => $now,
            'update_time'       => $now,
        ]);
        return (int)$row->id;
    }
}
