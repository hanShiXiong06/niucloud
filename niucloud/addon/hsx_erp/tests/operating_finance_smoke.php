<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname(dirname(dirname($root)));
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };

$service = (string)file_get_contents($root . '/app/service/admin/ErpOperatingFinanceService.php');
foreach (['ErpFinanceFactService', "'settlement_mode'", 'confirmReceipt', 'confirmPayableItemsPayment', "'hsx_erp.operating_'", 'ErpIdempotency::child'] as $needle) {
    $assert(str_contains($service, $needle), '经营收支服务缺少应收应付或现结能力：' . $needle);
}
$assert(str_contains($service, "['affects_asset_cost'] ?? 0) !== 0"), '经营费用必须拒绝影响设备成本的分类');
$assert(str_contains($service, "hash('sha256', \$requestId)"), '经营记账来源单号和行号必须由幂等键稳定生成');
$assert(str_contains($service, "'source_name' => (string)\$category['name']"), '经营收支业务来源必须使用支出类型，不能把用户说明冒充来源');

$config = (string)file_get_contents($root . '/app/service/admin/ErpConfigService.php');
foreach (['operating_rent', 'operating_utilities', 'operating_office', 'operating_salary', 'operating_marketing', 'operating_service_income'] as $needle) {
    $assert(str_contains($config, $needle), '经营收支动态分类缺少：' . $needle);
}
$finance = (string)file_get_contents($root . '/app/service/admin/ErpFinanceService.php');
foreach (['operating_income_amount', 'operating_expense_amount', 'operating_profit_amount'] as $needle) {
    $assert(str_contains($finance, $needle), '经营看板缺少经营利润口径：' . $needle);
}
foreach (['todayTurnoverMetrics', 'today_sold_count', 'opening_stock_count', 'turnover_rate'] as $needle) {
    $assert(str_contains($finance, $needle), '经营看板缺少今日动销率口径：' . $needle);
}
$payable = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/views/erp/payable/list.vue');
foreach (['isNonDevicePay', '费用明细', 'ErpPartySelect', 'ErpOverflowText'] as $needle) {
    $assert(str_contains($payable, $needle), 'PC 应付未兼容经营性无设备付款或公共组件：' . $needle);
}
$mobile = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/operating_finance/list.vue');
$pc = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/views/erp/operating_finance/list.vue');
foreach ([$mobile, $pc] as $view) {
    foreach (['经营收支', '转财务结算', '已经现场收付', '经营净额'] as $needle) {
        $assert(str_contains($view, $needle) || ($needle === '经营净额' && str_contains($view, '待收待付')), '经营收支页面缺少商业操作语义：' . $needle);
    }
}
$request = (string)file_get_contents($repo . '/site-uniapp/src/utils/request.ts');
$loadingHook = (string)file_get_contents($repo . '/site-uniapp/src/hooks/useRequestLoading.ts');
foreach (['beginRequestLoading', 'endRequestLoading', 'showLoading'] as $needle) $assert(str_contains($request, $needle), '统一请求层缺少 loading：' . $needle);
foreach (['pendingCount', 'activeTokens', 'withRequestLoading', 'loadingDelay'] as $needle) {
    $assert(str_contains($loadingHook, $needle) || ($needle === 'loadingDelay' && str_contains($request, $needle)), '请求 loading Hook 缺少并发或延迟控制：' . $needle);
}

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "[FAIL] {$failure}\n");
    exit(1);
}
echo "[PASS] ERP operating finance and request loading smoke test\n";
