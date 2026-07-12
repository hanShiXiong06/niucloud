<?php
declare(strict_types=1);

$project = dirname(__DIR__, 4);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$pc = (string)file_get_contents($project . '/admin/src/addon/hsx_erp/views/erp/workbench/index.vue');
$mobile = (string)file_get_contents($project . '/site-uniapp/src/addon/hsx_erp/pages/dashboard/index.vue');

$assert(str_contains($pc, 'Promise.allSettled') && str_contains($pc, 'dashboardLoadSequence'), 'PC工作台必须隔离可选KPI失败并防止旧请求覆盖新筛选');
$assert(str_contains($mobile, 'Promise.allSettled') && str_contains($mobile, 'loadSequence'), '移动工作台必须隔离可选KPI失败并防止请求竞态');
$assert(str_contains($mobile, 'MUST_LOGIN') && str_contains($mobile, 'profitStructureHasData'), '移动工作台不得在登录页误报经营数据错误，空利润数据不得渲染异常环图');

echo "[PASS] ERP dashboard resilience smoke test\n";
