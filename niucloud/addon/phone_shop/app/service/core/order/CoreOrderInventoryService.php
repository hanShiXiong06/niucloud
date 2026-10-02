<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderRefund;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderRefundDict;
use core\exception\CommonException;
use think\facade\Db;

/** 订单事务内占库/释放，异步消息只做通知，不能决定一台设备是否还能再次下单。 */
class CoreOrderInventoryService
{
    public static function isManaged(array $line): bool
    {
        return isset(ErpDeviceSnapshot::decode($line['extend'] ?? [])['inventory_hold']);
    }

    /** 调用方必须已开启订单事务。重新读取库存，不能信任算价缓存。 */
    public function reserve(int $siteId, int $orderId): array
    {
        $this->requireTransaction();
        $lines = OrderGoods::where('site_id', $siteId)->where('order_id', $orderId)->order('sku_id asc')->lock(true)->select();
        $skuIds = array_values(array_unique(array_column($lines->toArray(), 'sku_id')));
        $skus = GoodsSku::where('site_id', $siteId)->whereIn('sku_id', $skuIds)->order('sku_id asc')->lock(true)->select();
        $map = [];
        foreach ($skus as $sku) $map[(int)$sku->sku_id] = $sku;
        foreach ($lines as $line) {
            $extend = ErpDeviceSnapshot::decode($line->extend);
            if (isset($extend['inventory_hold'])) continue;
            $sku = $map[(int)$line->sku_id] ?? null;
            if (!$sku) throw new CommonException('下单商品不存在，请刷新后重试');
            $goods = Goods::where('site_id', $siteId)->where('goods_id', (int)$sku->goods_id)->lock(true)->findOrEmpty();
            $device = ErpDeviceSnapshot::fromSku($sku->toArray());
            $unique = (int)$sku->is_unique === 1 || (int)$sku->erp_asset_id > 0 || $device['imei'] !== '';
            $num = (int)$line->num;
            if ($goods->isEmpty() || (int)$goods->status !== 1 || in_array((string)$goods->sale_status, ['sold', 'locked'], true)) {
                throw new CommonException('商品已下架或被其他订单占用，请刷新后重试');
            }
            if ($num <= 0 || ($unique && ($num !== 1 || (int)$sku->stock !== 1)) || (int)$sku->stock < $num) {
                throw new CommonException('商品库存不足，该设备可能已被其他客户拍下，请刷新后重试');
            }
            $extend['inventory_hold'] = ['state' => 'held', 'device' => $unique ? 1 : 0, 'at' => time()];
            $line->save(['extend' => ErpDeviceSnapshot::checkLength($extend)]);
            $sku->save(['stock' => (int)$sku->stock - $num]);
            $this->refreshGoods($siteId, (int)$sku->goods_id, false);
            if ($unique && (int)GoodsSku::where('site_id', $siteId)->where('goods_id', (int)$sku->goods_id)->sum('stock') === 0) {
                $goods->save(['sale_status' => 'locked']);
            }
        }
        return OrderGoods::where('site_id', $siteId)->where('order_id', $orderId)->select()->toArray();
    }

    /** 未付取消立即释放；设备退款不等于实物已回库，需退回确认后才能恢复库存。 */
    public function releaseCancelled(int $siteId, int $orderId, bool $refundClose): void
    {
        $this->requireTransaction();
        $lines = OrderGoods::where('site_id', $siteId)->where('order_id', $orderId)->order('sku_id asc')->lock(true)->select();
        foreach ($lines as $line) {
            $extend = ErpDeviceSnapshot::decode($line->extend);
            $hold = $extend['inventory_hold'] ?? [];
            if (($hold['state'] ?? '') !== 'held') continue;
            if ($refundClose && !empty($hold['device'])) continue;
            $sku = GoodsSku::where('site_id', $siteId)->where('sku_id', (int)$line->sku_id)->lock(true)->findOrEmpty();
            if ($sku->isEmpty()) throw new CommonException('原订单商品不存在，无法释放库存，请联系管理员');
            $extend['inventory_hold']['state'] = 'released';
            $line->save(['extend' => ErpDeviceSnapshot::checkLength($extend)]);
            $sku->save(['stock' => (int)$sku->stock + (int)$line->num]);
            $this->refreshGoods($siteId, (int)$sku->goods_id, false);
            Goods::where('site_id', $siteId)->where('goods_id', (int)$sku->goods_id)->where('sale_status', 'locked')->update(['sale_status' => 'available']);
        }
    }

    /** 已确认实物退回后恢复上架及可售；仅关单或退款不得传入 returned。 */
    public function refreshGoods(int $siteId, int $goodsId, bool $returned): void
    {
        $stock = max(0, (int)GoodsSku::where('site_id', $siteId)->where('goods_id', $goodsId)->sum('stock'));
        $data = ['stock' => $stock, 'update_time' => time()];
        if ($returned && $stock > 0) $data += ['status' => 1, 'sale_status' => 'available'];
        Goods::where('site_id', $siteId)->where('goods_id', $goodsId)->update($data);
    }

    private function requireTransaction(): void
    {
        if (!Db::connect()->getPdo()->inTransaction()) throw new CommonException('库存操作必须与原订单处于同一事务');
    }

    /** ERP再次销售/发布前检查原商城订单；ERP销售占库必须参与同一事务。 */
    public function guardErpSale(int $siteId, int $assetId, int $sourceOrderId = 0, bool $reserve = false): array
    {
        if ($siteId <= 0 || $assetId <= 0) throw new CommonException('销售库存检查缺少站点或设备');
        if ($reserve) $this->requireTransaction();
        $skus = GoodsSku::where('site_id', $siteId)->where('erp_asset_id', $assetId)->lock($reserve)->select();
        if ($skus->isEmpty()) return ['allowed' => true, 'linked' => false];
        if (count($skus) !== 1) throw new CommonException('该ERP设备关联多条商城商品，请先核对关联，未执行销售或上架');
        $sku = $skus[0];
        $lines = OrderGoods::where('site_id', $siteId)->where('sku_id', (int)$sku->sku_id)->select()->toArray();
        $orders = Order::where('site_id', $siteId)->whereIn('order_id', array_column($lines, 'order_id'))->column('status', 'order_id');
        foreach ($lines as $line) {
            if ((int)$line['order_id'] === $sourceOrderId) continue; // 原商城订单入账，不是第二次卖出。
            $record = ErpDeviceSnapshot::decode($line['extend'])['erp_return'] ?? [];
            if (!empty($record['received'])) continue;
            $refunded = (float)OrderRefund::where('site_id', $siteId)->where('order_goods_id', (int)$line['order_goods_id'])
                ->where('status', OrderRefundDict::FINISH)->sum('money');
            if ($record || ($refunded > 0 && round($refunded, 2) >= round((float)$line['order_goods_money'], 2))) {
                throw new CommonException('该设备已办理退款，但尚未确认实物收回。请到原商城订单确认设备已收回，再销售或上架');
            }
            if (isset($orders[(int)$line['order_id']]) && (int)$orders[(int)$line['order_id']] !== OrderDict::CLOSE) {
                throw new CommonException('该设备已被商城原订单占用，请先处理原订单，不能再次销售或上架');
            }
        }
        if ($reserve) {
            // ERP出库的异步通知尚未运行时，商城也不能再次拍下这台设备；失败随ERP事务回滚。
            $sku->save(['stock' => 0]);
            $this->refreshGoods($siteId, (int)$sku->goods_id, false);
            Goods::where('site_id', $siteId)->where('goods_id', (int)$sku->goods_id)->update(['sale_status' => 'locked']);
        }
        return ['allowed' => true, 'linked' => true];
    }
}
