<?php


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
        // menu_key 留空=所有人可见；页面内已按"我的角色→负责环节"过滤，无负责环节者看到空列表。
        // 如需菜单级按角色控制，把 menu_key 设为一个已分配给角色的权限key即可。
        'menu_key' => '',
        'sort' => 0,
        'page' => '/addon/hsx_recycle/pages/task/index',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_02.png'
    ],
    [
        'name' => '数据统计',
        'key' => 'hsx_recycle_stats',
        'group' => 'hsx_recycle',
        'menu_key' => '',
        'sort' => 1,
        'page' => '/addon/hsx_recycle/pages/stats/index',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_07.png'
    ],
    [
        'name' => '扫码处理',
        'key' => 'hsx_recycle_scan_check',
        'group' => 'hsx_recycle',
        'menu_key' => '',
        'sort' => 2,
        'page' => '/addon/hsx_recycle/pages/check/scan',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_02.png'
    ],
    [
        'name' => '订单管理',
        'key' => 'hsx_recycle_order',
        'group' => 'hsx_recycle',
        'menu_key' => '',
        'sort' => 3,
        'page' => '/addon/hsx_recycle/pages/order/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_03.png'
    ],
    [
        'name' => '代卖订单',
        'key' => 'hsx_recycle_consignment_order',
        'group' => 'hsx_recycle',
        'menu_key' => '',
        'sort' => 4,
        'page' => '/addon/hsx_recycle/pages/consignment/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_04.png'
    ],
    [
        'name' => '退回订单',
        'key' => 'hsx_recycle_return_order',
        'group' => 'hsx_recycle',
        'menu_key' => '',
        'sort' => 5,
        'page' => '/addon/hsx_recycle/pages/return/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_05.png'
    ],
    [
        'name' => '快递运单',
        'key' => 'hsx_recycle_express_order',
        'group' => 'hsx_recycle',
        'menu_key' => '',
        'sort' => 6,
        'page' => '/addon/hsx_recycle/pages/express/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_06.png'
    ],
    
];
