<?php

return [
    [
        'key' => 'fast_pay_order_close',
        'name' => '商家快速收款超时关闭订单',
        'desc' => '商家快速收款超时关闭订单',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\fast_pay\app\job\order\OrderClose',
        'function' => ''
    ],
];
