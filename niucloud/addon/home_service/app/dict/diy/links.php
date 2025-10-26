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


//                    [
//                        'name' => 'HOME_SERVICE_GOODS_LIST',
//                        'title' => get_lang('dict_diy.o2o_link_goods_list'),
//                        'url' => '/addon/o2o/pages/goods/list',
//                        'is_share' => 1,
//                        'action' => ''
//                    ],
//
//
//                    [
//                        'name' => 'HOME_SERVICE_REFUND_LIST',
//                        'title' => get_lang('dict_diy.o2o_link_order_refund_list'),
//                        'url' => '/addon/o2o/pages/refund/list',
//                        'is_share' => 0,
//                        'action' => ''
//                    ],
//                    [
//                        'name' => 'HOME_SERVICE_TECHNICIAN_LIST',
//                        'title' => get_lang('dict_diy.o2o_link_technician_list'),
//                        'url' => '/addon/o2o/pages/technician/list',
//                        'is_share' => 1,
//                        'action' => ''
//                    ],
//                    [
//                        'name' => 'HOME_SERVICE_ADDRESS_LIST',
//                        'title' => get_lang('dict_diy.o2o_link_address_list'),
//                        'url' => '/addon/o2o/pages/address/index',
//                        'is_share' => 0,
//                        'action' => ''
//                    ],
//                    [
//                        'name' => 'HOME_SERVICE_LINK_MASTER_STAT_INDEX',
//                        'title' => get_lang('dict_diy.o2o_link_master_stat_index'),
//                        'url' => '/addon/o2o/pages/master/statistics/index',
//                        'is_share' => 1,
//                        'action' => ''
//                    ],
                ]
            ],
//            [
//                'name' => 'HOME_SERVICE_GOODS_SELECT',
//                'title' => get_lang('dict_diy.o2o_goods_select'),
//                'component' => '/src/addon/o2o/views/goods/components/goods-select-content.vue'
//            ],
//            [
//                'name' => 'HOME_SERVICE_GOODS_CATEGORY_SELECT',
//                'title' => get_lang('dict_diy.o2o_goods_category_select'),
//                'component' => '/src/addon/o2o/views/goods/components/category-select-content.vue'
//            ],
//            [
//                'name' => 'HOME_SERVICE_TECHNICIAN_SELECT',
//                'title' => get_lang('dict_diy.o2o_technician_select'),
//                'component' => '/src/addon/o2o/views/technician/components/technician-select-content.vue'
//            ],

        ]
    ],
];
