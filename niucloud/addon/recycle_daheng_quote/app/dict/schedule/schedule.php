<?php
declare(strict_types=1);

return [
    [
        'key' => 'recycle_daheng_quote_auto_sync',
        'name' => 'DH速收报价自动同步',
        'desc' => '按报价单配置的同步间隔抓取并导入报价数据',
        'time' => [
            'type' => 'min',
            'min' => 60,
        ],
        'class' => 'addon\recycle_daheng_quote\app\job\DahengQuoteAutoSync',
        'function' => 'doJob',
    ],
];
