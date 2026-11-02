<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 线下成交建单(回流/开单共用)
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use core\base\BaseCoreService;
use think\facade\Db;
use think\facade\Log;

/**
 * 由"成交"(ERP出库回流 / 商城开单)记录线下成交，并把商品置 已售/锁定·下架。
 * - ERP 对每台设备各发一个 sold 事件；本服务按 outbound_no 把同一出库单的多台【归并到一笔订单】(首台建单,后续台追加明细)。
 * - 不走 C 端购物车/算价流程，直接精简建单(成交已在 ERP 完成)。
 * - 幂等：同一台机(sku)已在未关闭订单成交 → 跳过。
 * - 商品状态：result_status=sold→已售下架；locked→锁定(挂账可退,仍可见)。
 */
class CoreOfflineSaleService extends BaseCoreService
{
    /**
     * @param array $d site_id, member_id, sku_id, sale_price, payment_mode, buyer_type,
     *                 source_device_id, staff_id, outbound_no, result_status(sold|locked)
     */
    public function createSaleOrder(array $d): array
    {
        $siteId   = (int) ($d['site_id'] ?? 0);
        $memberId = (int) ($d['member_id'] ?? 0);
        $skuId    = (int) ($d['sku_id'] ?? 0);
        if ($siteId <= 0 || $memberId <= 0 || $skuId <= 0) {
            return ['skipped' => true, 'reason' => 'missing_key'];
        }

        $sku = (new GoodsSku())->where([['site_id', '=', $siteId], ['sku_id', '=', $skuId]])->findOrEmpty();
        if ($sku->isEmpty()) return ['skipped' => true, 'reason' => 'sku_not_found'];
        $goodsId = (int) $sku['goods_id'];
        $goods = (new Goods())->where([['site_id', '=', $siteId], ['goods_id', '=', $goodsId]])->findOrEmpty();

        // 幂等：该台机已在未关闭订单成交过 → 跳过
        if ($this->alreadySold($siteId, $skuId)) {
            return ['skipped' => true, 'reason' => 'already_sold'];
        }

        $price        = (float) ($d['sale_price'] ?? $sku['sale_price'] ?? 0);
        $resultStatus = ((string) ($d['result_status'] ?? 'sold')) === 'locked' ? 'locked' : 'sold';
        $paymentMode  = (string) ($d['payment_mode'] ?? 'offline_cash');
        $isCredit     = $paymentMode === 'offline_credit' || $resultStatus === 'locked';
        if ($isCredit) $paymentMode = 'offline_credit'; // 已出库 sold 不是已收款。
        $outboundNo   = (string) ($d['outbound_no'] ?? '');
        $now = time();

        Db::startTrans();
        try {
            // 同一出库单(outbound_no)的已有订单 → 追加明细；否则新建
            $orderModel = new Order();
            $order = $outboundNo !== ''
                ? $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['relate_source', '=', $outboundNo],
                    ['status', '<>', OrderDict::CLOSE],
                ])->findOrEmpty()
                : $orderModel->newInstance();

            if ($order->isEmpty()) {
                $order = $orderModel->create([
                    'site_id'          => $siteId,
                    'order_no'         => create_no(),
                    'order_type'       => OrderDict::TYPE,
                    'order_from'       => 'offline',
                    'status'           => $isCredit ? OrderDict::WAIT_TAKE : OrderDict::FINISH,
                    'member_id'        => $memberId,
                    'goods_money'      => 0,
                    'order_money'      => 0,
                    'pay_money'        => 0,
                    'pay_time'         => $isCredit ? 0 : $now,
                    'finish_time'      => $isCredit ? 0 : $now,
                    'create_time'      => $now,
                    'delivery_time'    => $now,
                    'timeout'          => 0,                              // 不自动关单
                    'payment_mode'     => $paymentMode,
                    'buyer_type'       => (string) ($d['buyer_type'] ?? 'c'),
                    'source_device_id' => (int) ($d['source_device_id'] ?? 0),
                    'staff_id'         => (int) ($d['staff_id'] ?? 0),
                    'is_credit'        => $isCredit ? 1 : 0,
                    'credit_status'    => $isCredit ? 'created' : 'settled',
                    'settle_status'    => $isCredit ? 0 : 1,
                    'has_goods_types'  => json_encode(['real']),
                    'relate_source'    => $outboundNo,
                ]);
            }
            $orderId = (int) $order['order_id'];

            // 追加该台设备为订单项
            (new OrderGoods())->insertAll([[
                'site_id'           => $siteId,
                'order_id'          => $orderId,
                'member_id'         => $memberId,
                'goods_id'          => $goodsId,
                'sku_id'            => $skuId,
                'goods_name'        => (string) ($goods['goods_name'] ?? ''),
                'sku_name'          => (string) ($sku['sku_name'] ?? (string) ($goods['memory_group'] ?? '')),
                'goods_image'       => (string) ($goods['goods_cover'] ?? ''),
                'sku_image'         => (string) ($sku['sku_image'] ?? ''),
                'price'             => $price,
                'num'               => 1,
                'goods_money'       => $price,
                'order_goods_money' => $price,
                'original_price'    => $price,
                'goods_type'        => 'real',
                'extend'            => json_encode(ErpDeviceSnapshot::extend([], $sku->toArray(), $goods->toArray())),
                'status'            => 0,
                'delivery_status'   => $isCredit ? OrderDeliveryDict::DELIVERY_FINISH : OrderDeliveryDict::TAKED,
            ]]);

            // 重算订单合计 + 摘要(body) + 收款
            $items = (new OrderGoods())->where('order_id', $orderId)->field('goods_name,goods_money')->select()->toArray();
            $total = round(array_sum(array_column($items, 'goods_money')), 2);
            $body  = (string) ($items[0]['goods_name'] ?? '') . (count($items) > 1 ? ' 等' . count($items) . '台' : '');
            (new Order())->where('order_id', $orderId)->update([
                'goods_money' => $total,
                'order_money' => $total,
                'pay_money'   => $isCredit ? 0 : $total,
                'body'        => $body,
            ]);

            // 商品置 已售/锁定；已售则下架、库存清零(一物一码卖一台少一台)
            $goodsUpdate = ['sale_status' => $resultStatus, 'update_time' => $now];
            if ($resultStatus === 'sold') $goodsUpdate['status'] = 0;
            (new Goods())->where([['site_id', '=', $siteId], ['goods_id', '=', $goodsId]])->update($goodsUpdate);
            (new GoodsSku())->where([['site_id', '=', $siteId], ['sku_id', '=', $skuId]])->update(['stock' => 0]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::write('[phone_shop] 线下成交建单失败: ' . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }

        // 主站卖出(已售) -> 按主站商品ID精确下架关注副本。失败不影响成交。
        if ($resultStatus === 'sold') {
            try {
                (new \addon\phone_shop\app\service\core\agent\GoodsSyncService())->syncGoods((int)$goodsId, (int)$siteId, 0);
            } catch (\Throwable $e) {
                Log::write('[phone_shop] 卖出联动下架失败: ' . $e->getMessage());
            }
        }

        // TODO 推送客户：后续接 NoticeData（"您购买的 {型号} 已出库"）
        return ['ok' => true, 'order_id' => $orderId, 'sale_status' => $resultStatus];
    }

    /**
     * 出库事件只核对展示单，不把 sold 当作收款成功。
     * 真正收款由结算事件回传；订单结清后仍由正常完成入口结束，不绕过统一闸门。
     */
    public function finalizeByOutbound(int $siteId, string $outboundNo): array
    {
        if ($siteId <= 0 || $outboundNo === '') return ['skipped' => true];
        $order = (new Order())->where([
            ['site_id', '=', $siteId],
            ['relate_source', '=', $outboundNo],
            ['status', '<>', OrderDict::CLOSE],
        ])->findOrEmpty();
        if ($order->isEmpty()) return ['skipped' => true];
        $state = (new CoreOrderCreditStateService())->getState($order->toArray());
        return ['ok' => true, 'order_id' => (int)$order['order_id'],
            'can_complete' => !empty($state['can_complete']), 'finance_snapshot' => $state];
    }

    /** 该台机(sku)是否已在未关闭订单成交过 */
    protected function alreadySold(int $siteId, int $skuId): bool
    {
        $lines = (new OrderGoods())->where('site_id', $siteId)->where('sku_id', $skuId)->select()->toArray();
        $orderIds = array_column(array_filter($lines, static fn($line) => !isset(ErpDeviceSnapshot::decode($line['extend'] ?? [])['erp_return'])), 'order_id');
        if (empty($orderIds)) return false;
        return (new Order())->where([
            ['site_id', '=', $siteId],
            ['order_id', 'in', $orderIds],
            ['status', '<>', OrderDict::CLOSE],
        ])->count() > 0;
    }

    /** ERP 已完成退货账务后，只同步原单与库存；不删除原订单明细。 */
    public function returnSaleItemByAsset(int $siteId, int $assetId, string $outboundNo = '', array $context = []): array
    {
        return (new CoreOrderDeviceReturnService())->fromErp($siteId, $assetId, $outboundNo, $context);
    }
}
