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
$purchaseReturnStart = strpos($mirror, 'public function applyPurchaseReturn');
$purchaseReturnEnd = strpos($mirror, 'public function applyStage', $purchaseReturnStart ?: 0);
$purchaseReturnSource = $purchaseReturnStart === false
    ? ''
    : substr($mirror, $purchaseReturnStart, $purchaseReturnEnd === false ? null : $purchaseReturnEnd - $purchaseReturnStart);
$payment = (string)file_get_contents($root . '/app/service/admin/order/RecycleDevicePaymentService.php');
$orderPayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleOrderPaymentService.php');
$capability = (string)file_get_contents($root . '/app/service/core/recycle_order/RecycleErpCapabilityService.php');
$integration = (string)file_get_contents($root . '/app/service/core/recycle_order/RecycleErpIntegrationService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/order/RecycleOrder.php');
$repo = dirname($root, 3);
$actions = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/hooks/useRecycleOrderActions.ts');
$paymentScope = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/utils/payment-scope.ts');

foreach (['DEVICE_STATUS_RETURNED', 'DISPOSE_TYPE_RETURN', 'DISPOSE_STATUS_RETURNED', 'ORDER_STATUS_CLOSED', 'erp_purchase_return'] as $needle) {
    $assert(str_contains($mirror, $needle), 'ERP退货回写缺少业务状态：' . $needle);
}
$assert(!str_contains($purchaseReturnSource, "'pay_status' =>"), 'ERP采购退货不能抹掉已经发生的付款事实');
$assert(str_contains($integration, "in_array('hsx_erp', (new CoreSiteService())->getAddonKeysBySiteId(\$siteId), true)"), '配置服务必须按当前站点插件权限确认ERP安装能力');
$assert(str_contains($capability, "\$config['mode'] === 'self_erp' && \$config['installed']")
    && str_contains($capability, '->inspect($siteId, $orderId, $deviceIds)'), '新设备配置与旧设备付款归属必须分开判断，退货历史不能被安装开关重新归属');
$assert(str_contains($capability, "->claim(\$siteId, \$orderId, \$deviceIds, 'local')")
    && str_contains($capability, 'catch (\\Throwable $e)') && str_contains($capability, 'if ($e instanceof CommonException) throw $e;'), '本地付款归属无法确认必须上抛，不能以本地付款替代失败的ERP查询');
$assert(str_contains($payment, 'assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds)'), '回收打款服务必须向后端闸门传递真实站点、订单及设备ID');
$assert(str_contains($payment, "['site_id', '=', \$this->site_id]") && str_contains($payment, "['order_id', '=', \$orderId]")
    && str_contains($payment, "->whereIn('id', \$deviceIds)->order('id asc')->lock(true)"), '设备付款必须锁定本站订单内的真实选中设备，防止退货状态并发变化');
$assert(str_contains($orderPayment, 'assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds)'), '整单付款必须带真实设备归属，阻止绕过控制器重复付款');
$assert(str_contains($orderPayment, "['site_id', '=', \$this->site_id]") && str_contains($orderPayment, "['order_id', '=', \$order->id]")
    && str_contains($orderPayment, "->order('id asc')->lock(true)") && !str_contains($orderPayment, '$this->assertLocalPaymentAllowed();'), '整单付款必须锁定实际订单设备，不得使用无上下文站点拦截代替设备事实');
$assert(str_contains($controller, 'paymentCapability') && str_contains($controller, "array_merge(['accounts'"), '能力接口必须返回ERP财务接管状态');
$assert(str_contains($actions, 'getCapitalAccountOptions(row.id)') && str_contains($actions, "paymentOwner === 'self_erp'")
    && str_contains($actions, '前往 ERP 应付款'), 'PC必须按当前订单实际付款归属引导到ERP，不得按安装状态猜测');
$assert(str_contains($actions, 'if (!canOpenLocalPayment(capability.data || {}, orderPaymentMode))')
    && str_contains($actions, "row.flow_mode || row.payment_mode || paymentMode.value || 'order'")
    && str_contains($actions, "payment_owner || 'unknown'") && str_contains($actions, '付款归属查询失败，尚未执行付款'), 'PC付款必须按真实归属和订单原模式判断，未知或查询失败不得打开本地付款');
$assert(str_contains($paymentScope, "scope.payment_owner === 'local' && scope.local_allowed === true")
    && str_contains($paymentScope, "scope.payment_owner === 'mixed' && mode === 'device'")
    && str_contains($paymentScope, "?.owner || 'unknown'"), '混合订单只能沿用原按设备模式选择本地设备，整单混合与未知设备不能直接本地付款');

echo "[PASS] recycle ERP return and payment guard smoke test\n";
