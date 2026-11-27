<?php
declare(strict_types=1);

$workspace = dirname(__DIR__, 4);
$read = static fn(string $path): string => (string)file_get_contents($workspace . '/' . ltrim($path, '/'));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$error = $read('site-uniapp/src/addon/hsx_erp/utils/error.ts');
$assert(str_contains($error, 'erpErrorMessage') && str_contains($error, 'showErpError'), '移动ERP必须统一提取并展示后端错误原因');
$assert(str_contains($error, 'lastToastAt'), '分页自动重试时必须抑制重复错误弹窗');

$pages = [
    'stock/list.vue', 'payable/list.vue', 'purchase/list.vue', 'receivable/list.vue',
    'sale/list.vue', 'purchase_return/list.vue', 'sale_return/list.vue',
    'operating_finance/list.vue', 'serial_trace/list.vue', 'stocktake/list.vue', 'cost_adjust/list.vue',
];
foreach ($pages as $page) {
    $source = $read('site-uniapp/src/addon/hsx_erp/pages/' . $page);
    $assert(str_contains($source, 'showErpError'), $page . ' 加载失败时不能静默，只显示空列表');
    $assert(str_contains($source, 'complete(false)'), $page . ' 加载失败后必须保留分页重试能力');
}

$stock = $read('niucloud/addon/hsx_erp/app/service/admin/ErpStockService.php');
$assert(str_contains($stock, 'CASE WHEN a.retail_price > 0 THEN a.retail_price'), '库存价格筛选必须与前端“零售价优先”展示口径一致');
$assert(str_contains($stock, '最低销售价不能大于最高销售价'), '错误的价格区间必须返回明确提示');

echo "[PASS] mobile ERP list feedback and price contract\n";

