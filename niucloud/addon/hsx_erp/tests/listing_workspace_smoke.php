<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$workspace = file_get_contents($root . '/app/service/admin/ErpListingWorkspaceService.php');
$stock = file_get_contents($root . '/app/service/admin/ErpStockService.php');
$listener = file_get_contents($root . '/app/listener/DeviceAssetPriceCompleted.php');
$event = file_get_contents($root . '/app/event.php');
$deviceEvent = file_get_contents(dirname($root) . '/hsx_device_asset/app/event.php');
$deviceService = file_get_contents(dirname($root) . '/hsx_device_asset/app/service/admin/DeviceAssetService.php');
$shopPublisher = file_get_contents(dirname($root) . '/phone_shop/app/listener/erp/ErpPublishListing.php');
$phoneShop = file_get_contents(dirname($root) . '/phone_shop/app/listener/intake/DeviceAssetPricedListener.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$assert(str_contains($workspace, "event('HsxErpListingMediaCapability'"), 'ERP should discover media providers through event contract');
$assert(str_contains($workspace, "'media_provider' => 'erp'"), 'workspace must always provide ERP fallback');
$assert(str_contains($stock, 'prepare_listing_media'), 'stock action must expose middle-platform task from ERP entry');
$assert(str_contains($stock, 'complete_listing_media_price'), 'photo-price combined workflow action is missing');
$assert(str_contains($stock, 'autoPublishListingIfReady'), 'automatic publish must remain a guarded optional capability');
$assert(str_contains($event, 'DeviceAssetPriceCompleted'), 'ERP must consume device-asset completion event');
$assert(str_contains($deviceEvent, 'HsxErpListingMediaCapability'), 'device asset must expose capability without ERP hard dependency');
$assert(str_contains($listener, 'auto_publish'), 'middle-platform completion should continue ERP automatic flow');
$assert(str_contains($deviceService, "'video_url'") && str_contains($listener, "'video_url'"), 'middle-platform videos must write back to ERP');
$assert(str_contains($stock, "'video_url'") && str_contains($shopPublisher, "'goods_video'"), 'ERP videos must publish to marketplace goods');
$assert(str_contains($phoneShop, 'erp_owned_workflow'), 'phone shop must not bypass ERP-owned listing workflow');

echo "PASS listing workspace smoke\n";
