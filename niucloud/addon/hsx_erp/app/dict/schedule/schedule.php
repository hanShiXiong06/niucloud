<?php
declare(strict_types=1);

return [
    [
        'key' => 'hsx_erp_domain_event_retry',
        'name' => 'ERP跨插件事件补偿',
        'desc' => '重试销售、退货、拍照定价等跨插件发件箱事件',
        'time' => [
            'type' => 'min',
            'min' => 1,
        ],
        'class' => 'addon\hsx_erp\app\job\schedule\DomainEventRetry',
        'function' => 'doJob',
    ],
];
