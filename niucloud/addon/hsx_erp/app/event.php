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
        // 查往来账(谁欠谁多少) — 回收"打款即折账"查询用
        'GetFinanceCounterpartyBalance' => [
            'addon\hsx_erp\app\listener\FinanceCounterpartyBalanceProvider',
        ],
        // 折账结算指令 — 回收打款确认折账时触发, 经唯一结算服务执行
        'RequestFinanceSettlement' => [
            'addon\hsx_erp\app\listener\FinanceSettlementRequestListener',
        ],
    ],
    'subscribe' => [],
];
