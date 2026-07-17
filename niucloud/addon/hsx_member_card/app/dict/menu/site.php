<?php
declare(strict_types=1);

$permission = static fn(string $name, string $key, string $url, string $method = 'get', int $sort = 50): array => [
    'menu_name' => $name, 'menu_key' => $key, 'menu_short_name' => $name,
    'parent_select_key' => '', 'menu_type' => '2', 'icon' => '', 'api_url' => $url,
    'router_path' => '', 'view_path' => '', 'methods' => $method, 'sort' => (string)$sort,
    'status' => '1', 'is_show' => '0',
];

return [
    [
        'menu_name' => '会员服务卡', 'menu_key' => 'hsx_member_card_manage', 'menu_short_name' => '会员卡',
        'parent_select_key' => '', 'parent_key' => 'hsx_erp_manage', 'menu_type' => '0',
        'icon' => 'nc-iconfont nc-icon-huiyuan', 'api_url' => '',
        'router_path' => 'hsx_member_card', 'view_path' => '',
        'methods' => '', 'sort' => '85', 'status' => '1', 'is_show' => '1',
        'children' => [
            [
                'menu_name' => '会员卡工作台', 'menu_key' => 'hsx_member_card_dashboard', 'menu_short_name' => '工作台',
                'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'member_card/dashboard',
                'router_path' => 'hsx_member_card/dashboard', 'view_path' => 'dashboard/index', 'methods' => 'get',
                'sort' => '120', 'status' => '1', 'is_show' => '1',
            ],
            [
                'menu_name' => '卡种管理', 'menu_key' => 'hsx_member_card_product', 'menu_short_name' => '卡种管理',
                'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'member_card/product/lists',
                'router_path' => 'hsx_member_card/product', 'view_path' => 'product/index', 'methods' => 'get',
                'sort' => '110', 'status' => '1', 'is_show' => '1',
                'children' => [
                    $permission('卡种选项', 'hsx_member_card_product_options', 'member_card/product/options', 'get', 100),
                    $permission('卡种详情', 'hsx_member_card_product_info', 'member_card/product/<id>', 'get', 99),
                    $permission('保存卡种', 'hsx_member_card_product_save', 'member_card/product/save', 'post', 98),
                    $permission('编辑卡种', 'hsx_member_card_product_update', 'member_card/product/save/<id>', 'post', 97),
                    $permission('启停卡种', 'hsx_member_card_product_status', 'member_card/product/<id>/enable', 'post', 96),
                    $permission('停用卡种', 'hsx_member_card_product_disable', 'member_card/product/<id>/disable', 'post', 95),
                    $permission('删除卡种', 'hsx_member_card_product_delete', 'member_card/product/<id>', 'delete', 94),
                ],
            ],
            [
                'menu_name' => '开卡订单', 'menu_key' => 'hsx_member_card_order', 'menu_short_name' => '开卡订单',
                'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'member_card/order/lists',
                'router_path' => 'hsx_member_card/order', 'view_path' => 'order/index', 'methods' => 'get',
                'sort' => '100', 'status' => '1', 'is_show' => '1',
                'children' => [
                    $permission('会员选择', 'hsx_member_card_member_options', 'member_card/member/options', 'get', 100),
                    $permission('快速建客户', 'hsx_member_card_member_create', 'member_card/member/quick_create', 'post', 99),
                    $permission('会员持卡', 'hsx_member_card_member_cards', 'member_card/member/<member_id>/cards', 'get', 98),
                    $permission('订单详情', 'hsx_member_card_order_info', 'member_card/order/<id>', 'get', 97),
                    $permission('快速开卡', 'hsx_member_card_order_create', 'member_card/order/create', 'post', 96),
                    $permission('重试财务', 'hsx_member_card_order_retry_finance', 'member_card/order/<id>/retry_finance', 'post', 95),
                    $permission('取消开卡', 'hsx_member_card_order_cancel', 'member_card/order/<id>/cancel', 'post', 94),
                    $permission('申请整卡退款', 'hsx_member_card_refund_apply', 'member_card/order/<id>/refund/apply', 'post', 93),
                ],
            ],
            [
                'menu_name' => '手机号核销', 'menu_key' => 'hsx_member_card_card', 'menu_short_name' => '手机号核销',
                'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'member_card/card/search',
                'router_path' => 'hsx_member_card/card', 'view_path' => 'card/index', 'methods' => 'get',
                'sort' => '90', 'status' => '1', 'is_show' => '1',
                'children' => [
                    $permission('会员卡详情', 'hsx_member_card_card_info', 'member_card/card/<id>', 'get', 100),
                    $permission('确认核销', 'hsx_member_card_redeem', 'member_card/card/<id>/redeem', 'post', 99),
                    $permission('冻结会员卡', 'hsx_member_card_card_freeze', 'member_card/card/<id>/freeze', 'post', 98),
                    $permission('解冻会员卡', 'hsx_member_card_card_unfreeze', 'member_card/card/<id>/unfreeze', 'post', 97),
                ],
            ],
            [
                'menu_name' => '核销记录', 'menu_key' => 'hsx_member_card_redemption', 'menu_short_name' => '核销记录',
                'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'member_card/redemption/lists',
                'router_path' => 'hsx_member_card/redemption', 'view_path' => 'redemption/index', 'methods' => 'get',
                'sort' => '80', 'status' => '1', 'is_show' => '1',
                'children' => [
                    $permission('核销详情', 'hsx_member_card_redemption_info', 'member_card/redemption/<id>', 'get', 100),
                    $permission('核销冲正', 'hsx_member_card_redeem_reverse', 'member_card/redemption/<id>/reverse', 'post', 99),
                ],
            ],
            [
                'menu_name' => '退款记录', 'menu_key' => 'hsx_member_card_refund', 'menu_short_name' => '退款记录',
                'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'member_card/refund/lists',
                'router_path' => 'hsx_member_card/refund', 'view_path' => 'refund/index', 'methods' => 'get',
                'sort' => '75', 'status' => '1', 'is_show' => '1',
                'children' => [
                    $permission('退款详情', 'hsx_member_card_refund_info', 'member_card/refund/<id>', 'get', 100),
                    $permission('重试退款财务', 'hsx_member_card_refund_retry', 'member_card/refund/<id>/retry_finance', 'post', 99),
                ],
            ],
            $permission('会员卡配置', 'hsx_member_card_config', 'member_card/config', 'get', 70),
            $permission('保存会员卡配置', 'hsx_member_card_config_save', 'member_card/config', 'post', 69),
            $permission('会员卡字典', 'hsx_member_card_dicts', 'member_card/dicts', 'get', 68),
        ],
    ],
];
