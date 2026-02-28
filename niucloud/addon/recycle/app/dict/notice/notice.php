<?php
return [
    // 订单签收通知
    'recycle_order_sign' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_sign',
        'receiver_type' => 1,
        'name' => '订单已签收通知',
        'title' => '订单已签收通知',
        'async' => true,
        'variable' => [
            'order_no' => '订单号',
            'sign_time' => '签收时间',
            'remark' => '温馨提示',
        ],
    ],

    // 用户下单成功通知
    'recycle_order_add' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_add',
        'receiver_type' => 1,
        'name' => '用户下单成功通知',
        'title' => '用户下单成功通知',
        'async' => true,
        'variable' => [
            'order_no' => '订单号',
            'shop_name' => '下单门店',
            'address' => '收货地址',
            'create_time' => '下单时间',
        ],
    ],

    // 打款成功通知
    'recycle_order_pay' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_pay',
        'receiver_type' => 1,
        'name' => '打款成功通知',
        'title' => '打款成功通知',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'pay_type' => '收款方式',
            'pay_account' => '收款账号',
            'pay_result' => '打款结果',
        ],
    ],

    // 订单验收通知（待确认）
    'recycle_order_agree' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_agree',
        'receiver_type' => 1,
        'name' => '订单验收通知',
        'title' => '订单验收通知',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'time' => '时间',
            'status' => '状态',
        ],
    ],
];
