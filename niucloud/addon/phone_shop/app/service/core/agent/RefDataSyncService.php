<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 引用数据跨站同步（分类/品牌/内存/参数）
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\agent;

use addon\phone_shop\app\model\agent\PhoneShopAgent;
use core\base\BaseCoreService;
use think\facade\Db;
use think\facade\Log;

/**
 * 引用数据跨站单向同步（主站 -> 子站）。品牌、参数等仍按 *_no 公共键关联；
 * 分类由 CategoryMappingService 维护显式等价关系，按关系设置补建缺失分类。
 *
 * 设计：每个站 site_id 物理隔离、各存各的主键 id；跨站只认 *_no。
 * 同步把主站的 分类/品牌/内存分组/内存规格/商品参数 在子站建/更新同 *_no 的副本，
 * 副本 source_site_id=主站。子站自建记录 *_no=0、source_site_id=0，永不被覆盖。
 *
 * Class RefDataSyncService
 * @package addon\phone_shop\app\service\core\agent
 */
class RefDataSyncService extends BaseCoreService
{
    /**
     * 引用类型配置：type => [表名, 主键, 公共键, 可复制列]
     * @return array
     */
    protected function types(): array
    {
        return [
            'category' => [
                'table' => 'phone_shop_goods_category', 'pk' => 'category_id', 'no' => 'category_no',
                'cols'  => ['category_name', 'image', 'level', 'pid', 'category_full_name', 'is_show', 'sort'],
            ],
            'brand' => [
                'table' => 'phone_shop_goods_brand', 'pk' => 'brand_id', 'no' => 'brand_no',
                'cols'  => ['brand_name', 'logo', 'desc', 'color_json', 'sort'],
            ],
            'attr' => [
                'table' => 'phone_shop_goods_attr', 'pk' => 'attr_id', 'no' => 'attr_no',
                'cols'  => ['attr_name', 'attr_value_format', 'sort'],
                // 历史参数表没有 create_time / update_time，不能统一写入时间字段。
                'timestamps' => false,
            ],
            // 内存规格(真正在用的):规格分组绑分类,规格子项挂分组
            'spec_group' => [
                'table' => 'phone_shop_goods_spec_group', 'pk' => 'group_id', 'no' => 'spec_group_no',
                'cols'  => ['category_id', 'category_ids', 'label', 'sort'],
            ],
            'spec_item' => [
                'table' => 'phone_shop_goods_spec_item', 'pk' => 'item_id', 'no' => 'spec_item_no',
                'cols'  => ['group_id', 'item_value', 'sort'],
            ],
            'grade' => [
                'table' => 'phone_shop_goods_grade', 'pk' => 'grade_id', 'no' => 'grade_no',
                'cols'  => ['grade_name', 'grade_desc', 'grade_image', 'sort', 'status'],
            ],
        ];
    }

    public function typeNames(): array
    {
        return ['category' => '商品分类', 'brand' => '品牌', 'attr' => '商品参数',
            'spec_group' => '内存/规格分组', 'spec_item' => '内存/规格值', 'grade' => '成色等级', 'goods_category' => '商品分类关联'];
    }

    /** 小批次供手动和定时任务共用；缺失依赖也是失败，不能算作已同步。 */
    public function syncBatch(string $type, int $masterSiteId, int $agentSiteId, int $cursor = 0, int $limit = 30): array
    {
        $this->resolveCache = [];
        if ($type === 'goods_category') return $this->syncGoodsCategories($masterSiteId, $agentSiteId, $cursor, $limit);
        $cfg = $this->types()[$type] ?? null;
        if (!$cfg) throw new \InvalidArgumentException('不支持的基础资料类型');
        $limit = max(1, min(100, $limit));
        $ids = Db::name($cfg['table'])->where('site_id', '=', $masterSiteId)
            ->where($cfg['pk'], '>', $cursor)->order($cfg['pk'] . ' asc')->limit($limit)->column($cfg['pk']);
        $report = ['cursor' => $cursor, 'done' => count($ids) < $limit, 'scanned' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
        foreach ($ids as $id) {
            $report['cursor'] = (int)$id;
            $report['scanned']++;
            try {
                if ($this->syncRecord($type, $masterSiteId, $agentSiteId, (int)$id) <= 0) {
                    throw new \RuntimeException($type === 'category' ? '分类未关联，请检查分类映射中的待处理或忽略项' : '来源资料不存在或依赖分类/规格组尚未关联');
                }
                $report['success']++;
            } catch (\Throwable $e) {
                $this->resolveCache = [];
                $report['failed']++;
                if (count($report['errors']) < 20) $report['errors'][] = [
                    'type' => $type, 'source_id' => (int)$id, 'message' => mb_substr($e->getMessage(), 0, 250),
                ];
            }
        }
        return $report;
    }

    /**
     * 便捷静态入口：仅当 $siteId 为主站时，推送某引用记录到所有代理子站。
     * 触发点（分类/品牌/参数 的 add/edit）直接调用，自带主站判断与异常吞噬，绝不影响主流程。
     * @param string $type
     * @param int $siteId 当前操作站点
     * @param int $refId 引用记录主键
     * @return void
     */
    public static function pushIfMaster(string $type, int $siteId, int $refId): void
    {
        try {
            if ($refId <= 0) return;
            $masterSiteId = (new AgentConfigService())->getMasterSiteId();
            if ($siteId != $masterSiteId) return;
            (new self())->pushToAgents($type, $masterSiteId, $refId);
        } catch (\Throwable $e) {
            Log::write('[phone_shop 引用同步] pushIfMaster 失败: ' . $e->getMessage());
        }
    }

    /**
     * 主站某条引用数据创建/改名后，推送同步到所有「启用且订阅」的子站。
     * @param string $type
     * @param int $masterSiteId
     * @param int $masterRefId
     * @return void
     */
    public function pushToAgents(string $type, int $masterSiteId, int $masterRefId): void
    {
        if (!isset($this->types()[$type]) || $masterRefId <= 0) return;
        try {
            $agents = (new PhoneShopAgent())->where([
                ['master_site_id', '=', $masterSiteId],
                ['status', '=', 1],
                ['subscribe_category', '=', 1],
            ])->select()->toArray();
            foreach ($agents as $agent) {
                if (($agent['ref_sync_mode'] ?? 'realtime') !== 'realtime') continue;
                try {
                    $this->syncRecord($type, $masterSiteId, (int)$agent['agent_site_id'], $masterRefId);
                    if ($type === 'category') {
                        // 分类移动也会改变主站商品的分类ID集合，需重新解析从站商品引用。
                        \addon\phone_shop\app\job\AgentCategoryGoodsSync::dispatch([
                            'masterSiteId' => $masterSiteId, 'agentSiteId' => (int)$agent['agent_site_id'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::write('[phone_shop 引用同步] 子站' . $agent['agent_site_id'] . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::write('[phone_shop 引用同步] push 失败: ' . $e->getMessage());
        }
    }

    /**
     * 新订阅子站：全量补同步主站现有 分类/品牌/内存/参数。
     * 分类按 level 升序，保证父级先建。
     * @param int $masterSiteId
     * @param int $agentSiteId
     * @return array 各类型同步条数
     */
    public function backfill(int $masterSiteId, int $agentSiteId): array
    {
        $report = [];
        foreach ($this->types() as $type => $cfg) {
            $n = 0;
            try {
                if ($type === 'category') {
                    $summary = (new CategoryMappingService())->scan($masterSiteId, $agentSiteId);
                    $report[$type] = (int)($summary['mapped'] ?? 0);
                    continue;
                }
                $query = Db::name($cfg['table'])->where('site_id', $masterSiteId);
                if ($type === 'category') {
                    $query->order('level asc, ' . $cfg['pk'] . ' asc');
                }
                $ids = $query->column($cfg['pk']);
                foreach ($ids as $id) {
                    if ($this->syncRecord($type, $masterSiteId, $agentSiteId, (int) $id) > 0) $n++;
                }
            } catch (\Throwable $e) {
                Log::write("[phone_shop 引用同步] backfill {$type} 失败: " . $e->getMessage());
            }
            $report[$type] = $n;
        }
        return $report;
    }

    /**
     * 取得主站引用公共键。主站没有 *_no 时只在同步过程中使用其主键作为稳定公共键，
     * 不为子站同步反写主站数据。
     * @param string $type
     * @param int $masterSiteId
     * @param int $masterRefId
     * @return int
     */
    public function ensureMasterNo(string $type, int $masterSiteId, int $masterRefId): int
    {
        $cfg = $this->types()[$type] ?? null;
        if (!$cfg || $masterRefId <= 0) return 0;
        $row = Db::name($cfg['table'])->where([[$cfg['pk'], '=', $masterRefId], ['site_id', '=', $masterSiteId]])->find();
        if (!$row) return 0;
        $no = (int) ($row[$cfg['no']] ?? 0);
        return $no > 0 ? $no : $masterRefId;
    }

    /**
     * 解析「主站某引用 id」在子站对应的 id：子站有同 *_no 则返回，没有则建后返回。
     * 铺货时用它把主站引用翻译成子站自己的 id。
     * @param string $type
     * @param int $masterSiteId
     * @param int $agentSiteId
     * @param int $masterRefId
     * @return int 子站引用 id（0 表示无法解析）
     */
    /**
     * 单次同步内的解析缓存：同一个主站引用 id 只解析/upsert 一次，避免几千条货重复查同一分类。
     * @var array<string,int>
     */
    protected $resolveCache = [];

    public function resolveAgentRefId(string $type, int $masterSiteId, int $agentSiteId, int $masterRefId): int
    {
        if ($masterRefId <= 0) return 0;
        $key = "{$type}:{$masterSiteId}:{$agentSiteId}:{$masterRefId}";
        if (isset($this->resolveCache[$key])) return $this->resolveCache[$key];
        try {
            $id = $this->syncRecord($type, $masterSiteId, $agentSiteId, $masterRefId);
        } catch (\Throwable $e) {
            $table = (string)($this->types()[$type]['table'] ?? 'unknown');
            throw new \RuntimeException(
                "引用同步失败[type={$type},table={$table},master_ref_id={$masterRefId}]: " . $e->getMessage(),
                0,
                $e
            );
        }
        if ($id > 0) $this->resolveCache[$key] = $id;
        return $id;
    }

    /**
     * 同步单条：在子站按 *_no upsert 主站某引用记录，返回子站主键 id。
     * @param string $type
     * @param int $masterSiteId
     * @param int $agentSiteId
     * @param int $masterRefId
     * @return int 子站主键 id（0=失败）
     */
    public function syncRecord(string $type, int $masterSiteId, int $agentSiteId, int $masterRefId): int
    {
        if ($masterSiteId <= 0 || $agentSiteId <= 0 || $masterSiteId === $agentSiteId) return 0;
        (new CategoryMappingService())->ensureSchema();
        // 同一关系的引用写入串行化，防止商品任务与手动同步同时创建重复规格。
        return Db::transaction(function () use ($type, $masterSiteId, $agentSiteId, $masterRefId) {
            $relation = Db::name('phone_shop_agent')->where([
                ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId], ['status', '=', 1],
            ])->lock(true)->find();
            if (!$relation) return 0;
            return $this->syncRecordForRelation($type, $masterSiteId, $agentSiteId, $masterRefId, $relation);
        });
    }

    protected function syncRecordForRelation(string $type, int $masterSiteId, int $agentSiteId, int $masterRefId, array $relation): int
    {
        $cfg = $this->types()[$type] ?? null;
        if (!$cfg || $masterRefId <= 0 || $agentSiteId <= 0) return 0;

        $master = Db::name($cfg['table'])->where([[$cfg['pk'], '=', $masterRefId], ['site_id', '=', $masterSiteId]])->find();
        if (!$master) return 0;

        if ($type === 'category') {
            return (new CategoryMappingService())->resolveAgentCategoryId(
                $masterSiteId,
                $agentSiteId,
                $masterRefId,
                (int)($relation['ref_auto_create_category'] ?? 0) === 1
            );
        }

        // 主站只读：未落库公共键时使用主键作为本次同步的稳定公共键。
        $no = (int) ($master[$cfg['no']] ?? 0);
        if ($no <= 0) {
            $no = $this->ensureMasterNo($type, $masterSiteId, $masterRefId);
            if ($no <= 0) return 0;
        }

        // 组装可复制列 + 特殊字段重映射
        $payload = [];
        foreach ($cfg['cols'] as $c) {
            $payload[$c] = $master[$c] ?? null;
        }
        // 一个规格组可以绑定多个分类，所有关联均使用子站ID，不留下主站ID或部分关联。
        if ($type === 'spec_group') {
            $categoryIds = array_values(array_unique(array_filter(array_map('intval',
                explode(',', (string)(($master['category_ids'] ?? '') ?: ($master['category_id'] ?? '')))
            ), static fn(int $id) => $id > 0)));
            $mappedIds = [];
            foreach ($categoryIds as $id) {
                $mapped = $this->resolveAgentRefId('category', $masterSiteId, $agentSiteId, $id);
                if ($mapped <= 0) return 0;
                $mappedIds[] = $mapped;
            }
            $mappedIds = array_values(array_unique($mappedIds));
            $payload['category_id'] = $mappedIds[0] ?? 0;
            $payload['category_ids'] = implode(',', $mappedIds);
        }
        // 规格子项：所属 group_id 翻译成子站规格分组 id
        if ($type === 'spec_item') {
            if (empty($master['group_id'])) return 0;
            $payload['group_id'] = $this->resolveAgentRefId('spec_group', $masterSiteId, $agentSiteId, (int) $master['group_id']);
            if ((int)$payload['group_id'] <= 0) return 0;
        }

        $now = time();
        $timestamps = (bool)($cfg['timestamps'] ?? true);
        $existQuery = Db::name($cfg['table'])->where([
            ['site_id', '=', $agentSiteId], [$cfg['no'], '=', $no], ['source_site_id', '=', $masterSiteId],
        ]);
        // 必须用当前读：外层商品事务可能早已建立旧的 RR 快照。
        $exist = $existQuery->lock(true)->find();
        if ($exist) {
            // 已存在：跟随主站更新（改名等），但不覆盖子站本地自建（source_site_id=0 且 no=0 的不会命中这里）
            $payload['source_site_id'] = $masterSiteId;
            if ($timestamps) $payload['update_time'] = $now;
            Db::name($cfg['table'])->where($cfg['pk'], $exist[$cfg['pk']])->update($payload);
            return (int) $exist[$cfg['pk']];
        }

        $payload['site_id'] = $agentSiteId;
        $payload[$cfg['no']] = $no;
        $payload['source_site_id'] = $masterSiteId;
        if ($timestamps) {
            $payload['create_time'] = $now;
            $payload['update_time'] = $now;
        }
        return (int) Db::name($cfg['table'])->insertGetId($payload);
    }

    /** 只更新既有代理商品的分类，不改价格、库存、上下架或子站自营商品。 */
    private function syncGoodsCategories(int $masterSiteId, int $agentSiteId, int $cursor, int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $rows = Db::name('phone_shop_goods')->where([
            ['site_id', '=', $agentSiteId], ['source', '=', (string)$masterSiteId],
            ['goods_id', '>', $cursor], ['delete_time', '=', 0], ['source_goods_id', '>', 0],
        ])->order('goods_id asc')->limit($limit)->field('goods_id,source_goods_id,goods_category')->select()->toArray();
        $report = ['cursor' => $cursor, 'done' => count($rows) < $limit, 'scanned' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
        foreach ($rows as $row) {
            $report['cursor'] = (int)$row['goods_id'];
            $report['scanned']++;
            try {
                Db::transaction(function () use ($masterSiteId, $agentSiteId, $row) {
                    $relation = Db::name('phone_shop_agent')->where([
                        ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId], ['status', '=', 1],
                    ])->lock(true)->find();
                    if (!$relation) throw new \RuntimeException('站点跟随关系已停用');
                    $master = Db::name('phone_shop_goods')->where([
                        ['site_id', '=', $masterSiteId], ['goods_id', '=', $row['source_goods_id']], ['delete_time', '=', 0],
                    ])->find();
                    if (!$master) throw new \RuntimeException('来源商品不存在，已保留原关联');
                    $ids = is_array($master['goods_category']) ? $master['goods_category'] : json_decode((string)$master['goods_category'], true);
                    if (!is_array($ids)) throw new \RuntimeException('来源商品分类格式不正确');
                    $mapped = [];
                    foreach ($ids as $id) {
                        $target = $this->resolveAgentRefId('category', $masterSiteId, $agentSiteId, (int)$id);
                        if ($target <= 0) throw new \RuntimeException('商品分类尚未关联：' . (int)$id);
                        $mapped[] = (string)$target;
                    }
                    $mapped = array_values(array_unique($mapped));
                    $old = is_array($row['goods_category']) ? $row['goods_category'] : json_decode((string)$row['goods_category'], true);
                    if ($mapped === $old) return;
                    Db::name('phone_shop_goods')->where([
                        ['site_id', '=', $agentSiteId], ['source', '=', (string)$masterSiteId], ['goods_id', '=', $row['goods_id']],
                    ])->update(['goods_category' => json_encode($mapped), 'update_time' => time()]);
                });
                $report['success']++;
            } catch (\Throwable $e) {
                $this->resolveCache = [];
                $report['failed']++;
                if (count($report['errors']) < 20) $report['errors'][] = [
                    'type' => 'goods_category', 'source_id' => (int)$row['source_goods_id'],
                    'message' => mb_substr($e->getMessage(), 0, 250),
                ];
            }
        }
        return $report;
    }

    /**
     * 把逗号分隔的「主站引用 id 串」整体翻译成子站 id 串。
     * @param string $idList
     * @param string $type
     * @param int $masterSiteId
     * @param int $agentSiteId
     * @return string
     */
    public function remapIdList(string $idList, string $type, int $masterSiteId, int $agentSiteId): string
    {
        $out = [];
        foreach (explode(',', $idList) as $id) {
            $id = (int) trim($id);
            if ($id <= 0) continue;
            $aid = $this->resolveAgentRefId($type, $masterSiteId, $agentSiteId, $id);
            if ($aid > 0) $out[] = $aid;
        }
        return implode(',', $out);
    }
}
