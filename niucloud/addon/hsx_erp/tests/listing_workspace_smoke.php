<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$workspace = file_get_contents($root . '/app/service/admin/ErpListingWorkspaceService.php');
$formContract = file_get_contents($root . '/app/support/ErpListingFormContract.php');
$configService = file_get_contents($root . '/app/service/admin/ErpConfigService.php');
$stock = file_get_contents($root . '/app/service/admin/ErpStockService.php');
$stockController = file_get_contents($root . '/app/adminapi/controller/ErpStock.php');
$workspaceRoot = dirname(dirname(dirname($root)));
$pcStock = file_get_contents($workspaceRoot . '/admin/src/addon/hsx_erp/views/erp/stock/list.vue');
$packagedPcStock = file_get_contents($root . '/admin/views/erp/stock/list.vue');
$pcPurchase = file_get_contents($workspaceRoot . '/admin/src/addon/hsx_erp/views/erp/purchase/list.vue');
$packagedPcPurchase = file_get_contents($root . '/admin/views/erp/purchase/list.vue');
$pcForm = file_get_contents($workspaceRoot . '/admin/src/addon/hsx_erp/components/ErpListingWorkspaceForm.vue');
$packagedPcForm = file_get_contents($root . '/admin/components/ErpListingWorkspaceForm.vue');
$pcFormHook = file_get_contents($workspaceRoot . '/admin/src/addon/hsx_erp/hooks/useErpListingForm.ts');
$packagedPcFormHook = file_get_contents($root . '/admin/hooks/useErpListingForm.ts');
$pcFeedback = file_get_contents($workspaceRoot . '/admin/src/addon/hsx_erp/hooks/useErpListingFeedback.ts');
$packagedPcFeedback = file_get_contents($root . '/admin/hooks/useErpListingFeedback.ts');
$mobileStock = file_get_contents($workspaceRoot . '/site-uniapp/src/addon/hsx_erp/pages/stock/list.vue');
$mobileDetail = file_get_contents($workspaceRoot . '/site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue');
$mobilePurchase = file_get_contents($workspaceRoot . '/site-uniapp/src/addon/hsx_erp/pages/purchase/create.vue');
$mobileForm = file_get_contents($workspaceRoot . '/site-uniapp/src/addon/hsx_erp/components/ErpListingWorkspaceForm.vue');
$listener = file_get_contents($root . '/app/listener/DeviceAssetPriceCompleted.php');
$inboundListener = file_get_contents($root . '/app/listener/ErpDeviceInboundRequested.php');
$purchaseService = file_get_contents($root . '/app/service/admin/ErpPurchaseService.php');
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
$assert(str_contains($workspace, "'forms' =>") && str_contains($workspace, "'steps' =>"), 'workspace must expose the shared form and step contract');
$assert(str_contains($formContract, "ACTION_ONE_STOP = 'one_stop'") && str_contains($formContract, "ACTION_MEDIA_PRICE = 'media_price'"), 'listing form contract must define one-person and team actions');
$assert(str_contains($formContract, "'editable_fields'") && str_contains($formContract, "'required_fields'"), 'listing form contract must expose visibility and requiredness');
$assert(str_contains($configService, "['purchase']['mobile_entry_mode'] =") && str_contains($configService, "'collaborative'"), 'purchase mode must be derived from the listing workspace');
$assert(str_contains($stock, 'prepare_listing_media'), 'stock action must expose middle-platform task from ERP entry');
$assert(str_contains($stock, 'complete_listing_media_price'), 'photo-price combined workflow action is missing');
$assert(str_contains($stock, 'autoPublishListingIfReady'), 'automatic publish must remain a guarded optional capability');
$assert(str_contains($stock, 'ErpListingWorkflow::canHandoffToShop') && str_contains($stock, "'marketplace_unavailable'"), 'automatic flow must support channel handoff and visible warehouse-policy outcomes');
$assert(str_contains($event, 'DeviceAssetPriceCompleted'), 'ERP must consume device-asset completion event');
$assert(str_contains($deviceEvent, 'HsxErpListingMediaCapability'), 'device asset must expose capability without ERP hard dependency');
$assert(str_contains($listener, 'auto_publish'), 'middle-platform completion should continue ERP automatic flow');
$assert(str_contains($inboundListener, 'autoPublishListingIfReady') && str_contains($purchaseService, 'autoPublishListingIfReady'), 'recycle and ERP purchase inbound must continue the configured automatic publish flow');
$assert(str_contains($deviceService, "'video_url'") && str_contains($listener, "'video_url'"), 'middle-platform videos must write back to ERP');
$assert(str_contains($stock, "'video_url'") && str_contains($shopPublisher, "'goods_video'"), 'ERP videos must publish to marketplace goods');
$assert(str_contains($phoneShop, 'erp_owned_workflow'), 'phone shop must not bypass ERP-owned listing workflow');
$assert(str_contains($stockController, "['my_task', 0]"), 'stock API must accept the current assignee filter');
$assert(str_contains($stockController, "['workflow_action', '']"), 'stock API must accept scoped workflow actions');
$assert(str_contains($stock, 'ErpListingFormContract::editableFields') && str_contains($stock, 'ErpListingFormContract::requiredFields'), 'stock save must filter hidden fields and validate the shared contract');
$assert(str_contains($stock, "'a.task_assignee_uid'") && str_contains($stock, "'a.task_stage_key'"), 'stock list must query real assigned tasks');
$assert(str_contains($pcStock, '只看我的待办') && str_contains($mobileStock, '我的待办'), 'PC and mobile inventory must expose assigned tasks');
$assert(str_contains($pcStock, '<ErpListingWorkspaceForm') && str_contains($mobileDetail, '<ErpListingWorkspaceForm'), 'PC and mobile stock actions must reuse the shared listing form');
$assert(str_contains($pcStock, "openFlow(row, 'price')") && str_contains($mobileDetail, "openProduct('price')"), 'sales pricing role must use the scoped role form on both clients');
$assert(str_contains($mobilePurchase, "purchaseEntryMode !== 'collaborative'") && str_contains($mobilePurchase, 'listingFieldRequired'), 'mobile purchase must hide team-only material fields and validate one-person rules');
$assert(str_contains($pcPurchase, 'purchaseOneStop') && str_contains($pcPurchase, 'purchaseItemPayload'), 'PC purchase must switch one-person/team forms and strip hidden listing fields');
$assert(str_contains($purchaseService, 'normalizeManualListingItems') && str_contains($purchaseService, 'ErpListingFormContract::hasValue'), 'purchase service must enforce one-person rules without blocking plugin inbound');
$assert(str_contains($pcForm, 'erpListingFieldVisible') && str_contains($mobileForm, 'erpListingFieldVisible'), 'shared forms must render from contract visibility');
$assert($pcStock === $packagedPcStock, 'packaged PC inventory view must mirror the admin source');
$assert($pcPurchase === $packagedPcPurchase, 'packaged PC purchase view must mirror the admin source');
$assert($pcForm === $packagedPcForm, 'packaged PC listing form must mirror the admin source');
$assert($pcFormHook === $packagedPcFormHook, 'packaged PC listing form helper must mirror the admin source');
$assert($pcFeedback === $packagedPcFeedback, 'packaged PC listing feedback helper must mirror the admin source');

echo "PASS listing workspace smoke\n";
