<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 商品铺货（主站 -> 子站代理副本）
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\agent;

use addon\phone_shop\app\service\core\goods\CoreGoodsChangeLogService;

use addon\phone_shop\app\model\agent\PhoneShopAgent;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\GoodsSpec;
use core\base\BaseCoreService;
use think\facade\Db;
use think\facade\Log;

/**
 * 商品铺货：主站商品变化时，复制到所有订阅该主站的启用子站。
 * 引用字段经 *_no 翻译为子站自己的 id；售价 = 主站零售价 + 子站固定加价。
 * 代理副本在从站作为普通可售商品使用，但不复制履约/供应商/标签等站内私有引用。
 * 单向：主站 -> 子站。站点订阅关系是唯一业务开关；商品副本通过
 * source + source_goods_id 精确定位。goods_no 只作跨站展示编号，历史数据
 * 可能重复，不能单独作为覆盖依据。
 *
 * Class GoodsDistributionService
 * @package addon\phone_shop\app\service\core\agent
 */
class GoodsDistributionService extends BaseCoreService
{
    /**
     * 主站商品铺货到所有启用站点关系的子站。
     * @param int $goodsId 主站商品id
     * @return array 本次同步结果；单站失败保留原因，不影响其他子站。
     */
    public function distribute(int $goodsId): array
    {
        $report = ['success_count' => 0, 'failed_count' => 0, 'errors' => []];
        try {
            $masterSiteId = (new AgentConfigService())->getMasterSiteId();
            $goods = (new Goods())->where([['goods_id', '=', $goodsId], ['site_id', '=', $masterSiteId]])->findOrEmpty();
            if ($goods->isEmpty()) return $report;
            $source = trim((string)$goods->source);
            // 兼容旧货盘：历史版本的本站自营商品可能把 source 保存为字符串 0。
            if (!in_array($source, ['', '0', (string)$masterSiteId], true)) return $report;

            // 主站始终只读：没有公共编号时，在本次同步中用主站商品ID作为稳定公共键，
            // 不能为了子站跟随反写主站商品。
            $goodsNo = trim((string)$goods->goods_no);
            if ($goodsNo === '' || $goodsNo === '0') {
                $goodsNo = (string)$goodsId;
            }

            $agents = (new PhoneShopAgent())->where([
                ['master_site_id', '=', $masterSiteId],
                ['status', '=', 1],
            ])->field('agent_site_id,markup_value')->select()->toArray();
            foreach ($agents as $agent) {
                try {
                    $this->distributeToOne(
                        $goods->toArray(),
                        $masterSiteId,
                        (int)$agent['agent_site_id'],
                        (float)$agent['markup_value'],
                        $goodsNo
                    );
                    $report['success_count']++;
                } catch (\Throwable $e) {
                    $report['failed_count']++;
                    if (count($report['errors']) < 10) $report['errors'][] = '子站 ' . (int)$agent['agent_site_id'] . '：' . mb_substr($e->getMessage(), 0, 180);
                    // 单个从站的数据异常不能阻断其余订阅站点的自动同步。
                    Log::write('[phone_shop 铺货] 从站' . (int)$agent['agent_site_id'] . '同步失败: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $report['failed_count']++;
            $report['errors'][] = mb_substr($e->getMessage(), 0, 180);
            Log::write('[phone_shop 铺货] 失败: ' . $e->getMessage());
        }
        return $report;
    }

    /**
     * 将一个主站商品刷新到指定从站。用于全量初始化、恢复代理关系和状态联动。
     *
     * @return int 从站商品ID
     */
    public function distributeToAgent(
        int $masterGoodsId,
        int $agentSiteId,
        ?float $markup = null,
        ?string $goodsNo = null
    ): int {
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $goods = (new Goods())->where([
            ['site_id', '=', $masterSiteId],
            ['goods_id', '=', $masterGoodsId],
        ])->findOrEmpty();
        if ($goods->isEmpty()) {
            throw new \RuntimeException('主站商品不存在');
        }
        $goodsNo = trim((string)($goodsNo ?: $goods->goods_no ?: $masterGoodsId));
        $relation = (new PhoneShopAgent())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
            ['status', '=', 1],
        ])->field('markup_value')->findOrEmpty();
        if ($relation->isEmpty()) {
            throw new \RuntimeException('主从站关系不存在或已停用');
        }
        // 加价只能来自当前站点关系，调用方传参仅保留旧签名兼容，不能成为第二事实源。
        $markup = (float)$relation->markup_value;
        return $this->distributeToOne(
            $goods->toArray(),
            $masterSiteId,
            $agentSiteId,
            (float)$markup,
            $goodsNo
        );
    }

    /**
     * 将主站全部自有商品同步到一个从站。业务关联粒度是 site_id，不存在单品关注。
     * 使用游标分批读取，避免一次把大货盘全部载入内存。
     *
     * @return int 同步成功条数
     */
    public function distributeAllToAgent(int $masterSiteId, int $agentSiteId, float $markup): int
    {
        $report = $this->distributeAllToAgentWithReport($masterSiteId, $agentSiteId, $markup);
        return (int)$report['success_count'];
    }

    /**
     * 带结果明细的全量同步，供管理端同步台账展示。
     * @param callable|null $progress 每批完成后接收当前统计
     */
    public function distributeAllToAgentWithReport(
        int $masterSiteId,
        int $agentSiteId,
        float $markup,
        ?callable $progress = null
    ): array
    {
        $relation = (new PhoneShopAgent())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
            ['status', '=', 1],
        ])->field('markup_value')->findOrEmpty();
        if ($relation->isEmpty()) throw new \RuntimeException('主从站关系不存在或已停用');
        // 站点关系是价格规则唯一事实源，队列中的旧参数不能覆盖最新加价。
        $markup = (float)$relation->markup_value;

        $lastId = 0;
        $report = [
            'scanned_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'failure_summary' => [],
            'error_samples' => [],
        ];
        do {
            // 全量校准只处理主站“当前应销售”的货盘。历史已售/已下架商品由末尾
            // 的批量离架逻辑收口，不再逐条扫描数万条历史记录。
            $rows = $this->eligibleMasterQuery($masterSiteId)
                ->where('goods_id', '>', $lastId)
                ->field('goods_id')->order('goods_id asc')->limit(100)->select()->toArray();
            $batch = $this->syncRows($rows, $agentSiteId, $markup);
            $report = $this->mergeReports($report, $batch);
            if ($rows) $lastId = (int)end($rows)['goods_id'];
            if ($progress) $progress($report);
        } while (count($rows) === 100);

        $this->offlineStaleProxies($masterSiteId, $agentSiteId);
        if ($progress) $progress($report);
        return $report;
    }

    /**
     * 管理端浏览器驱动的一小批校准。每次请求只处理少量当前可售商品，避免依赖队列。
     */
    public function distributeEligibleBatch(
        int $masterSiteId,
        int $agentSiteId,
        int $cursor = 0,
        int $limit = 25
    ): array {
        $relation = (new PhoneShopAgent())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
            ['status', '=', 1],
        ])->field('markup_value')->findOrEmpty();
        if ($relation->isEmpty()) throw new \RuntimeException('主从站关系不存在或已停用');

        $limit = max(5, min(50, $limit));
        $rows = $this->eligibleMasterQuery($masterSiteId)
            ->where('goods_id', '>', max(0, $cursor))
            ->field('goods_id')->order('goods_id asc')->limit($limit)->select()->toArray();
        $report = $this->syncRows($rows, $agentSiteId, (float)$relation->markup_value);
        $nextCursor = $rows ? (int)end($rows)['goods_id'] : max(0, $cursor);
        $done = count($rows) < $limit;
        if ($done) $this->offlineStaleProxies($masterSiteId, $agentSiteId);

        return $report + [
            'cursor' => $nextCursor,
            'done' => $done ? 1 : 0,
        ];
    }

    public function countEligibleMasterGoods(int $masterSiteId): int
    {
        return (int)$this->eligibleMasterQuery($masterSiteId)->count();
    }

    /** 主站当前应进入从站货盘的商品查询。 */
    protected function eligibleMasterQuery(int $masterSiteId)
    {
        return (new Goods())->where([
            ['site_id', '=', $masterSiteId],
            ['delete_time', '=', 0],
            ['status', '=', 1],
        ])->whereIn('sale_status', ['', 'available'])
            ->where(function ($query) use ($masterSiteId) {
                $query->where('source', '=', '')
                    ->whereOr('source', '=', '0')
                    ->whereOr('source', '=', (string)$masterSiteId);
            });
    }

    /** @param array<int,array<string,mixed>> $rows */
    protected function syncRows(array $rows, int $agentSiteId, float $markup): array
    {
        $report = [
            'scanned_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'failure_summary' => [],
            'error_samples' => [],
        ];
        foreach ($rows as $row) {
            $goodsId = (int)($row['goods_id'] ?? 0);
            if ($goodsId <= 0) continue;
            $report['scanned_count']++;
            try {
                $this->distributeToAgent($goodsId, $agentSiteId, $markup);
                $report['success_count']++;
            } catch (\Throwable $e) {
                $report['failed_count']++;
                $reason = $this->syncFailureReason($e->getMessage());
                $report['failure_summary'][$reason] = (int)($report['failure_summary'][$reason] ?? 0) + 1;
                if (count($report['error_samples']) < 20) {
                    $report['error_samples'][] = [
                        'goods_id' => $goodsId,
                        'reason_code' => $reason,
                        'message' => mb_substr($e->getMessage(), 0, 300),
                    ];
                }
                Log::write("[phone_shop 全站跟随] 商品{$goodsId}同步到从站{$agentSiteId}失败: " . $e->getMessage());
            }
        }
        return $report;
    }

    protected function mergeReports(array $total, array $batch): array
    {
        foreach (['scanned_count', 'success_count', 'failed_count'] as $key) {
            $total[$key] = (int)($total[$key] ?? 0) + (int)($batch[$key] ?? 0);
        }
        foreach ((array)($batch['failure_summary'] ?? []) as $reason => $count) {
            $total['failure_summary'][$reason] = (int)($total['failure_summary'][$reason] ?? 0) + (int)$count;
        }
        $total['error_samples'] = array_slice(array_merge(
            (array)($total['error_samples'] ?? []),
            (array)($batch['error_samples'] ?? [])
        ), 0, 20);
        return $total;
    }

    /**
     * 当前不再可售的主站商品只下架其从站副本，绝不删除历史订单关联数据。
     */
    protected function offlineStaleProxies(int $masterSiteId, int $agentSiteId): int
    {
        $eligibleIds = $this->eligibleMasterQuery($masterSiteId)->column('goods_id');
        $query = (new Goods())->where([
            ['site_id', '=', $agentSiteId],
            ['source', '=', (string)$masterSiteId],
            ['delete_time', '=', 0],
        ]);
        if ($eligibleIds) {
            $query->whereNotIn('source_goods_id', array_map('intval', $eligibleIds));
        }
        return $query->update([
            'status' => 0,
            'is_online_sellable' => 0,
            'stock' => 0,
            'update_time' => time(),
        ]);
    }

    protected function syncFailureReason(string $message): string
    {
        if (str_contains($message, '分类映射待处理')) return 'category_mapping';
        if (str_contains($message, 'fields not exists') || str_contains($message, 'Unknown column')) return 'schema';
        if (str_contains($message, 'SKU')) return 'sku';
        if (str_contains($message, 'Duplicate entry')) return 'duplicate';
        if (str_contains($message, '主从站关系')) return 'relation';
        return 'other';
    }

    /** 将该主站的全部从站副本下架，保留历史数据。 */
    public function offlineAllForAgent(int $masterSiteId, int $agentSiteId): int
    {
        return (new Goods())->where([
            ['site_id', '=', $agentSiteId],
            ['source', '=', (string)$masterSiteId],
        ])->where('status', '<>', 0)->update(['status' => 0, 'update_time' => time()]);
    }

    /**
     * 铺货到单个子站（幂等 upsert）
     * @param array $master 主站商品数组（含 json 转好的数组字段）
     * @param int $masterSiteId
     * @param int $agentSiteId
     * @param float $markup
     * @param string $goodsNo
     * @return int 从站商品ID
     */
    protected function distributeToOne(
        array $master,
        int $masterSiteId,
        int $agentSiteId,
        float $markup,
        string $goodsNo,
        ?RefDataSyncService $ref = null
    ): int
    {
        $ref = $ref ?: new RefDataSyncService();
        // 分类映射在商品事务外解析，确保“待处理”台账不会因商品回滚而丢失。
        // 任意一个主站分类未映射时都不能用不完整分类上架商品。
        $category = [];
        $unresolvedCategoryIds = [];
        foreach ((array)($master['goods_category'] ?? []) as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0) continue;
            $aid = $ref->resolveAgentRefId('category', $masterSiteId, $agentSiteId, $cid);
            if ($aid > 0) {
                $category[] = (string)$aid;
            } else {
                $unresolvedCategoryIds[] = $cid;
            }
        }
        if ($unresolvedCategoryIds) {
            $this->offlineProxyForMissingCategory(
                $agentSiteId,
                $masterSiteId,
                (int)($master['goods_id'] ?? 0)
            );
            throw new \RuntimeException('分类映射待处理：' . implode(',', $unresolvedCategoryIds));
        }

        Db::startTrans();
        try {
            // 其余引用字段翻译为子站 id
            $brandId = !empty($master['brand_id'])
                ? $ref->resolveAgentRefId('brand', $masterSiteId, $agentSiteId, (int) $master['brand_id'])
                : 0;
            // memory_group 在 goods 上是文本(如 "128G"，normalizeMemory 结果)，不是 id，直接拷贝不做映射
            $memoryGroup = (string) ($master['memory_group'] ?? '');
            $attrIds = [];
            $attrIdMap = [];
            foreach ((array) ($master['attr_ids'] ?? []) as $aid0) {
                $aid = $ref->resolveAgentRefId('attr', $masterSiteId, $agentSiteId, (int) $aid0);
                if ($aid <= 0) throw new \RuntimeException('商品参数模板未对应，未覆盖子站资料');
                $attrIds[] = (string) $aid;
                $attrIdMap[(int)$aid0] = $aid;
            }
            $attrFormat = (array)($master['attr_format'] ?? []);
            foreach ($attrFormat as &$parameter) {
                $attrId = (int)($parameter['attr_id'] ?? 0);
                if ($attrId <= 0) continue;
                if (!isset($attrIdMap[$attrId])) throw new \RuntimeException('商品参数缺少模板对应，未覆盖子站资料');
                $parameter['attr_id'] = $attrIdMap[$attrId];
            }
            unset($parameter);

            $now = time();
            // 子站可见集合严格跟随主站“已上架 + 在售”状态。
            // 锁定、已售或下架时保留副本用于历史链接，但立即从子站在售列表退出。
            $masterSaleStatus = trim((string)($master['sale_status'] ?? ''));
            // sale_status 是后加字段，旧商品可能遗留空值；空值沿用历史 status=1 即在售的语义。
            if ($masterSaleStatus === '') $masterSaleStatus = 'available';
            // “是否进入子站可售货盘”只跟随主站的业务上下架与交易状态。
            // is_online_sellable 是各站自己的渠道策略，goods.stock 在历史一机一码数据中
            // 也可能没有及时汇总；二者都不能把主站明明在售的商品误判成待上架。
            // 子站副本明确设置线上可售、库存 1，真正成交后再由 sale_status 联动离架。
            $masterAvailable = (int)($master['status'] ?? 0) === 1
                && $masterSaleStatus === 'available'
                && (int)($master['delete_time'] ?? 0) === 0;
            $goodsData = [
                'site_id'            => $agentSiteId,
                'source'             => (string) $masterSiteId,
                'source_goods_id'    => (int)$master['goods_id'],
                'goods_no'           => $goodsNo,
                'is_proxy'           => 1,
                'goods_name'         => $master['goods_name'] ?? '',
                'sub_title'          => $master['sub_title'] ?? '',
                'goods_type'         => $master['goods_type'] ?? 'real',
                'goods_cover'        => $master['goods_cover'] ?? '',
                'goods_image'        => $master['goods_image'] ?? '',
                'goods_video'        => $master['goods_video'] ?? '',
                'goods_desc'         => $master['goods_desc'] ?? '',
                'brand_id'           => $brandId,
                'goods_category'     => $category,
                'memory_group'       => $memoryGroup,
                'condition_grade'    => $master['condition_grade'] ?? '',
                'device_color'       => $master['device_color'] ?? '',
                'battery_health'     => (int)($master['battery_health'] ?? -1),
                'warranty_expire_time' => (int)($master['warranty_expire_time'] ?? 0),
                'attr_ids'           => $attrIds,
                'attr_format'        => $attrFormat,
                'qc_report'          => $master['qc_report'] ?? null,
                'status'             => $masterAvailable ? 1 : 0,
                'sale_status'        => $masterSaleStatus,
                // 主站当前在售时，从站副本必须同时允许线上购买，否则前端会过滤掉。
                // 子站成交只修改子站副本和子站订单，不反向写入主站商品。
                'is_online_sellable' => $masterAvailable ? 1 : 0,
                'member_discount'    => '',        // 不沿用主站会员价，子站只呈现零售展示价
                'label_ids'          => [],
                'service_ids'        => [],
                'unit'               => $master['unit'] ?? '',
                'stock'              => $masterAvailable ? 1 : 0,
                'sort'               => 0,
                'update_time'        => $now,
            ];

            $goodsModel = new Goods();
            $exist = $goodsModel->where([
                ['site_id', '=', $agentSiteId],
                ['source', '=', (string)$masterSiteId],
                ['source_goods_id', '=', (int)$master['goods_id']],
            ])->findOrEmpty();

            // 兼容旧关注数据：只有公共编号、来源和名称都只匹配到一个副本时才复用。
            // goods_no 在历史库中可能重复，绝不能凭它直接覆盖任意商品。
            if ($exist->isEmpty()) {
                $candidateIds = $goodsModel->where([
                    ['site_id', '=', $agentSiteId],
                    ['source', '=', (string)$masterSiteId],
                    ['goods_no', '=', $goodsNo],
                    ['goods_name', '=', (string)($master['goods_name'] ?? '')],
                ])->where(function ($query) {
                    $query->whereNull('source_goods_id')->whereOr('source_goods_id', '=', 0);
                })->limit(2)->column('goods_id');
                if (count($candidateIds) === 1) {
                    $exist = $goodsModel->where([
                        ['goods_id', '=', (int)$candidateIds[0]],
                        ['site_id', '=', $agentSiteId],
                    ])->findOrEmpty();
                }
            }
            if (!$exist->isEmpty()) {
                $goodsId = (int) $exist->goods_id;
                $auditBefore = (new CoreGoodsChangeLogService())->capture($agentSiteId, $goodsId);
                $goodsModel->where('goods_id', $goodsId)->update($goodsData);
            } else {
                $goodsData['create_time'] = $now;
                $auditBefore = [];
                $created = $goodsModel->create($goodsData);
                $goodsId = (int) $created->goods_id;
            }

            // 复制/刷新 sku（售价 = 主站零售价 + 加价；一物一码库存=1）
            $this->syncSku((int) $master['goods_id'], $goodsId, $agentSiteId, $markup);
            // 复制规格值
            $this->syncSpec((int) $master['goods_id'], $goodsId);
            (new CoreGoodsChangeLogService())->record($agentSiteId, $goodsId, $auditBefore, 'agent_sync', ['uid' => 0, 'name' => '主站同步']);
            Db::commit();
            return $goodsId;
        } catch (\Throwable $e) {
            Db::rollback();
            Log::write("[phone_shop 铺货] 子站{$agentSiteId} 失败: " . $e->getMessage());
            throw $e;
        }
    }

    /** 分类映射失效时只下架对应子站副本，不删除商品和历史订单。 */
    protected function offlineProxyForMissingCategory(int $agentSiteId, int $masterSiteId, int $masterGoodsId): void
    {
        if ($agentSiteId <= 0 || $masterSiteId <= 0 || $masterGoodsId <= 0) return;
        (new Goods())->where([
            ['site_id', '=', $agentSiteId],
            ['source', '=', (string)$masterSiteId],
            ['source_goods_id', '=', $masterGoodsId],
        ])->update([
            'status' => 0,
            'is_online_sellable' => 0,
            'stock' => 0,
            'update_time' => time(),
        ]);
    }

    /**
     * 同步 SKU：按 SKU 编码（无编码时按规格快照）原地更新，保持从站 sku_id 稳定。
     * 分享链接和已生成的海报通常携带 sku_id，不能在主站每次改价时删掉重建。
     */
    protected function syncSku(int $masterGoodsId, int $agentGoodsId, int $agentSiteId, float $markup): void
    {
        $skuModel = new GoodsSku();
        $existing = $skuModel->where([
            ['goods_id', '=', $agentGoodsId],
            ['site_id', '=', $agentSiteId],
        ])->field('sku_id,sku_name,sku_no,sku_spec_format,is_default')->order('sku_id asc')->select()->toArray();
        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[$this->skuIdentity($row)][] = (int)$row['sku_id'];
        }

        $field = 'sku_name,sku_image,sku_no,sku_spec_format,price,market_price,cost_price,weight,volume,is_default,condition_grade,device_snapshot';
        $list = $skuModel->where('goods_id', $masterGoodsId)->field($field)->select()->toArray();
        if (empty($list)) {
            throw new \RuntimeException('主站商品缺少SKU，无法在子站销售');
        }
        $hasDefaultSku = false;
        $retainedIds = [];
        foreach ($list as $sku) {
            $sku['site_id']      = $agentSiteId;
            $sku['goods_id']     = $agentGoodsId;
            $sku['sale_num']     = 0;
            $sku['stock']        = 1;
            $sku['price']        = round((float) ($sku['price'] ?? 0) + $markup, 2);
            $sku['market_price'] = round((float) ($sku['market_price'] ?? 0) + $markup, 2);
            $sku['sale_price']   = $sku['price'];
            $sku['cost_price']   = 0; // 子站不呈现成本
            $sku['member_price'] = '';
            // 会员加价快照属于站点，不随质检快照跨站复制；代理仍走原有主从站加价规则。
            $snapshot = \addon\phone_shop\app\service\core\goods\CoreTierPricingService::snapshot($sku['device_snapshot'] ?? '');
            unset($snapshot['_tier_pricing']);
            $sku['device_snapshot'] = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ((int)($sku['is_default'] ?? 0) === 1) $hasDefaultSku = true;

            $identity = $this->skuIdentity($sku);
            $skuId = !empty($existingMap[$identity]) ? (int)array_shift($existingMap[$identity]) : 0;
            if ($skuId > 0) {
                $skuModel->where([
                    ['sku_id', '=', $skuId],
                    ['site_id', '=', $agentSiteId],
                    ['goods_id', '=', $agentGoodsId],
                ])->update($sku);
                $retainedIds[] = $skuId;
            } else {
                $retainedIds[] = (int)$skuModel->insertGetId($sku);
            }
        }

        if (!$hasDefaultSku) {
            throw new \RuntimeException('主站商品缺少默认SKU，前端无法展示');
        }

        // 主站已经删除的规格才从副本删除；其余规格的 sku_id 始终不变。
        $staleQuery = $skuModel->where([
            ['goods_id', '=', $agentGoodsId],
            ['site_id', '=', $agentSiteId],
        ]);
        if (!empty($retainedIds)) {
            $staleQuery->whereNotIn('sku_id', $retainedIds);
        }
        $staleQuery->delete();
    }

    /** @param array<string,mixed> $sku */
    protected function skuIdentity(array $sku): string
    {
        $skuNo = trim((string)($sku['sku_no'] ?? ''));
        if ($skuNo !== '') return 'no:' . $skuNo;
        return 'spec:' . trim((string)($sku['sku_name'] ?? ''))
            . '|' . trim((string)($sku['sku_spec_format'] ?? ''))
            . '|' . (int)($sku['is_default'] ?? 0);
    }

    /**
     * 同步规格值
     */
    protected function syncSpec(int $masterGoodsId, int $agentGoodsId): void
    {
        $specModel = new GoodsSpec();
        $specModel->where('goods_id', $agentGoodsId)->delete();

        $list = $specModel->where('goods_id', $masterGoodsId)->field('spec_name,spec_values')->select()->toArray();
        foreach ($list as $spec) {
            $spec['goods_id'] = $agentGoodsId;
            $specModel->insert($spec);
        }
    }
}
