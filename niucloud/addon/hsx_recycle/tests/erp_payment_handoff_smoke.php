<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$capability = (string)file_get_contents($root . '/app/service/core/recycle_order/RecycleErpCapabilityService.php');
$devicePayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleDevicePaymentService.php');
$orderPayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleOrderPaymentService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/order/RecycleOrder.php');
$runtimeActions = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/hooks/useRecycleOrderActions.ts');
$packageActions = (string)file_get_contents($root . '/admin/hooks/useRecycleOrderActions.ts');

$assert(str_contains($capability, 'getAddonKeysBySiteId') && str_contains($capability, "'hsx_erp'"), 'ERP接管必须按当前站点插件权限判断');
$assert(str_contains($capability, 'ErpWarehouseOptionsRequested'), 'ERP接管必须通过跨插件事件契约确认仓库能力');
$assert(!str_contains($capability, "class_exists('\\\\addon\\\\hsx_erp"), '回收插件不能通过ERP具体服务类形成硬依赖');
$assert(str_contains($devicePayment, 'assertLocalPaymentAllowed') && str_contains($devicePayment, '财务已由 ERP 接管'), '设备级打款必须提供后端硬拦截');
$assert(str_contains($orderPayment, 'assertLocalPaymentAllowed') && str_contains($orderPayment, '财务已由 ERP 接管'), '整单打款必须提供后端硬拦截');
$assert(str_contains($controller, 'paymentCapability') && str_contains($controller, "array_merge(['accounts'"), '能力接口必须返回ERP财务接管状态');
$assert(str_contains($runtimeActions, 'payment_managed_by_erp') && str_contains($runtimeActions, '前往 ERP 应付款'), 'PC打款入口必须提示并引导到ERP');
$assert($runtimeActions === $packageActions, '运行目录和插件包的打款交接逻辑必须一致');

echo "[PASS] recycle ERP payment handoff smoke test\n";
