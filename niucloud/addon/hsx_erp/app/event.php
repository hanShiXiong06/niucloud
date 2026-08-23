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
        'ErpQuantityInventoryCapabilityRequested' => [
            'addon\hsx_erp\app\listener\ErpQuantityInventoryCapabilityRequested',
        ],
        'ErpQuantityInventoryConsumeRequested' => [
            'addon\hsx_erp\app\listener\ErpQuantityInventoryConsumeRequested',
        ],
        'ErpQuantityInventoryRestoreRequested' => [
            'addon\hsx_erp\app\listener\ErpQuantityInventoryRestoreRequested',
        ],
        'ErpQuantityInventoryAdjustRequested' => [
            'addon\hsx_erp\app\listener\ErpQuantityInventoryAdjustRequested',
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
        'ErpExternalSaleRecordedRequested' => [
            'addon\hsx_erp\app\listener\ErpExternalSaleRecordedRequested',
        ],
        'ErpExternalSaleRefundedRequested' => [
            'addon\hsx_erp\app\listener\ErpExternalSaleRefundedRequested',
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
        'DeviceAssetPriceCompleted' => [
            'addon\hsx_erp\app\listener\DeviceAssetPriceCompleted',
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
        'HsxAiIntegrationRegistryRequested' => [
            'addon\hsx_erp\app\listener\ai\AiIntegrationRegistryRequested',
        ],
        'HsxAiAdminAgentRegistryRequested' => [
            'addon\hsx_erp\app\listener\ai\AiAdminAgentRegistryRequested',
        ],
        'HsxAiToolRegistryRequested' => [
            'addon\hsx_erp\app\listener\ai\AiToolRegistryRequested',
        ],
        'HsxAiToolExecuteRequested' => [
            'addon\hsx_erp\app\listener\ai\AiToolExecuteRequested',
        ],
        'HsxAiDefaultToolArgumentsRequested' => [
            'addon\hsx_erp\app\listener\ai\AiDefaultToolArgumentsRequested',
        ],
        'HsxAiToolIntentRequested' => [
            'addon\hsx_erp\app\listener\ai\AiToolIntentRequested',
        ],
    ],
];
