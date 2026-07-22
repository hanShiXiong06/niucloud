<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if ($condition) return;
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

$mapping = (string)file_get_contents($root . '/app/service/admin/ErpChannelMappingService.php');
$completion = (string)file_get_contents($root . '/app/listener/marketplace/PhoneShopListingMaterialCompleted.php');
$intake = (string)file_get_contents(dirname($root) . '/phone_shop/app/service/admin/intake/DeviceIntakeService.php');
$event = (string)file_get_contents($root . '/app/event.php');
$shopEvent = (string)file_get_contents(dirname($root) . '/phone_shop/app/event.php');
$projection = (string)file_get_contents($root . '/app/service/admin/ErpCatalogChannelProjectionService.php');
$install = (string)file_get_contents($root . '/sql/install.sql');

foreach (['erp_channel_category_mapping', 'erp_channel_attribute_mapping', 'erp_channel_attribute_value_mapping', 'erp_channel_listing'] as $table) {
    $assert(str_contains($install, $table), '缺少渠道桥接表：' . $table);
}
$assert(str_contains($event, 'HsxErpChannelMappingResolve'), 'ERP必须通过Hook向渠道提供映射结果');
$assert(!str_contains($event, 'HsxErpPublishListing') && str_contains($shopEvent, 'HsxErpPublishListing'), '商城发布实现必须由商城插件热插拔装配');
$assert(str_contains($projection, "event('HsxErpChannelCategoryProject'") && !str_contains($projection, 'phone_shop_goods_category'), 'ERP目录服务不得直接依赖商城分类表');
$assert(str_contains($intake, "event('HsxErpChannelMappingResolve'"), '商城不得直接读取ERP映射私表');
$assert(str_contains($mapping, 'site_id') && str_contains($mapping, 'channel_key'), '所有映射必须按站点和渠道隔离');
$assert(str_contains($mapping, 'recordManualCompletion') && str_contains($completion, 'recordManualCompletion'), '商城人工映射完成后必须沉淀为可复用桥接关系');
$assert(!str_contains($completion, "'retail_price' =>") && !str_contains($completion, "'spec_json' =>"), '商城不得反向覆盖ERP主数据');

echo "[PASS] ERP channel mapping bridge smoke test\n";
