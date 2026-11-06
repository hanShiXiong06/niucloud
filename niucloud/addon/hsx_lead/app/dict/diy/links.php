<?php
declare(strict_types=1);

return [
    'HSX_LEAD_MERCHANT_LINK' => [
        'key' => 'hsx_lead',
        'addon_title' => '商户客资协作',
        'title' => '商户客资协作',
        'child_list' => [
            [
                'name' => 'HSX_LEAD_MERCHANT_HOME',
                'title' => '协作首页',
                'url' => '/addon/hsx_lead/pages/index',
                'is_share' => 1,
                'action' => 'decorate',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_WORKBENCH',
                'title' => '找客户',
                'url' => '/addon/hsx_lead/pages/workbench/index',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_STORES',
                'title' => '找门店',
                'url' => '/addon/hsx_lead/pages/stores/index',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_WALLET',
                'title' => '我的收益',
                'url' => '/addon/hsx_lead/pages/wallet/index',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_PROFILE',
                'title' => '个人中心',
                'url' => '/addon/hsx_lead/pages/profile/index',
                'is_share' => 0,
                'action' => 'decorate',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_PROFILE_EDIT',
                'title' => '门店资料与交付能力',
                'url' => '/addon/hsx_lead/pages/task/index?task=profile-edit',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_INVENTORY',
                'title' => '我的库存快照',
                'url' => '/addon/hsx_lead/pages/task/index?task=inventory',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_CONNECTION',
                'title' => 'ERP 库存同步',
                'url' => '/addon/hsx_lead/pages/task/index?task=connection',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_MEMBERSHIP',
                'title' => '门店会员',
                'url' => '/addon/hsx_lead/pages/membership/index',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_PROMOTION',
                'title' => '推广中心',
                'url' => '/addon/hsx_lead/pages/task/index?task=promotion',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_PUBLISH',
                'title' => '发布线索',
                'url' => '/addon/hsx_lead/pages/task/index?task=publish',
                'is_share' => 0,
                'action' => '',
            ],
        ],
    ],
];
