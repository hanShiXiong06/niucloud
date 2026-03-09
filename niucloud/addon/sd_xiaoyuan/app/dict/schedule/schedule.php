<?php

return [
    [
        'key' => 'sd_xiaoyuan_order_auto_cancel',
        'name' => '校园帮订单自动取消',
        'desc' => '超时未接单自动取消',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\sd_xiaoyuan\app\job\OrderAutoCancel',
        'function' => 'fire'
    ],
    [
        'key' => 'sd_xiaoyuan_order_accept_timeout',
        'name' => '校园帮接单超时处理',
        'desc' => '接单后超时未完成自动取消',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\sd_xiaoyuan\app\job\OrderAcceptTimeout',
        'function' => 'fire'
    ]
];
