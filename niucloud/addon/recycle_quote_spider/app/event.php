<?php

return [
    'bind' => [
    ],
    'listen' => [
        // DIY组件
        'DiyComponent' => [
            'addon\recycle_quote_spider\app\listener\diy\DiyComponentListener',
        ],
    ],
    'subscribe' => [
    ],
];
