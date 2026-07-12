<?php
declare(strict_types=1);

$repo = dirname(__DIR__, 4);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$listHeader = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpListHeader.vue');
$listHook = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/hooks/useListHeader.ts');
$pageHeader = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/components/ErpPageHeader.vue');
$navbar = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/utils/navbar.ts');

$assert(!str_contains($listHeader, 'statusBarHeight'), '原生导航下列表工具区不得再次叠加状态栏高度');
$assert(!str_contains($listHeader, 'webkit-scrollbar'), '公共列表头不得生成微信不兼容的伪元素选择器');
$assert(str_contains($listHeader, 'getErpListHeaderHeightRpx'), '列表头占位和分页顶部必须共享统一高度公式');
$assert(!str_contains($listHook, 'navbarHeightRpx +'), 'z-paging 顶部不得再次叠加原生导航高度');
$assert(str_contains($pageHeader, '#ifdef H5'), '使用原生导航的小程序不得重复渲染ERP自定义标题栏');
$assert(str_contains($navbar, 'viewport 已经从原生导航栏下方开始'), '导航尺寸工具必须声明原生导航坐标约束');

$standardLists = [
    'purchase/list.vue', 'sale/list.vue', 'payable/list.vue', 'receivable/list.vue',
    'stock/list.vue', 'purchase_return/list.vue', 'sale_return/list.vue', 'cost_adjust/list.vue',
];
foreach ($standardLists as $relative) {
    $source = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/' . $relative);
    $assert(str_contains($source, "useListHeader({ tabs: true"), $relative . ' 必须使用统一原生导航列表高度');
    $assert(str_contains($source, ':paging-style="pagingStyle"'), $relative . ' 必须通过z-paging官方paging-style传递顶部偏移');
    $assert(!str_contains($source, ':style="pagingStyle"'), $relative . ' 不得把顶部偏移写在微信自定义组件宿主style上');
}

echo "[PASS] ERP mobile native header layout smoke test\n";
