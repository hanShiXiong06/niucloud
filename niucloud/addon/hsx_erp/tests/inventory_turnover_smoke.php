<?php
declare(strict_types=1);

$repo = dirname(__DIR__, 4);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$turnoverService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpTurnoverService.php');
$configService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpConfigService.php');
$stockService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpStockService.php');
$financeService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpFinanceService.php');
$routes = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/adminapi/route/route.php');
$pcStock = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/views/erp/stock/list.vue');
$mobileStock = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/stock/list.vue');

$assert(str_contains($configService, "'turnover' => ["), '业务规则必须提供库存周转配置');
$assert(str_contains($turnoverService, "'warning_total_cost'"), '周转汇总必须统计预警库存占用成本');
$assert(str_contains($turnoverService, "'average_age_days'"), '周转汇总必须返回平均库龄');
$assert(str_contains($stockService, 'turnover_level'), '库存列表必须支持周转等级筛选');
$assert(str_contains($stockService, '->decorate('), '库存设备必须由后端统一补齐周转字段');
$assert(str_contains($financeService, "'turnover' => ["), '经营工作台必须返回库存周转提醒');
$assert(str_contains($routes, "stock/turnover_summary"), '必须开放统一库存周转汇总接口');
$assert(str_contains($pcStock, '周转预警') && str_contains($pcStock, '严重滞销'), 'PC库存中心必须展示周转分层');
$assert(str_contains($mobileStock, 'turnover-overview') && str_contains($mobileStock, '周转等级'), '移动库存中心必须展示并筛选周转分层');

echo "[PASS] ERP inventory turnover smoke test\n";
