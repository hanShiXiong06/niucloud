<?php

return [
    'RECYCLE_LINK' => [
        'key' => 'hsx_recycle',
        'addon_title' => '回收报价系统',
        'title' => '产品报价',
        'child_list' => [
            [
                'name' => 'recycle_INDEX',
                'title' => '回收主页',
                'url' => '/addon/hsx_recycle/pages/index',
                'is_share' => 1,
                'action' => 'decorate'
            ], [
                'name' => 'DIY_RECYCLE_MEMBER_INDEX',
                'title' => '回收个人中心',
                'url' => '/addon/hsx_recycle/pages/member/index',
                'is_share' => 0,
                'action' => 'decorate'
            ], [
                'name' => 'recycle_ORDER',
                'title' => '立即下单',
                'url' => '/addon/hsx_recycle/pages/order/order',
                'is_share' => 1,
                'action' => ''
            ], [
                'name' => 'recycle_ORDER_LIST',
                'title' => '订单列表',
                'url' => '/addon/hsx_recycle/pages/order/list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'recycle_price',
                'title' => '回收报价单',
                'url' => '/addon/hsx_recycle/pages/price',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'recycle_return_order',
                'title' => '退货订单',
                'url' => '/addon/hsx_recycle/pages/return_order/list',
                'is_share' => 1,
                'action' => ''
            ], [
                'name' => 'payment_manager',
                'title' => '收款管理',
                'url' => '/addon/hsx_recycle/pages/payment/index',
                'is_share' => 1,
                'action' => ''
            ],
            // pages/return_order/list
            [
                'name' => 'recycle_return_order_list',
                'title' => '退货订单列表',
                'url' => '/addon/hsx_recycle/pages/return_order/list',
                'is_share' => 1,
                'action' => ''
            ],

        ]
    ]
];
