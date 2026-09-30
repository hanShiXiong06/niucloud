<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\agent;

use addon\phone_shop\app\job\AgentReferenceSync;
use core\base\BaseCoreService;
use think\facade\Db;
use think\facade\Log;

/** 基础资料同步共用执行器：浏览器分批推进，定时任务在队列推进。 */
class AgentReferenceSyncService extends BaseCoreService
{
    public const INTERVALS = [15, 30, 60, 180, 360, 720, 1440];
    private const LEASE_SECONDS = 1800;

    public static function settings(array $data, array $current = []): array
    {
        $mode = (string)($data['ref_sync_mode'] ?? $current['ref_sync_mode'] ?? 'realtime');
        if (!array_key_exists('ref_sync_mode', $data) && array_key_exists('subscribe_category', $data)) {
            $mode = (int)$data['subscribe_category'] === 1 ? 'realtime' : 'manual';
        } elseif (!array_key_exists('ref_sync_mode', $data) && (int)($current['subscribe_category'] ?? 1) !== 1) {
            $mode = 'manual';
        }
        if (!in_array($mode, ['manual', 'realtime', 'interval'], true)) {
            throw new \InvalidArgumentException('请选择有效的基础资料同步方式');
        }
        $interval = $data['ref_sync_interval'] ?? $current['ref_sync_interval'] ?? 60;
        if (filter_var($interval, FILTER_VALIDATE_INT) === false || !in_array((int)$interval, self::INTERVALS, true)) {
            throw new \InvalidArgumentException('请选择有效的同步周期');
        }
        $create = $data['ref_auto_create_category'] ?? $current['ref_auto_create_category'] ?? 1;
        if (!in_array($create, [0, 1, '0', '1', false, true], true)) {
            throw new \InvalidArgumentException('缺失分类处理方式不正确');
        }
        return [
            'ref_sync_mode' => $mode, 'ref_sync_interval' => (int)$interval,
            'ref_auto_create_category' => (int)$create, 'subscribe_category' => $mode === 'manual' ? 0 : 1,
        ];
    }

    public static function nextTime(array $relation, int $now): int
    {
        return (int)($relation['status'] ?? 0) === 1 && (int)($relation['subscribe_category'] ?? 0) === 1
            && ($relation['ref_sync_mode'] ?? '') === 'interval'
            ? $now + max(15, (int)($relation['ref_sync_interval'] ?? 60)) * 60 : 0;
    }

    public function info(int $masterSiteId, int $agentSiteId): array
    {
        (new AgentSyncMonitorService())->ensureSchema();
        return $this->publicInfo($this->relation($masterSiteId, $agentSiteId));
    }

    public function start(int $masterSiteId, int $agentSiteId, string $trigger = 'manual'): array
    {
        (new AgentSyncMonitorService())->ensureSchema();
        return Db::transaction(function () use ($masterSiteId, $agentSiteId, $trigger) {
            $row = $this->relation($masterSiteId, $agentSiteId, true);
            if ((int)$row['status'] !== 1) throw new \RuntimeException('站点跟随关系已停用');
            $now = time();
            if (in_array($row['ref_sync_status'], ['queued', 'running'], true)
                && (int)$row['ref_sync_started_at'] > $now - self::LEASE_SECONDS) {
                throw new \RuntimeException('已有基础资料同步任务，请等待完成或继续未完成的手动同步');
            }
            if ($trigger === 'auto' && ((int)$row['subscribe_category'] !== 1 || $row['ref_sync_mode'] !== 'interval'
                    || (int)$row['ref_sync_next_time'] > $now)) {
                throw new \RuntimeException('同步周期尚未到达或定时同步已关闭');
            }
            $types = [];
            foreach ((new RefDataSyncService())->typeNames() as $type => $name) {
                $types[$type] = ['name' => $name, 'scanned' => 0, 'success' => 0, 'failed' => 0];
            }
            $result = [
                'trigger' => $trigger, 'revision' => 0, 'type_index' => 0, 'cursor' => 0,
                'scanned' => 0, 'success' => 0, 'failed' => 0, 'types' => $types, 'errors' => [], 'done' => false,
            ];
            $update = [
                'ref_sync_token' => bin2hex(random_bytes(16)),
                'ref_sync_status' => $trigger === 'manual' ? 'running' : 'queued',
                'ref_sync_started_at' => $now, 'ref_sync_result' => $this->encode($result),
                'ref_sync_next_time' => self::nextTime($row, $now),
            ];
            Db::name('phone_shop_agent')->where('id', '=', $row['id'])->update($update);
            return $this->publicInfo(array_merge($row, $update));
        });
    }

    /** revision 阻止浏览器重试或重复队列消息重复累计同一批。 */
    public function step(int $masterSiteId, int $agentSiteId, string $token, int $revision): array
    {
        try {
            return Db::transaction(function () use ($masterSiteId, $agentSiteId, $token, $revision) {
                $row = $this->relation($masterSiteId, $agentSiteId, true);
                if ($token === '' || !hash_equals((string)$row['ref_sync_token'], $token)) {
                    throw new \RuntimeException('同步批次已失效，请刷新同步结果');
                }
                $result = $this->result($row);
                if (!empty($result['done']) || $revision < (int)$result['revision']) return $this->publicInfo($row);
                if ($revision !== (int)$result['revision']) throw new \RuntimeException('同步进度不一致，请刷新后继续');
                if ((int)$row['status'] !== 1) throw new \RuntimeException('站点跟随关系已停用，同步已停止');
                if (($result['trigger'] ?? '') === 'auto' && ($row['ref_sync_mode'] !== 'interval' || (int)$row['subscribe_category'] !== 1)) {
                    throw new \RuntimeException('定时同步已关闭，同步已停止');
                }
                $names = (new RefDataSyncService())->typeNames();
                $keys = array_keys($names);
                $index = (int)$result['type_index'];
                $type = $keys[$index];
                $batch = (new RefDataSyncService())->syncBatch($type, $masterSiteId, $agentSiteId, (int)$result['cursor']);
                foreach (['scanned', 'success', 'failed'] as $key) {
                    $result[$key] += $batch[$key];
                    $result['types'][$type][$key] += $batch[$key];
                }
                $result['errors'] = array_slice(array_merge($result['errors'], $batch['errors']), 0, 20);
                $result['cursor'] = $batch['done'] ? 0 : $batch['cursor'];
                $result['type_index'] = $batch['done'] ? $index + 1 : $index;
                $result['done'] = $result['type_index'] >= count($keys);
                $result['revision']++;
                $update = [
                    'ref_sync_status' => $result['done'] ? ($result['failed'] > 0 ? 'partial' : 'success') : 'running',
                    'ref_sync_started_at' => time(), 'ref_sync_result' => $this->encode($result),
                ];
                if ($result['done']) {
                    $update['ref_sync_last_time'] = time();
                    $update['ref_sync_next_time'] = self::nextTime($row, time());
                }
                Db::name('phone_shop_agent')->where('id', '=', $row['id'])->update($update);
                return $this->publicInfo(array_merge($row, $update));
            });
        } catch (\Throwable $e) {
            $this->fail($masterSiteId, $agentSiteId, $token, $e->getMessage());
            throw $e;
        }
    }

    public function fail(int $masterSiteId, int $agentSiteId, string $token, string $message): void
    {
        if ($token === '') return;
        Db::transaction(function () use ($masterSiteId, $agentSiteId, $token, $message) {
            $row = Db::name('phone_shop_agent')->where([
                ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId], ['ref_sync_token', '=', $token],
            ])->lock(true)->find();
            if (!$row || !in_array($row['ref_sync_status'], ['queued', 'running'], true)) return;
            $result = $this->result($row);
            $result['done'] = true;
            $result['error_message'] = mb_substr($message, 0, 500);
            Db::name('phone_shop_agent')->where('id', '=', $row['id'])->update([
                'ref_sync_status' => 'failed', 'ref_sync_last_time' => time(),
                'ref_sync_next_time' => self::nextTime($row, time()), 'ref_sync_result' => $this->encode($result),
            ]);
        });
    }

    public function queue(int $masterSiteId, int $agentSiteId, string $trigger = 'relation'): array
    {
        $info = $this->start($masterSiteId, $agentSiteId, $trigger);
        try {
            AgentReferenceSync::dispatch(['masterSiteId' => $masterSiteId, 'agentSiteId' => $agentSiteId, 'token' => $info['token']]);
        } catch (\Throwable $e) {
            $this->fail($masterSiteId, $agentSiteId, $info['token'], $e->getMessage());
            throw $e;
        }
        return $info;
    }

    public function tick(): void
    {
        (new AgentSyncMonitorService())->ensureSchema();
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $rows = Db::name('phone_shop_agent')->where([
            ['master_site_id', '=', $masterSiteId], ['status', '=', 1], ['subscribe_category', '=', 1],
            ['ref_sync_mode', '=', 'interval'], ['ref_sync_next_time', '<=', time()],
        ])->where(function ($q) {
            $q->whereNotIn('ref_sync_status', ['queued', 'running'])
                ->whereOr('ref_sync_started_at', '<=', time() - self::LEASE_SECONDS);
        })->order('ref_sync_next_time asc,id asc')->limit(20)->select()->toArray();
        foreach ($rows as $row) {
            try {
                $this->queue($masterSiteId, (int)$row['agent_site_id'], 'auto');
            } catch (\Throwable $e) {
                Log::write('[phone_shop 基础资料定时同步] ' . $row['agent_site_id'] . ': ' . $e->getMessage());
            }
        }
    }

    private function relation(int $masterSiteId, int $agentSiteId, bool $lock = false): array
    {
        if ($masterSiteId <= 0 || $agentSiteId <= 0 || $masterSiteId === $agentSiteId) throw new \RuntimeException('主子站关系不正确');
        $row = Db::name('phone_shop_agent')->where([
            ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId],
        ])->lock($lock)->find();
        if (!$row) throw new \RuntimeException('站点跟随关系不存在');
        return $row;
    }

    private function result(array $row): array
    {
        $result = $row['ref_sync_result'] ?? [];
        return is_array($result) ? $result : (json_decode((string)$result, true) ?: []);
    }

    private function publicInfo(array $row): array
    {
        return [
            'id' => (int)$row['id'], 'master_site_id' => (int)$row['master_site_id'], 'agent_site_id' => (int)$row['agent_site_id'],
            'relation_status' => (int)$row['status'],
            'settings' => self::settings([], $row), 'token' => (string)($row['ref_sync_token'] ?? ''),
            'status' => (string)($row['ref_sync_status'] ?? 'idle'), 'result' => $this->result($row),
            'last_time' => (int)($row['ref_sync_last_time'] ?? 0), 'next_time' => (int)($row['ref_sync_next_time'] ?? 0),
        ];
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
