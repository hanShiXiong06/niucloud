<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use core\exception\CommonException;

/** ERP 对账扩展点：商城自己读取/绑定 SKU，ERP 不直接操作商城表。无独立 HTTP 入口。 */
final class ErpMallInventoryProvider
{
    public function handle(array $event = []): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        if ($siteId <= 0) throw new CommonException('商城库存对账缺少站点');
        $action = (string)($event['action'] ?? '');
        if ($action === 'sale_guard') {
            return ['provider' => 'phone_shop', 'data' => (new \addon\phone_shop\app\service\core\order\CoreOrderInventoryService())->guardErpSale(
                $siteId, (int)($event['asset_id'] ?? 0), (int)($event['source_order_id'] ?? 0), !empty($event['reserve'])
            )];
        }
        if ($action === 'stock') {
            $query = (new GoodsSku())->alias('s')
                ->join((new Goods())->getTable() . ' g', 'g.goods_id = s.goods_id AND g.site_id = s.site_id')
                ->where([['s.site_id', '=', $siteId], ['g.delete_time', '=', 0], ['s.stock', '>', 0]])
                // 与商城自营/代理筛选一致：source 才是货主来源，is_proxy 已废弃。
                // 主站历史自营数据可能仍为 is_proxy=1，不能据此排除本店库存。
                ->whereIn('g.source', ['', '0', '1', (string)$siteId]);
            if (empty($event['include_linked'])) $query->where('s.erp_asset_id', 0);
            $keyword = mb_substr(trim((string)($event['keyword'] ?? '')), 0, 100);
            if ($keyword !== '') $query->whereLike('s.sku_no|g.goods_name', '%' . $keyword . '%');
            $page = $query->field('s.sku_id')->order('s.sku_id desc')->paginate([
                'page' => max(1, (int)($event['page'] ?? 1)),
                'list_rows' => min(50, max(1, (int)($event['limit'] ?? 20))),
            ])->toArray();
            $page['data'] = array_map(fn(array $row): array => $this->readSku($siteId, (int)$row['sku_id']), $page['data']);
            return ['provider' => 'phone_shop', 'data' => $page];
        }
        if ($action === 'order_line') {
            $line = (new OrderGoods())->where([
                ['site_id', '=', $siteId], ['order_id', '=', (int)($event['order_id'] ?? 0)],
                ['order_goods_id', '=', (int)($event['line_id'] ?? 0)],
            ])->findOrEmpty();
            if ($line->isEmpty()) throw new CommonException('商城原订单明细不存在，请人工核对，不能按商品名称猜测');
            $row = $this->readSku($siteId, (int)$line->sku_id, !empty($event['lock']));
            $extend = ErpDeviceSnapshot::decode($line->extend);
            $hasSnapshot = is_array($extend['erp_device'] ?? null);
            $row['current_device'] = $row['device'];
            if ($hasSnapshot) $row['device'] = $extend['erp_device'];
            $row['quantity'] = (int)$line->num;
            $row['goods_name'] = (string)$line->goods_name;
            $row['sku_name'] = (string)$line->sku_name;
            $row['cost_price'] = (float)$line->cost_price_snapshot;
            $row['identity_source'] = $hasSnapshot ? '订单串号快照' : '当前商城商品（旧订单无串号快照，请核对）';
            return ['provider' => 'phone_shop', 'data' => $row];
        }
        $skuId = (int)($event['sku_id'] ?? 0);
        $row = $this->readSku($siteId, $skuId, $action === 'bind' || !empty($event['lock']));
        if ($action === 'bind') {
            $assetId = (int)($event['asset_id'] ?? 0);
            if ($assetId <= 0) throw new CommonException('ERP关联资产无效');
            if (!hash_equals($row['token'], (string)($event['token'] ?? ''))) {
                throw new CommonException('商城商品已发生变化，请重新预览后确认');
            }
            if ((int)$row['erp_asset_id'] > 0 && (int)$row['erp_asset_id'] !== $assetId) {
                throw new CommonException('商城商品已经关联其他ERP设备，不能覆盖');
            }
            $other = (new GoodsSku())->alias('s')
                ->join((new Goods())->getTable() . ' g', 'g.goods_id = s.goods_id AND g.site_id = s.site_id')
                ->where([['s.site_id', '=', $siteId], ['s.erp_asset_id', '=', $assetId], ['s.sku_id', '<>', $skuId], ['g.delete_time', '=', 0]])
                ->lock(true)->value('s.sku_id');
            if ($other) throw new CommonException('该ERP设备已经关联另一条商城商品，请先核对重复商品');
            (new GoodsSku())->where([['site_id', '=', $siteId], ['sku_id', '=', $skuId]])
                ->update(['erp_asset_id' => $assetId, 'is_unique' => 1]);
            return ['provider' => 'phone_shop', 'data' => ['bound' => true, 'sku_id' => $skuId, 'asset_id' => $assetId]];
        }
        if ($action !== 'sku') throw new CommonException('不支持的商城库存对账操作');
        return ['provider' => 'phone_shop', 'data' => $row];
    }

    private function readSku(int $siteId, int $skuId, bool $lock = false): array
    {
        $sku = (new GoodsSku())->where([['site_id', '=', $siteId], ['sku_id', '=', $skuId]])->lock($lock)->findOrEmpty();
        if ($sku->isEmpty()) throw new CommonException('商城SKU不存在，无法自动关联，请人工核对原订单');
        $goods = (new Goods())->where([['site_id', '=', $siteId], ['goods_id', '=', (int)$sku->goods_id]])->lock($lock)->findOrEmpty();
        if ($goods->isEmpty()) throw new CommonException('商城商品已删除，不能自动建立库存关联');
        $row = [
            'sku_id' => $skuId, 'goods_id' => (int)$goods->goods_id,
            'goods_name' => (string)$goods->goods_name, 'sku_name' => (string)$sku->sku_name,
            'stock' => (int)$sku->stock, 'quantity' => 1,
            'cost_price' => (float)$sku->cost_price, 'price' => (float)$sku->price,
            'status' => (int)$goods->status, 'sale_status' => (string)$goods->sale_status,
            'erp_asset_id' => (int)$sku->erp_asset_id,
            'device' => ErpDeviceSnapshot::fromSku($sku->toArray(), $goods->toArray()),
            'image_urls' => is_array($goods->goods_image) ? $goods->goods_image : array_values(array_filter(array_map('trim', explode(',', (string)$goods->goods_image)))),
            'identity_source' => '当前商城商品',
        ];
        $row['token'] = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $row;
    }
}
