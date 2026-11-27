<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use think\facade\Log;

/** 小程序订单付款后，把其中关联 ERP 一物一码资产的明细交给 ERP 标准销售 Hook。 */
class PhoneShopOrderPaidToErp
{
    public function handle($data): array
    {
        try {
            $orderId = (int)($data['order_id'] ?? $data['trade_id'] ?? 0);
            $siteId = (int)($data['order_data']['site_id'] ?? $data['site_id'] ?? 0);
            if ($orderId <= 0 || $siteId <= 0) return ['consumer' => 'phone_shop', 'status' => 'skipped', 'reason' => 'missing_order'];
            $order = (new Order())->where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->findOrEmpty();
            if ($order->isEmpty()) return ['consumer' => 'phone_shop', 'status' => 'skipped', 'reason' => 'order_not_found'];
            // ERP 出库回流创建的线下展示单不能再次反向进入 ERP。
            if ((string)($order['order_from'] ?? '') === 'offline' || trim((string)($order['relate_source'] ?? '')) !== '') {
                return ['consumer' => 'phone_shop', 'status' => 'skipped', 'reason' => 'erp_mirror_order'];
            }

            $goodsRows = (new OrderGoods())->where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])
                ->field('order_goods_id,sku_id,goods_name,sku_name,num,price,goods_money,order_goods_money,extend,inventory_source')->select()->toArray();
            $skuIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['sku_id'] ?? 0), $goodsRows))));
            $assetBySku = $skuIds === [] ? [] : (new GoodsSku())->where([['site_id', '=', $siteId]])
                ->whereIn('sku_id', $skuIds)->where('erp_asset_id', '>', 0)->column('erp_asset_id', 'sku_id');
            $items = [];
            $allLineAmount = 0.0;
            $assetLineAmount = 0.0;
            foreach ($goodsRows as $row) {
                $lineAmount = round((float)($row['order_goods_money'] ?? $row['goods_money'] ?? $row['price'] ?? 0), 2);
                $allLineAmount = round($allLineAmount + max(0, $lineAmount), 2);
                $device = ErpDeviceSnapshot::fromOrderLine($row, ['erp_asset_id' => (int)($assetBySku[(int)($row['sku_id'] ?? 0)] ?? 0)]);
                $assetId = (int)($device['erp_asset_id'] ?? 0);
                if ($assetId <= 0) continue;
                if ((int)$row['num'] !== 1) throw new \RuntimeException('关联ERP设备的订单数量必须为1，请核对设备关联');
                $price = $lineAmount;
                if ($price <= 0) continue;
                $assetLineAmount = round($assetLineAmount + $price, 2);
                $items[] = [
                    'asset_id' => $assetId,
                    'source_line_id' => (string)($row['order_goods_id'] ?? ''),
                    'sale_price' => $price,
                    'remark' => trim((string)($row['goods_name'] ?? '') . ' ' . (string)($row['sku_name'] ?? '')),
                ];
            }
            if ($items === []) return ['consumer' => 'phone_shop', 'status' => 'skipped', 'reason' => 'no_erp_asset'];

            $orderNo = trim((string)($order['order_no'] ?? '')) ?: ('PHONE-' . $orderId);
            $paymentMode = (string)($order['payment_mode'] ?? 'online');
            $isCredit = $paymentMode === 'offline_credit';
            $isOfflineCash = $paymentMode === 'offline_cash';
            $pricingIdentity = (string)($order['pricing_identity'] ?? 'retail');
            // 一个订单可能同时包含 ERP 一物一码和商城普通商品。两个消费者只能
            // 按各自明细占比分摊同一笔支付，不能分别把整单金额重复入账。
            $ratio = $allLineAmount > 0 ? min(1, $assetLineAmount / $allLineAmount) : 1.0;
            $orderGrossAmount = round((float)($order['order_money'] ?? 0), 2);
            $orderNetAmount = round((float)($order['merchant_net_amount'] ?? $orderGrossAmount), 2);
            $feeAmount = $isOfflineCash || $isCredit ? 0.0 : round((float)($order['payment_fee_amount'] ?? 0) * $ratio, 2);
            $grossAmount = round($orderGrossAmount * $ratio, 2);
            $netAmount = $isOfflineCash || $isCredit
                ? $grossAmount
                : round($orderNetAmount * $ratio, 2);
            $feeBearer = (string)($order['payment_fee_bearer'] ?? 'merchant');
            // 线上付款沿用历史 event_id，避免升级后重放旧事件时重复生成 ERP 销售单。
            $eventSuffix = $isCredit
                ? ':credit_confirmed:v1'
                : ($isOfflineCash ? ':offline_paid:v1' : ':paid:v1');
            $event = [
                'event_name' => 'erp.sale.created_requested',
                'event_version' => 1,
                'event_id' => 'phone_shop:order:' . $orderId . $eventSuffix,
                'site_id' => $siteId,
                'targets' => ['self_erp'],
                'source' => [
                    'plugin' => 'phone_shop', 'plugin_name' => '手机商城',
                    'type' => 'phone_shop.mini_program_sale', 'name' => '小程序销售',
                    'id' => (string)$orderId, 'order_no' => $orderNo,
                ],
                'channel' => ['code' => 'phone_shop_mini_program', 'name' => '小程序商城'],
                'counterparty' => [
                    'party_id' => 0,
                    'name' => trim((string)($order['taker_name'] ?? '')) ?: ('商城会员#' . (int)($order['member_id'] ?? 0)),
                ],
                'operator' => [
                    'type' => $isOfflineCash || $isCredit ? 'staff' : 'system',
                    'id' => (int)($order['staff_id'] ?? $data['operator_id'] ?? 0),
                    'name' => trim((string)($data['operator_name'] ?? '')) ?: ($isOfflineCash || $isCredit ? '商城业务员' : '手机商城'),
                ],
                'occurred_at' => ErpDeviceSnapshot::saleTime($order->getData(), $goodsRows),
                'items' => $items,
                'payment' => [
                    'status' => $isCredit ? 'unpaid' : 'paid',
                    'mode' => $paymentMode === 'online' ? 'wechat_online' : $paymentMode,
                    'pricing_identity' => $pricingIdentity,
                    'gross_amount' => number_format($grossAmount, 2, '.', ''),
                    'fee_rate' => number_format($isOfflineCash || $isCredit ? 0 : (float)($order['payment_fee_rate'] ?? 0), 6, '.', ''),
                    'fee_amount' => number_format($feeAmount, 2, '.', ''),
                    'fee_bearer' => $feeBearer,
                    'merchant_net_amount' => number_format($netAmount, 2, '.', ''),
                    'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
                    'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
                    'voucher_urls' => array_values((array)($data['voucher_urls'] ?? [])),
                ],
                'remark' => trim(sprintf(
                    '%s%s；成交¥%.2f，手续费¥%.2f（%s承担），净额¥%.2f。',
                    $isCredit ? '客户选择线下挂账' : ($isOfflineCash ? '业务员已确认线下收款' : '商城微信已支付'),
                    $pricingIdentity === 'peer' ? '（同行价）' : '（零售价）',
                    $grossAmount,
                    $feeAmount,
                    $feeBearer === 'customer' ? '客户' : '商家',
                    $netAmount
                )),
            ];
            $results = (array)event('ErpSaleCreatedRequested', $event);
            foreach ($results as $result) {
                if (is_array($result) && (string)($result['consumer'] ?? '') === 'hsx_erp') {
                    return ['consumer' => 'phone_shop', 'status' => (string)($result['status'] ?? 'processed'), 'erp' => $result];
                }
            }
            return ['consumer' => 'phone_shop', 'status' => 'skipped', 'reason' => 'erp_not_installed'];
        } catch (\Throwable $e) {
            Log::error('[phone_shop] 小程序销售同步ERP失败: ' . $e->getMessage());
            // 支付主流程已经成立，不能因下游 ERP 暂时失败回滚客户支付；event_id 允许人工重推。
            return ['consumer' => 'phone_shop', 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }
}
