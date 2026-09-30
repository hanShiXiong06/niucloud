<?php

namespace addon\phone_shop\app\service\core\agent;

use addon\phone_shop\app\model\agent\PhoneShopAgentSyncRun;
use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;
use core\base\BaseCoreService;
use think\facade\Cache;
use think\facade\Db;

/**
 * 主从站同步的可观察层：记录每次全量同步，并实时计算当前货盘差异。
 */
class AgentSyncMonitorService extends BaseCoreService
{
    protected static bool $schemaReady = false;

    public function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $connection = (array)config('database.connections.mysql');
        $schemaCacheKey = 'phone_shop_agent_schema_v4_' . md5(
            (string)($connection['database'] ?? '') . '|' . (string)($connection['prefix'] ?? '')
        );
        if (Cache::get($schemaCacheKey)) {
            self::$schemaReady = true;
            return;
        }
        $schema = new SchemaSyncService();
        // 老站点经常只覆盖了代码，商品表仍缺少 source_goods_id / sale_status 等字段。
        // 在站点跟随入口一次性按 install.sql 幂等补齐依赖表，避免“补一个字段再报下一个”。
        foreach ([
            'phone_shop_agent',
            'phone_shop_goods',
            'phone_shop_goods_category',
            'phone_shop_goods_brand',
            'phone_shop_goods_attr',
            'phone_shop_goods_spec_group',
            'phone_shop_goods_spec_item',
            'phone_shop_goods_grade',
            'phone_shop_category_mapping',
        ] as $table) {
            if (!$schema->ensureTable($table)) {
                throw new \RuntimeException('跟随业务表初始化失败：' . $table);
            }
        }
        if (!$schema->ensureTable('phone_shop_agent_sync_run')) {
            throw new \RuntimeException('同步台账表初始化失败');
        }
        Cache::set($schemaCacheKey, 1, 86400);
        self::$schemaReady = true;
    }

    public function begin(int $masterSiteId, int $agentSiteId, string $triggerType = 'manual'): int
    {
        $this->ensureSchema();
        $now = time();
        return (int)(new PhoneShopAgentSyncRun())->insertGetId([
            'master_site_id' => $masterSiteId,
            'agent_site_id' => $agentSiteId,
            'trigger_type' => $triggerType,
            'status' => 'queued',
            'failure_summary' => '[]',
            'error_samples' => '[]',
            'create_time' => $now,
            'update_time' => $now,
        ]);
    }

    /** 新的手动校准开始时，结束同关系下未真正执行完的旧批次，避免永远显示“等待队列”。 */
    public function supersedeActive(int $masterSiteId, int $agentSiteId): void
    {
        $this->ensureSchema();
        (new PhoneShopAgentSyncRun())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
        ])->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed',
            'error_message' => '已由新的手动全量校准接管',
            'finished_at' => time(),
            'update_time' => time(),
        ]);
    }

    public function running(int $runId): void
    {
        if ($runId <= 0) return;
        $this->ensureSchema();
        (new PhoneShopAgentSyncRun())->where('run_id', $runId)->update([
            'status' => 'running',
            'started_at' => time(),
            'update_time' => time(),
        ]);
    }

    public function progress(int $runId, array $report): void
    {
        if ($runId <= 0) return;
        (new PhoneShopAgentSyncRun())->where('run_id', $runId)->update([
            'scanned_count' => (int)($report['scanned_count'] ?? 0),
            'success_count' => (int)($report['success_count'] ?? 0),
            'failed_count' => (int)($report['failed_count'] ?? 0),
            'failure_summary' => json_encode($report['failure_summary'] ?? [], JSON_UNESCAPED_UNICODE),
            'error_samples' => json_encode($report['error_samples'] ?? [], JSON_UNESCAPED_UNICODE),
            'update_time' => time(),
        ]);
    }

    /** 浏览器分批执行时原子累加进度，刷新页面看到的永远是真实已完成数量。 */
    public function appendProgress(int $runId, array $batch): array
    {
        if ($runId <= 0) throw new \RuntimeException('同步批次不存在');
        $this->ensureSchema();
        return Db::transaction(function () use ($runId, $batch) {
            $row = Db::name('phone_shop_agent_sync_run')->where('run_id', $runId)->lock(true)->find();
            if (!$row) throw new \RuntimeException('同步批次不存在或已清理');
            if (!in_array((string)$row['status'], ['queued', 'running'], true)) {
                throw new \RuntimeException('该同步批次已结束，请重新执行全量校准');
            }
            $summary = json_decode((string)($row['failure_summary'] ?? '[]'), true);
            if (!is_array($summary)) $summary = [];
            foreach ((array)($batch['failure_summary'] ?? []) as $reason => $count) {
                $summary[$reason] = (int)($summary[$reason] ?? 0) + (int)$count;
            }
            $samples = json_decode((string)($row['error_samples'] ?? '[]'), true);
            if (!is_array($samples)) $samples = [];
            $samples = array_slice(array_merge($samples, (array)($batch['error_samples'] ?? [])), 0, 20);
            $report = [
                'scanned_count' => (int)$row['scanned_count'] + (int)($batch['scanned_count'] ?? 0),
                'success_count' => (int)$row['success_count'] + (int)($batch['success_count'] ?? 0),
                'failed_count' => (int)$row['failed_count'] + (int)($batch['failed_count'] ?? 0),
                'failure_summary' => $summary,
                'error_samples' => $samples,
            ];
            Db::name('phone_shop_agent_sync_run')->where('run_id', $runId)->update([
                'status' => 'running',
                'started_at' => (int)$row['started_at'] ?: time(),
                'scanned_count' => $report['scanned_count'],
                'success_count' => $report['success_count'],
                'failed_count' => $report['failed_count'],
                'failure_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                'error_samples' => json_encode($samples, JSON_UNESCAPED_UNICODE),
                'update_time' => time(),
            ]);
            return $report;
        });
    }

    public function assertRun(int $runId, int $masterSiteId, int $agentSiteId): array
    {
        $this->ensureSchema();
        $row = Db::name('phone_shop_agent_sync_run')->where([
            ['run_id', '=', $runId],
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
        ])->find();
        if (!$row) throw new \RuntimeException('同步批次不存在或不属于当前站点');
        return $row;
    }

    public function finish(int $runId, array $report): void
    {
        if ($runId <= 0) return;
        $this->progress($runId, $report);
        (new PhoneShopAgentSyncRun())->where('run_id', $runId)->update([
            'status' => (int)($report['failed_count'] ?? 0) > 0 ? 'partial' : 'success',
            'finished_at' => time(),
            'update_time' => time(),
        ]);
    }

    public function fail(int $runId, \Throwable $e): void
    {
        if ($runId <= 0) return;
        $this->ensureSchema();
        (new PhoneShopAgentSyncRun())->where('run_id', $runId)->update([
            'status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
            'finished_at' => time(),
            'update_time' => time(),
        ]);
    }

    /** 实时数据，不依赖上一次任务是否正常结束。 */
    public function dashboard(int $masterSiteId, int $agentSiteId): array
    {
        $this->ensureSchema();
        $masterBase = fn() => Db::name('phone_shop_goods')->where([
            ['site_id', '=', $masterSiteId], ['delete_time', '=', 0],
        ])->where(function ($q) use ($masterSiteId) {
            $q->where('source', '=', '')->whereOr('source', '=', '0')
                ->whereOr('source', '=', (string)$masterSiteId);
        });
        $agentBase = fn() => Db::name('phone_shop_goods')->where([
            ['site_id', '=', $agentSiteId], ['delete_time', '=', 0],
        ]);
        $proxyBase = fn() => $agentBase()->where('source', '=', (string)$masterSiteId);
        // “当前代理货盘”只统计仍处于主站当前可售集合中的副本；历史已售、已下架
        // 记录继续保留，但不再冒充成需要运营处理的待上架商品。
        $currentProxyBase = fn() => Db::name('phone_shop_goods')->alias('c')
            ->join('phone_shop_goods m', 'm.goods_id=c.source_goods_id AND m.site_id=' . $masterSiteId)
            ->where([
                ['c.site_id', '=', $agentSiteId],
                ['c.source', '=', (string)$masterSiteId],
                ['c.delete_time', '=', 0],
                ['m.delete_time', '=', 0],
                ['m.status', '=', 1],
            ])->whereIn('m.sale_status', ['', 'available'])
            ->where(function ($q) use ($masterSiteId) {
                $q->where('m.source', '=', '')->whereOr('m.source', '=', '0')
                    ->whereOr('m.source', '=', (string)$masterSiteId);
            });
        $sellable = static function ($q) {
            return $q->where('status', '=', 1)->where('sale_status', '=', 'available')
                ->where('is_online_sellable', '=', 1)->where('stock', '>', 0);
        };
        $masterSellable = static function ($q) {
            return $q->where('status', '=', 1)
                ->whereIn('sale_status', ['', 'available']);
        };

        $masterTotal = (int)$masterBase()->count();
        $masterSellableCount = (int)$masterSellable($masterBase())->count();
        $proxyHistoryAll = (int)$proxyBase()->count();
        $proxyTotal = (int)$currentProxyBase()->count();
        $proxySellable = (int)$currentProxyBase()->where('c.status', '=', 1)
            ->where('c.sale_status', '=', 'available')->where('c.is_online_sellable', '=', 1)
            ->where('c.stock', '>', 0)->count();
        $selfTotal = (int)$agentBase()->where('source', '<>', (string)$masterSiteId)->count();
        $selfSellable = (int)$sellable($agentBase()->where('source', '<>', (string)$masterSiteId))->count();

        $offShelf = (int)$currentProxyBase()->where('c.sale_status', '=', 'available')->where('c.status', '<>', 1)->count();
        $offlineOnly = (int)$currentProxyBase()->where('c.sale_status', '=', 'available')->where('c.status', '=', 1)
            ->where('c.is_online_sellable', '<>', 1)->count();
        $outOfStock = (int)$currentProxyBase()->where('c.sale_status', '=', 'available')->where('c.status', '=', 1)
            ->where('c.is_online_sellable', '=', 1)->where('c.stock', '<=', 0)->count();

        $missing = (int)Db::name('phone_shop_goods')->alias('m')
            ->leftJoin('phone_shop_goods c',
                "c.site_id={$agentSiteId} AND c.source='" . addslashes((string)$masterSiteId) . "' AND c.source_goods_id=m.goods_id AND c.delete_time=0")
            ->where('m.site_id', '=', $masterSiteId)
            ->where('m.delete_time', '=', 0)
            ->where(function ($q) use ($masterSiteId) {
                $q->where('m.source', '=', '')->whereOr('m.source', '=', '0')
                    ->whereOr('m.source', '=', (string)$masterSiteId);
            })
            ->where('m.status', '=', 1)
            ->whereIn('m.sale_status', ['', 'available'])
            ->whereNull('c.goods_id')->count();

        $categoryCounts = Db::name('phone_shop_category_mapping')->where([
            ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId],
        ])->group('status')->column('count(*)', 'status');
        $mappingPending = (int)($categoryCounts['pending'] ?? 0) + (int)($categoryCounts['broken'] ?? 0);
        $mappingIgnored = (int)($categoryCounts['ignored'] ?? 0);

        $issues = [];
        $addIssue = static function (string $code, string $name, int $count, string $message, string $action, string $unit = '台') use (&$issues) {
            if ($count > 0) $issues[] = compact('code', 'name', 'count', 'unit', 'message', 'action');
        };
        $addIssue('missing_proxy', '尚未同步', $missing, '主站当前可售，但子站还没有对应商品副本', '执行全量校准');
        $addIssue('category_mapping', '分类待处理', $mappingPending, '分类无法唯一对应，相关商品为避免错分类不会上架', '进入分类映射处理', '个分类');
        $addIssue('off_shelf', '商品未上架', $offShelf, '子站代理商品 status 不是 1', '执行全量校准；若仍存在请查看同步失败原因');
        $addIssue('offline_only', '仅限线下', $offlineOnly, 'is_online_sellable 不是 1，小程序会过滤', '执行全量校准');
        $addIssue('out_of_stock', '库存不足', $outOfStock, '商品总库存为 0，小程序无法下单', '执行全量校准');
        $addIssue('ignored_mapping', '已忽略分类', $mappingIgnored, '管理员已忽略分类，该分类商品不会上架', '如需销售请恢复并建立映射', '个分类');

        $lastRun = (new PhoneShopAgentSyncRun())->where([
            ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId],
        ])->order('run_id desc')->findOrEmpty();

        return [
            'master_site_id' => $masterSiteId,
            'agent_site_id' => $agentSiteId,
            'master_total' => $masterTotal,
            'master_sellable' => $masterSellableCount,
            'self_total' => $selfTotal,
            'self_sellable' => $selfSellable,
            'proxy_total' => $proxyTotal,
            'proxy_history_total' => max(0, $proxyHistoryAll - $proxyTotal),
            'proxy_sellable' => $proxySellable,
            'proxy_unavailable' => max(0, $proxyTotal - $proxySellable),
            'missing_count' => $missing,
            'mapping_pending' => $mappingPending,
            'issues' => $issues,
            'last_run' => $lastRun->isEmpty() ? null : $lastRun->toArray(),
        ];
    }
}
