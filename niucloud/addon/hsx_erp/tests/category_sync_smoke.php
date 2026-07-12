<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$project = dirname($root, 3);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$schema = (string)file_get_contents($root . '/app/support/ErpSchema.php');
$install = (string)file_get_contents($root . '/sql/install.sql');
$events = (string)file_get_contents($root . '/app/event.php');
$sync = (string)file_get_contents($root . '/app/service/admin/ErpCategorySyncService.php');
$meta = (string)file_get_contents($root . '/app/service/admin/ErpGoodsMetaService.php');
$pcMeta = (string)file_get_contents($project . '/admin/src/addon/hsx_erp/views/erp/goods/meta.vue');
$pcPurchase = (string)file_get_contents($project . '/admin/src/addon/hsx_erp/views/erp/purchase/list.vue');

$assert(str_contains($schema, 'erp_category_mapping') && str_contains($install, 'erp_category_mapping'), '分类映射表必须同时进入升级和新装结构');
$assert(str_contains($events, 'HsxErpCategoryProviders') && str_contains($events, 'HsxErpCategoryPull') && str_contains($events, 'HsxErpCategoryPush'), '分类联动必须通过事件 Hook 装配');
$assert(str_contains($sync, "['pull', 'push', 'reconcile']") && str_contains($sync, 'target_category_id'), '分类同步必须支持首次导入、首次推送和重装校准');
$assert(str_contains($meta, 'mappedShopCategoryIds') && str_contains($meta, "'category_source' => \$categorySyncReady"), '商品资料必须使用 ERP 稳定分类 ID 并映射商城规格');
$assert(str_contains($pcMeta, '已有商城分类，导入并绑定') && str_contains($pcMeta, '双向校准'), 'PC 商品资料必须提供可理解的首次同步和校准入口');
$assert(str_contains($pcPurchase, '<el-cascader') && str_contains($pcPurchase, 'emitPath: true'), 'PC采购开单必须使用级联分类选择并保存完整路径');

echo "[PASS] ERP category mapping and plugin Hook smoke test\n";
