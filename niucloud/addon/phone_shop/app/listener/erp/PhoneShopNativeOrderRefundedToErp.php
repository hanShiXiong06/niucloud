<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use think\facade\Log;

/** 商城自有商品退款完成后，通知 ERP 原路冲销销售与资金事实。 */
class PhoneShopNativeOrderRefundedToErp
{
    public function handle($data): array
    {
        try {
            $refund = (array)($data['refund_data'] ?? []);
            $siteId = (int)($refund['site_id'] ?? 0);
            $orderId = (int)($refund['order_id'] ?? 0);
            $lineId = (int)($refund['order_goods_id'] ?? 0);
            $refundAmount = round((float)($refund['money'] ?? 0), 2);
            $refundGoodsAmount = round((float)($refund['refund_order_goods_money'] ?? $refundAmount), 2);
            $refundDeliveryAmount = round((float)($refund['refund_delivery_money'] ?? 0), 2);
            if ($siteId <= 0 || $orderId <= 0 || $lineId <= 0 || $refundAmount <= 0 || $refundGoodsAmount <= 0) {
                return $this->skip('invalid_refund');
            }

            $order = (new Order())->where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->findOrEmpty();
            if ($order->isEmpty()) return $this->skip('order_not_found');
            if ((string)($order['order_from'] ?? '') === 'offline' || trim((string)($order['relate_source'] ?? '')) !== '') {
                return $this->skip('erp_mirror_order');
            }
            if ((string)($order['payment_mode'] ?? 'online') !== 'online') {
                return $this->skip('offline_refund_owned_by_erp');
            }
            $line = (new OrderGoods())->where([['site_id', '=', $siteId], ['order_goods_id', '=', $lineId]])->findOrEmpty();
            if ($line->isEmpty()) return $this->skip('line_not_found');
            $assetId = (int)((new GoodsSku())->where([['site_id', '=', $siteId], ['sku_id', '=', (int)$line->sku_id]])->value('erp_asset_id') ?? 0);
            $device = ErpDeviceSnapshot::fromOrderLine($line->toArray(), ['erp_asset_id' => $assetId]);
            $assetId = (int)($device['erp_asset_id'] ?? 0);

            $refundNo = trim((string)($refund['order_refund_no'] ?? '')) ?: ('REFUND-' . (int)($refund['refund_id'] ?? 0));
            $refundEventKey = (int)($refund['refund_id'] ?? 0) > 0
                ? (string)(int)$refund['refund_id']
                : substr(hash('sha256', $refundNo), 0, 24);
            $transferTime = $refund['transfer_time'] ?? 0;
            $occurredAt = is_numeric($transferTime) ? (int)$transferTime : (int)strtotime((string)$transferTime);
            $event = [
                'event_name' => 'erp.external_sale.refunded_requested.v1',
                'event_version' => 1,
                'event_id' => 'phone_shop:native_refund:' . $refundEventKey . ':finished:v1',
                'site_id' => $siteId,
                'source_plugin' => 'phone_shop',
                'source_order_id' => (string)$orderId,
                'source_order_no' => (string)($order['order_no'] ?? ''),
                'source_line_id' => (string)$lineId,
                'asset_id' => $assetId,
                'refund_id' => (string)($refund['refund_id'] ?? ''),
                'refund_no' => $refundNo,
                'refund_amount' => number_format($refundAmount, 2, '.', ''),
                'refund_goods_amount' => number_format($refundGoodsAmount, 2, '.', ''),
                'refund_delivery_amount' => number_format($refundDeliveryAmount, 2, '.', ''),
                'occurred_at' => $occurredAt > 0 ? $occurredAt : time(),
                'reason' => trim((string)($refund['reason'] ?? $refund['remark'] ?? '商城退款完成')),
            ];
            $results = (array)event('ErpExternalSaleRefundedRequested', $event);
            foreach ($results as $result) {
                if (is_array($result) && (string)($result['consumer'] ?? '') === 'hsx_erp') {
                    return ['consumer' => 'phone_shop', 'status' => (string)($result['status'] ?? 'processed'), 'erp' => $result];
                }
            }
            return $this->skip('erp_not_installed');
        } catch (\Throwable $e) {
            Log::error('[phone_shop] 商城自有商品退款同步ERP失败: ' . $e->getMessage());
            return ['consumer' => 'phone_shop', 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    private function skip(string $reason): array
    {
        return ['consumer' => 'phone_shop', 'status' => 'skipped', 'reason' => $reason];
    }
}
