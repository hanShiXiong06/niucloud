<?php
return [
    'home_service_order_service' => [
        'key' => 'home_service_order_service',
        'receiver_type' => 1,
        'name' => '订单开始服务',
        'title' => '订单开始服务',
        'async' => true,
        'variable' =>[
            'technician' => '服务人员',
            'mobile' => '联系电话'
        ]
    ],
    'home_service_store_dispatch' => [
        'key' => 'home_service_store_dispatch',
        'receiver_type' => 1,
        'name' => '门店派单',
        'title' => '门店派单',
        'async' => true,
        'variable' =>[
            'store_name' => '门店名称',
            'order_name' => '服务项目',
            'taker_name' => '客户姓名',
            'taker_mobile' => '联系方式',
            'taker_full_address' => '客户地址',
        ]
    ],
    'home_service_refund' => [
        'key' => 'home_service_refund',
        'receiver_type' => 1,
        'name' => '订单退款',
        'title' => '订单退款',
        'async' => true,
        'variable' =>[
            'order_no' => '订单编号',
            'goods_name' => '服务名称',
            'money' => '退款金额'
        ]
    ],
];
