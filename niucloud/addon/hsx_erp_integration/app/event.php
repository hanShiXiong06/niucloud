<?php

// ERP集成事件订阅
return [
    'listen' => [
        'HsxErpLocalOutboxStored' => ['addon\hsx_erp_integration\app\listener\OriginalDocumentRecorded'],
        'HsxErpPurchaseActions' => ['addon\hsx_erp_integration\app\listener\OriginalPurchaseActions'],
        'HsxErpSaleActions' => ['addon\hsx_erp_integration\app\listener\OriginalPurchaseActions'],
        'HsxErpFinanceDocumentLinks' => ['addon\hsx_erp_integration\app\listener\OriginalFinanceLinks'],
        'ErpDomainEvent' => [
            'addon\hsx_erp_integration\app\listener\OriginalDocumentRecorded',
        ],
        // 监听ERP采购单创建
        'ErpPurchaseCreated' => [
            'addon\hsx_erp_integration\app\listener\ErpPurchaseCreated',
        ],

        // 监听ERP销售单创建（预留）
        'ErpSaleCreated' => [
            // 'addon\hsx_erp_integration\app\listener\ErpSaleCreated',
        ],

        // 监听ERP付款（预留）
        'ErpPaymentCreated' => [
            // 'addon\hsx_erp_integration\app\listener\ErpPaymentCreated',
        ],
    ],

    'subscribe' => [
        // 事件订阅器（可选）
    ],
];
