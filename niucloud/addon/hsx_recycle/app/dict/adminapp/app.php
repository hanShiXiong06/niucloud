<?php


// key 是移动端入口标识（快捷入口配置使用），menu_key 对应后台 site.php 的权限标识。
// 两者不需要同名；不要通过修改 key 来关联权限，以免已有快捷入口失效。
return [
    // [
    //     'name' => '回收主页',
    //     'key' => 'hsx_recycle_home',
    //     'group' => 'hsx_recycle',
    //     'menu_key' => '',
    //     'sort' => 1,
    //     'page' => '/addon/hsx_recycle/pages/index',
    //     'icon' => '/addon/hsx_recycle/site-tabbar/icon_01.png'
    // ],
    [
        'name' => '我的任务',
        'key' => 'hsx_recycle_my_task',
        'group' => 'hsx_recycle',
        // 入口权限与后台一致；任务数据仍按角色负责的环节过滤。
        'menu_key' => 'recycle_my_task',
        'sort' => 0,
        'page' => '/addon/hsx_recycle/pages/task/index',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_01.png'
    ],
    [
        'name' => '数据统计',
        'key' => 'hsx_recycle_stats',
        'group' => 'hsx_recycle',
        'menu_key' => 'recycle_workbench',
        'sort' => 1,
        'page' => '/addon/hsx_recycle/pages/stats/index',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_07.png'
    ],
    [
        'name' => '扫码处理',
        'key' => 'hsx_recycle_scan_check',
        'group' => 'hsx_recycle',
        // 扫码是订单/设备查询入口，具体业务操作继续校验各自的接口权限。
        'menu_key' => 'recycle_order_list',
        'sort' => 2,
        'page' => '/addon/hsx_recycle/pages/check/scan',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_02.png'
    ],
    [
        'name' => '订单管理',
        'key' => 'hsx_recycle_order',
        'group' => 'hsx_recycle',
        'menu_key' => 'recycle_order_list',
        'sort' => 3,
        'page' => '/addon/hsx_recycle/pages/order/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_03.png'
    ],
    [
        'name' => '代卖订单',
        'key' => 'hsx_recycle_consignment_order',
        'group' => 'hsx_recycle',
        'menu_key' => 'recycle_consignment_order_list',
        'sort' => 4,
        'page' => '/addon/hsx_recycle/pages/consignment/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_04.png'
    ],
    [
        'name' => '退回订单',
        'key' => 'hsx_recycle_return_order',
        'group' => 'hsx_recycle',
        'menu_key' => 'recycle_return_order_list',
        'sort' => 5,
        'page' => '/addon/hsx_recycle/pages/return/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_05.png'
    ],
    [
        'name' => '快递运单',
        'key' => 'hsx_recycle_express_order',
        'group' => 'hsx_recycle',
        'menu_key' => 'express_order_record_list',
        'sort' => 6,
        'page' => '/addon/hsx_recycle/pages/express/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_06.png'
    ],

   

];
