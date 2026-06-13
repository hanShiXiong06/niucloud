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
        // 财务中心: 消费 回收(应付)/销售(应收) 产生的事实, 幂等落库
        'FinancePayableCreated' => [
            'addon\hsx_erp\app\listener\PayableCreatedListener',
        ],
        'FinanceReceivableCreated' => [
            'addon\hsx_erp\app\listener\ReceivableCreatedListener',
        ],
    ],
    'subscribe' => [],
];
