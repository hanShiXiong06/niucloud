<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

require_once $root . '/hsx_performance/app/support/PerformanceDecimal.php';

use addon\hsx_performance\app\support\PerformanceDecimal;

$assert(PerformanceDecimal::normalize('00012.30', 2) === '12.30', '十进制规范化结果错误');
$assert(PerformanceDecimal::signed('-12.30', 2, 1) === '12.30', '正向事实必须保存绝对值');
$assert(PerformanceDecimal::signed('12.30', 2, -1) === '-12.30', '冲红事实必须保存负值');
$assert(PerformanceDecimal::subtract('10000.10', '9999.99', 2) === '0.11', '十进制相减不得使用浮点误差');
$assert(PerformanceDecimal::subtract('10.00', '12.50', 2) === '-2.50', '十进制负结果计算错误');

$factService = $read('hsx_performance/app/service/core/PerformanceFactService.php');
$projection = $read('hsx_performance/app/service/core/PerformanceProjectionService.php');
$sql = $read('hsx_performance/sql/install.sql');
$upgradeSql = $read('hsx_performance/sql/update_1.1.0.sql');
$routes = $read('hsx_performance/app/adminapi/route/route.php');
$memberCard = $read('hsx_member_card/app/service/admin/MemberCardStaffFactService.php');
$recycle = $read('hsx_recycle/app/service/core/recycle_device/CoreRecycleDeviceLogService.php');
$erpLedger = $read('hsx_erp/app/service/admin/ErpLedgerService.php');
$erpListener = $read('hsx_performance/app/listener/ErpDomainPerformance.php');

$assert(str_contains($sql, 'UNIQUE KEY `uk_site_event`') && str_contains($factService, 'hash_equals'), '事实幂等必须同时由数据库唯一键和载荷哈希保证');
$assert(str_contains($factService, 'event_payload_conflict') && str_contains($factService, 'duplicate_reversal'), '内容冲突和重复冲红必须进入异常中心');
$assert(str_contains($factService, 'partial_reversal_not_supported') && str_contains($factService, 'reversal_of_event_id'), '第一期必须只允许可追溯的完整冲红');
$assert(str_contains($factService, 'Db::transaction') && str_contains($factService, 'rebuildForFact') && str_contains($factService, 'assertReversal($factData, $event, true)'), '事实、并发冲红校验和日汇总投影必须在同一事务内完成');
$assert(str_contains($factService, "config('app.default_timezone'") && str_contains($factService, 'DateTimeImmutable'), '业务日期必须显式使用框架统一时区');
$assert(str_contains($projection, 'COALESCE(SUM(quantity),0)') && str_contains($projection, 'SUM(direction)') && str_contains($projection, '->lock(true)'), '日汇总必须由数据库DECIMAL计算并通过指标行串行化');
$assert(str_contains($sql, 'performance_employee_daily') && str_contains($sql, 'performance_anomaly') && str_contains($sql, 'performance_metric'), '安装SQL必须包含指标、汇总和异常表');
$assert(str_contains($upgradeSql, "SET time_zone = '+08:00'") && str_contains($upgradeSql, 'payload_hash') && str_contains($upgradeSql, 'business_date') && str_contains($upgradeSql, 'INSERT INTO `{{prefix}}performance_employee_daily`'), '升级SQL必须固定时区、补齐契约字段并回填历史日汇总');
$assert(str_contains($routes, "Route::post('output/reconcile'") && str_contains($routes, "Route::post('output/rebuild'"), '必须提供对账和可重建接口');
$assert(str_contains($memberCard, "'event_name' => 'performance.fact.recorded.v1'") && str_contains($memberCard, '$isReversal = $reversalOfEventId !==') && str_contains($memberCard, '$sourceFact = $original->toArray()'), '会员卡必须区分负向资金动作与冲红，并把冲红归回原员工');
$assert(str_contains($recycle, "'hsx_recycle:device_log:'") && str_contains($recycle, "'recycle.check.completed'"), '回收事实必须以设备日志为稳定事件源');
$assert(str_contains($erpLedger, "'hsx_erp:asset_ledger:'") && str_contains($erpLedger, "'listing_photo_complete'"), 'ERP资产动作必须以资产流水为稳定事件源');
$assert(str_contains($erpListener, "'erp.asset.returned.v1'") && str_contains($erpListener, "'reversal_of_event_id'"), 'ERP销售退货必须冲红原销售结果');

echo "[PASS] HSX performance output fact contract smoke test\n";
