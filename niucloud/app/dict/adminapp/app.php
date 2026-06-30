<?php


return [
    [
        'name' => '商家管理',
        'key' => 'site_manage',
        'group' => 'site',
        'menu_key' => 'setting_system_basic_info',
        'sort' => 1,
        'page' => '/app/pages/site/setting',
        'icon' => '/addon/home_service/admin/icon-1.png'
    ],
    [
        'name' => '商家信息',
        'key' => 'site_detail',
        'group' => 'site',
        'menu_key' => 'shop_setting_index',
        'sort' => 2,
        'page' => '/app/pages/site/info',
        'icon' => '/addon/home_service/admin/icon-2.png'
    ],
//    [
//        'name' => '运费模板',
//        'key' => 'delivery_template',
//        'group' => 'delivery',
//        'menu_key' => 'delivery_shipping_template',
//        'sort' => 1,
//        'page' => '',
//        'icon' => '/addon/mall/site/menu/delivery_template.png'
//    ],
   [
       'name' => '地址库',
       'key' => 'delivery_address',
       'group' => 'delivery',
       'menu_key' => 'site_address',
       'sort' => 2,
       'page' => '/app/pages/site_address/list',
       'icon' => '/addon/home_service/admin/icon-3.png'
   ],
    [
        'name' => '客户管理',
        'key' => 'member_manage',
        'group' => 'member',
        'menu_key' => 'shop_member_list',
        'sort' => 1,
        'page' => '/app/pages/member/index',
        'icon' => '/addon/home_service/admin/icon-4.png'
    ],
//    [
//        'name' => '客户标签',
//        'key' => 'member_label',
//        'group' => 'member',
//        'menu_key' => 'shop_member_label',
//        'sort' => 2,
//        'page' => '',
//        'icon' => '/addon/mall/site/menu/member_label.png'
//    ],
//    [
//        'name' => '客户看板',
//        'key' => 'member_stat',
//        'group' => 'member',
//        'menu_key' => '',
//        'sort' => 3,
//        'page' => '',
//        'icon' => '/addon/mall/site/menu/member_stat.png'
//    ],
//    [
//        'name' => '客户订单',
//        "key" => "mall_member_order",
//        'group' => 'member',
//        'menu_key' => '',
//        'sort' => 4,
//        'page' => '',
//        'icon' => '/addon/mall/site/menu/mall_member_order.png'
//    ],
    [
        'name' => '核销',
        'key' => 'verify',
        'group' => 'site',
        'menu_key' => '',
        'sort' => 3,
        'page' => '/app/pages/verify/index',
        'icon' => '/addon/home_service/admin/icon-5.png'
    ],
    [
        'name' => '核销记录',
        'key' => 'verify_record',
        'group' => 'site',
        'menu_key' => '',
        'sort' => 4,
        'page' => '/app/pages/verify/record',
        'icon' => '/addon/home_service/admin/link_icon-1.png'
    ],

    // [
    //     'name' => '提现',
    //     'key' => 'cash_out',
    //     'group' => 'finance',
    //     'menu_key' => '',
    //     'sort' => 5,
    //     'page' => '/app/pages/account/cash_out',
    //     'icon' => '/addon/mall/site/menu/cash_out.png'
    // ],
    // [
    //     'name' => '提现方式',
    //     'key' => 'transfer_type',
    //     'group' => 'finance',
    //     'menu_key' => '',
    //     'sort' => 6,
    //     'page' => '/app/pages/account/transfer_type',
    //     'icon' => '/addon/mall/site/menu/transfer_type.png'
    // ],

    // [
    //     'name' => '提现管理',
    //     'key' => 'cash_out_record',
    //     'group' => 'finance',
    //     'menu_key' => '',
    //     'sort' => 7,
    //     'page' => '/app/pages/account/cash_out_record',
    //     'icon' => '/addon/mall/site/menu/cash_out_record.png'
    // ],
    // [
    //     'name' => '商城看板',
    //     'key' => 'mall_stat',
    //     'group' => 'stats',
    //     'menu_key' => '',
    //     'sort' => 7,
    //     'page' => '/addon/mall/pages/account/bill',
    //     'icon' => '/addon/mall/site/menu/shop.png'
    // ],
    // [
    //     'name' => '资产概况',
    //     'key' => 'account',
    //     'group' => 'stats',
    //     'menu_key' => '',
    //     'sort' => 8,
    //     'page' => '/app/pages/account/index',
    //     'icon' => '/addon/mall/site/menu/account_index.png'
    // ],
    [
        'name' => '图片空间',
        'key' => 'album',
        'group' => 'site',
        'menu_key' => 'attachment',
        'sort' => 4,
        'page' => '/app/pages/sys/album?att_type=image',
        'icon' => '/addon/home_service/admin/link_icon-2.png'
    ],
    [
        'name' => '视频空间',
        'key' => 'album',
        'group' => 'site',
        'menu_key' => 'attachment',
        'sort' => 5,
        'page' => '/app/pages/sys/album?att_type=video',
        'icon' => '/addon/home_service/admin/link_icon-3.png'
    ],
    // [
    //     'name' => '新闻资讯',
    //     'key' => 'article',
    //     'group' => 'site',
    //     'menu_key' => '',
    //     'sort' => 49,
    //     // 'page' => '/app/pages/article/list',
    //     'page' => '/addon/mall/pages/goods/album?att_type=image',
    //     'icon' => '/addon/home_service/admin/link_icon-4.png'
    //     // 'icon' => '/addon/mall/site/menu/article.png'
    // ]
];
