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
$regressionRequestId = 'operating-finance:mrhodgi3:8tseaz45p9';
$regressionHash = hash('sha256', $regressionRequestId);
$legacyOverflowId = (string)base_convert(substr($regressionHash, 0, 12), 16, 10);
$assert((float)$legacyOverflowId > 2147483647, '线上回归样例必须能够复现旧行号溢出');
$assert($legacyOverflowId === '120275037744500', '线上回归样例的完整行号必须稳定保留到origin_id');
$assert(str_contains($service, "'source_name' => (string)\$category['name']"), '经营收支业务来源必须使用支出类型，不能把用户说明冒充来源');

$config = (string)file_get_contents($root . '/app/service/admin/ErpConfigService.php');
foreach (['operating_rent', 'operating_utilities', 'operating_office', 'operating_salary', 'operating_marketing', 'operating_service_income'] as $needle) {
    $assert(str_contains($config, $needle), '经营收支动态分类缺少：' . $needle);
}
$finance = (string)file_get_contents($root . '/app/service/admin/ErpFinanceService.php');
foreach (['operating_income_amount', 'operating_expense_amount', 'operating_profit_amount'] as $needle) {
    $assert(str_contains($finance, $needle), '经营看板缺少经营利润口径：' . $needle);
}
$factService = (string)file_get_contents($root . '/app/service/admin/ErpFinanceFactService.php');
$assert(str_contains($factService, 'LEGACY_SOURCE_ID_MAX = 2147483647'), '财务事实入口必须按数据库INT上限处理旧source_id');
$assert(str_contains($factService, 'normalizeLegacySourceId($lineId)'), '财务事实入口必须统一规范外部明细ID');
$assert(str_contains($factService, "'origin_id' => (string)\$snapshot['line_id']"), '超大或字符串明细ID必须完整保存到origin_id');
$assert(substr_count($factService, "unset(\$request['source_id'])") === 1 && substr_count($factService, "unset(\$current['source_id'])") === 1, '失败记录重试必须忽略派生source_id但继续校验原始line_id');
require_once $repo . '/niucloud/vendor/autoload.php';
$factReflection = new ReflectionClass(addon\hsx_erp\app\service\admin\ErpFinanceFactService::class);
$factInstance = $factReflection->newInstanceWithoutConstructor();
$normalizeLegacyId = $factReflection->getMethod('normalizeLegacySourceId');
$normalizeLegacyId->setAccessible(true);
$assert($normalizeLegacyId->invoke($factInstance, '2147483647') === 2147483647, '数据库INT上限必须可正常写入');
$assert($normalizeLegacyId->invoke($factInstance, '2147483648') === 0, '超过数据库INT上限的数字必须只保存到origin_id');
$assert($normalizeLegacyId->invoke($factInstance, $legacyOverflowId) === 0, '线上溢出行号不得再写入source_id');
$assert($normalizeLegacyId->invoke($factInstance, 'external-uuid') === 0, '外部字符串行号不得错误转换为source_id');
$assert($normalizeLegacyId->invoke($factInstance, '00042') === 42, '合法前导零数字ID应规范为整数');
$assertSameRequest = $factReflection->getMethod('assertSameInboxRequest');
$assertSameRequest->setAccessible(true);
$storedRequest = ['event_id' => $regressionRequestId, 'line_id' => $legacyOverflowId, 'source_id' => (int)$legacyOverflowId, 'amount' => '10000.00'];
try {
    $assertSameRequest->invoke(
        $factInstance,
        ['payload_json' => json_encode(['request' => $storedRequest], JSON_UNESCAPED_UNICODE)],
        array_replace($storedRequest, ['source_id' => 0])
    );
} catch (Throwable $e) {
    $assert(false, '升级前失败的经营收支必须允许使用同一request_id重试：' . $e->getMessage());
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
