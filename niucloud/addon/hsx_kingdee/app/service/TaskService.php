<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\service;

use addon\hsx_kingdee\app\support\SyncPlan;
use think\facade\Db;

final class TaskService
{
    public function accept(array $event): array
    {
        if (($event['event_name'] ?? '') !== 'erp.ledger.recorded.v1') return ['skipped' => true];
        SyncPlan::fromEvent($event);
        $site = (int)$event['site_id'];
        $configService = new ConfigService();
        if (!$configService->installed($site)) return ['skipped' => true];
        $config = $configService->get($site);
        if ($config['mode'] !== 'preview' || (int)$event['occurred_at'] < (int)$config['collect_from']) return ['skipped' => true];
        $fact = $event['payload']['fact_key'];
        $identity = [['site_id', '=', $site], ['connection_key', '=', $config['connection_key']], ['fact_key', '=', $fact]];
        $existing = Db::name('hsx_kingdee_task')->where($identity)->find();
        if ($existing) return $this->duplicate($existing, $event);
        $ledger = $event['payload']['ledger'];
        try {
            Db::transaction(function () use ($event, $site, $config, $fact, $ledger): void {
                $id = Db::name('hsx_kingdee_task')->insertGetId([
                    'site_id' => $site, 'connection_key' => $config['connection_key'], 'fact_key' => $fact,
                    'event_id' => $event['event_id'], 'kind' => $event['payload']['kind'],
                    'business_no' => mb_substr((string)($ledger['source_no'] ?? $ledger['biz_no'] ?? $ledger['ledger_no'] ?? ''), 0, 100),
                    'status' => 'pending', 'snapshot_json' => self::json($event), 'plan_json' => '{}',
                    'assignee_uid' => $config['assignee_uid'], 'create_at' => time(), 'update_at' => time(),
                ]);
                self::audit($site, (int)$id, 'receive', 'queued', '业务事实已入队，尚未向金蝶写入');
            });
        } catch (\Throwable $e) {
            $existing = Db::name('hsx_kingdee_task')->where($identity)->find();
            if ($existing) return $this->duplicate($existing, $event);
            throw $e;
        }
        return ['consumer' => 'hsx_kingdee', 'status' => 'processed'];
    }

    private function duplicate(array $task, array $event): array
    {
        $original = json_decode($task['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        if (($original['payload'] ?? null) !== $event['payload']) throw new \InvalidArgumentException('同一业务事实出现不同内容，禁止覆盖原记录');
        return ['consumer' => 'hsx_kingdee', 'status' => 'duplicate'];
    }

    public function process(int $site, int $id): bool
    {
        $configService = new ConfigService();
        if (!$configService->installed($site)) return false;
        $config = $configService->get($site);
        if ($config['mode'] !== 'preview') return false;
        $base = [['site_id', '=', $site], ['id', '=', $id], ['connection_key', '=', $config['connection_key']]];
        $token = bin2hex(random_bytes(16));
        $claim = Db::name('hsx_kingdee_task')->where($base)->where('next_retry_at', '<=', time())
            ->where(function ($q): void {
                $q->whereIn('status', ['pending', 'retry'])->whereOr(function ($stale): void {
                    $stale->where('status', 'processing')->where('lease_until', '<', time());
                });
            })->update(['status' => 'processing', 'lease_token' => $token, 'lease_until' => time() + 120, 'update_at' => time()]);
        if ($claim !== 1) return false;
        $owned = array_merge($base, [['lease_token', '=', $token], ['status', '=', 'processing']]);
        try {
            Db::transaction(function () use ($base, $owned, $site, $id, $config): void {
                $task = Db::name('hsx_kingdee_task')->where($owned)->lock(true)->find();
                if (!$task) return;
                $map = [];
                foreach (Db::name('hsx_kingdee_mapping')->where([['site_id', '=', $site], ['connection_key', '=', $config['connection_key']]])->select()->toArray() as $row) {
                    $map[$row['kind'] . ':' . $row['local_id']] = $row['external_number'];
                }
                $plan = SyncPlan::fromEvent(json_decode($task['snapshot_json'], true, 512, JSON_THROW_ON_ERROR), $map);
                $status = $plan['requirements'] ? 'blocked' : 'preview';
                Db::name('hsx_kingdee_task')->where($owned)->update([
                    'status' => $status, 'plan_json' => self::json($plan), 'reason' => mb_substr(implode('；', $plan['requirements']), 0, 500),
                    'attempts' => (int)$task['attempts'] + 1, 'lease_token' => '', 'lease_until' => 0, 'update_at' => time(),
                ]);
                self::audit($site, $id, 'preflight', $status, $status === 'blocked' ? '预检未通过，请查看待处理事项；未发送外部请求' : '预览已生成；未发送外部请求');
            });
        } catch (\Throwable $error) {
            $task = Db::name('hsx_kingdee_task')->where($owned)->find();
            if (!$task) return false;
            $attempts = (int)$task['attempts'] + 1;
            $permanent = $error instanceof \InvalidArgumentException || $error instanceof \JsonException;
            $status = $permanent || $attempts >= 5 ? 'blocked' : 'retry';
            // Never persist exception text: DB and HTTP exceptions may contain credentials or SQL.
            Db::transaction(function () use ($owned, $site, $id, $attempts, $status): void {
                Db::name('hsx_kingdee_task')->where($owned)->update([
                    'status' => $status, 'attempts' => $attempts, 'reason' => '本地预检异常，请检查服务日志和事件格式',
                    'next_retry_at' => time() + min(1800, 60 * 2 ** min(5, $attempts)),
                    'lease_token' => '', 'lease_until' => 0, 'update_at' => time(),
                ]);
                self::audit($site, $id, 'preflight', $status, '预检异常，未向外部写入');
            });
        }
        return true;
    }

    public function retry(int $site, int $id, int $uid): void
    {
        Db::transaction(function () use ($site, $id, $uid): void {
            $task = Db::name('hsx_kingdee_task')->where([['site_id', '=', $site], ['id', '=', $id]])->lock(true)->find();
            if (!$task || !in_array($task['status'], ['blocked', 'preview', 'retry'], true)) throw new \RuntimeException('任务当前状态不允许重新预检');
            Db::name('hsx_kingdee_task')->where('id', $task['id'])->where('site_id', $site)->update([
                'status' => 'pending', 'next_retry_at' => 0, 'attempts' => 0, 'update_at' => time(),
            ]);
            self::audit($site, $id, 'retry', 'pending', '人工请求重新预检，不重发金蝶单据', $uid);
        });
    }

    public function resolve(int $site, int $id, int $uid, string $note, string $externalNo): void
    {
        $note = trim($note);
        if (mb_strlen($note) < 5 || mb_strlen($note) > 1000 || mb_strlen($externalNo) > 100) throw new \RuntimeException('请填写5至1000字处理说明，外部单号不能超过100字');
        Db::transaction(function () use ($site, $id, $uid, $note, $externalNo): void {
            $where = [['site_id', '=', $site], ['id', '=', $id]];
            $task = Db::name('hsx_kingdee_task')->where($where)->lock(true)->find();
            if (!$task || !in_array($task['status'], ['blocked', 'preview'], true)) throw new \RuntimeException('仅待处理或预览任务允许人工结案');
            Db::name('hsx_kingdee_task')->where($where)->update([
                'status' => 'manual_closed', 'resolution' => $note, 'external_no' => trim($externalNo),
                'resolved_by' => $uid, 'update_at' => time(),
            ]);
            self::audit($site, $id, 'resolve', 'manual_closed', '人工结案；不代表已验证金蝶同步成功', $uid);
        });
    }

    public static function audit(int $site, int $taskId, string $action, string $outcome, string $message, int $uid = 0): void
    {
        Db::name('hsx_kingdee_log')->insert([
            'site_id' => $site, 'task_id' => $taskId, 'action' => $action, 'outcome' => $outcome,
            'message' => mb_substr($message, 0, 1000), 'operator_uid' => $uid, 'create_at' => time(),
        ]);
    }

    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
