<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/** 库存列表与档案使用同一成本口径；采购应付不是含整备的设备总成本。 */
final class ErpStockCostPresentation
{
    public static function summarize(array $asset, float $supplierAmount): array
    {
        $purchase = round((float)($asset['purchase_cost'] ?? 0), 2);
        $refurbish = round((float)($asset['refurbish_cost'] ?? 0), 2);
        $total = round((float)($asset['total_cost'] ?? 0), 2);
        $supplierAmount = round($supplierAmount, 2);
        return [
            'purchase_cost' => $purchase,
            'supplier_adjust_cost' => round($supplierAmount - $purchase, 2),
            'supplier_amount' => $supplierAmount,
            'refurbish_cost' => $refurbish,
            'internal_adjust_cost' => round($total - $supplierAmount - $refurbish, 2),
            'total_cost' => $total,
        ];
    }
}
