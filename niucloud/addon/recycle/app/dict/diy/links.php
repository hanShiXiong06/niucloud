<?php

return [
    'RECYCLE_LINK' => [
        'key' => 'recycle',
        'addon_title' => '产品报价查看',
        'title' => '产品报价',
        'child_list' => [
            [
                'name' => 'recycle_INDEX',
                'title' => '报价系统',
                'url' => '/addon/recycle/pages/index',
                'is_share' => 1,
                'action' => 'decorate'
            ], [
                'name' => 'recycle_ORDER',
                'title' => '回收下单',
                'url' => '/addon/recycle/pages/order/order',
                'is_share' => 1,
                'action' => ''
            ], [
                'name' => 'recycle_ORDER_LIST',
                'title' => '回收订单列表',
                'url' => '/addon/recycle/pages/order/list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'recycle_price',
                'title' => '回收报价',
                'url' => '/addon/recycle/pages/price',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'recycle_return_order',
                'title' => '退货订单',
                'url' => '/addon/recycle/pages/return_order/list',
                'is_share' => 1,
                'action' => ''
            ], [
                'name' => 'payment_manager',
                'title' => '收款管理',
                'url' => '/addon/recycle/pages/payment/index',
                'is_share' => 1,
                'action' => ''
            ]
        ]
    ]
];
