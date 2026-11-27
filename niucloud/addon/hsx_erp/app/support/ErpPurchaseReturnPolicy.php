<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use addon\hsx_erp\app\dict\ErpDict;

/**
 * 采购退货商业规则：供应商结算成本与内部整备成本严格分离。
 */
final class ErpPurchaseReturnPolicy
{
    public static function assess(array $asset, float $supplierAmount, float $paidAmount, ?float $returnAmount = null): array
    {
        $supplierAmount = max(0, round($supplierAmount, 2));
        $paidAmount = max(0, round($paidAmount, 2));
        $purchaseCost = max(0, round((float)($asset['purchase_cost'] ?? 0), 2));
        $refurbishCost = max(0, round((float)($asset['refurbish_cost'] ?? 0), 2));
        $totalCost = max(0, round((float)($asset['total_cost'] ?? $purchaseCost), 2));
        $refurbishStatus = (string)($asset['refurbish_status'] ?? 'none');
        $unclassifiedCost = round($totalCost - $supplierAmount - $refurbishCost, 2);
        $flow = ErpDict::purchaseReturnFlow($supplierAmount, $paidAmount, $returnAmount);

        $blockReason = '';
        if ($refurbishCost > 0.0001 || in_array($refurbishStatus, ['processing', 'done'], true)) {
            $blockReason = sprintf(
                '该设备已发生整备（整备成本 ¥%.2f），不能走标准采购退货；请销售出库并按总成本计算毛利。',
                $refurbishCost
            );
        } elseif (abs($unclassifiedCost) > 0.0001) {
            $blockReason = sprintf(
                '该设备存在未分类成本差额 ¥%.2f，请先归类为供应商调价、整备费用或内部成本修正。',
                $unclassifiedCost
            );
        }

        return array_merge($flow, [
            'returnable' => $blockReason === '',
            'block_reason' => $blockReason,
            'supplier_amount' => $supplierAmount,
            'purchase_cost' => $purchaseCost,
            'refurbish_cost' => $refurbishCost,
            'refurbish_status' => $refurbishStatus,
            'unclassified_cost' => $unclassifiedCost,
            'total_cost' => $totalCost,
            'default_return_amount' => $supplierAmount,
            'action_label' => $blockReason === '' ? $flow['action_label'] : '不可采购退货',
            'description' => $blockReason === '' ? $flow['description'] : $blockReason,
        ]);
    }
}
