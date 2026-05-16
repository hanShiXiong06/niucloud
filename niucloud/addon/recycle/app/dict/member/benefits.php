<?php

return [
    'hsx_quote' => [
        'key' => 'hsx_quote',
        'name' => '报价查看', // 权益名称
        'desc' => '报价查看', // 权益说明test
        'component' => '/src/addon/recycle/views/member/components/benefits-quote.vue',
        "content" => [
            'admin' => function($site_id, $config) {
                return "报价查看权限";
            },
            'member_level' => function($site_id, $config) {
                return [
                    'title' => "VIP报价查看",
                    'desc' => '报价查看',
                    'icon' => '/addon/recycle/VIP.png'
                ];
            }
        ]
        ],
];
