<?php
return [
    'recycle_order_sign' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_sign',
        'receiver_type' => 1,
        'name' => '快件签收',
        'title' => '快件签收',
        'async' => true,
        'variable' => [
            'remark' => '温馨提示',
            'order_no' => '订单编号',
        ],
    ],
    'recycle_order_add' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_add',
        'receiver_type' => 1,
        'name' => '回收订单下单',
        'title' => '回收订单下单',
        'async' => true,
        'variable' => [ 
            'order_no' => '订单编号',
            'status_name' => '订单状态',
            'create_time' => '创建时间',
            'address' => '邮寄地址',
            'remark' => '温馨提示',
        ],
    ],
    'recycle_order_agree' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_agree',
        'receiver_type' => 1,
        'name' => '回收订单同意',
        'title' => '回收订单同意',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'time' => '时间',
            'status' => '状态',
        ],
    ],
    'recycle_order_pay' => [
        'addon' => 'recycle',
        'key' => 'recycle_order_pay',
        'receiver_type' => 1,
        'name' => '回收订单打款',
        'title' => '回收订单打款',
        'async' => true,
        'variable' => [
            'goods_name' => '商品名',
            'order_no' => '订单编号',
            'pay_type' => '收款方式',
            'pay_account' => '收款账号',
            'pay_result' => '打款结果',
        ],
    ],
    
];
