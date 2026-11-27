<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use think\facade\Log;

/**
 * 商城自有商品付款桥。
 *
 * 传递成交时点的串号、售价及成本。ERP可复用唯一现存设备补出库；
 * 缺失的设备由库存对账确认期初，不在付款回调中猜成本、创建采购应付。
 */
class PhoneShopNativeOrderPaidToErp
{
    public function handle($data): array
    {
        try {
            $orderId = (int)($data['order_id'] ?? $data['trade_id'] ?? 0);
            $siteId = (int)($data['order_data']['site_id'] ?? $data['site_id'] ?? 0);
            if ($orderId <= 0 || $siteId <= 0) return $this->skip('missing_order');

            $order = (new Order())->where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->findOrEmpty();
            if ($order->isEmpty()) return $this->skip('order_not_found');
            if ((string)($order['order_from'] ?? '') === 'offline' || trim((string)($order['relate_source'] ?? '')) !== '') {
                return $this->skip('erp_mirror_order');
            }

            $rows = (new OrderGoods())->where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])
                ->field('order_goods_id,goods_id,sku_id,goods_name,sku_name,num,price,goods_money,order_goods_money,cost_price_snapshot,total_cost_snapshot,supplier_id_snapshot,inventory_source,extend')
                ->select()->toArray();
            if ($rows === []) return $this->skip('no_goods');

            $skuIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['sku_id'] ?? 0), $rows))));
            $skuSnapshots = $skuIds === [] ? [] : (new GoodsSku())->where([['site_id', '=', $siteId]])
                ->whereIn('sku_id', $skuIds)->field('sku_id,goods_id,sku_no,cost_price,erp_asset_id,is_unique,device_snapshot')->select()->toArray();
            $skuSnapshots = array_column($skuSnapshots, null, 'sku_id');
            $goodsIds = array_values(array_unique(array_filter(array_map(
                static fn(array $row): int => (int)($row['goods_id'] ?? 0),
                $skuSnapshots
            ))));
            $goodsSnapshots = $goodsIds === [] ? [] : (new Goods())->where([['site_id', '=', $siteId]])
                ->whereIn('goods_id', $goodsIds)->field('goods_id,supplier_id,source,is_proxy')->select()->toArray();
            $goodsSnapshots = array_column($goodsSnapshots, null, 'goods_id');

            $items = [];
            $allLineAmount = 0.0;
            $nativeAmount = 0.0;
            foreach ($rows as $row) {
                $lineAmount = round((float)($row['order_goods_money'] ?? $row['goods_money'] ?? 0), 2);
                $allLineAmount = round($allLineAmount + max(0, $lineAmount), 2);
                $skuSnapshot = (array)($skuSnapshots[(int)($row['sku_id'] ?? 0)] ?? []);
                $goodsSnapshot = (array)($goodsSnapshots[(int)($row['goods_id'] ?? 0)] ?? []);
                $device = ErpDeviceSnapshot::fromOrderLine($row, $skuSnapshot, $goodsSnapshot);
                // 成交后补关联不能让同一订单重放时切换通道，再造一张销售/应收。
                if ((int)($device['erp_asset_id'] ?? 0) > 0) continue;
                if ($lineAmount <= 0) continue;
                $quantity = max(1, (int)($row['num'] ?? 1));
                $unitCost = round((float)($row['cost_price_snapshot'] ?? 0), 2);
                if ($unitCost <= 0 && (float)($skuSnapshot['cost_price'] ?? 0) > 0) {
                    $unitCost = round((float)$skuSnapshot['cost_price'], 2);
                }
                $totalCost = round((float)($row['total_cost_snapshot'] ?? ($unitCost * $quantity)), 2);
                if ($totalCost <= 0 && $unitCost > 0) $totalCost = round($unitCost * $quantity, 2);
                $supplierId = (int)($row['supplier_id_snapshot'] ?? 0);
                if ($supplierId <= 0) {
                    $supplierId = (int)($goodsSnapshot['supplier_id'] ?? 0);
                }
                $source = trim((string)($row['inventory_source'] ?? ''));
                if ($source !== 'opening' && $supplierId > 0) {
                    $source = 'supplier';
                } elseif (!in_array($source, ['supplier', 'self_owned', 'opening'], true)) {
                    $source = $supplierId > 0 ? 'supplier' : 'self_owned';
                }
                $nativeAmount = round($nativeAmount + $lineAmount, 2);
                $items[] = [
                    'source_line_id' => (string)$row['order_goods_id'],
                    'goods_id' => (int)$row['goods_id'],
                    'sku_id' => (int)$row['sku_id'],
                    'goods_name' => trim((string)$row['goods_name']),
                    'sku_name' => trim((string)$row['sku_name']),
                    'quantity' => $quantity,
                    'sale_amount' => number_format($lineAmount, 2, '.', ''),
                    'unit_cost' => number_format($unitCost, 2, '.', ''),
                    'total_cost' => number_format($totalCost, 2, '.', ''),
                    'supplier_id' => $supplierId,
                    'inventory_source' => $source,
                    'device' => $device,
                ];
            }
            if ($items === []) return $this->skip('no_native_goods');

            $paymentMode = (string)($order['payment_mode'] ?? 'online');
            $isCredit = $paymentMode === 'offline_credit';
            $isOfflineCash = $paymentMode === 'offline_cash';
            $ratio = $allLineAmount > 0 ? min(1, $nativeAmount / $allLineAmount) : 1.0;
            $orderGross = round((float)($order['order_money'] ?? 0), 2);
            $orderNet = round((float)($order['merchant_net_amount'] ?? $orderGross), 2);
            $gross = round($orderGross * $ratio, 2);
            $fee = $isCredit || $isOfflineCash ? 0.0 : round((float)($order['payment_fee_amount'] ?? 0) * $ratio, 2);
            $net = $isCredit || $isOfflineCash
                ? $gross
                : round($orderNet * $ratio, 2);
            $orderNo = trim((string)($order['order_no'] ?? '')) ?: ('PHONE-' . $orderId);
            // 线上付款沿用历史 event_id，避免升级后重放旧事件时重复入账。
            $eventSuffix = $isCredit
                ? ':credit_confirmed:v1'
                : ($isOfflineCash ? ':offline_paid:v1' : ':paid:v1');
            $event = [
                'event_name' => 'erp.external_sale.recorded_requested.v1',
                'event_version' => 1,
                'event_id' => 'phone_shop:native_order:' . $orderId . $eventSuffix,
                'site_id' => $siteId,
                'source_plugin' => 'phone_shop',
                'source_plugin_name' => '手机商城',
                'source_type' => 'phone_shop.native_goods_sale',
                'source_name' => '商城自有商品线上销售',
                'source_id' => (string)$orderId,
                'source_order_no' => $orderNo,
                'party_name' => trim((string)($order['taker_name'] ?? '')) ?: ('商城会员#' . (int)($order['member_id'] ?? 0)),
                'channel_code' => 'phone_shop_mini_program',
                'channel_name' => '小程序商城',
                'occurred_at' => ErpDeviceSnapshot::saleTime($order->getData(), $rows),
                'items' => $items,
                'payment' => [
                    'status' => $isCredit ? 'unpaid' : 'paid',
                    'mode' => $paymentMode === 'online' ? 'wechat_online' : $paymentMode,
                    'pricing_identity' => (string)($order['pricing_identity'] ?? 'retail'),
                    'gross_amount' => number_format($gross, 2, '.', ''),
                    'fee_rate' => number_format($isCredit || $isOfflineCash ? 0 : (float)($order['payment_fee_rate'] ?? 0), 6, '.', ''),
                    'fee_amount' => number_format($fee, 2, '.', ''),
                    'fee_bearer' => (string)($order['payment_fee_bearer'] ?? 'merchant'),
                    'merchant_net_amount' => number_format($net, 2, '.', ''),
                    'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
                    'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
                    'voucher_urls' => array_values((array)($data['voucher_urls'] ?? [])),
                ],
                'operator_id' => (int)($order['staff_id'] ?? $data['operator_id'] ?? 0),
                'operator_name' => trim((string)($data['operator_name'] ?? '')) ?: ($isCredit || $isOfflineCash ? '商城业务员' : '商城自动入账'),
                'remark' => $isCredit
                    ? '商城线下挂账；按订单串号关联ERP库存，缺失或冲突设备请到商城库存对账处理，不重复生成采购应付。'
                    : ($isOfflineCash
                        ? '商城线下现结；按订单串号关联ERP库存，缺失或冲突设备待库存对账，不重复收款。'
                        : '商城已线上支付；按订单串号关联ERP库存，缺失或冲突设备待库存对账，不重复收款。'),
            ];
            return $this->erpResult((array)event('ErpExternalSaleRecordedRequested', $event));
        } catch (\Throwable $e) {
            Log::error('[phone_shop] 商城自有商品成交同步ERP失败: ' . $e->getMessage());
            return ['consumer' => 'phone_shop', 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    private function skip(string $reason): array
    {
        return ['consumer' => 'phone_shop', 'status' => 'skipped', 'reason' => $reason];
    }

    private function erpResult(array $results): array
    {
        foreach ($results as $result) {
            if (is_array($result) && (string)($result['consumer'] ?? '') === 'hsx_erp') {
                return ['consumer' => 'phone_shop', 'status' => (string)($result['status'] ?? 'processed'), 'erp' => $result];
            }
        }
        return $this->skip('erp_not_installed');
    }
}
