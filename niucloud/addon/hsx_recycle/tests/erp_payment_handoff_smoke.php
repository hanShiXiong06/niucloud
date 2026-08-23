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
$orderEvent = (string)file_get_contents($root . '/app/service/core/recycle_order/CoreRecycleOrderEventService.php');
$orderPayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleOrderPaymentService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/order/RecycleOrder.php');
$runtimeActions = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/hooks/useRecycleOrderActions.ts');
$packageActions = (string)file_get_contents($root . '/admin/hooks/useRecycleOrderActions.ts');

$assert(str_contains($capability, 'getAddonKeysBySiteId') && str_contains($capability, "'hsx_erp'"), 'ERP接管必须按当前站点插件权限判断');
$assert(str_contains($capability, 'ErpWarehouseOptionsRequested'), 'ERP接管必须通过跨插件事件契约确认仓库能力');
$assert(!str_contains($capability, "class_exists('\\\\addon\\\\hsx_erp"), '回收插件不能通过ERP具体服务类形成硬依赖');
$assert(str_contains($devicePayment, 'assertLocalPaymentAllowed((int)$this->site_id)'), '设备级打款必须调用统一后端硬拦截');
$assert(str_contains($devicePayment, '$completedOrderIds') && str_contains($devicePayment, 'marketingDeliveryFactAfter'), 'ERP结清回写必须补发营销事实');
$assert(str_contains($devicePayment, "'source_plugin' => 'hsx_erp'"), 'ERP结清事实必须标识 ERP 为事实来源');
$assert(strpos($devicePayment, 'Db::commit();') < strpos($devicePayment, 'CoreRecycleOrderEventService::marketingDeliveryFactAfter'), '营销事实必须在财务事务提交后发布');
$assert(str_contains($orderEvent, 'public static function marketingDeliveryFactAfter') && str_contains($orderEvent, 'self::emitMarketingFact($data, false)'), '营销事实补发必须使用独立入口，避免重复发送打款通知和旧积分');
$assert(str_contains($orderEvent, 'isPaymentManaged($siteId)') && str_contains($orderEvent, '营销事实等待 ERP 结清事件'), '安装 ERP 时回收完成事件必须等待 ERP 结清，不能提前累计');
$assert(str_contains($orderPayment, 'assertLocalPaymentAllowed((int)$this->site_id)'), '整单打款必须调用统一后端硬拦截');
$assert(str_contains($controller, 'paymentCapability') && str_contains($controller, "array_merge(['accounts'"), '能力接口必须返回ERP财务接管状态');
$assert(str_contains($runtimeActions, 'payment_managed_by_erp') && str_contains($runtimeActions, '前往 ERP 应付款'), 'PC打款入口必须提示并引导到ERP');
$assert($runtimeActions === $packageActions, '运行目录和插件包的打款交接逻辑必须一致');

echo "[PASS] recycle ERP payment handoff smoke test\n";
