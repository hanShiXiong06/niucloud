<?php
declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$erp = dirname(__DIR__);
$repo = dirname($erp, 3);
$recycle = dirname($erp) . '/hsx_recycle';

$erpEvents = (string)file_get_contents($erp . '/app/event.php');
$warehouseContract = (string)file_get_contents($erp . '/app/service/admin/ErpWarehouseOptionService.php');
$settlementContract = (string)file_get_contents($erp . '/app/service/admin/ErpSourcePayableSettlementService.php');
$sale = (string)file_get_contents($erp . '/app/service/admin/ErpSaleService.php');
$finance = (string)file_get_contents($erp . '/app/service/admin/ErpFinanceService.php');
$financeSource = (string)file_get_contents($erp . '/app/service/admin/ErpFinanceSourceService.php');
$schema = (string)file_get_contents($erp . '/sql/install.sql');
$bridge = (string)file_get_contents($recycle . '/app/service/core/recycle_order/RecycleErpFinanceBridgeService.php');
$consignment = (string)file_get_contents($recycle . '/app/service/admin/order/RecycleConsignmentOrderService.php');
$mirror = (string)file_get_contents($recycle . '/app/service/core/recycle_device/CoreRecycleDownstreamMirrorService.php');
$payment = (string)file_get_contents($recycle . '/app/service/admin/order/RecycleDevicePaymentService.php');
$controller = (string)file_get_contents($recycle . '/app/adminapi/controller/order/RecycleOrder.php');
$deviceController = (string)file_get_contents($recycle . '/app/adminapi/controller/order/RecycleDevice.php');
$pcConsignment = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/views/consignment_order/list.vue');
$pcSale = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/views/erp/sale/list.vue');
$mobileSale = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/sale/create.vue');

foreach (['ErpWarehouseOptionsRequested', 'ErpSourcePayableSettlementRequested'] as $eventName) {
    $assert(str_contains($erpEvents, $eventName), 'ERP未注册跨插件契约：' . $eventName);
}
$assert(str_contains($warehouseContract, "warehouse_type") && str_contains($warehouseContract, "ownership_type"), '仓库契约必须按仓库类型和物权过滤');
$assert(str_contains($bridge, "event('ErpSourcePayableSettlementRequested'"), '回收付款必须通过标准ERP付款契约');
$assert(!str_contains($controller, 'SettleErpPayableByDevice') && !str_contains($controller, 'RecordErpCapitalFlow'), '回收付款不能继续调用旧事件或重复记资金流水');
$assert(!str_contains($deviceController, 'GetErpWarehouseList') && !str_contains($deviceController, 'ErpWarehouseService'), '回收仓库查询不能保留旧事件或直接依赖ERP服务类');
$assert(str_contains($settlementContract, 'confirmPayableItemsPayment') && str_contains($settlementContract, 'capital_account_id'), 'ERP付款契约必须复用正式结算并强制资金账户');
$assert(str_contains($finance, "'hsx_recycle'") && str_contains($finance, '$requiredConsumers'), '回收结算回写必须作为ERP资金事件的必达消费者');

foreach (['ownership_type', 'owner_party_id', 'consignment_settlement_amount', 'consignment_payable_id'] as $field) {
    $assert(str_contains($schema, '`' . $field . '`'), '销售明细缺少代卖字段：' . $field);
    $assert(str_contains($sale, "'{$field}'"), '销售服务未固化代卖字段：' . $field);
}
$assert(str_contains($sale, "'source_type' => 'consignment_sale'"), '代卖成交必须按设备生成货主应付');
$assert(str_contains($sale, "'sale_cost_basis'") && str_contains($sale, 'resolveConsignmentSettlementSnapshot'), '待售库存必须向前端提供统一的代卖结算成本口径');
$assert(str_contains($sale, 'voidConsignmentPayable'), '销售撤销必须同步作废未付款的代卖应付');
$assert(str_contains($financeSource, "'consignment_sale' => 'consignment_sale'"), '代卖应付不能回退识别为普通采购');

$assert(str_contains($consignment, "defaultInboundPlacement((int)\$this->site_id, 'consignment')"), '转入代卖必须自动选择ERP代卖仓');
$assert(str_contains($consignment, 'RecycleDeviceErpSyncService())->dispatch'), '转入代卖提交后必须同步ERP入库');
$assert(str_contains($mirror, 'applyConsignmentSale') && str_contains($mirror, 'STATUS_PENDING_SETTLEMENT'), 'ERP售出后必须把回收代卖单推进待结算');
$assert(str_contains($mirror, 'applyConsignmentSaleCancellation') && str_contains($mirror, 'STATUS_SELLING'), 'ERP撤销销售后必须恢复代卖可售状态');
$assert(str_contains($payment, 'STATUS_SETTLED') && str_contains($payment, 'ERP财务结清代卖货款'), 'ERP付款完成后必须回写代卖已结算');
$assert(str_contains($payment, "\$info['site_id']") && str_contains($payment, 'getPaymentSummary($orderId, $siteId)'), '定时补偿回写必须显式携带站点，不能依赖后台请求上下文');
$assert(str_contains($pcConsignment, 'capital_account_id') && str_contains($pcConsignment, '付款凭证'), '回收代卖结算入口必须选择ERP账户并支持付款凭证');
$assert(str_contains($pcSale, '客户代卖') && str_contains($pcSale, 'saleCostBasis'), 'PC销售必须展示代卖归属并按货主结算款预估毛利');
$assert(str_contains($mobileSale, '客户代卖') && str_contains($mobileSale, 'saleCostBasis'), '移动销售必须展示代卖归属并按货主结算款预估毛利');

echo "[PASS] recycle consignment finance bridge smoke test\n";
