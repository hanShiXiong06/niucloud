<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\dict\order\RecycleOrderDict as Dict;
use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use addon\hsx_recycle\app\service\admin\order\RecycleOrderPaymentService;

final class PureAdminOrderPayment extends RecycleOrderPaymentService
{
    public function __construct() {}

    public function plan(array $order, array $devices, array $data = []): array
    {
        return $this->preparePayment($order, $devices, $data);
    }
}

final class PureAdminDevicePayment extends RecycleDevicePaymentService
{
    public function __construct() {}

    public function checkOrder(array $order): void
    {
        $this->assertDevicePaymentOrderState($order);
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
$normalize = static fn($ids): array => RecycleDevicePaymentService::normalizePaymentDeviceIds($ids);
$assert($normalize([103, '00101', 102, 101]) === [101, 102, 103], '正整型与数字字符串去重并稳定排序');
$assert($normalize([(string)PHP_INT_MAX]) === [PHP_INT_MAX], '最大合法整数不丢精度');
foreach (['101x', '101.1', 101.0, 101.1, true, false, null, [], -1, 0, '0', '-101', '1e2', ' 101', '101 ', '99999999999999999999999999999'] as $bad) {
    $reject(static fn() => $normalize([101, $bad]), '任一非法ID拒绝整个付款批次，不转换不静默过滤');
}
foreach (['101,102', null, 101, new stdClass()] as $badList) {
    $reject(static fn() => $normalize($badList), '付款设备列表必须是数组');
}

$service = new PureAdminOrderPayment();
$order = ['id' => 20, 'site_id' => 7, 'status' => Dict::ORDER_STATUS_PENDING_PAYMENT,
    'pay_status' => 0, 'pay_time' => 0];
$deviceService = new PureAdminDevicePayment();
$deviceService->checkOrder($order);
$deviceService->checkOrder(array_replace($order, ['pay_status' => Dict::PAY_STATUS_PARTIAL, 'pay_time' => 123]));
$assert(true, '设备模式允许未付订单及合法部分付款订单继续选择未付设备');
foreach ([['pay_status' => 1], ['pay_status' => 99], ['pay_time' => 123], ['status' => Dict::ORDER_STATUS_COMPLETED],
    ['status' => Dict::ORDER_STATUS_CANCELLED], ['status' => Dict::ORDER_STATUS_CLOSED]] as $change) {
    $reject(static fn() => $deviceService->checkOrder(array_replace($order, $change)), '设备模式也阻断已付或记录不一致的订单，不能用切换模式重付');
}
$first = ['id' => 101, 'site_id' => 7, 'order_id' => 20, 'status' => Dict::DEVICE_STATUS_RECYCLED,
    'pay_status' => 0, 'pay_amount' => 0, 'pay_time' => 0, 'final_price' => '120.50', 'initial_price' => '99.00'];
$second = array_replace($first, ['id' => 102, 'final_price' => '79.50']);
$devices = [$second, $first];
$plan = $service->plan($order, $devices);
$assert($plan['device_ids'] === [101, 102] && $plan['total_amount'] === 200.0, '整单计划使用实际设备ID和最终价');
$assert($plan['amounts'] === [101 => 120.5, 102 => 79.5], '后续设备付款事实与实际转账金额一致');
$assert($service->plan($order, $devices, ['device_ids' => ['00102', 101, 101]]) === $plan, '允许去重后完整的实际付款集合');
foreach ([[], [101], [101, 102, 999], [101, 102, '3x'], '101,102'] as $selection) {
    $reject(static fn() => $service->plan($order, $devices, ['device_ids' => $selection]), '全单接口不能传子集或非法ID后完成全单');
}
foreach ([['pay_status' => 1], ['pay_status' => 2], ['pay_time' => 123], ['status' => Dict::ORDER_STATUS_COMPLETED],
    ['status' => Dict::ORDER_STATUS_CANCELLED], ['status' => Dict::ORDER_STATUS_CLOSED]] as $change) {
    $reject(static fn() => $service->plan(array_replace($order, $change), $devices), '历史订单付款事实或完成状态阻断再次付款');
}
foreach ([['pay_status' => 1], ['pay_status' => 2], ['pay_amount' => 1.0], ['pay_time' => 123]] as $change) {
    $reject(static fn() => $service->plan($order, [$first, array_replace($second, $change)]), '任一设备付款事实阻断全单，不默默扣除后重付');
}
foreach ([['site_id' => 8], ['order_id' => 21], ['id' => 0], ['id' => 101]] as $change) {
    $reject(static fn() => $service->plan($order, [$first, array_replace($second, $change)]), '跨站跨单与重复设备关联均拒绝');
}
foreach ([['status' => Dict::DEVICE_STATUS_RETURNED], ['status' => Dict::DEVICE_STATUS_CONSIGNED],
    ['confirm_status' => Dict::CONFIRM_STATUS_REJECTED], ['dispose_type' => Dict::DISPOSE_TYPE_RETURN],
    ['dispose_type' => Dict::DISPOSE_TYPE_CONSIGN]] as $change) {
    $excluded = array_replace($second, $change);
    $actual = $service->plan($order, [$first, $excluded], ['device_ids' => [101]]);
    $assert($actual['device_ids'] === [101] && $actual['total_amount'] === 120.5 && count($actual['devices']) === 1, '退回和代卖不进入实际转账或付款状态集合');
    $reject(static fn() => $service->plan($order, [$first, array_replace($excluded, ['pay_amount' => 1])]), '不能过滤退回设备掩盖历史已付事实');
}
foreach ([0, -1, null, INF, NAN] as $badPrice) {
    $reject(static fn() => $service->plan($order, [array_replace($first, ['final_price' => $badPrice])]), '整单真实转账保留最终价口径，缺失/无效不能猜测初始价');
}
$reject(static fn() => $service->plan($order, []), '没有可付设备不能完成全单');

// 结构检查不初始化应用、模型或数据库，仅约束所有执行入口的最后闸门。
$base = dirname(__DIR__) . '/app/service/admin/order/';
$source = file_get_contents($base . 'RecycleOrderPaymentService.php');
foreach (['payment', 'confirmPayment'] as $method) {
    $start = strpos($source, 'public function ' . $method . '(');
    $end = strpos($source, "\n    private function ", $start);
    if ($method === 'payment') $end = strpos($source, 'public function confirmPayment(', $start);
    $body = substr($source, $start, $end === false ? null : $end - $start);
    $gate = strpos($body, "\$this->assertLocalPaymentAllowed(\$orderId, \$plan['device_ids'])");
    $assert($gate !== false && strpos($body, 'Db::startTrans()') < $gate && strpos($body, '->lock(true)->findOrEmpty()') < $gate, $method . '归属闸门在事务和站点订单锁内');
    $assert($gate < strpos($body, '$this->updateOrderPaymentInfo(') && $gate < strpos($body, '$this->updateDevicePaymentInfo('), $method . '最后闸门早于任何付款事实写入');
    $assert(str_contains($body, "'devices' => array_map(static fn(int \$id): array => ['id' => \$id], \$plan['device_ids'])"), $method . '状态转换仅使用真实付款设备集合');
}
$assert(str_contains($source, 'assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds)'), '真实站点订单设备传至统一归属闸门');
$assert(str_contains($source, "->order('id asc')->lock(true)->select()"), '同订单下设备固定升序锁避免交叉批次竞争');
$assert(str_contains($source, '$transferService->create(') && str_contains($source, '$siteAccountService->addTransferLog('), '既有真实转账和账户记录调用保留');
$assert(str_contains($source, "\$totalAmount = \$plan['total_amount']") && !str_contains($source, "\$order->devices()->sum('final_price')"), '转账不重新读取未锁定全单并混入退回或代卖');
$deviceSource = file_get_contents($base . 'RecycleDevicePaymentService.php');
$deviceStart = strpos($deviceSource, 'public function payDevices(');
$deviceEnd = strpos($deviceSource, 'public function assertLocalPaymentAllowed(', $deviceStart);
$deviceBody = substr($deviceSource, $deviceStart, $deviceEnd - $deviceStart);
$deviceGate = strpos($deviceBody, '$this->assertLocalPaymentAllowed($orderId, $deviceIds)');
$assert($deviceGate !== false && strpos($deviceBody, 'Db::startTrans()') < $deviceGate
    && strpos($deviceBody, '->lock(true)->findOrEmpty()') < $deviceGate
    && strpos($deviceBody, "->order('id asc')->lock(true)->select()") < $deviceGate, '按设备打款也持有站点订单和设备锁后最终确认归属');
$assert($deviceGate < strpos($deviceBody, '$device->save(') && $deviceGate < strpos($deviceBody, 'RecycleDevicePayment::create('), '整批校验和归属确认早于首笔设备付款写入');
$assert(!str_contains($deviceBody, "array_map('intval'") && str_contains($deviceBody, 'self::normalizePaymentDeviceIds('), '按设备入口使用严格IDs，不静默截断');
echo "[PASS] local admin payment guard: {$count} assertions; no database\n";
