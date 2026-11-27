<?php
declare(strict_types=1);

// Only run against a disposable local UNIX-socket Redis, never an application/server address.
use addon\tk_jhkd\app\service\core\RedisWeappDeliveryGuard;
require __DIR__ . '/../app/service/core/RedisWeappDeliveryGuard.php';
$socket = $argv[1] ?? '';
if (!str_starts_with($socket, '/tmp/tk-jhkd-report-test-') || !str_ends_with($socket, '.sock')) {
    throw new RuntimeException('Pass a disposable /tmp/tk-jhkd-report-test-*.sock Redis socket; no TCP/application Redis accepted.');
}
$redis = new Redis();
$redis->connect($socket);
$prefix = 'isolated-weapp-report:' . bin2hex(random_bytes(8)) . ':';
$guard = new RedisWeappDeliveryGuard($redis, $prefix);
$checks = 0;
$check = function ($condition, string $reason) use (&$checks): void { $checks++; if (!$condition) throw new RuntimeException($reason); };
try {
    $claim = $guard->claim('order|paid-1');
    $check($claim['status'] === 'acquired', 'atomic acquire');
    $check($guard->claim('order|paid-1')['status'] === 'busy', 'lease excludes concurrent submit');
    $guard->beforeSend('order|paid-1', $claim['token']);
    $key = $prefix . hash('sha256', 'order|paid-1');
    $check($redis->get($key . ':state') === 'unknown' && $redis->ttl($key . ':state') === -1, 'unknown survives lease expiration');
    $check($guard->claim('order|paid-1')['status'] === 'unknown', 'in-flight marked unknown before request');
    $guard->complete('order|paid-1', $claim['token'], 'done');
    $check($guard->claim('order|paid-1')['status'] === 'done' && $redis->ttl($key . ':state') === -1, 'done persists without ttl');
    $check(!$redis->exists($key . ':lease'), 'lease released on confirmed outcome');
    $unknown = $guard->claim('order|timeout-2');
    $guard->beforeSend('order|timeout-2', $unknown['token']);
    $unknownKey = $prefix . hash('sha256', 'order|timeout-2');
    $redis->del($unknownKey . ':lease'); // Simulate worker death/expired short lease.
    $check($guard->claim('order|timeout-2')['status'] === 'unknown', 'worker death never silently unlocks unknown operation');
    $rejected = $guard->claim('order|rejected-3');
    $guard->beforeSend('order|rejected-3', $rejected['token']);
    $guard->complete('order|rejected-3', $rejected['token'], 'rejected');
    $rejectedKey = $prefix . hash('sha256', 'order|rejected-3');
    $check($guard->claim('order|rejected-3')['status'] === 'rejected', 'explicit rejection cooldown');
    $check($redis->ttl($rejectedKey . ':state') > 290 && $redis->ttl($rejectedKey . ':state') <= 300, 'bounded rejection cooldown');
    $bad = $guard->claim('order|lease-lost-4');
    $redis->set($prefix . hash('sha256', 'order|lease-lost-4') . ':lease', 'another-owner');
    try { $guard->beforeSend('order|lease-lost-4', $bad['token']); throw new LogicException('lost lease sent'); }
    catch (RuntimeException $expected) { $check(true, 'lost lease blocks before mutation'); }
    echo "PASS {$checks} actual Redis/Lua checks on disposable UNIX socket only.\n";
} finally {
    foreach ($redis->keys($prefix . '*') as $key) $redis->del($key);
    $redis->close();
}
