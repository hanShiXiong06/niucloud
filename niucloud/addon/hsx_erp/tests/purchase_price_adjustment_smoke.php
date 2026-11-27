<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\support\ErpPurchasePriceAdjustment as Price;
use addon\hsx_recycle\app\support\RecyclePurchaseSettlementPolicy as Mirror;

$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) throw new RuntimeException($message);
};
$reject = static function (callable $fn, string $message) use ($assert): void {
    try { $fn(); } catch (Throwable $e) { $assert(true, $message); return; }
    $assert(false, $message);
};
foreach ([[4600, 4600, 100, 4700, 100, 'partial'], [4600, 0, 100, 4700, 4700, 'pending'],
    [4600, 4000, 100, 4700, 700, 'partial'], [4700, 4600, -100, 4600, 0, 'settled'],
    [4600.01, 4600.01, 0.01, 4600.02, 0.01, 'partial']] as [$before, $paid, $delta, $after, $remaining, $status]) {
    $result = Price::calculate($before, $paid, $delta);
    $assert($result['amount'] == $after, '当前应付包含真实调价');
    $assert($result['settled_amount'] == $paid, '历史已付不变');
    $assert($result['remaining_amount'] == $remaining && $result['status'] === $status, '差额与状态一致');
}
foreach ([[4600,4600,-100], [4600,4700,100], [4600,-1,100], [0,0,100], [4600,0,0],
    [4600,0,-4600], [INF,0,100], [4600,NAN,100], [4600,0,INF], [999999999.99,0,1]] as $args) {
    $reject(static fn() => Price::calculate(...$args), '非法金额或需退差款不能覆盖原账');
}
$device = ['final_price'=>4600, 'pay_amount'=>4600, 'pay_status'=>1];
$change = ['before_amount'=>4600, 'after_amount'=>4700, 'settled_amount'=>4600, 'delta'=>100];
$result = Mirror::adjustment($device, $change);
$assert($result === ['final_price'=>4700, 'pay_amount'=>4600, 'pay_status'=>2], '补差重新待付但保留4600');
$assert(Mirror::adjustment(array_replace($device, ['pay_amount'=>0]), $change)['pay_amount'] == 4600, '旧全额付款兼容');
foreach ([['before_amount'=>4500], ['settled_amount'=>4500], ['after_amount'=>4500,'delta'=>-100], ['delta'=>99], ['delta'=>NAN]] as $invalid) {
    $reject(static fn() => Mirror::adjustment($device,array_replace($change,$invalid)), '回收与ERP不一致应拦截');
}
$first = ['target_amount'=>4700,'settled_amount'=>4650,'applied_amount'=>50,'remaining_amount'=>50];
$second = ['target_amount'=>4700,'settled_amount'=>4700,'applied_amount'=>50,'remaining_amount'=>0];
$partial = Mirror::settlement($result, $first);
$assert($partial === ['pay_amount'=>4650,'payment_amount'=>50,'pay_status'=>2], '第一次50流水，累计4650');
$full = Mirror::settlement(array_replace($result,$partial), $second);
$assert($full === ['pay_amount'=>4700,'payment_amount'=>50,'pay_status'=>1], '第二次50流水，累计4700');
$late = Mirror::settlement(array_replace($result,$full),$first);
$assert($late['pay_amount'] == 4700 && $late['payment_amount'] == 50 && $late['pay_status'] === 1, '乱序事件不倒退累计');
$assert(Mirror::settlement($result,['target_amount'=>4700,'settled_amount'=>4700,'applied_amount'=>100,'remaining_amount'=>0])['payment_amount'] == 100, '补付100不记4700流水');
foreach ([['applied_amount'=>0], ['remaining_amount'=>0], ['settled_amount'=>4800], ['target_amount'=>null], ['applied_amount'=>INF]] as $invalid) {
    $reject(static fn() => Mirror::settlement($result,array_replace($first,$invalid)), '结算快照必须金额一致');
}
echo "PASS purchase price adjustment: {$count} assertions\n";
