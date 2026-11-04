<?php
declare(strict_types=1);

return [
    'HSX_LEAD_MERCHANT_LINK' => [
        'key' => 'hsx_lead',
        'addon_title' => '商户客资协作',
        'title' => '商户客资协作',
        'child_list' => [
            [
                'name' => 'HSX_LEAD_MERCHANT_WORKBENCH',
                'title' => '我的交付',
                'url' => '/addon/hsx_lead/pages/workbench/index',
                'is_share' => 0,
                'action' => '',
            ],
            [
                'name' => 'HSX_LEAD_MERCHANT_STORES',
                'title' => '找交付门店',
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
                'title' => '我的门店',
                'url' => '/addon/hsx_lead/pages/profile/index',
                'is_share' => 0,
                'action' => '',
            ],
        ],
    ],
];
