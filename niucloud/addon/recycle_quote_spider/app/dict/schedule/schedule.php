<?php
declare(strict_types=1);

return [
    [
        'key' => 'recycle_quote_spider_auto_sync',
        'name' => '回收报价爬虫自动同步',
        'desc' => '按报价源配置的同步间隔抓取报价数据',
        'time' => [
            'type' => 'min',
            'min' => 60,
        ],
        'class' => 'addon\recycle_quote_spider\app\job\QuoteSpiderAutoSync',
        'function' => 'doJob',
    ],
];
