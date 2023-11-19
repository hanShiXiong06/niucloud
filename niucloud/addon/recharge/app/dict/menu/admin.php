<?php

return
    [
        [
            'menu_name' => '会员充值',
            'menu_key' => 'recharge',
            'menu_type' => 0,
            'icon' => 'element-Help',
            'api_url' => '',
            'router_path' => 'recharge',
            'view_path' => '',
            'methods' => '',
            'sort' => 100,
            'status' => 1,
            'is_show' => 1,
            'children' => [
                [
                    'menu_name' => '充值订单',
                    'menu_key' => 'recharge_order',
                    'menu_type' => 0,
                    'icon' => 'iconfont-iconchongzhidingdan',
                    'api_url' => '',
                    'router_path' => 'order',
                    'view_path' => '',
                    'methods' => 'get',
                    'sort' => 100,
                    'status' => 1,
                    'is_show' => 1,
                    'children' => [
                        [
                            'menu_name' => '订单列表',
                            'menu_key' => 'recharge_order_list',
                            'menu_type' => 1,
                            'icon' => '',
                            'api_url' => 'recharge/order',
                            'router_path' => 'list',
                            'view_path' => 'order/list',
                            'methods' => 'get',
                            'sort' => 100,
                            'status' => 1,
                            'is_show' => 1,
                            'children' => [
                                [
                                    'menu_name' => '退款',
                                    'menu_key' => 'recharge_order_refund',
                                    'menu_type' => 2,
                                    'icon' => '',
                                    'api_url' => 'recharge/refund/<order_id>',
                                    'router_path' => '',
                                    'view_path' => '',
                                    'methods' => 'put',
                                    'sort' => 100,
                                    'status' => 1,
                                    'is_show' => 0,
                                ],
                            ]
                        ],
                        [
                            'menu_name' => '订单详情',
                            'menu_key' => 'recharge_order_detail',
                            'menu_type' => 1,
                            'icon' => '',
                            'api_url' => 'recharge/order/<order_id>',
                            'router_path' => 'detail',
                            'view_path' => 'order/detail',
                            'methods' => 'get',
                            'sort' => 90,
                            'status' => 1,
                            'is_show' => 0,
                        ],
                        [
                            'menu_name' => '退款记录',
                            'menu_key' => 'recharge_order_refund_list',
                            'menu_type' => 1,
                            'icon' => 'iconfont-icontuikuanjilu',
                            'api_url' => 'recharge/refund',
                            'router_path' => 'refund',
                            'view_path' => 'order/refund',
                            'methods' => 'get',
                            'sort' => 90,
                            'status' => 1,
                            'is_show' => 1,
                            'children' => [
                                [
                                    'menu_name' => '退款详情',
                                    'menu_key' => 'recharge_refund_detail',
                                    'menu_type' => 2,
                                    'icon' => '',
                                    'api_url' => 'recharge/refund/<refund_id>',
                                    'router_path' => '',
                                    'view_path' => '',
                                    'methods' => 'get',
                                    'sort' => 100,
                                    'status' => 1,
                                    'is_show' => 0,
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
