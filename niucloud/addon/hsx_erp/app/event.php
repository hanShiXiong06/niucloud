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
        'DeviceAssetPriceCompleted' => [
            'addon\hsx_erp\app\listener\DeviceAssetPriceCompletedListener',
        ],
    ],
    'subscribe' => [],
];
