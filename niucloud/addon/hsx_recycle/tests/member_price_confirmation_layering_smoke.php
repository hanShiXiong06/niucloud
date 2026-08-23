<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$api = (string)file_get_contents($root . '/app/service/api/recycle_order/RecycleDeviceService.php');
$core = (string)file_get_contents($root . '/app/service/core/recycle_order/CoreRecycleDeviceService.php');
$event = (string)file_get_contents($root . '/app/event.php');
$listener = (string)file_get_contents($root . '/app/listener/order/RecycleDeviceConfirmedListener.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$confirmStart = strpos($api, 'public function confirmPrice(int $id, bool $accept)');
$confirmEnd = strpos($api, 'public function confirm(int $id, bool $is_sell', $confirmStart ?: 0);
$confirmMethod = $confirmStart !== false && $confirmEnd !== false
    ? substr($api, $confirmStart, $confirmEnd - $confirmStart)
    : '';

$assert($confirmMethod !== '', 'API 用户确认报价方法必须存在');
$assert(
    str_contains($confirmMethod, 'confirmMemberPrice('),
    'API 层只能把身份与参数交给 Core'
);
$assert(
    !str_contains($confirmMethod, 'startTrans(')
    && !str_contains($confirmMethod, 'RecycleDeviceLog')
    && !str_contains($confirmMethod, 'dispatchAfterRecycle('),
    'API 层不得再持有事务、日志或下游编排业务'
);
$assert(
    str_contains($core, 'public function confirmMemberPrice(')
    && str_contains($core, 'lock(true)')
    && str_contains($core, 'RecycleDeviceLog::create(')
    && str_contains($core, 'syncConfirmedOrderStatus('),
    'Core 必须完整负责归属校验、并发锁、日志和订单状态汇总'
);
$assert(
    str_contains($core, "event('RecycleDeviceConfirmed'")
    && str_contains($event, "'RecycleDeviceConfirmed'")
    && str_contains($listener, 'dispatchAfterRecycle($deviceIds)'),
    '事务提交后的 ERP/财务扩展必须通过事件适配'
);
$assert(
    str_contains($core, "'changed' => false")
    && str_contains($core, '不能重复更改确认结果'),
    '确认报价必须支持同结果幂等，并禁止终态互相翻转'
);

echo "[PASS] member price confirmation layering smoke test\n";
