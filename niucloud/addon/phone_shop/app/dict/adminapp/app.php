<?php
// 手机站点端 工作台 应用入口（menu_key 留空表示始终显示）
return [
   
    [
        'name'     => '商城运营',
        'key'      => 'phone_shop_device_intake',
        'group'    => 'phone_shop_goods',
        'menu_key' => 'phone_shop_device_intake',
        'sort'     => 1,
        'page'     => '/addon/phone_shop/pages/intake/list',
        'icon'     => '/addon/phone_shop/site-app/goods.png'
    ],
    [
        'name'     => '商品管理',
        'key'      => 'phone_shop_goods_list',
        'group'    => 'phone_shop_goods',
        'menu_key' => '',
        'sort'     => 2,
        'page'     => '/addon/phone_shop/pages/goods/list',
        'icon'     => '/addon/phone_shop/site-app/goods.png'
    ],
     [
        'name'     => '线下订单',
        'key'      => 'phone_shop_offline_order',
        'group'    => 'phone_shop_goods',
        'menu_key' => 'shop_order_offline_pending',
        'sort'     => 3,
        'page'     => '/addon/phone_shop/pages/order/list',
        'icon'     => '/addon/phone_shop/site-app/goods.png'
    ],
    [
        'name'     => '商品分类',
        'key'      => 'phone_shop_goods_category',
        'group'    => 'phone_shop_goods',
        'menu_key' => '',
        'sort'     => 4,
        'page'     => '/addon/phone_shop/pages/goods/category',
        'icon'     => '/addon/phone_shop/site-app/category.png'
    ],
    [
        'name'     => '会员管理',
        'key'      => 'phone_shop_member_list',
        'group'    => 'phone_shop_member',
        'menu_key' => '',
        'sort'     => 1,
        'page'     => '/app/pages/member/index',   // 链到框架现成的会员列表页
        'icon'     => '/addon/home_service/admin/icon-4.png'
    ]
];
