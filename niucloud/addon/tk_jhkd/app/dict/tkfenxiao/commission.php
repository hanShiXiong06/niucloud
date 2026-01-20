<?php

return [
    'tk_jhkd_order' => [
        'key' => 'tk_jhkd_order',
        'name' => '聚合快递分销', // 权益名称
        'desc' => '聚合快递分销', // 权益说明
        'component' => '/src/addon/tk_jhkd/views/tkfenxiao/components/commission/tk-jhkd-fenxiao.vue',//付费分销佣金
        'content' => [
            'admin' => function($site_id, $config) {
                return "分销下级订单完成后可获得{$config['children']}成长值";
            },
            'task' => function($site_id, $config) {
                return [
                    'icon' => '/addon/tk_cps/rule/growth-rule-bwc.png',
                    'title' => '分销下级订单',
                    'desc' => "分销下级订单可获得{$config['growth']}成长值",
                    'button' => [
                        'text' => '去下单',
                        'wap_redirect' => '/addon/tk_cps/pages/bwc/act'
                    ]
                ];
            }
        ]
    ],
];
