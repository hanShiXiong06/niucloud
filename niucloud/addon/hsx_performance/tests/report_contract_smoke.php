<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$service = $read('hsx_performance/app/service/core/PerformanceReportService.php');
$sql = $read('hsx_performance/sql/install.sql');
$routes = $read('hsx_performance/app/adminapi/route/route.php');
$wecom = $read('hsx_wecom/app/service/core/WecomNotificationService.php');
$erpEvents = $read('hsx_erp/app/event.php');
$recycleEvents = $read('hsx_recycle/app/event.php');
$recycleMetrics = $read('hsx_recycle/app/service/core/report/RecycleBusinessReportMetricsService.php');

$assert(str_contains($service, 'HsxBusinessReportMetricsRequested') && str_contains($service, 'HsxBusinessReportGenerated'), '经营报告必须通过事件采集指标并派发通知');
$assert(str_contains($sql, 'UNIQUE KEY `uk_site_period`'), '报告周期必须具备数据库幂等约束');
$assert(str_contains($routes, "Route::post('reports/generate'") && str_contains($routes, "Route::get('reports/:id'"), '经营报告必须支持手动生成和详情查询');
$assert(str_contains($erpEvents, 'BusinessReportMetricsProvider') && str_contains($recycleEvents, 'RecycleBusinessReportMetricsProvider'), 'ERP和回收必须独立提供经营指标');
$assert(str_contains($wecom, 'enqueueBusinessReport') && str_contains($wecom, "scene' => 'business_report'"), '企业微信必须独立消费报告事件');
$assert(str_contains($wecom, 'MessageSend::dispatch') && str_contains($wecom, "env('queue.state', false)"), '企业微信消息在队列启用时必须即时投递发送任务');
$assert(str_contains($recycleMetrics, "alias('d')") && str_contains($recycleMetrics, "where('d.site_id'"), '回收分类联表查询必须限定设备表别名，避免site_id歧义');
$assert(str_contains($service, "notify_status' => 'failed'") && str_contains($service, 'notify_attempts'), '报告通知失败必须可补偿');
$assert(str_contains($service, "date('H:i') >= (string)\$config['send_time']"), '定时任务延迟后必须能够补生成报告');
$assert(str_contains($service, 'hasUnavailableProvider') && str_contains($service, "'skipped'"), '已有异常报告再次生成时必须刷新数据源并允许补推');
$assert(str_contains($wecom, "(string)\$exists->status === 'skipped'") && str_contains($wecom, "'status' => 'pending'"), '企业微信配置补齐后必须能够原地补推跳过消息');

echo "[PASS] HSX performance report contract smoke test\n";
