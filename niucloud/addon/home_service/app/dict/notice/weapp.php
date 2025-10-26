<?php
return [
    //开始服务
    'home_service_order_service' => [
        'tid' => '31127',
        'content' => [
            ['服务人员', '{technician}', 'thing14'],
            ['联系电话', '{mobile}', 'phone_number31'],
        ],
        'kid_list' => [14,31],
        'scene_desc' => '服务进度提示'
    ],
    //门店派单
    'home_service_store_dispatch' => [
        'tid' => '31397',
        'content' => [
            ['门店名称', '{store_name}', 'thing16'],
            ['服务项目', '{order_name}', 'thing8'],
            ['客户姓名', '{taker_name}', 'thing3'],
            ['联系方式', '{taker_mobile}', 'phone_number4'],
            ['客户地址', '{taker_full_address}', 'thing5'],
        ],
        'kid_list' => [16,8,3,4,5],
        'scene_desc' => '门店派单订单通知'
    ],
    //退款成功
    'home_service_refund' => [
        'tid' => '30805',
        'content' => [
            ['订单编号', '{order_no}', 'character_string3'],
            ['服务名称', '{goods_name}', 'thing24'],
            ['退款金额', '{money}', 'amount1'],
        ],
        'kid_list' => [3,24,1],
        'scene_desc' => '门店派单订单通知'
    ]
];
