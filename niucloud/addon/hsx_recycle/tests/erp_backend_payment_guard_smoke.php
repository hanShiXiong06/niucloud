<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$capability = (string)file_get_contents($root . '/app/service/core/recycle_order/RecycleErpCapabilityService.php');
$flow = (string)file_get_contents($root . '/app/service/core/recycle_order/CoreRecycleOrderFlowService.php');
$handler = (string)file_get_contents($root . '/app/service/core/recycle_order/handler/PaymentHandler.php');
$orderPayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleOrderPaymentService.php');
$devicePayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleDevicePaymentService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/order/RecycleOrder.php');

$assert(str_contains($capability, 'public function assertLocalPaymentAllowed'), '必须提供统一的后端本地打款闸门');
$assert(str_contains($capability, '为避免重复打款暂停本次操作'), 'ERP接管状态查询异常时必须安全失败，不能放行本地打款');
$assert(str_contains($capability, '回收插件禁止本地打款'), 'ERP接管时必须返回明确的禁止本地打款提示');
$assert(str_contains($capability, '本地打款已被ERP财务接管闸门拦截'), '后端拦截必须留下可排查的审计日志');
$assert(str_contains($flow, "if (\$action === 'payment')") && str_contains($flow, 'assertLocalPaymentAllowed($siteId)'), '核心订单流程必须拦截旧接口的payment动作');
$assert(str_contains($handler, 'RecycleErpCapabilityService') && str_contains($handler, 'assertLocalPaymentAllowed($siteId)'), '最终付款处理器必须具备纵深拦截');
$assert(str_contains($orderPayment, 'assertLocalPaymentAllowed((int)$this->site_id)'), '整单本地打款服务必须使用统一闸门');
$assert(str_contains($devicePayment, 'assertLocalPaymentAllowed((int)$this->site_id)'), '设备级本地打款服务必须使用统一闸门');
$assert(substr_count($controller, 'settleByErp(') >= 3, 'ERP安装后整单、确认和设备打款入口必须继续路由到ERP结算');

echo "[PASS] recycle backend payment guard smoke test\n";
