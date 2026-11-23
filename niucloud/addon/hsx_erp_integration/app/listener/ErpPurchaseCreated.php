<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\listener;

use addon\hsx_erp\app\model\ErpPurchaseOrder;

/**
 * ERP采购单创建监听器
 *
 * 监听采购单创建事件，自动触发外部ERP推送
 */
class ErpPurchaseCreated
{
    /**
     * 事件监听处理
     *
     * @param array $data 事件数据 ['purchase_id' => 采购单ID]
     */
    public function handle($data)
    {
        if (empty($data['purchase_id'])) {
            return;
        }

        $purchaseId = (int)$data['purchase_id'];

        try {
            // 获取采购单信息
            $purchase = ErpPurchaseOrder::with(['items.asset'])
                ->where('id', $purchaseId)
                ->findOrEmpty();

            if ($purchase->isEmpty()) {
                return;
            }

            // 构建推送数据
            $pushData = $this->buildPurchaseData($purchase);

            // 触发集成推送
            if (function_exists('trigger_erp_integration')) {
                trigger_erp_integration('purchase', 'erp_purchase', $purchaseId, $pushData);
            }

        } catch (\Throwable $e) {
            \think\facade\Log::error("ERP采购推送监听器异常: " . $e->getMessage());
        }
    }

    /**
     * 构建采购推送数据
     */
    private function buildPurchaseData($purchase): array
    {
        $lines = [];
        foreach ($purchase->items as $item) {
            $asset = $item->asset;
            $lines[] = [
                'line_id' => $item->id,
                'device_id' => $asset->id ?? 0,
                'product_id' => $item->product_id ?? 0,
                'product_name' => $item->item_name ?? '',
                'imei' => $asset->imei ?? '',
                'sn' => $asset->sn ?? '',
                'amount' => $item->purchase_cost ?? '0.00',
                'quantity' => $item->quantity ?? 1,
            ];
        }

        return [
            'site_id' => $purchase->site_id,
            'purchase_id' => $purchase->id,
            'purchase_no' => $purchase->purchase_no,
            'supplier_id' => $purchase->party_id,
            'supplier_name' => $purchase->party_name,
            'warehouse_id' => $purchase->warehouse_id ?? 0,
            'warehouse_name' => $purchase->warehouse_name ?? '',
            'total_amount' => $purchase->total_cost ?? '0.00',
            'confirmed_at' => $purchase->purchase_at ? date('Y-m-d H:i:s', $purchase->purchase_at) : date('Y-m-d H:i:s'),
            'status' => $purchase->status,
            'lines' => $lines,
        ];
    }
}
