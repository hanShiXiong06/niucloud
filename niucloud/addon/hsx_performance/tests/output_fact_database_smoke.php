<?php
declare(strict_types=1);

use addon\hsx_performance\app\model\PerformanceAnomaly;
use addon\hsx_performance\app\model\PerformanceEmployeeDaily;
use addon\hsx_performance\app\model\PerformanceFact;
use addon\hsx_performance\app\model\PerformanceMetric;
use addon\hsx_performance\app\service\admin\PerformanceOutputAdminService;
use addon\hsx_performance\app\service\core\PerformanceFactService;

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$siteId = 2147483000;
$employeeUid = 2147483001;
$eventPrefix = 'hsx_performance:db-smoke:';
$businessDate = date('Y-m-d');
$cleanup = static function () use ($siteId): void {
    PerformanceAnomaly::where('site_id', '=', $siteId)->delete();
    PerformanceEmployeeDaily::where('site_id', '=', $siteId)->delete();
    PerformanceMetric::where('site_id', '=', $siteId)->delete();
    PerformanceFact::where('site_id', '=', $siteId)->delete();
};

$cleanup();
try {
    $service = new PerformanceFactService();
    $original = [
        'event_name' => 'performance.fact.recorded.v1',
        'event_version' => 1,
        'site_id' => $siteId,
        'event_id' => $eventPrefix . 'original',
        'source_plugin' => 'hsx_performance_test',
        'business_chain' => 'test',
        'metric_key' => 'test.output.completed',
        'metric_name' => '测试产出',
        'fact_scope' => 'outcome',
        'direction' => 1,
        'employee_uid' => $employeeUid,
        'employee_name' => '数据库测试员工',
        'role_key' => 'tester',
        'business_type' => 'test_order',
        'business_id' => 'DB-SMOKE-1',
        'business_no' => 'DB-SMOKE-1',
        'quantity' => '1.00',
        'amount' => '100.10',
        'profit' => '20.01',
        'duration_seconds' => 61,
        'quality_score' => '4.5000',
        'unit' => 'order',
        'occurred_at' => time() - 5,
    ];

    $created = $service->consume($original);
    $assert(($created['status'] ?? '') === 'processed', '正向事实没有写入');
    $duplicate = $service->consume($original);
    $assert(($duplicate['status'] ?? '') === 'duplicate', '相同事件重放没有命中幂等');

    $conflictRejected = false;
    try {
        $service->consume(array_merge($original, ['amount' => '100.11']));
    } catch (Throwable $e) {
        $conflictRejected = str_contains($e->getMessage(), '不同事实内容');
    }
    $assert($conflictRejected, '相同事件ID的不同载荷没有被拒绝');

    $reversal = array_merge($original, [
        'event_id' => $eventPrefix . 'reversal',
        'direction' => -1,
        'fact_type' => 'reversal',
        'reversal_of_event_id' => $original['event_id'],
        'occurred_at' => time(),
    ]);
    $reversed = $service->consume($reversal);
    $assert(($reversed['status'] ?? '') === 'processed', '完整冲红没有写入');

    $duplicateReversalRejected = false;
    try {
        $service->consume(array_merge($reversal, ['event_id' => $eventPrefix . 'reversal-2']));
    } catch (Throwable $e) {
        $duplicateReversalRejected = str_contains($e->getMessage(), '已经完成冲红');
    }
    $assert($duplicateReversalRejected, '同一原事实被重复冲红');

    $daily = PerformanceEmployeeDaily::where([
        ['site_id', '=', $siteId],
        ['business_date', '=', $businessDate],
        ['employee_uid', '=', $employeeUid],
        ['metric_key', '=', 'test.output.completed'],
    ])->findOrEmpty();
    $assert(!$daily->isEmpty(), '日汇总没有生成');
    $assert((string)$daily->quantity === '0.0000', '冲红后数量没有归零');
    $assert((string)$daily->amount === '0.00', '冲红后金额没有归零');
    $assert((string)$daily->profit === '0.00', '冲红后利润没有归零');
    $assert((int)$daily->duration_seconds === 0, '冲红后时长没有归零');
    $assert((string)$daily->quality_score === '0.0000', '冲红后质量分没有归零');
    $assert((int)$daily->fact_count === 2 && (int)$daily->effective_fact_count === 0, '正向/冲红计数错误');
    $assert((int)$daily->original_count === 1 && (int)$daily->reversal_count === 1, '事实类型计数错误');

    $output = new PerformanceOutputAdminService();
    $siteProperty = (new ReflectionClass($output))->getProperty('site_id');
    $siteProperty->setAccessible(true);
    $siteProperty->setValue($output, $siteId);
    $reconcile = $output->reconcile(['start_date' => $businessDate, 'end_date' => $businessDate]);
    $assert(($reconcile['passed'] ?? false) === true, '事实账本与日汇总对账未通过');
    $assert((int)($reconcile['projection_mismatch_count'] ?? -1) === 0, '逐字段对账存在差异');

    echo "[PASS] HSX performance output database smoke test\n";
} finally {
    $cleanup();
}
