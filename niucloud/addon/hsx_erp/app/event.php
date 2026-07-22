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
        'ErpPartyResolveRequested' => [
            'addon\hsx_erp\app\listener\ErpPartyResolveRequested',
        ],
        'ErpCapitalAccountOptionsRequested' => [
            'addon\hsx_erp\app\listener\ErpCapitalAccountOptionsRequested',
        ],
        'ErpWarehouseOptionsRequested' => [
            'addon\hsx_erp\app\listener\ErpWarehouseOptionsRequested',
        ],
        'ErpFinanceSettlementRequested' => [
            'addon\hsx_erp\app\listener\ErpFinanceSettlementRequested',
        ],
        'ErpSourcePayableSettlementRequested' => [
            'addon\hsx_erp\app\listener\ErpSourcePayableSettlementRequested',
        ],
        'ErpFinanceFactVoidRequested' => [
            'addon\hsx_erp\app\listener\ErpFinanceFactVoidRequested',
        ],
        'ErpSaleCreatedRequested' => [
            'addon\hsx_erp\app\listener\ErpSaleCreatedRequested',
        ],
        'HsxErpListingMaterialPolicy' => [
            'addon\hsx_erp\app\listener\marketplace\ListingMaterialPolicy',
        ],
        'HsxErpChannelMappingResolve' => [
            'addon\hsx_erp\app\listener\marketplace\ChannelMappingResolver',
        ],
        'PhoneShopListingMaterialCompleted' => [
            'addon\hsx_erp\app\listener\marketplace\PhoneShopListingMaterialCompleted',
        ],
        'HsxErpCatalogProducts' => [
            'addon\hsx_erp\app\listener\catalog\ErpCatalogProducts',
        ],
        'HsxBusinessTaskValidate' => [
            'addon\hsx_erp\app\listener\BusinessTaskValidate',
        ],
        'HsxBusinessReportMetricsRequested' => [
            'addon\hsx_erp\app\listener\BusinessReportMetricsProvider',
        ],
    ],
];
