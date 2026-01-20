<?php
return [
    'tk_jhkd_order_pay' => [
        'addon' => 'tk_jhkd',
        'key' => 'tk_jhkd_order_pay',
        'receiver_type' => 1,
        'name' => '订单支付成功',
        'title' => '订单支付成功后发送',
        'async' => true,
        'variable' => [
            'order_money' => '订单总额',
            'pay_time' => '支付时间',
            'create_time' => '支付时间',
            'body' => '订单内容',
            'order_no' => '订单编号',
        ],
    ],
    'tk_jhkd_order_pick' => [
        'addon' => 'tk_jhkd',
        'key' => 'tk_jhkd_order_pick',
        'receiver_type' => 1,
        'name' => '订单揽收通知',
        'title' => '订单揽收通知',
        'async' => true,
        'variable' => [
            'status_name' => '订单状态',
            'remark' => '温馨提示',
            'order_no' => '订单编号',
        ],
    ],
    'tk_jhkd_order_add' => [
        'addon' => 'tk_jhkd',
        'key' => 'tk_jhkd_order_add',
        'receiver_type' => 1,
        'name' => '补差价通知',
        'title' => '补差价通知',
        'async' => true,
        'variable' => [
            'amount' => '补差价金额',
            'remark' => '温馨提示',
            'order_no' => '订单编号',
        ],
    ],
    'tk_jhkd_order_sign' => [
        'addon' => 'tk_jhkd',
        'key' => 'tk_jhkd_order_sign',
        'receiver_type' => 1,
        'name' => '快件签收',
        'title' => '快件签收',
        'async' => true,
        'variable' => [
            'remark' => '温馨提示',
            'order_no' => '订单编号',
        ],
    ],
];
