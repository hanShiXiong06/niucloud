<?php

return [
    'PHONE_SHOP_BASE_LINK' => [
        'title' => get_lang('dict_diy.shop_link'),
        'type' => 'folder', // 类型，folder 表示文件夹，link 表示链接
        'child_list' => [
            [
                'name' => 'PHONE_SHOP_LINK',
                'title' => get_lang('dict_diy.shop_link_basic'),
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
                        'name' => 'PHONE_SHOP_GOODS_LIST',
                        'title' => get_lang('dict_diy.shop_link_goods_list'),
                        'url' => '/addon/phone_shop/pages/goods/list',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_GOODS_RANK',
                        'title' => get_lang('dict_diy.shop_link_goods_rank'),
                        'url' => '/addon/phone_shop/pages/goods/rank',
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
                        'is_share' => 0,
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
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_MY_GOODS_COLLECT',
                        'title' => get_lang('dict_diy.shop_link_my_goods_collect'),
                        'url' => '/addon/phone_shop/pages/goods/collect',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_MY_GOODS_BROWSE',
                        'title' => get_lang('dict_diy.shop_link_my_goods_browse'),
                        'url' => '/addon/phone_shop/pages/goods/browse',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_ORDER_LIST',
                        'title' => get_lang('dict_diy.shop_link_order_list'),
                        'url' => '/addon/phone_shop/pages/order/list',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_REFUND_LIST',
                        'title' => get_lang('dict_diy.shop_link_order_refund_list'),
                        'url' => '/addon/phone_shop/pages/refund/list',
                        'is_share' => 0,
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
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_DISCOUNT_LIST',
                        'title' => get_lang('dict_diy.shop_link_discount_list'),
                        'url' => '/addon/phone_shop/pages/discount/list',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_NEWCOMER_LIST',
                        'title' => get_lang('dict_diy.shop_link_newcomer_list'),
                        'url' => '/addon/phone_shop/pages/newcomer/list',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'PHONE_SHOP_INVOICE_LIST',
                        'title' => get_lang('dict_diy.shop_link_invoice_list'),
                        'url' => '/addon/phone_shop/pages/invoice/list',
                        'is_share' => 1,
                        'action' => ''
                    ],
                ]
            ],
            [
                'name' => 'PHONE_SHOP_GOODS_SELECT',
                'title' => get_lang('dict_diy.shop_link_goods_select'),
                'component' => '/src/addon/phone_shop/views/goods/components/goods-select-content.vue'
            ],
            [
                'name' => 'PHONE_SHOP_GOODS_CATEGORY_SELECT',
                'title' => get_lang('dict_diy.shop_link_goods_category_select'),
                'component' => '/src/addon/phone_shop/views/goods/components/goods-category-select-content.vue'
            ],
            [
                'name' => 'PHONE_SHOP_COUPON_SELECT',
                'title' => get_lang('dict_diy.shop_link_coupon_select'),
                'component' => '/src/addon/phone_shop/views/marketing/coupon/components/coupon-select-content.vue'
            ],
            [
                'name' => 'PHONE_SHOP_POINT_EXCHANGE_SELECT',
                'title' => get_lang('dict_diy.shop_link_point_exchange_select'),
                'component' => '/src/addon/phone_shop/views/marketing/exchange/components/goods-select-content.vue'
            ]
        ],
    ],
];
