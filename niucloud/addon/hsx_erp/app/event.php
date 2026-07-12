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
        'HsxErpCategoryChanged' => [
            'addon\hsx_erp\app\listener\ErpCategoryChanged',
        ],
        'HsxErpCategoryProviders' => [
            'addon\hsx_erp\app\listener\category\PhoneShopCategoryProvider',
        ],
        'HsxErpCategoryPull' => [
            'addon\hsx_erp\app\listener\category\PhoneShopCategoryPull',
        ],
        'HsxErpCategoryPush' => [
            'addon\hsx_erp\app\listener\category\PhoneShopCategoryPush',
        ],
    ],
];
