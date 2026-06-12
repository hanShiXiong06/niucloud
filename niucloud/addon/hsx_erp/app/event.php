<?php

return [
    'bind' => [],
    'listen' => [
        'ErpDeviceInboundRequested' => [
            'addon\hsx_erp\app\listener\DeviceInboundRequestedListener',
        ],
        'GetErpDeviceSyncStatus' => [
            'addon\hsx_erp\app\listener\DeviceSyncStatusListener',
        ],
        'GetErpWarehouseList' => [
            'addon\hsx_erp\app\listener\WarehouseListProvider',
        ],
        'DeviceAssetPriceCompleted' => [
            'addon\hsx_erp\app\listener\DeviceAssetPriceCompletedListener',
        ],
        'RecycleDeviceCancelled' => [
            'addon\hsx_erp\app\listener\DeviceCancelledListener',
        ],
    ],
    'subscribe' => [],
];
