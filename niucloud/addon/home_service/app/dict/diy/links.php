<?php

return [
    'HOME_SERVICE_BASE_LINK' => [
        'title' => get_lang('dict_diy.home_service_link'),
        'type' => 'folder', // 类型，folder 表示文件夹，link 表示链接
        'child_list' => [
            [
                'name' => 'HOME_SERVICE_LINK',
                'title' => get_lang('dict_diy.home_service_title'),
                'child_list' => [
                    [
                        'name' => 'HOME_SERVICE_INDEX',
                        'title' => get_lang('dict_diy.home_service_index'),
                        'url' => '/addon/home_service/user/pages/index',
                        'is_share' => 1,
                        'action' => 'decorate'
                    ],
                    [
                        'name' => 'HOME_SERVICE_CATEGORY',
                        'title' => get_lang('dict_diy.home_service_category'),
                        'url' => '/addon/home_service/user/pages/goods/category',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_ORDER_LIST',
                        'title' => get_lang('dict_diy.home_service_link_order_list'),
                        'url' => '/addon/home_service/user/pages/order/list',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_MEMBER_INDEX',
                        'title' => get_lang('dict_diy.home_service_link_member_index'),
                        'url' => '/addon/home_service/user/pages/member/index',
                        'is_share' => 1,
                        'action' => 'decorate'
                    ],
                    [
                        'name' => 'HOME_SERVICE_COUPON',
                        'title' => get_lang('dict_diy.home_service_link_coupon'),
                        'url' => '/addon/home_service/user/pages/coupon/coupon',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_GOODS_SEARCH',
                        'title' => get_lang('dict_diy.home_service_link_goods_search'),
                        'url' => '/addon/home_service/user/pages/goods/list',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_CARD_LIST',
                        'title' => get_lang('dict_diy.home_service_link_card_list'),
                        'url' => '/addon/home_service/user/pages/card/list',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_TECHNICIAN_ENTRANCE',
                        'title' => get_lang('dict_diy.home_service_link_technician_entrance'),
                        'url' => '/addon/home_service/technician/pages/auth/login',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_STORE_ENTRANCE',
                        'title' => get_lang('dict_diy.home_service_link_store_entrance'),
                        'url' => '/addon/home_service/store/pages/auth/login',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_SETTLE_STORE',
                        'title' => get_lang('dict_diy.home_service_link_settle_store'),
                        'url' => '/addon/home_service/user/pages/settle/store',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'HOME_SERVICE_SETTLE_TECHNICIAN',
                        'title' => get_lang('dict_diy.home_service_link_settle_technician'),
                        'url' => '/addon/home_service/user/pages/settle/technician',
                        'is_share' => 0,
                        'action' => ''
                    ],
                ]
            ],
            [
                'name' => 'HOME_SERVICE_GOODS_SELECT',
                'title' => get_lang('dict_diy.home_service_goods_select'),
                'component' => '/src/addon/home_service/views/diy/components/link-select-goods.vue'
            ],
            [
                'name' => 'HOME_SERVICE_GOODS_CATEGORY_SELECT',
                'title' => get_lang('dict_diy.home_service_goods_category_select'),
                'component' => '/src/addon/home_service/views/diy/components/link-select-category.vue'
            ],
            [
                'name' => 'HOME_SERVICE_CARD_SELECT',
                'title' => get_lang('dict_diy.home_service_card_select'),
                'component' => '/src/addon/home_service/views/diy/components/link-select-card.vue'
            ],

        ]
    ],
];
