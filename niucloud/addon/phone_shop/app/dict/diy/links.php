<?php

return [
    'PHONE_SHOP_LINK' => [
        'key' => 'phone_shop',
        'addon_title' => '二手商城',
        'title' => '二手商城',
        'child_list' => [
            [
                'name' => 'PHONE_SHOP_INDEX',
                'title' => get_lang('dict_diy.shop_link_index'),
                'url' => '/addon/phone_shop/pages/index',
                'is_share' => 1,
                'action' => 'decorate'
            ],
            [
                'name' => 'PHONE_SHOP_GOODS_CATEGORY',
                'title' => get_lang('dict_diy.shop_link_goods_category'),
                'url' => '/addon/phone_shop/pages/goods/category',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_GOODS_OPERATION',
                'title' => get_lang('dict_diy.shop_link_operation'),
                'url' => '/addon/phone_shop/pages/goods/operation',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_GOODS_LIST',
                'title' => get_lang('dict_diy.shop_link_goods_list'),
                'url' => '/addon/phone_shop/pages/goods/list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_GOODS_SEARCH',
                'title' => get_lang('dict_diy.shop_link_goods_search'),
                'url' => '/addon/phone_shop/pages/goods/search',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_GOODS_CART',
                'title' => get_lang('dict_diy.shop_link_goods_cart'),
                'url' => '/addon/phone_shop/pages/goods/cart',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_COUPON_LIST',
                'title' => get_lang('dict_diy.shop_link_coupon_list'),
                'url' => '/addon/phone_shop/pages/coupon/list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_MEMBER_INDEX',
                'title' => get_lang('dict_diy.page_link_member_index'),
                'url' => '/addon/phone_shop/pages/member/index',
                'is_share' => 1,
                'action' => 'decorate'
            ],
            [
                'name' => 'PHONE_SHOP_MY_COUPON',
                'title' => get_lang('dict_diy.shop_link_my_coupon'),
                'url' => '/addon/phone_shop/pages/member/my_coupon',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_ORDER_LIST',
                'title' => get_lang('dict_diy.shop_link_order_list'),
                'url' => '/addon/phone_shop/pages/order/list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_REFUND_LIST',
                'title' => get_lang('dict_diy.shop_link_order_refund_list'),
                'url' => '/addon/phone_shop/pages/refund/list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_POINT_INDEX',
                'title' => get_lang('dict_diy.shop_link_point_index'),
                'url' => '/addon/phone_shop/pages/point/index',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_POINT_LIST',
                'title' => get_lang('dict_diy.shop_link_point_list'),
                'url' => '/addon/phone_shop/pages/point/list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_POINT_ORDER_LIST',
                'title' => get_lang('dict_diy.shop_link_point_order_list'),
                'url' => '/addon/phone_shop/pages/point/order_list',
                'is_share' => 1,
                'action' => ''
            ],
            [
                'name' => 'PHONE_SHOP_DISCOUNT_LIST',
                'title' => get_lang('dict_diy.shop_link_discount_list'),
                'url' => '/addon/phone_shop/pages/discount/list',
                'is_share' => 1,
                'action' => ''
            ],[
                'name' => 'PHONE_SHOP_DISCOUNT_NEW_ARRIVALS',
                'title' => '每日上新',
                'url' => '/addon/phone_shop/pages/goods/new_arrivals',
                'is_share' => 1,
                'action' => ''
            ],[
                'name' => 'PHONE_SHOP_ADD',
                'title' => '添加商品',
                'url' => '/addon/phone_shop/pages/manager/add_goods',
                'is_share' => 0,
                'action' => ''
            ],
        ]
    ],
];
