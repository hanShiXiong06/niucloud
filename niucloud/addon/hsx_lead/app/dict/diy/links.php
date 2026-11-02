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
                'title' => '商户工作台（我的工单）',
                'url' => '/addon/hsx_lead/pages/workbench/index',
                'is_share' => 0,
                'action' => '',
            ],
        ],
    ],
];
