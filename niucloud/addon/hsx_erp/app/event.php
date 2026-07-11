<?php
declare(strict_types=1);

return [
    'listen' => [
        'ErpDeviceInboundRequested' => [
            'addon\hsx_erp\app\listener\ErpDeviceInboundRequested',
        ],
        'ErpFinanceFactRequested' => [
            'addon\hsx_erp\app\listener\ErpFinanceFactRequested',
        ],
        'ErpSaleCreatedRequested' => [
            'addon\hsx_erp\app\listener\ErpSaleCreatedRequested',
        ],
    ],
];
