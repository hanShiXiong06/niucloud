<?php

return [
    'tk_jhkd_fenxiao' => [
        'key' => 'tk_jhkd_fenxiao',
        'name' => '分销', // 权益名称
        'desc' => '快递分销', // 权益说明
        'component' => '/src/addon/tk_jhkd/views/member/components/benefits-jhkd-fenxiao.vue',
        "content" => [
            'admin' => function($site_id, $config) {
                return "快递分销特权";
            },
            'member_level' => function($site_id, $config) {
                return [
                    'title' => "快递分销",
                    'desc' => '分销特权',
                    'icon' => '/addon/tk_jhkd/rule/fenxiao.png'
                ];
            }
        ]
    ]
];
