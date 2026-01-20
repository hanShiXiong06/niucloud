<?php

return [
    'KD_API_COMMONLINK' => [
        'key' => 'kd_api',
        'addon_title' => '快递API',
        'title' => '快递API',
        'child_list' => [
            [
                'name' => 'KD_API_INDEX',
                'title' => 'API信息',
                'url' => '/addon/kd_api/pages/index',
                'is_share' => 1,
                'action' => 'decorate'
            ],
            [
                'name' => 'KD_API_ORDER',
                'title' => '订单列表',
                'url' => '/addon/kd_api/pages/order',
                'is_share' => 1,
                'action' => 'decorate'
            ],

        ]
    ],

];