<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname(dirname(dirname($root)));
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$service = (string)file_get_contents($root . '/app/service/admin/ErpSaleProfitReportService.php');
foreach ([
    "where('i.status', '=', ErpDict::ASSET_SOLD)",
    "where('o.status', '<>', 'void')",
    'GREATEST(i.cost - i.refunded_cost, 0)',
    "'report_amount'",
    "'profit_state'",
    'appendReturnContext',
    'EXPORT_LIMIT = 20000',
] as $needle) {
    $assert(str_contains($service, $needle), '销售利润报表缺少有效成交、退款或导出口径：' . $needle);
}
$assert(str_contains($service, '$this->add($cost, $profit, 2)'), '销售净额必须使用精确小数计算“有效成本 + 当前毛利”');
$assert(str_contains($service, "'end_exclusive'") && str_contains($service, "' 00:00:00'"), '设备台账必须由后端统一处理自然日半开时间区间');
foreach (['sales_profit', 'loss', 'inventory', 'lifecycle', 'invalid'] as $preset) {
    $assert(str_contains($service, "'{$preset}'"), '设备经营台账缺少预设视图：' . $preset);
}
foreach (['columnRegistry', 'saveView', 'VIEW_CONFIG_KEY', 'buildAssetQuery'] as $needle) {
    $assert(str_contains($service, $needle), '设备经营台账缺少字段配置或资产口径：' . $needle);
}

$controller = (string)file_get_contents($root . '/app/adminapi/controller/ErpSale.php');
$route = (string)file_get_contents($root . '/app/adminapi/route/route.php');
$menu = (string)file_get_contents($root . '/app/dict/menu/site.php');
foreach (['profitReport', 'profitReportExport', 'profitReportMeta', 'saveProfitReportView', 'profitReportParams'] as $needle) {
    $assert(str_contains($controller, $needle), '销售控制器缺少利润报表能力：' . $needle);
}
foreach (['sale/profit_report', 'sale/profit_report/export', 'sale/profit_report/meta', 'sale/profit_report/view'] as $needle) {
    $assert(str_contains($route, $needle), '销售利润报表路由缺少：' . $needle);
    $assert(str_contains($menu, 'erp/' . $needle), '销售菜单权限缺少：erp/' . $needle);
}

$pc = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/components/ErpSaleProfitReport.vue');
$pluginPc = (string)file_get_contents($root . '/admin/components/ErpSaleProfitReport.vue');
$salePage = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/views/erp/sale/list.vue');
foreach (['设备经营台账', '台账视图', '字段设置', '导出当前结果', 'exportErpConfiguredTable', '总计'] as $needle) {
    $assert(str_contains($pc, $needle), 'PC 销售利润报表缺少产品能力：' . $needle);
}
$assert($pc === $pluginPc, '开发端与插件发布端的销售利润报表组件必须保持一致');
$exportHook = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/hooks/useErpTableExport.ts');
$pluginExportHook = (string)file_get_contents($root . '/admin/hooks/useErpTableExport.ts');
$assert(str_contains($exportHook, 'XLSX.writeFile') && $exportHook === $pluginExportHook, 'ERP 配置型表格公共导出器缺失或镜像不一致');
$assert(str_contains($salePage, 'ErpSaleProfitReport') && str_contains($salePage, '经营台账'), '销售出库页缺少设备经营台账入口');
$catalogSelect = (string)file_get_contents($repo . '/admin/src/addon/hsx_erp/components/ErpCatalogProductSelect.vue');
$pluginCatalogSelect = (string)file_get_contents($root . '/admin/components/ErpCatalogProductSelect.vue');
$assert(str_contains($catalogSelect, 'lazy: true') && str_contains($catalogSelect, 'node_type') && str_contains($catalogSelect, 'site_product_id'), '公共 ERP 商品目录组件必须支持品类、品牌、系列、型号懒加载');
$assert(str_contains($pc, 'ErpCatalogProductSelect') && str_contains($pc, 'catalog_product_id'), '设备经营台账必须复用完整商品目录组件，不能把一级品类误判为叶子节点');
$assert(str_contains($pc, 'period-row__picker-wrap') && str_contains($pc, 'max-width: 280px'), '设备经营台账必须通过固定外层限制日期控件宽度');
$assert($catalogSelect === $pluginCatalogSelect, '开发端与插件发布端的公共商品目录组件必须保持一致');
$assert(str_contains($controller, "['catalog_product_id', 0]") && str_contains($service, "where('a.catalog_product_id', '=', (int)\$where['catalog_product_id'])"), '设备经营台账的目录型号筛选必须传入后端并实际过滤');

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "[FAIL] {$failure}\n");
    exit(1);
}
echo "[PASS] ERP sale profit report smoke test\n";
