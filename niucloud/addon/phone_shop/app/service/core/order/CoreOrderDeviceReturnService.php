<?php
declare(strict_types=1);
namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderRefundDict;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\model\order\OrderRefund;
use addon\phone_shop\app\model\order\OrderOfflineRecord;
use core\exception\CommonException;
use think\facade\Db;

/** 只处理原单留痕与实物回库。绝不创建销售、应收或调用付款接口。 */
class CoreOrderDeviceReturnService
{
    /** 商城线下退回与 ERP 账务在同一事务中确认；这里不创建新销售或实际转账。 */
    public function receiveOffline(int $siteId, int $lineId, array $result, string $receiver): array
    {
        if (!Db::connect()->getPdo()->inTransaction()) throw new CommonException('线下退回必须在原订单事务内办理');
        $line = OrderGoods::where('site_id', $siteId)->where('order_goods_id', $lineId)->lock(true)->findOrEmpty();
        if ($line->isEmpty() || (int)$line->num !== 1) throw new CommonException('原订单设备明细无效');
        $order = Order::where('site_id', $siteId)->where('order_id', (int)$line->order_id)->lock(true)->findOrEmpty();
        if ($order->isEmpty() || (empty($order->relate_source) && !in_array((string)$order->payment_mode, ['offline_cash', 'offline_credit'], true))) {
            throw new CommonException('此入口仅支持已收款或已挂账的线下订单');
        }
        if (!empty(ErpDeviceSnapshot::decode($line->extend)['erp_return']['received'])) return ['duplicate' => true];
        if ((int)($result['return_id'] ?? 0) <= 0 || (string)($result['return_no'] ?? '') === '') throw new CommonException('ERP 未确认退货账务，本次未恢复库存');
        $sku = GoodsSku::where('site_id', $siteId)->where('sku_id', (int)$line->sku_id)->lock(true)->findOrEmpty();
        if ($sku->isEmpty()) throw new CommonException('原商城商品不存在，未执行本次退回');
        $identity = ErpDeviceSnapshot::fromOrderLine($line->toArray(), $sku->toArray());
        if ((int)$sku->is_unique !== 1 && (int)$sku->erp_asset_id <= 0 && ($identity['imei'] ?? '') === '') {
            throw new CommonException('此入口仅支持单台实物设备，普通多库存商品请通过原售后流程办理');
        }
        if ((int)$sku->erp_asset_id !== (int)($result['asset_id'] ?? 0)) throw new CommonException('原商品的 ERP 关联已变化，请核对后退回；未覆盖库存');
        if ((int)$sku->erp_asset_id > 0 && !$this->erpAssetIsCurrentReturn($siteId, (int)$sku->erp_asset_id, PHP_INT_MAX)) {
            throw new CommonException('ERP 设备未回库，本次退货已回滚');
        }
        return $this->record($siteId, $order, $line, $sku, [
            'received' => 1, 'type' => 'sale_return', 'no' => (string)$result['return_no'],
            'receiver' => mb_substr($receiver, 0, 30),
        ]);
    }

    public function fromErp(int $siteId, int $assetId, string $outboundNo, array $context): array
    {
        return Db::transaction(function () use ($siteId, $assetId, $outboundNo, $context) {
            $sourceOrderId = (int)($context['source_order_id'] ?? 0);
            $orderQuery = Order::where('site_id', $siteId);
            if ($sourceOrderId > 0) $orderQuery->where('order_id', $sourceOrderId);
            elseif ($outboundNo !== '') $orderQuery->where('relate_source', $outboundNo);
            else throw new CommonException('退回通知缺少原订单标识，未改变商城库存');
            $orders = $orderQuery->lock(true)->select();
            if (count($orders) > 1) throw new CommonException('原出库单对应多笔商城订单，请核对后重试，未自动选择订单');
            if ($sourceOrderId > 0 && $orders->isEmpty()) throw new CommonException('退回通知的原商城订单不存在');
            $skus = GoodsSku::where('site_id', $siteId)->where('erp_asset_id', $assetId)->lock(true)->select();
            if ($skus->isEmpty()) return ['skipped' => true, 'reason' => '设备没有对应商城商品'];
            if (count($skus) !== 1) throw new CommonException('ERP设备对应多个商城SKU，无法安全恢复库存');
            $sku = $skus[0];
            $order = $orders->isEmpty() ? null : $orders[0];
            $line = null;
            if ($order) {
                $query = OrderGoods::where('site_id', $siteId)->where('order_id', (int)$order->order_id)->where('sku_id', (int)$sku->sku_id);
                if ((int)($context['source_line_id'] ?? 0) > 0) $query->where('order_goods_id', (int)$context['source_line_id']);
                $lines = $query->lock(true)->select();
                if (count($lines) !== 1) throw new CommonException('退回设备与原订单明细无法唯一对应，未改变订单或库存');
                $line = $lines[0];
                if ((int)$line->num !== 1) throw new CommonException('原订单不是单台设备明细，请核对数量后处理退回');
                $extend = ErpDeviceSnapshot::decode($line->extend);
                if (isset($extend['erp_return'])) return ['ok' => true, 'duplicate' => true];
            }
            if (!$this->erpAssetIsCurrentReturn($siteId, $assetId, (int)($context['snapshot_at'] ?? 0))) {
                return ['ok' => true, 'stale' => true, 'message' => '设备已有新的ERP状态，本次旧退回通知未覆盖新状态'];
            }
            $received = !empty($context['received']) || in_array($context['return_type'] ?? '', ['sale_return', 'sale_cancel', 'sale_item_cancel'], true);
            if (!$order) {
                // ERP直接出库未生成商城展示单：只恢复库存，不额外造一笔商城订单。
                if (!$received) return ['ok' => true, 'awaiting_receipt' => true];
                $this->restoreStock($siteId, $sku, 0);
                return ['returned' => true, 'goods_id' => (int)$sku->goods_id];
            }
            return $this->record($siteId, $order, $line, $sku, [
                'received' => $received ? 1 : 0,
                'type' => (string)($context['return_type'] ?? 'sale_return'),
                'no' => mb_substr((string)($context['return_no'] ?? $context['refund_no'] ?? $outboundNo), 0, 60),
                'receiver' => mb_substr((string)($context['receiver_name'] ?? ''), 0, 30),
            ]);
        });
    }

    /** 线上原路退款完成后，由管理员确认实物收回；不做退款、不把退款中当成功。 */
    public function confirmReceived(int $siteId, int $lineId, string $receiver): array
    {
        return Db::transaction(function () use ($siteId, $lineId, $receiver) {
            $orderId = (int)OrderGoods::where('site_id', $siteId)->where('order_goods_id', $lineId)->value('order_id');
            $order = Order::where('site_id', $siteId)->where('order_id', $orderId)->lock(true)->findOrEmpty();
            $line = OrderGoods::where('site_id', $siteId)->where('order_goods_id', $lineId)->lock(true)->findOrEmpty();
            if ($order->isEmpty() || $line->isEmpty()) throw new CommonException('原订单设备不存在');
            $extend = ErpDeviceSnapshot::decode($line->extend);
            if (!empty($extend['erp_return']['received'])) return ['ok' => true, 'duplicate' => true];
            if (!empty($order->relate_source) || in_array((string)$order->payment_mode, ['offline_cash', 'offline_credit'], true)) {
                throw new CommonException('此订单的退回和退款由ERP处理，请从原销售单办理退货');
            }
            $refunded = (float)OrderRefund::where('site_id', $siteId)->where('order_goods_id', $lineId)->where('status', OrderRefundDict::FINISH)->sum('money');
            if ($refunded <= 0 || round($refunded, 2) < round((float)$line->order_goods_money, 2)) {
                throw new CommonException('此设备尚未全额退款成功，不能恢复库存。请先在原订单完成退款');
            }
            $sku = GoodsSku::where('site_id', $siteId)->where('sku_id', (int)$line->sku_id)->lock(true)->findOrEmpty();
            if ($sku->isEmpty()) throw new CommonException('原商品已不存在，无法恢复库存');
            $identity = ErpDeviceSnapshot::fromSku($sku->toArray());
            if ((int)$line->num !== 1 || ((int)$sku->is_unique !== 1 && (int)$sku->erp_asset_id <= 0 && $identity['imei'] === '')) {
                throw new CommonException('此入口只处理单台设备，普通商品请使用原售后流程');
            }
            if ((int)$sku->erp_asset_id > 0 && !$this->erpAssetIsCurrentReturn($siteId, (int)$sku->erp_asset_id, PHP_INT_MAX)) {
                throw new CommonException('ERP退回尚未同步成功或设备已再次售出，请先核对ERP设备状态');
            }
            return $this->record($siteId, $order, $line, $sku, array_merge($extend['erp_return'] ?? [], [
                'received' => 1, 'type' => 'online_payment_refund', 'receiver' => mb_substr($receiver, 0, 30),
            ]));
        });
    }

    private function record(int $siteId, Order $order, OrderGoods $line, GoodsSku $sku, array $record): array
    {
        $extend = ErpDeviceSnapshot::decode($line->extend);
        $record += ['order_status' => (int)$order->status, 'refund_enabled' => (int)$line->is_enable_refund];
        $record['at'] = time();
        $extend['erp_return'] = $record;
        if (isset($extend['inventory_hold'])) $extend['inventory_hold']['state'] = !empty($record['received']) ? 'returned' : 'awaiting_receipt';
        if (!empty($record['received'])) $this->restoreStock($siteId, $sku, (int)$line->order_goods_id);
        $line->save(['extend' => ErpDeviceSnapshot::checkLength($extend), 'is_enable_refund' => 0]);
        $allReturned = true;
        foreach (OrderGoods::where('site_id', $siteId)->where('order_id', (int)$order->order_id)->where('is_gift', 0)->select() as $item) {
            if (!isset(ErpDeviceSnapshot::decode($item->extend)['erp_return'])) $allReturned = false;
        }
        if ($allReturned && (int)$order->status !== OrderDict::CLOSE) {
            $order->save(['status' => OrderDict::CLOSE, 'close_type' => OrderDict::REFUND_CLOSE, 'close_time' => time(),
                'close_remark' => '原单设备已退回/退款；原成交金额及收付款记录保留，退款进度以原退款单或ERP退货单为准',
                'is_enable_refund' => 0, 'timeout' => 0]);
        }
        if ($allReturned && in_array((string)$order->payment_mode, ['offline_cash', 'offline_credit'], true)) {
            OrderOfflineRecord::where('site_id', $siteId)->where('order_id', (int)$order->order_id)->update([
                'status' => 'closed', 'close_reason' => '设备已退回，原收款记录保留；退款进度请查看ERP原退货单', 'update_time' => time(),
            ]);
        }
        // 不改原单金额、已付金额或业务员，不调用建单/收款事件。
        return ['returned' => true, 'received' => (bool)$record['received'], 'goods_id' => (int)$sku->goods_id, 'order_id' => (int)$order->order_id];
    }

    private function restoreStock(int $siteId, GoodsSku $sku, int $originalLineId): void
    {
        $lines = OrderGoods::where('site_id', $siteId)->where('sku_id', (int)$sku->sku_id)->where('order_goods_id', '<>', $originalLineId)->select()->toArray();
        $activeIds = Order::where('site_id', $siteId)->whereIn('order_id', array_column($lines, 'order_id'))->where('status', '<>', OrderDict::CLOSE)->column('order_id');
        foreach ($lines as $line) {
            if (in_array((int)$line['order_id'], array_map('intval', $activeIds), true) && !isset(ErpDeviceSnapshot::decode($line['extend'])['erp_return'])) {
                throw new CommonException('该设备已被其他订单占用，本次退回未覆盖新订单库存');
            }
        }
        if ((int)$sku->stock > 1) throw new CommonException('单台设备的商城库存大于一台，请先核对异常数量，未自动覆盖');
        // 同一实物只能恢复一台；已经回库/手工上架的重复事件不再下架。
        if ((int)$sku->stock > 0) return;
        $sku->save(['stock' => 1]);
        (new CoreOrderInventoryService())->refreshGoods($siteId, (int)$sku->goods_id, true);
    }

    /** ERP撤销退货时参与同一事务，保留原销售，不能另造商城订单或覆盖新订单。 */
    public function undoErpReturn(array $data): array
    {
        if (!Db::connect()->getPdo()->inTransaction()) throw new CommonException('撤销退货必须与ERP原单处于同一事务');
        $siteId = (int)$data['site_id'];
        $orderQuery = Order::where('site_id', $siteId);
        if ((int)($data['source_order_id'] ?? 0) > 0) $orderQuery->where('order_id', (int)$data['source_order_id']);
        else $orderQuery->where('relate_source', (string)$data['outbound_no']);
        $orders = $orderQuery->lock(true)->select();
        if (count($orders) > 1) throw new CommonException('原出库单对应多笔商城订单，不能自动撤销退货');
        $sku = GoodsSku::where('site_id', $siteId)->where('erp_asset_id', (int)$data['asset_id'])->lock(true)->findOrEmpty();
        if ($sku->isEmpty()) return ['skipped' => true];
        $order = $orders->isEmpty() ? null : $orders[0];
        $line = $order ? OrderGoods::where('site_id', $siteId)->where('order_id', (int)$order->order_id)->where('sku_id', (int)$sku->sku_id)->lock(true)->findOrEmpty() : null;
        $originalLineId = $line && !$line->isEmpty() ? (int)$line->order_goods_id : 0;
        // 只使用占用检查，不允许再次恢复库存。
        $otherLines = OrderGoods::where('site_id', $siteId)->where('sku_id', (int)$sku->sku_id)->where('order_goods_id', '<>', $originalLineId)->select();
        foreach ($otherLines as $other) {
            if (isset(ErpDeviceSnapshot::decode($other->extend)['erp_return'])) continue;
            if (Order::where('site_id', $siteId)->where('order_id', (int)$other->order_id)->where('status', '<>', OrderDict::CLOSE)->count()) {
                throw new CommonException('该设备已被商城新订单占用，不能撤销原退货，请先处理新订单');
            }
        }
        if ($line && !$line->isEmpty()) {
            $extend = ErpDeviceSnapshot::decode($line->extend);
            $record = $extend['erp_return'] ?? [];
            if ($record && ($record['no'] ?? '') !== (string)$data['return_no']) throw new CommonException('商城记录的退货单不一致，不能撤销其他退货');
            if ($record) {
                unset($extend['erp_return']);
                $extend['return_cancelled'] = ['no' => (string)$data['return_no'], 'at' => time()];
                if (isset($extend['inventory_hold'])) $extend['inventory_hold']['state'] = 'held';
                $line->save(['extend' => ErpDeviceSnapshot::checkLength($extend), 'is_enable_refund' => (int)($record['refund_enabled'] ?? 0)]);
                if ((int)$order->status === OrderDict::CLOSE) $order->save([
                    'status' => max(1, (int)($record['order_status'] ?? OrderDict::FINISH)), 'close_time' => 0, 'close_type' => '', 'close_remark' => '',
                ]);
                OrderOfflineRecord::where('site_id', $siteId)->where('order_id', (int)$order->order_id)->update([
                    'status' => (int)$order->is_credit === 1 ? 'credit' : 'paid', 'close_reason' => '', 'update_time' => time(),
                ]);
            }
        }
        $sku->save(['stock' => 0]);
        (new CoreOrderInventoryService())->refreshGoods($siteId, (int)$sku->goods_id, false);
        Goods::where('site_id', $siteId)->where('goods_id', (int)$sku->goods_id)->update(['status' => 0, 'sale_status' => 'sold']);
        return ['ok' => true];
    }

    private function erpAssetIsCurrentReturn(int $siteId, int $assetId, int $snapshotAt): bool
    {
        foreach ((array)event('PhoneShopOrderReturnContext', ['site_id' => $siteId, 'action' => 'asset_state', 'asset_id' => $assetId]) as $response) {
            if (($response['provider'] ?? '') !== 'hsx_erp') continue;
            $asset = $response['asset'] ?? [];
            return ($asset['status'] ?? '') === 'in_stock' && (int)($asset['sale_order_id'] ?? 0) === 0 && (int)($asset['update_at'] ?? 0) <= $snapshotAt;
        }
        throw new CommonException('ERP状态校验服务不可用，未恢复商城库存，请检查插件更新及同步日志');
    }
}
