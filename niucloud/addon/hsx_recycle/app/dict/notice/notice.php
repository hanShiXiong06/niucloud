<?php
return [
    // 1. 订单签收通知
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
            'delivery_type' => '配送方式代码',
            'delivery_type_name' => '配送方式',
            'url' => '订单链接'
        ],
    ],

    // 2. 用户下单成功通知
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
            'delivery_type' => '配送方式代码',
            'delivery_type_name' => '配送方式',
            'url' => '订单链接'
        ],
    ],

    //3.  打款成功通知
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
            'pay_time' => '打款时间',
            'delivery_type' => '配送方式代码',
            'delivery_type_name' => '配送方式',
            'url' => '订单链接'
        ],
    ],

    // 5. 订单完成奖励通知
    'recycle_order_reward' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_reward',
        'receiver_type' => 1,
        'name' => '订单完成积分奖励通知',
        'title' => '订单完成积分奖励通知',
        'async' => true,
        'variable' => [
            'order_no'      => '订单编号',
            'complete_time' => '完成时间',
            'reward_point'  => '奖励积分',
            'remark'        => '温馨提示',
            'url'           => '积分页链接'
        ],
    ],

    // 4. 订单验收通知（待确认）
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
            'delivery_type' => '配送方式代码',
            'delivery_type_name' => '配送方式',
            'status' => '状态',
            'url' => '订单链接'
        ],
    ],
];
