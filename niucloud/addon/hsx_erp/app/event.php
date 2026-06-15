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
        // 资金账户列表（回收打款选"出账户头"用）— ERP 未装则无人应答
        'GetErpCapitalAccountList' => [
            'addon\hsx_erp\app\listener\CapitalAccountListProvider',
        ],
        // 记一笔资金流水（回收打款确认后出账扣余额）
        'RecordErpCapitalFlow' => [
            'addon\hsx_erp\app\listener\RecordCapitalFlowListener',
        ],
        // 设备下游同步健康度查询（回收设备列表判断是否需要显示「重新同步」）
        'GetErpDeviceSyncHealth' => [
            'addon\hsx_erp\app\listener\DeviceSyncHealthProvider',
        ],
        // 设备重新同步（重发卡住的 outbox 事件，补齐中台拍照等下游步骤）
        'ResyncErpDevice' => [
            'addon\hsx_erp\app\listener\DeviceResyncListener',
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
