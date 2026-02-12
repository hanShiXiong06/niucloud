<?php
return [
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

    // 收款成功通知
    'recycle_order_pay' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_pay',
        'receiver_type' => 1,
        'name' => '收款成功通知',
        'title' => '收款成功通知',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'pay_amount' => '收款共计',
            'shop_name' => '门店',
            'pay_time' => '支付时间',
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
            'goods_name' => '商品名称',
            'order_amount' => '订单金额',
            'create_time' => '创建时间',
            'auditor' => '审核人员',
        ],
    ],
];
