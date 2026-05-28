<?php


return [
    [
        'name' => '回收主页',
        'key' => 'recycle_home',
        'group' => 'recycle',
        'menu_key' => '',
        'sort' => 1,
        'page' => '/addon/recycle/pages/index',
        'icon' => '/addon/recycle/site-tabbar/icon_01.png'
    ],
    [
        'name' => '扫码处理',
        'key' => 'recycle_scan_check',
        'group' => 'recycle',
        'menu_key' => '',
        'sort' => 2,
        'page' => '/addon/recycle/pages/check/scan',
        'icon' => '/addon/recycle/site-tabbar/icon_02.png'
    ],
    [
        'name' => '订单管理',
        'key' => 'recycle_order',
        'group' => 'recycle',
        'menu_key' => '',
        'sort' => 3,
        'page' => '/addon/recycle/pages/order/list',
        'icon' => '/addon/recycle/site-tabbar/icon_03.png'
    ],
    [
        'name' => '代卖订单',
        'key' => 'recycle_consignment_order',
        'group' => 'recycle',
        'menu_key' => '',
        'sort' => 4,
        'page' => '/addon/recycle/pages/consignment/list',
        'icon' => '/addon/recycle/site-tabbar/icon_04.png'
    ],
    [
        'name' => '退回订单',
        'key' => 'recycle_return_order',
        'group' => 'recycle',
        'menu_key' => '',
        'sort' => 5,
        'page' => '/addon/recycle/pages/return/list',
        'icon' => '/addon/recycle/site-tabbar/icon_05.png'
    ],
    [
        'name' => '快递运单',
        'key' => 'recycle_express_order',
        'group' => 'recycle',
        'menu_key' => '',
        'sort' => 6,
        'page' => '/addon/recycle/pages/express/list',
        'icon' => '/addon/recycle/site-tabbar/icon_06.png'
    ],
];
