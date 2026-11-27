<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\support;

use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleItem;

/** 对外传递原交易标识，不读取商城的表，也不依赖当前 SKU 的绑定关系。 */
final class ErpMallOrderReference
{
    public static function fromSale(int $siteId, int $saleId, int $itemId): array
    {
        $sale = ErpSaleOrder::where('site_id', $siteId)->where('id', $saleId)->findOrEmpty();
        $item = ErpSaleItem::where('site_id', $siteId)->where('sale_order_id', $saleId)->where('id', $itemId)->findOrEmpty();
        if ($sale->isEmpty() || $item->isEmpty()) return [];
        return [
            'sale_order_id' => $saleId, 'sale_item_id' => $itemId,
            'origin_plugin' => (string)$sale->origin_plugin,
            'source_order_id' => (string)$sale->origin_plugin === 'phone_shop' ? (int)$sale->origin_id : 0,
            'source_line_id' => (string)$sale->origin_plugin === 'phone_shop' ? (int)$item->external_line_id : 0,
            'staff_id' => (int)$sale->salesman_uid,
            'staff_name' => (string)($sale->salesman_name ?: $sale->operator_name),
        ];
    }
}
