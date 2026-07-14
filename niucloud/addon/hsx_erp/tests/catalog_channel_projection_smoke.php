<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$install = (string)file_get_contents($root . '/sql/install.sql');
$service = (string)file_get_contents($root . '/app/service/admin/ErpGoodsCatalogService.php');
$projection = (string)file_get_contents($root . '/app/service/admin/ErpCatalogChannelProjectionService.php');
$events = (string)file_get_contents($root . '/app/event.php');
$meta = (string)file_get_contents($root . '/app/service/admin/ErpGoodsMetaService.php');
$purchase = (string)file_get_contents($root . '/admin/views/erp/purchase/list.vue');

$assert(!str_contains($install, 'erp_goods_category') && !str_contains($install, 'erp_category_mapping'), '旧分类表与映射表必须完全退役');
$assert(str_contains($install, '`catalog_product_id`') && str_contains($service, 'productSnapshot'), '采购、库存必须通过站点目录产品ID关联主数据');
$assert(str_contains($service, 'catalogPathSegments') && str_contains($service, 'count($segments) > 2'), '商品目录必须限制为一级品类和可选子分类');
$assert(str_contains($events, 'HsxErpCatalogProducts') && str_contains($service, "event('HsxErpCatalogChanged'"), '目录必须同时提供只读Hook并发布变更事件');
$assert(str_contains($projection, 'phone_shop_goods_category') && str_contains($projection, 'site_product_id'), '商城分类只能作为ERP目录的渠道投影');
$assert(str_contains($meta, "'category_source' => 'erp_catalog'"), '商品元数据必须声明ERP目录是分类主数据');
$assert(str_contains($purchase, 'ErpCatalogProductSelect') && str_contains($purchase, 'catalog_product_id'), '采购开单必须选择完整商品型号，不再选旧分类');

echo "[PASS] ERP catalog master and channel projection smoke test\n";
