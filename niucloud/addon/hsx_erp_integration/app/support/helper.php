<?php
/**
 * ERP集成辅助函数
 *
 * 用于在ERP业务确认后触发外部ERP集成推送
 */

if (!function_exists('trigger_erp_integration')) {
    /**
     * 触发ERP集成推送
     *
     * @param string $businessType 业务类型：purchase/sale/payment/receipt
     * @param string $sourceType 来源类型：erp_purchase/erp_sale等
     * @param int $sourceId 业务单ID
     * @param array $data 业务数据
     */
    function trigger_erp_integration(string $businessType, string $sourceType, int $sourceId, array $data): void
    {
        // 检查集成框架是否已安装
        if (!class_exists(\addon\hsx_erp_integration\app\service\IntegrationBusService::class)) {
            return;
        }

        try {
            $siteId = $data['site_id'] ?? 0;
            if ($siteId <= 0) {
                return;
            }

            // 调用集成总线
            app(\addon\hsx_erp_integration\app\service\IntegrationBusService::class)
                ->{'dispatch' . ucfirst($businessType)}($siteId, $sourceType, $sourceId, $data);

            \think\facade\Log::info("ERP集成触发成功 [type={$businessType}] [source={$sourceType}] [id={$sourceId}]");

        } catch (\Throwable $e) {
            // 推送失败不影响主业务
            \think\facade\Log::error("ERP集成触发失败 [type={$businessType}] [error={$e->getMessage()}]");
        }
    }
}

if (!function_exists('build_purchase_integration_data')) {
    /**
     * 构建采购推送数据
     *
     * @param object $purchase 采购单对象
     * @return array
     */
    function build_purchase_integration_data($purchase): array
    {
        // 获取采购明细
        $lines = \addon\hsx_erp\app\model\ErpPurchaseItem::where('purchase_order_id', $purchase->id)
            ->with(['asset'])
            ->select()
            ->toArray();

        $lineData = [];
        foreach ($lines as $line) {
            $asset = $line['asset'] ?? [];
            $lineData[] = [
                'line_id' => $line['id'],
                'device_id' => $asset['id'] ?? 0,
                'product_id' => $line['product_id'] ?? 0,
                'product_name' => $line['item_name'] ?? '',
                'imei' => $asset['imei'] ?? '',
                'sn' => $asset['sn'] ?? '',
                'amount' => $line['purchase_cost'] ?? '0.00',
                'quantity' => $line['quantity'] ?? 1,
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
            'lines' => $lineData,
        ];
    }
}

if (!function_exists('build_sale_integration_data')) {
    /**
     * 构建销售推送数据
     *
     * @param object $sale 销售单对象
     * @return array
     */
    function build_sale_integration_data($sale): array
    {
        // TODO: 实现销售数据构建
        return [
            'site_id' => $sale->site_id,
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no ?? '',
            'customer_id' => $sale->party_id ?? 0,
            'customer_name' => $sale->party_name ?? '',
            'total_amount' => $sale->total_amount ?? '0.00',
            'confirmed_at' => date('Y-m-d H:i:s'),
            'lines' => [],
        ];
    }
}
