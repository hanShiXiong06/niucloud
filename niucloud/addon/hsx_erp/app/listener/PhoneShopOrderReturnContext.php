<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleReturnOrder;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;

/** 只读能力：商城显示原业务员/退货入口，以及消费退回事件前校验设备最新状态。 */
class PhoneShopOrderReturnContext
{
    public function handle(array $data): array
    {
        $siteId = (int)($data['site_id'] ?? 0);
        if ($siteId <= 0) return [];
        if (($data['action'] ?? '') === 'asset_state') {
            $asset = ErpAsset::where('site_id', $siteId)->where('id', (int)($data['asset_id'] ?? 0))->lock(!empty($data['lock']))->findOrEmpty();
            return ['provider' => 'hsx_erp', 'asset' => $asset->isEmpty() ? null : [
                'status' => (string)$asset->status, 'sale_order_id' => (int)$asset->sale_order_id,
                'update_at' => (int)$asset->update_at,
            ]];
        }
        $orders = array_slice((array)($data['orders'] ?? []), 0, 100);
        $ids = array_column($orders, 'order_id');
        $numbers = array_values(array_filter(array_column($orders, 'relate_source')));
        $query = ErpSaleOrder::where('site_id', $siteId)->where(function ($q) use ($ids, $numbers) {
            $q->where(function ($sub) use ($ids) { $sub->where('origin_plugin', 'phone_shop')->whereIn('origin_id', $ids); });
            if ($numbers) $q->whereOr('sale_no', 'in', $numbers);
        });
        $sales = $query->select()->toArray();
        $items = $sales ? ErpSaleItem::where('site_id', $siteId)->whereIn('sale_order_id', array_column($sales, 'id'))->select()->toArray() : [];
        $result = [];
        foreach ($orders as $order) {
            $matches = array_values(array_filter($sales, static fn($s) =>
                ($s['origin_plugin'] === 'phone_shop' && (int)$s['origin_id'] === (int)$order['order_id'])
                || (!empty($order['relate_source']) && $order['relate_source'] === $s['sale_no'])
            ));
            $active = array_values(array_filter($matches, static fn($s) => !in_array($s['status'], ['void', 'returned'], true)));
            $sale = count($active) === 1 ? $active[0] : (count($matches) === 1 ? $matches[0] : null);
            if (!$sale) continue; // 多笔冲突不能替用户随意选一笔。
            $returnIds = ErpSaleReturnOrder::where('site_id', $siteId)->where('sale_order_id', (int)$sale['id'])->where('status', 'confirmed')->column('id');
            $refunds = $returnIds ? ErpPayable::where('site_id', $siteId)->where('source_type', 'sale_return')->whereIn('source_id', $returnIds)->where('status', '<>', 'void')->select()->toArray() : [];
            $refundPending = 0.0;
            foreach ($refunds as $refund) $refundPending += max(0, (float)$refund['amount'] - (float)$refund['settled_amount']);
            $receivables = ErpReceivable::where('site_id', $siteId)->where('source_id', (int)$sale['id'])->where('status', '<>', 'void')
                ->where(function ($q) { $q->where('source_type', 'sale')->whereOr('source_type', 'like', 'phone_shop.%'); })->select()->toArray();
            $remaining = 0.0;
            foreach ($receivables as $receivable) $remaining += max(0, (float)$receivable['amount'] - (float)$receivable['settled_amount']);
            $result[(int)$order['order_id']] = [
                'sale_order_id' => (int)$sale['id'], 'sale_no' => $sale['sale_no'],
                'staff_name' => $sale['salesman_name'] ?: $sale['operator_name'],
                'receivable_remaining' => round($remaining, 2), 'refund_pending' => round($refundPending, 2),
                'items' => array_values(array_map(static fn($i) => [
                    'asset_id' => (int)$i['asset_id'], 'source_line_id' => (int)$i['external_line_id'],
                    'imei' => (string)$i['imei'], 'status' => (string)$i['status'],
                ], array_filter($items, static fn($i) => (int)$i['sale_order_id'] === (int)$sale['id']))),
            ];
        }
        return ['provider' => 'hsx_erp', 'orders' => $result];
    }
}
