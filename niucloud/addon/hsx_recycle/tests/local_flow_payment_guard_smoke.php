<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\dict\order\RecycleOrderDict as Dict;
use addon\hsx_recycle\app\service\core\recycle_order\handler\PaymentHandler;

final class PureFlowPaymentHandler extends PaymentHandler
{
    public function plan(array $order, array $devices, array $data = []): array
    {
        return $this->preparePayment($order, $devices, $data);
    }
}

$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) throw new RuntimeException($message);
};
$reject = static function (callable $call, string $message) use ($assert): void {
    try {
        $call();
    } catch (\core\exception\CommonException $e) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};
$handler = new PureFlowPaymentHandler();
$order = ['id' => 20, 'site_id' => 7, 'status' => Dict::ORDER_STATUS_PENDING_PAYMENT,
    'pay_status' => 0, 'pay_time' => 0];
$first = ['id' => 101, 'site_id' => 7, 'order_id' => 20, 'status' => Dict::DEVICE_STATUS_RECYCLED,
    'pay_status' => 0, 'pay_amount' => 0, 'pay_time' => 0, 'final_price' => '120.50', 'initial_price' => '99.00'];
$second = array_replace($first, ['id' => 102, 'final_price' => '79.50']);
$devices = [$second, $first];
$plan = $handler->plan($order, $devices);
$assert($plan['device_ids'] === [101, 102], '以真实设备ID稳定排序');
$assert($plan['total_amount'] === 200.0 && $plan['amounts'] === [101 => 120.5, 102 => 79.5], '整单金额仅来自实际设备最终价');
$assert($handler->plan($order, $devices, ['device_ids' => ['00102', 101, 101]]) === $plan, '严格数字字符串与去重后完整集合兼容');

foreach (['101x', '101.9', 101.9, true, false, null, [], -1, 0, '0', '-101', '1e2', ' 101', '99999999999999999999999999999'] as $bad) {
    $reject(static fn() => $handler->plan($order, $devices, ['device_ids' => [101, 102, $bad]]), '不能截断、忽略或过滤非法批量ID');
}
foreach ([[], [101], [101, 102, 999], '101,102'] as $selection) {
    $reject(static fn() => $handler->plan($order, $devices, ['device_ids' => $selection]), '显式集合必须恰为全部可付款设备');
}
foreach ([['pay_status' => 1], ['pay_status' => 2], ['pay_time' => 123], ['status' => Dict::ORDER_STATUS_COMPLETED]] as $change) {
    $reject(static fn() => $handler->plan(array_replace($order, $change), $devices), '旧订单付款事实和完成状态阻断再次确认');
}
foreach ([['pay_status' => 1], ['pay_status' => 2], ['pay_amount' => 1.0], ['pay_time' => 123]] as $change) {
    $reject(static fn() => $handler->plan($order, [$first, array_replace($second, $change)]), '任一设备付款事实阻断整单，不静默扣减后付款');
}
foreach ([['site_id' => 8], ['order_id' => 21], ['id' => 0], ['id' => 101]] as $change) {
    $reject(static fn() => $handler->plan($order, [$first, array_replace($second, $change)]), '跨站跨单和重复关联设备拒绝付款');
}
foreach ([['status' => Dict::DEVICE_STATUS_RETURNED], ['status' => Dict::DEVICE_STATUS_CONSIGNED],
    ['confirm_status' => Dict::CONFIRM_STATUS_REJECTED], ['dispose_type' => Dict::DISPOSE_TYPE_RETURN],
    ['dispose_type' => Dict::DISPOSE_TYPE_CONSIGN]] as $change) {
    $excluded = array_replace($second, $change);
    $actual = $handler->plan($order, [$first, $excluded], ['device_ids' => [101]]);
    $assert($actual['device_ids'] === [101] && $actual['total_amount'] === 120.5, '退回或代卖设备不进入付款和后续状态转换集合');
    $reject(static fn() => $handler->plan($order, [$first, array_replace($excluded, ['pay_amount' => 1])]), '退回设备的历史已付款事实也不能被过滤掩盖');
}
$withoutFinal = $first;
unset($withoutFinal['final_price']);
$assert($handler->plan($order, [$withoutFinal])['total_amount'] === 99.0, '缺失最终价仍保留原初始价回退');
foreach ([0, -1, INF, NAN] as $badPrice) {
    $reject(static fn() => $handler->plan($order, [array_replace($first, ['final_price' => $badPrice])]), '零最终价不回退且无效金额不能打款');
}
$reject(static fn() => $handler->plan($order, []), '空设备订单不能完成付款');

// 不调用模型/事务/Capability：仅检查纯计划及关键调用点的结构约束。
$base = dirname(__DIR__) . '/app/service/core/recycle_order/';
$source = file_get_contents($base . 'handler/PaymentHandler.php');
$record = substr($source, strpos($source, 'private function recordPayment'), strpos($source, 'protected function preparePayment') - strpos($source, 'private function recordPayment'));
$assert(str_contains($source, "->order('id asc')->lock(true)->select()"), '设备行采用稳定顺序锁');
$assert(str_contains($source, 'return Db::transaction('), '直接调用处理器也有事务');
$assert(strpos($record, 'assertLocalPaymentAllowed($siteId, $orderId, $plan[\'device_ids\'])') < strpos($record, 'RecycleDevice::where('), '归属最终闸门必须早于设备付款事实写入');
$assert(str_contains($record, "'pay_amount' => \$amount") && str_contains($record, "'pay_status' => RecycleOrderDict::PAY_STATUS_PAID"), '订单和设备付款事实同时固化');
$flow = file_get_contents($base . 'CoreRecycleOrderFlowService.php');
$assert(!str_contains($flow, 'assertLocalPaymentAllowed('), '外层不在处理器已写付款后重复认领本地归属');
$assert(str_contains($flow, "\$data['devices'] = array_map(static fn(int \$id): array => ['id' => \$id], \$paidDeviceIds)"), '后续状态转换只使用已核实的设备集合');
$assert(str_contains($flow, "\$context['devices'] = \$data['devices']"), '上下文不能覆盖已核实的状态转换设备集合');
echo "[PASS] local flow payment guard: {$count} assertions; no database\n";
