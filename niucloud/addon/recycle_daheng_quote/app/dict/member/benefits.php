<?php

return [
    'daheng_quote_report' => [
        'key' => 'daheng_quote_report',
        'name' => '报价单生成', // 权益名称
        'desc' => '报价单生成', // 权益说明
        'component' => '/src/addon/recycle_daheng_quote/views/member/components/benefits-report.vue',
        'content' => [
            'admin' => function ($site_id, $config) {
                return '报价单生成权限';
            },
            'member_level' => function ($site_id, $config) {
                return [
                    'title' => '报价单生成',
                    'desc' => '生成回收报价单图片',
                    'icon' => '/addon/hsx_recycle/VIP.png'
                ];
            }
        ]
    ],
];
