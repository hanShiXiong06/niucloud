<?php
declare(strict_types=1);

return [
    'listen' => [
        'ErpDeviceInboundRequested' => [
            'addon\hsx_erp\app\listener\ErpDeviceInboundRequested',
        ],
        'GetErpDeviceSyncStatus' => [
            'addon\hsx_erp\app\listener\ErpDeviceSyncStatus',
        ],
        'GetErpDeviceSyncHealth' => [
            'addon\hsx_erp\app\listener\ErpDeviceSyncStatus',
        ],
        'ResyncErpDevice' => [
            'addon\hsx_erp\app\listener\ErpDeviceSyncStatus',
        ],
        'RefreshErpDeviceSnapshot' => [
            'addon\hsx_erp\app\listener\ErpDeviceSyncStatus',
        ],
        'ErpFinanceFactRequested' => [
            'addon\hsx_erp\app\listener\ErpFinanceFactRequested',
        ],
        'ErpSaleCreatedRequested' => [
            'addon\hsx_erp\app\listener\ErpSaleCreatedRequested',
        ],
        'HsxErpMarketplaceProviders' => [
            'addon\hsx_erp\app\listener\marketplace\PhoneShopMarketplaceProvider',
        ],
        'HsxErpPublishListing' => [
            'addon\hsx_erp\app\listener\marketplace\PhoneShopDirectListing',
        ],
        'HsxErpCatalogProducts' => [
            'addon\hsx_erp\app\listener\catalog\ErpCatalogProducts',
        ],
    ],
];
