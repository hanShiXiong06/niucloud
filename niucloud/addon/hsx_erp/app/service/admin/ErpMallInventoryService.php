<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\support\ErpMallDevicePolicy;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** 存量商城库存与ERP对账。关联只改设备链路，绝不补造采购应付或重放收款。 */
class ErpMallInventoryService extends BaseAdminService
{
    private array $outboxIds = [];
    public static function forSite(int $siteId, int $uid = 0, string $name = '商城自动关联'): self
    {
        if ($siteId <= 0) throw new CommonException('商城对账缺少有效站点');
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $uid;
        $service->username = $name;
        return $service;
    }

    public function preview(array $params): array
    {
        $receivableId = (int)($params['receivable_id'] ?? 0);
        $sale = $receivableId > 0 ? $this->saleFromReceivable($receivableId) : null;
        if (!$sale && ($params['scope'] ?? '') === 'sold') return $this->soldPreview($params);
        $page = $sale ? ['data' => [], 'total' => 0] : $this->mall(['action' => 'stock'] + $params);
        if ($sale) {
            $lines = ErpSaleItem::where([['site_id', '=', $this->site_id], ['sale_order_id', '=', (int)$sale->id]])->order('id asc')->select();
            foreach ($lines as $line) {
                if ((int)$line->asset_id > 0) continue;
                try {
                    $source = $this->saleSource($sale, $line);
                    $page['data'][] = $this->row($source, $line);
                } catch (CommonException $e) {
                    $page['data'][] = ['sale_item_id' => (int)$line->id, 'goods_name' => (string)$line->model, 'state' => 'conflict', 'message' => $e->getMessage()];
                }
            }
            $page['total'] = count($page['data']);
        } else {
            $page['data'] = array_map(fn(array $source): array => $this->row($source), $page['data']);
        }
        $page['summary'] = array_count_values(array_column($page['data'], 'state'));
        $page['receivable_id'] = $receivableId;
        return $page;
    }

    /** 每台独立事务；部分失败明确返回，不影响已确认的其他设备。 */
    public function confirm(array $params): array
    {
        $items = (array)($params['items'] ?? []);
        if (!$items || count($items) > 50) throw new CommonException('请先预览并选择1至50台设备');
        $results = [];
        foreach ($items as $request) {
            $outboxCount = count($this->outboxIds);
            try {
                if (!is_array($request)) throw new CommonException('设备参数无效');
                $results[] = Db::transaction(function () use ($request, $params): array {
                    $sale = (int)($params['receivable_id'] ?? 0) > 0
                        ? $this->saleFromReceivable((int)$params['receivable_id'], true)
                        : ((int)($request['sale_order_id'] ?? 0) > 0 ? $this->mallSale((int)$request['sale_order_id'], true) : null);
                    $line = null;
                    if ($sale) {
                        $line = ErpSaleItem::where([['site_id', '=', $this->site_id], ['sale_order_id', '=', (int)$sale->id], ['id', '=', (int)($request['sale_item_id'] ?? 0)]])->lock(true)->findOrEmpty();
                        if ($line->isEmpty()) throw new CommonException('成交明细不存在');
                        if ((int)$line->asset_id > 0) return ['state' => 'duplicate', 'asset_id' => (int)$line->asset_id, 'message' => '已关联，未重复记账'];
                        $source = $this->saleSource($sale, $line, true);
                    } else {
                        $source = $this->mall(['action' => 'sku', 'sku_id' => (int)($request['sku_id'] ?? 0), 'lock' => true]);
                    }
                    $preview = $this->row($source, $line, true);
                    if (!$sale && $preview['state'] === 'linked') return ['state' => 'duplicate', 'asset_id' => (int)$preview['asset_id'], 'message' => '已关联，未重复建账'];
                    if (!hash_equals((string)$preview['preview_token'], (string)($request['preview_token'] ?? ''))) throw new CommonException('商品、成本或ERP状态已变化，请重新预览');
                    if (!in_array($preview['state'], ['match', 'opening', 'linked'], true)) throw new CommonException($preview['message']);
                    $opening = $preview['state'] === 'opening';
                    if ($opening && (string)($request['action'] ?? '') !== 'opening') throw new CommonException('该设备需要期初建账，请明确选择后确认');
                    if (!$opening && (string)($request['action'] ?? '') !== 'link') throw new CommonException('该设备应复用现有ERP资产，不能重复建账');
                    $asset = $opening ? $this->openAsset($source, $params, $sale) : $this->asset((int)$preview['asset_id']);
                    $this->bind($source, (int)$asset->id);
                    if ($sale && $line) $this->completeSaleAsset($sale, $line, $asset);
                    $this->log($source, $asset, $sale, $opening);
                    return ['sku_id' => (int)$source['sku_id'], 'state' => 'completed', 'asset_id' => (int)$asset->id, 'message' => $sale ? '设备已关联并补齐出库；原应收及已收金额未改变' : ($opening ? '期初库存已建立并关联商城' : '已关联ERP原库存')];
                });
            } catch (\Throwable $e) {
                $this->outboxIds = array_slice($this->outboxIds, 0, $outboxCount);
                $results[] = ['sku_id' => is_array($request) ? (int)($request['sku_id'] ?? 0) : 0, 'state' => 'failed', 'message' => $e->getMessage()];
            }
        }
        $this->flushEvents();
        return ['results' => $results, 'success' => count(array_filter($results, static fn(array $r): bool => in_array($r['state'], ['completed', 'duplicate'], true))), 'failed' => count(array_filter($results, static fn(array $r): bool => $r['state'] === 'failed'))];
    }

    /** 已成立的销售事实：仅自动复用唯一且可销售的原资产，不自动创造成本/采购。外层销售事务托管。 */
    public function attachRecordedSale(ErpSaleOrder $sale, ErpSaleItem $line, array $device): string
    {
        if (ErpMallDevicePolicy::identity($device) === ['imei' => '', 'sn' => '']) return '缺少完整串号，待库存对账';
        try {
            $source = $this->mall(['action' => 'sku', 'sku_id' => (int)$line->external_sku_id, 'lock' => true]);
            $this->assertCurrentDevice($device, $source['device']);
            $source['device'] = $device;
            $source['quantity'] = (int)$line->quantity;
            $source['sale_at'] = (int)$sale->sale_at;
            $preview = $this->row($source, $line, true);
            if (!in_array($preview['state'], ['match', 'linked'], true)) return $preview['message'];
            $asset = $this->asset((int)$preview['asset_id']);
            $this->bind($source, (int)$asset->id);
        } catch (CommonException $e) {
            // 资料冲突不抹掉已经成立的商城应收。数据库故障仍抛出并走收件箱重试。
            return $e->getMessage();
        }
        $this->completeSaleAsset($sale, $line, $asset);
        $this->log($source, $asset, $sale, false);
        return '';
    }

    private function row(array $source, ?ErpSaleItem $line = null, bool $lock = false): array
    {
        $identity = ErpMallDevicePolicy::identity((array)$source['device']);
        $codes = array_values(array_filter($identity));
        $assets = $codes === [] ? [] : ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where(function ($q) use ($codes) { $q->whereIn('imei', $codes)->whereOr('sn', 'in', $codes); })
            ->order('id asc')->lock($lock)->select()->toArray();
        $result = ErpMallDevicePolicy::inspect((int)$this->site_id, $source, $assets, $line !== null);
        if (in_array($result['state'], ['match', 'linked'], true)) {
            $warehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', (int)$assets[0]['warehouse_id']]])->findOrEmpty();
            if ($warehouse->isEmpty() || (int)$warehouse->status !== 1 || (int)$warehouse->allow_direct_sale !== 1) {
                $result = ['state' => 'conflict', 'asset_id' => (int)$assets[0]['id'], 'message' => 'ERP所在仓库未启用销售，请先核对仓库配置，不能跳过仓库限制'];
            }
        }
        if ($line && ((float)$line->refunded_amount > 0 || (string)$line->status !== 'sold')) {
            $result = ['state' => 'conflict', 'asset_id' => 0, 'message' => '成交明细已退款或撤回，请从售后核对，不自动补出库'];
        }
        if ($line && $result['state'] === 'linked') {
            // SKU先关联了库存，不代表原成交已经出库；必须仍可从成交入口补齐。
            $result['state'] = 'match';
            $result['message'] = '商城已关联ERP原库存；确认后仅补齐这次销售出库，不重复入库或收付款';
        }
        return array_merge($source, $identity, $result, [
            'sale_item_id' => $line ? (int)$line->id : 0,
            'sale_order_id' => $line ? (int)$line->sale_order_id : 0,
            'erp_cost' => count($assets) === 1 ? (float)$assets[0]['total_cost'] : null,
            'preview_token' => hash('sha256', json_encode([$source, $assets, $line ? $line->toArray() : null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);
    }

    private function openAsset(array $source, array $params, ?ErpSaleOrder $sale): ErpAsset
    {
        [$warehouse, $location] = ErpWarehouseService::forSite((int)$this->site_id)->validateInboundLocation((int)($params['warehouse_id'] ?? 0), (int)($params['location_id'] ?? 0));
        if ((int)$warehouse->allow_direct_sale !== 1) throw new CommonException('请选择允许销售的期初仓库');
        $date = (int)($params['opening_at'] ?? 0);
        if ($date <= 0 || $date > time() || ($sale && $date > (int)$sale->sale_at)) throw new CommonException('请填写有效期初日期，且不能晚于成交时间');
        $identity = ErpMallDevicePolicy::identity($source['device']);
        $cost = round((float)$source['cost_price'], 2);
        $asset = ErpAsset::create([
            'site_id' => $this->site_id, 'asset_no' => ErpLedgerService::makeNo('AS'),
            'imei' => $identity['imei'], 'sn' => $identity['sn'],
            'model' => mb_substr(trim($source['goods_name'] . ' ' . $source['sku_name']), 0, 255),
            'warehouse_id' => (int)$warehouse->id, 'warehouse_name' => (string)$warehouse->warehouse_name,
            'location_id' => (int)$location->id, 'location_name' => (string)$location->location_name,
            'ownership_type' => 'owned', 'owner_party_name' => '本公司',
            'ownership_source_type' => 'opening', 'ownership_changed_at' => $date,
            'purchase_cost' => $cost, 'total_cost' => $cost, 'retail_price' => (float)$source['price'],
            'image_urls' => json_encode($source['image_urls'], JSON_UNESCAPED_UNICODE),
            'spec_json' => json_encode(['mall_opening' => ['goods_id' => $source['goods_id'], 'sku_id' => $source['sku_id'], 'cost_source' => '管理员确认商城成本']], JSON_UNESCAPED_UNICODE),
            'sale_target' => 'mall', 'listing_status' => (int)$source['status'] === 1 ? 'listed' : 'ready',
            'status' => 'in_stock', 'source_plugin' => 'phone_shop', 'source_type' => 'mall_opening', 'source_id' => (string)$source['sku_id'],
            'remark' => '商城存量自有库存期初；不生成采购应付', 'stock_in_at' => $date, 'create_at' => time(), 'update_at' => time(),
        ]);
        ErpLedgerService::forSite((int)$this->site_id, (int)$this->uid, (string)$this->username)->asset([
            'request_id' => 'mall-opening:' . $source['sku_id'] . ':' . $asset->id,
            'asset_id' => (int)$asset->id, 'action' => 'inbound', 'before_status' => '', 'after_status' => 'in_stock',
            'before_total_cost' => 0, 'after_total_cost' => $cost, 'cost_delta' => $cost,
            'source_type' => 'opening', 'source_id' => (int)$asset->id, 'source_no' => (string)$asset->asset_no,
            'occurred_at' => $date, 'remark' => '商城存量期初库存（不生成采购应付）', 'skip_performance' => true,
            'extra' => ['goods_id' => $source['goods_id'], 'sku_id' => $source['sku_id']],
        ]);
        return $asset;
    }

    private function completeSaleAsset(ErpSaleOrder $sale, ErpSaleItem $line, ErpAsset $asset): void
    {
        $cost = round((float)$asset->total_cost, 2);
        $price = round((float)$line->sale_price, 2);
        $remark = (string)$line->remark;
        if (str_starts_with($remark, '库存待核对：')) $remark = explode('；', $remark, 2)[1] ?? '';
        $remark = mb_substr(trim($remark . '；已完成商城设备关联与出库', '；'), 0, 255);
        $asset->save(['sale_order_id' => (int)$sale->id, 'sale_item_id' => (int)$line->id, 'sale_price' => $price, 'profit' => round($price - $cost, 2), 'status' => 'sold', 'update_at' => time()]);
        $line->save(['asset_id' => (int)$asset->id, 'imei' => (string)($asset->imei ?: $asset->sn), 'model' => (string)$asset->model, 'cost' => $cost, 'profit' => round($price - $cost, 2), 'inventory_source' => 'erp_asset', 'item_type' => 'device', 'unit' => '台', 'remark' => $remark, 'update_at' => time()]);
        ErpLedgerService::forSite((int)$this->site_id, (int)$this->uid, (string)$this->username)->asset([
            'request_id' => 'mall-sale-link:' . $line->id . ':' . $asset->id, 'asset_id' => (int)$asset->id,
            'action' => 'sold', 'before_status' => 'in_stock', 'after_status' => 'sold',
            'before_total_cost' => $cost, 'after_total_cost' => $cost, 'party_id' => (int)$sale->party_id, 'party_name' => (string)$sale->party_name,
            'source_type' => 'sale', 'source_id' => (int)$sale->id, 'source_no' => (string)$sale->sale_no,
            'occurred_at' => (int)$sale->sale_at, 'remark' => '商城成交关联ERP原设备，补齐销售出库；不重复收款',
            'extra' => ['sale_item_id' => (int)$line->id],
        ]);
        $totalCost = round((float)ErpSaleItem::where([['site_id', '=', $this->site_id], ['sale_order_id', '=', (int)$sale->id]])->sum('cost'), 2);
        $sale->save(['total_cost' => $totalCost, 'profit' => round((float)$sale->total_amount - $totalCost, 2), 'update_at' => time()]);
        $event = ErpIntegrationService::forSite((int)$this->site_id, (int)$this->uid, (string)$this->username)->enqueueDomainEvent(
            'erp.asset.sold.v1', 'asset', (int)$asset->id,
            ['asset_id' => (int)$asset->id, 'asset_no' => (string)$asset->asset_no, 'imei' => (string)$asset->imei,
                'model' => (string)$asset->model, 'sale_order_id' => (int)$sale->id, 'sale_item_id' => (int)$line->id,
                'outbound_no' => (string)$sale->sale_no, 'origin_plugin' => 'phone_shop',
                'build_mall_order' => false, 'result_status' => 'sold', 'snapshot_at' => time()],
            [], ['phone_shop.erp_asset_state']
        );
        $this->outboxIds[] = (int)$event['id'];
    }

    private function saleFromReceivable(int $id, bool $lock = false): ErpSaleOrder
    {
        $receivable = ErpReceivable::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock($lock)->findOrEmpty();
        if ($receivable->isEmpty() || (string)$receivable->status === 'void' || (string)$receivable->source_type !== 'phone_shop.native_goods_sale') throw new CommonException('仅可核对有效的商城原生商品销售应收');
        return $this->mallSale((int)$receivable->source_id, $lock);
    }

    private function mallSale(int $id, bool $lock = false): ErpSaleOrder
    {
        $sale = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id], ['origin_plugin', '=', 'phone_shop'], ['origin_type', '=', 'phone_shop.native_goods_sale']])->lock($lock)->findOrEmpty();
        if ($sale->isEmpty() || (string)$sale->status !== 'completed' || (float)$sale->refunded_amount > 0) throw new CommonException('销售已撤回或发生退款，请从售后核对');
        return $sale;
    }

    /** 线上现结没有应收单，也必须有可见的库存核对入口。 */
    private function soldPreview(array $params): array
    {
        $query = ErpSaleItem::alias('i')->join((new ErpSaleOrder())->getTable() . ' s', 's.id=i.sale_order_id AND s.site_id=i.site_id')
            ->where([['i.site_id', '=', $this->site_id], ['i.asset_id', '=', 0], ['i.status', '=', 'sold'],
                ['s.origin_plugin', '=', 'phone_shop'], ['s.origin_type', '=', 'phone_shop.native_goods_sale'], ['s.status', '=', 'completed']]);
        $keyword = mb_substr(trim((string)($params['keyword'] ?? '')), 0, 100);
        if ($keyword !== '') $query->whereLike('i.imei|i.model|s.origin_no', '%' . $keyword . '%');
        $page = $query->field('i.id,i.sale_order_id')->order('i.id desc')->paginate([
            'page' => max(1, (int)($params['page'] ?? 1)), 'list_rows' => min(50, max(1, (int)($params['limit'] ?? 20))),
        ])->toArray();
        $page['data'] = array_map(function (array $item): array {
            $line = ErpSaleItem::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item['id']]])->findOrEmpty();
            try {
                $sale = $this->mallSale((int)$item['sale_order_id']);
                return $this->row($this->saleSource($sale, $line), $line);
            } catch (CommonException $e) {
                return ['sale_item_id' => (int)$line->id, 'goods_name' => (string)$line->model, 'state' => 'conflict', 'message' => $e->getMessage()];
            }
        }, $page['data']);
        $page['summary'] = array_count_values(array_column($page['data'], 'state'));
        $page['receivable_id'] = 0;
        return $page;
    }

    private function saleSource(ErpSaleOrder $sale, ErpSaleItem $line, bool $lock = false): array
    {
        $source = $this->mall(['action' => 'order_line', 'order_id' => (int)$sale->origin_id, 'line_id' => (int)$line->external_line_id, 'lock' => $lock]);
        if ((int)$source['sku_id'] !== (int)$line->external_sku_id || (int)$source['quantity'] !== (int)$line->quantity) throw new CommonException('商城与ERP原成交明细不一致，请人工核对');
        $this->assertCurrentDevice($source['device'], $source['current_device']);
        $source['sale_at'] = (int)$sale->sale_at;
        $source['source_no'] = (string)$sale->origin_no;
        return $source;
    }

    private function assertCurrentDevice(array $snapshot, array $current): void
    {
        if (!empty($snapshot['identity_conflict']) || !empty($current['identity_conflict'])) throw new CommonException('商城串号与原始采集信息存在冲突，请先核对');
        if (ErpMallDevicePolicy::identity($snapshot) !== ErpMallDevicePolicy::identity($current)) throw new CommonException('当前商城串号与原订单快照不一致，不能覆盖关联');
        // 与商城来源筛选一致，不使用已废弃的 is_proxy 标记判断物权。
        if (!in_array((string)($current['source'] ?? ''), ['', '0', '1', (string)$this->site_id], true)) {
            throw new CommonException('当前商城商品已变为代理来源，不能建立本站自有库存关联');
        }
    }

    private function asset(int $id): ErpAsset
    {
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('ERP设备不存在');
        return $asset;
    }

    private function bind(array $source, int $assetId): void
    {
        $result = $this->mall(['action' => 'bind', 'sku_id' => $source['sku_id'], 'token' => $source['token'], 'asset_id' => $assetId]);
        if (empty($result['bound'])) throw new CommonException('商城未确认设备关联，操作已回滚');
    }

    private function mall(array $request): array
    {
        foreach ((array)event('HsxErpMallInventory', array_merge($request, ['site_id' => (int)$this->site_id])) as $result) {
            if (is_array($result) && ($result['provider'] ?? '') === 'phone_shop') return (array)($result['data'] ?? []);
        }
        throw new CommonException('当前站点未启用手机商城，或商城插件尚未更新库存对账接口');
    }

    private function log(array $source, ErpAsset $asset, ?ErpSaleOrder $sale, bool $opening): void
    {
        ErpOperationLogService::forSite((int)$this->site_id, (int)$this->uid, (string)$this->username)->record(
            'mall_inventory_link', 'asset', (int)$asset->id, (string)$asset->asset_no,
            $opening ? '商城库存期初并关联' : '商城关联已有ERP资产',
            ['goods_id' => $source['goods_id'], 'sku_id' => $source['sku_id'], 'sale_order_id' => $sale ? (int)$sale->id : 0, 'receivable_unchanged' => true, 'purchase_payable_created' => false]
        );
    }

    /** 仅在事务提交后派发；失败保留发件箱重试，不能把已记账操作误报为未成功。 */
    public function flushEvents(): void
    {
        if (Db::connect()->getPdo()->inTransaction()) return;
        foreach (array_unique($this->outboxIds) as $id) {
            try {
                ErpIntegrationService::forSite((int)$this->site_id)->dispatchDomainEvent((int)$id, (int)$this->site_id);
            } catch (\Throwable $e) {
                Log::warning('商城库存关联已提交，商品状态通知待重试：' . $e->getMessage());
            }
        }
        $this->outboxIds = [];
    }
}
