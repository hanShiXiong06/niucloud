<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$dict = (string)file_get_contents($root . '/app/dict/ErpPrintDict.php');
$service = (string)file_get_contents($root . '/app/service/admin/ErpPrintService.php');
$provider = (string)file_get_contents($root . '/app/support/print/ErpPrintProviderManager.php');
$route = (string)file_get_contents($root . '/app/adminapi/route/route.php');
$sale = (string)file_get_contents($root . '/app/service/admin/ErpSaleService.php');
$purchase = (string)file_get_contents($root . '/app/service/admin/ErpPurchaseService.php');
$finance = (string)file_get_contents($root . '/app/service/admin/ErpFinanceService.php');
$pc = (string)file_get_contents(dirname($root, 3) . '/admin/src/addon/hsx_erp/views/erp/print/index.vue');
$mobile = (string)file_get_contents(dirname($root, 3) . '/site-uniapp/src/addon/hsx_erp/pages/print/index.vue');

foreach (['xpyun', 'feie', 'yilianyun', 'bluetooth_escpos', 'bluetooth_tspl'] as $driver) {
    $assert(str_contains($dict, "'{$driver}'"), '缺少打印驱动：' . $driver);
}
foreach (['sale_created', 'sale_cancelled', 'receipt_confirmed', 'payment_confirmed', 'offset_confirmed', 'asset_inbound', 'asset_label'] as $scene) {
    $assert(str_contains($dict, "'{$scene}'"), '缺少打印场景：' . $scene);
}
foreach (['print/meta', 'print/printers', 'print/templates', 'print/scenes', 'print/jobs', 'print/manual', 'client_complete'] as $uri) {
    $assert(str_contains($route, $uri), '缺少打印中心接口：' . $uri);
}
$assert(str_contains($service, 'triggerSafely') && str_contains($service, '自动打印不允许阻断'), '自动打印必须隔离业务主流程');
$assert(str_contains($service, 'saleDeviceContexts') && str_contains($service, 'withSettlementContext'), '打印必须支持设备级和真实结算级上下文');
$assert(str_contains($provider, "'waiting_client'"), '蓝牙任务必须由移动端接力，不得在服务端伪造成功');
$assert(str_contains($sale, "triggerSafely('sale_created'") && str_contains($sale, "triggerSafely('sale_cancelled'"), '销售开单和撤销必须接入场景打印');
$assert(str_contains($purchase, "triggerSafely('asset_inbound'"), '设备入库必须接入标签场景');
$assert(str_contains($finance, "triggerSafely('receipt_confirmed'") && str_contains($finance, "triggerSafely('payment_confirmed'"), '实际收付款必须接入凭证打印');
$assert(str_contains($finance, "triggerSafely('offset_confirmed'") && str_contains($service, "'offset'"), '折账确认必须接入独立折账凭证');
$assert(str_contains($dict, "'document_title'") && str_contains($service, 'financeItemLines'), '打印模板必须支持业务标题和设备明细');
$assert(str_contains($pc, '打印设备') && str_contains($pc, '触发场景') && str_contains($pc, '打印模板') && str_contains($pc, '任务日志'), 'PC 打印中心必须覆盖配置和审计闭环');
$assert(str_contains($mobile, '移动打印台') && str_contains($mobile, '选择蓝牙设备打印') && str_contains($mobile, 'completeMobileErpPrintJob'), '移动端必须完成蓝牙发现、发送和状态回写');

echo "[PASS] ERP print center smoke test\n";
