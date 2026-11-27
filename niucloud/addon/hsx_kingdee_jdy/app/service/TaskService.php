<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\service;

use addon\hsx_kingdee_jdy\app\support\Plan;
use addon\hsx_kingdee_jdy\app\support\Problem;
use think\facade\Db;

final class TaskService
{
    public function accept(array $event): array
    {
        if (($event['event_name'] ?? '') !== 'erp.ledger.recorded.v1') return [];
        Plan::validate($event);
        $site = (int)$event['site_id'];
        $service = new ConfigService();
        if (!$service->installed($site)) return [];
        $config = $service->get($site);
        if ($config['mode'] !== 'preview' || (int)$event['occurred_at'] < (int)$config['collect_from']) return [];
        $where = [['site_id', '=', $site], ['connection_key', '=', $config['connection_key']], ['fact_key', '=', $event['payload']['fact_key']]];
        $existing = Db::name('hsx_kingdee_jdy_task')->where($where)->find();
        if ($existing) return $this->duplicate($existing, $event);
        try {
            Db::transaction(function () use ($site, $config, $event): void {
                $row = $event['payload']['ledger'];
                $id = Db::name('hsx_kingdee_jdy_task')->insertGetId([
                    'site_id' => $site, 'connection_key' => $config['connection_key'], 'fact_key' => $event['payload']['fact_key'],
                    'event_id' => $event['event_id'], 'kind' => $event['payload']['kind'],
                    'business_no' => mb_substr((string)($row['source_no'] ?? $row['biz_no'] ?? $row['ledger_no'] ?? ''), 0, 100),
                    'status' => 'pending', 'snapshot_json' => self::json($event), 'plan_json' => '{}',
                    'assignee_uid' => $config['assignee_uid'], 'create_at' => time(), 'update_at' => time(),
                ]);
                self::audit($site, (int)$id, 'receive', 'queued', '已接收ERP事实，尚未向精斗云写入');
            });
        } catch (\Throwable $e) {
            $existing = Db::name('hsx_kingdee_jdy_task')->where($where)->find();
            if ($existing) return $this->duplicate($existing, $event);
            throw $e;
        }
        return ['consumer' => 'hsx_kingdee_jdy', 'status' => 'processed'];
    }

    private function duplicate(array $task, array $event): array
    {
        $original = json_decode($task['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        if (($original['payload'] ?? null) !== $event['payload']) throw new Problem('FACT_CONFLICT', '同一ERP事实内容冲突，已保留原始记录，不允许覆盖');
        return ['consumer' => 'hsx_kingdee_jdy', 'status' => 'duplicate'];
    }

    public function process(int $site, int $id): bool
    {
        $service = new ConfigService();
        if (!$service->installed($site)) return false;
        $config = $service->get($site);
        if ($config['mode'] !== 'preview') return false;
        $where = [['site_id', '=', $site], ['id', '=', $id], ['connection_key', '=', $config['connection_key']]];
        $lease = bin2hex(random_bytes(16));
        $claimed = Db::name('hsx_kingdee_jdy_task')->where($where)->where('next_retry_at', '<=', time())
            ->where(static function ($query): void {
                $query->whereIn('status', ['pending', 'retry'])->whereOr(static function ($stale): void {
                    $stale->where('status', 'processing')->where('lease_until', '<', time());
                });
            })->update(['status' => 'processing', 'lease_token' => $lease, 'lease_until' => time() + 120, 'update_at' => time()]);
        if ($claimed !== 1) return false;
        $owned = array_merge($where, [['lease_token', '=', $lease], ['status', '=', 'processing']]);
        try {
            Db::transaction(function () use ($site, $id, $owned, $config): void {
                $task = Db::name('hsx_kingdee_jdy_task')->where($owned)->lock(true)->find();
                if (!$task) return;
                $maps = [];
                foreach (Db::name('hsx_kingdee_jdy_mapping')->where('site_id', $site)->where('connection_key', $config['connection_key'])->select()->toArray() as $map) {
                    $maps[$map['kind'] . ':' . $map['local_id']] = $map;
                }
                $plan = Plan::fromEvent(json_decode($task['snapshot_json'], true, 512, JSON_THROW_ON_ERROR), $maps);
                $status = $plan['requirements'] ? 'blocked' : 'prepared';
                Db::name('hsx_kingdee_jdy_task')->where($owned)->update([
                    'status' => $status, 'plan_json' => self::json($plan), 'reason' => mb_substr(implode('；', $plan['requirements']), 0, 1000),
                    'attempts' => (int)$task['attempts'] + 1, 'lease_token' => '', 'lease_until' => 0, 'update_at' => time(),
                ]);
                self::audit($site, $id, 'preflight', $status, $status === 'prepared'
                    ? '本地草稿已生成；等待真实账套验收，未写单' : '资料或业务规则需要人工核对，未写单');
            });
        } catch (\Throwable $error) {
            $task = Db::name('hsx_kingdee_jdy_task')->where($owned)->find();
            if (!$task) return false;
            $attempt = (int)$task['attempts'] + 1;
            $status = $error instanceof Problem || $error instanceof \JsonException || $attempt >= 5 ? 'blocked' : 'retry';
            Db::transaction(function () use ($site, $id, $owned, $attempt, $status): void {
                Db::name('hsx_kingdee_jdy_task')->where($owned)->update([
                    'status' => $status, 'attempts' => $attempt, 'reason' => '本地预检异常，请核查ERP来源事实或服务状态',
                    'next_retry_at' => time() + min(1800, 30 * 2 ** min(6, $attempt)),
                    'lease_token' => '', 'lease_until' => 0, 'update_at' => time(),
                ]);
                self::audit($site, $id, 'preflight', $status, '预检未完成，未发出外部写请求');
            });
        }
        return true;
    }

    public function retry(int $site, int $id, int $uid): void
    {
        if ((new ConfigService())->get($site)['mode'] !== 'preview') throw new Problem('MODE_DISABLED', '请先开启本地预检');
        Db::transaction(function () use ($site, $id, $uid): void {
            $where = [['site_id', '=', $site], ['id', '=', $id]];
            $task = Db::name('hsx_kingdee_jdy_task')->where($where)->lock(true)->find();
            if (!$task || !in_array($task['status'], ['blocked', 'prepared', 'retry'], true)) throw new Problem('TASK_STATE', '此任务当前不能重新预检');
            Db::name('hsx_kingdee_jdy_task')->where($where)->update(['status' => 'pending', 'next_retry_at' => 0, 'attempts' => 0, 'update_at' => time()]);
            self::audit($site, $id, 'retry', 'pending', '人工请求重新预检，非重发精斗云单据', $uid);
        });
    }

    public function resolve(int $site, int $id, int $uid, string $note, string $externalNo): void
    {
        $note = trim($note);
        $externalNo = trim($externalNo);
        if (mb_strlen($note) < 5 || mb_strlen($note) > 1000 || mb_strlen($externalNo) > 100) throw new Problem('RESOLUTION_INVALID', '请填写5至1000字的处理说明，外部单号最多100字');
        Db::transaction(function () use ($site, $id, $uid, $note, $externalNo): void {
            $where = [['site_id', '=', $site], ['id', '=', $id]];
            $task = Db::name('hsx_kingdee_jdy_task')->where($where)->lock(true)->find();
            if (!$task || !in_array($task['status'], ['blocked', 'prepared'], true)) throw new Problem('TASK_STATE', '只能对待处理或已预检任务进行人工结案');
            Db::name('hsx_kingdee_jdy_task')->where($where)->update(['status' => 'manual_closed', 'resolution' => $note,
                'external_no' => $externalNo, 'resolved_by' => $uid, 'update_at' => time()]);
            self::audit($site, $id, 'resolve', 'manual_closed', '人工结案，未验证外部单据，不代表自动同步成功', $uid);
        });
    }

    public static function json(array $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }

    public static function audit(int $site, int $task, string $action, string $outcome, string $message, int $uid = 0): void
    {
        Db::name('hsx_kingdee_jdy_log')->insert(['site_id' => $site, 'task_id' => $task, 'action' => $action,
            'outcome' => $outcome, 'message' => mb_substr($message, 0, 1000), 'operator_uid' => $uid, 'create_at' => time()]);
    }
}
