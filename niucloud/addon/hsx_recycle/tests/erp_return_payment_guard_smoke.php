<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\listener\downstream\ErpAssetDownstreamListener;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

class FakeErpPurchaseReturnListener extends ErpAssetDownstreamListener
{
    public array $calls = [];

    protected function mirrorPurchaseReturn(int $deviceId, array $extra, string $eventId): array
    {
        $this->calls[] = compact('deviceId', 'extra', 'eventId');
        return ['updated' => true, 'device_id' => $deviceId];
    }
}

$listener = new FakeErpPurchaseReturnListener();
$result = $listener->handle([
    'event_name' => 'erp.purchase_return.completed.v1',
    'event_id' => 'EV-RETURN-1',
    'site_id' => 100005,
    'payload' => [
        'return_no' => 'PR202607120001',
        'assets' => [
            ['asset_id' => 81, 'source_device_id' => 101],
            ['asset_id' => 82, 'source_device_id' => 102],
        ],
    ],
]);

$assert(($result['status'] ?? '') === 'processed', 'ERP采购退货事件必须被回收插件消费');
$assert(array_column($listener->calls, 'deviceId') === [101, 102], '采购退货必须按来源设备逐台回写');
$assert(($listener->calls[0]['extra']['site_id'] ?? 0) === 100005, '回写必须携带站点隔离条件');
$assert(($listener->calls[0]['extra']['return_no'] ?? '') === 'PR202607120001', '回写日志必须保留ERP退货单号');
$assert(($listener->calls[0]['eventId'] ?? '') === 'EV-RETURN-1:101', '多设备事件必须生成设备级幂等键');

$root = dirname(__DIR__);
$mirror = (string)file_get_contents($root . '/app/service/core/recycle_device/CoreRecycleDownstreamMirrorService.php');
$payment = (string)file_get_contents($root . '/app/service/admin/order/RecycleDevicePaymentService.php');
$orderPayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleOrderPaymentService.php');
$capability = (string)file_get_contents($root . '/app/service/core/recycle_order/RecycleErpCapabilityService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/order/RecycleOrder.php');
$repo = dirname($root, 3);
$actions = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/hooks/useRecycleOrderActions.ts');

foreach (['DEVICE_STATUS_RETURNED', 'DISPOSE_TYPE_RETURN', 'DISPOSE_STATUS_RETURNED', 'ORDER_STATUS_CLOSED', 'erp_purchase_return'] as $needle) {
    $assert(str_contains($mirror, $needle), 'ERP退货回写缺少业务状态：' . $needle);
}
$assert(!str_contains($mirror, "'pay_status' =>"), 'ERP退货不能抹掉已经发生的付款事实');
$assert(str_contains($capability, 'getAddonKeysBySiteId') && str_contains($capability, "'hsx_erp'"), 'ERP接管必须按当前站点插件权限判断');
$assert(str_contains($payment, 'assertLocalPaymentAllowed') && str_contains($payment, '财务已由 ERP 接管'), '回收打款服务必须提供后端硬拦截');
$assert(str_contains($orderPayment, 'assertLocalPaymentAllowed') && str_contains($orderPayment, '财务已由 ERP 接管'), '整单打款流程服务必须阻止绕过控制器付款');
$assert(str_contains($controller, 'paymentCapability') && str_contains($controller, "array_merge(['accounts'"), '能力接口必须返回ERP财务接管状态');
$assert(str_contains($actions, 'payment_managed_by_erp') && str_contains($actions, '前往 ERP 应付款'), 'PC打款入口必须提示并引导到ERP');

echo "[PASS] recycle ERP return and payment guard smoke test\n";
