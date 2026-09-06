<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};
$methodSource = static function (string $source, string $name): string {
    if (!preg_match('/(?:public|protected|private)\s+(?:static\s+)?function\s+' . preg_quote($name, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE)) return '';
    $parts = preg_split('/\n\s+(?:public|protected|private)\s+(?:static\s+)?function\s+/', substr($source, $match[0][1]), 2);
    return $parts[0] ?? '';
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
$localGate = $methodSource($capability, 'assertLocalPaymentAllowed');
$assert(str_contains($localGate, "->claim(\$siteId, \$orderId, \$deviceIds, 'local')"), '最终闸门必须按真实站点、订单和设备集合确认并记录本地归属');
$assert(str_contains($localGate, 'catch (\\Throwable $e)') && str_contains($localGate, 'throw new CommonException(')
    && str_contains($localGate, 'if ($e instanceof CommonException) throw $e;'), '归属查询或认领失败必须上抛，不得放行本地付款');
$assert(str_contains($localGate, "Log::error('回收插件无法确认ERP财务接管状态，本地打款已安全拦截'"), '后端归属拦截必须留下可排查的审计日志');
$assert(str_contains($flow, "if (\$action === 'payment')") && str_contains($flow, '$this->getOrderInfo($orderId, $paymentSiteId, $action === \'payment\')'), '核心旧payment入口必须按付款站点读取并锁定真实订单');
$flowOrder = $methodSource($flow, 'getOrderInfo');
$assert(str_contains($flowOrder, "->where('site_id', \$siteId)") && str_contains($flowOrder, '->lock(true)'), '核心付款订单查询必须具有站点条件和行锁');
$assert(str_contains($flow, "\$handlerResult['data']['device_ids']") && str_contains($flow, "\$context['devices'] = \$data['devices']"), '状态转换必须沿用处理器核实的付款集合，不能由请求上下文替换');
$assert(!str_contains($flow, 'assertLocalPaymentAllowed('), '最终付款闸门由处理器在写入前执行，Core不能在写入后重复认领归属');
$handle = $methodSource($handler, 'handle');
$record = $methodSource($handler, 'recordPayment');
$assert(str_contains($handle, 'Db::transaction(') && substr_count($handle, '->lock(true)') >= 2
    && str_contains($handle, "['site_id', '=', \$siteId]") && str_contains($handle, "['order_id', '=', \$orderId]"), '直接调用付款处理器也必须在事务中锁定本站订单及设备');
$handlerGate = strpos($record, 'assertLocalPaymentAllowed($siteId, $orderId, $plan[\'device_ids\'])');
$handlerWrite = strpos($record, 'RecycleDevice::where(');
$assert($handlerGate !== false && $handlerWrite !== false && $handlerGate < $handlerWrite, '处理器必须在首次付款写入前用真实站点、订单和实际设备集合校验归属');
$assert(str_contains($orderPayment, 'assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds)'), '整单本地付款wrapper必须完整传递站点、订单和实际设备集合');
$assert(str_contains($devicePayment, 'assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds)'), '设备级付款wrapper必须完整传递站点、订单和实际设备集合');
$payDevices = $methodSource($devicePayment, 'payDevices');
$deviceGate = strpos($payDevices, '$this->assertLocalPaymentAllowed($orderId, $deviceIds)');
$deviceWrite = strpos($payDevices, '$device->save(');
$deviceTransaction = strpos($payDevices, 'Db::startTrans();');
$assert($deviceTransaction !== false && $deviceGate !== false && $deviceWrite !== false && $deviceTransaction < $deviceGate && $deviceGate < $deviceWrite,
    '设备付款必须在事务内全批验证后、首次付款写入前执行实际设备归属闸门');
$assert(substr_count($payDevices, '->lock(true)') >= 2 && str_contains($payDevices, "['site_id', '=', \$this->site_id]")
    && str_contains($payDevices, "->whereIn('id', \$deviceIds)->order('id asc')->lock(true)"), '设备付款必须按站点锁订单，再按真实选中ID稳定锁设备');
$orderPlan = $methodSource($orderPayment, 'lockPaymentPlan');
$assert(str_contains($orderPlan, "['site_id', '=', \$this->site_id]") && str_contains($orderPlan, "['order_id', '=', \$order->id]")
    && str_contains($orderPlan, "->order('id asc')->lock(true)"), '整单付款必须锁定本站真实订单的稳定排序设备集合');
foreach (['payment', 'confirmPayment'] as $method) {
    $body = $methodSource($orderPayment, $method);
    $steps = array_map(static fn(string $needle) => strpos($body, $needle), [
        'Db::startTrans();', '->lock(true)->findOrEmpty()', '$this->lockPaymentPlan($order, $data)',
        '$this->assertLocalPaymentAllowed($orderId, $plan[\'device_ids\'])', '$this->updateOrderPaymentInfo($order, $data)',
        '$this->updateDevicePaymentInfo($order, $plan)', 'Db::commit();',
    ]);
    $ordered = !in_array(false, $steps, true);
    for ($i = 1; $ordered && $i < count($steps); $i++) $ordered = $steps[$i - 1] < $steps[$i];
    $assert($ordered && str_contains($body, "['site_id', '=', \$this->site_id]"), $method . '必须在事务内锁定真实订单及完整设备计划，最后校验归属后才写订单/设备付款事实');
}
$assert(!str_contains($orderPayment, '$this->assertLocalPaymentAllowed();') && !str_contains($payDevices, '$this->assertLocalPaymentAllowed();'), '最终付款入口不得保留无订单/设备上下文的闸门调用');
$assert(substr_count($controller, 'settleByErp(') >= 3, 'ERP安装后整单、确认和设备打款入口必须继续路由到ERP结算');

echo "[PASS] recycle backend payment guard smoke test\n";
