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
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_01.png'
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

    // ── ERP 管理 ────────────────────────────────────────────────────────────
    [
        'name' => '采购管理',
        'key' => 'hsx_erp_purchase',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_purchase',
        'sort' => 10,
        'page' => '/addon/hsx_erp/pages/purchase/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_03.png'
    ],
    [
        'name' => '销售出库',
        'key' => 'hsx_erp_sale',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_sale',
        'sort' => 11,
        'page' => '/addon/hsx_erp/pages/sale/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_04.png'
    ],
    [
        'name' => '应付款',
        'key' => 'hsx_erp_payable',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_payable',
        'sort' => 12,
        'page' => '/addon/hsx_erp/pages/payable/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_05.png'
    ],
    [
        'name' => '应收款',
        'key' => 'hsx_erp_receivable',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_receivable',
        'sort' => 13,
        'page' => '/addon/hsx_erp/pages/receivable/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_06.png'
    ],
    [
        'name' => '库存设备',
        'key' => 'hsx_erp_stock',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_stock',
        'sort' => 14,
        'page' => '/addon/hsx_erp/pages/stock/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_01.png'
    ],
    [
        'name' => '采购退货',
        'key' => 'hsx_erp_purchase_return',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_purchase_return',
        'sort' => 15,
        'page' => '/addon/hsx_erp/pages/purchase_return/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_02.png'
    ],
    [
        'name' => '销售退货',
        'key' => 'hsx_erp_sale_return',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_sale_return',
        'sort' => 16,
        'page' => '/addon/hsx_erp/pages/sale_return/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_05.png'
    ],
    [
        'name' => '成本调整',
        'key' => 'hsx_erp_cost_adjust',
        'group' => 'hsx_erp',
        'menu_key' => '',
        'sort' => 17,
        'page' => '/addon/hsx_erp/pages/cost_adjust/list',
        'icon' => '/addon/hsx_recycle/site-tabbar/icon_07.png'
    ],

];
