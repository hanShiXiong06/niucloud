<?php

return [
    'grant' => [
        'tk_jhkd_order' => [
            'key' => 'tk_jhkd_order',
            'name' => '快递下单',
            'desc' => '快递订单完成赠送积分',
            'component' => '/src/addon/tk_jhkd/views/member/components/point-rule-jhkd.vue',
            'calculate' => '', // 计算成长值,
            'content' => [
                'admin' => function ($site_id, $config) {
                    return "快递订单完成后赠送{$config['point']}积分";
                },
                'task' => function ($site_id, $config) {
                    return [
                        'icon' => '/addon/tk_jhkd/rule/growth-rule-cart.png',
                        'title' => '快递下单',
                        'desc' => "快递订单完成后赠送{$config['point']}积分",
                        'button' => [
                            'text' => '去下单',
                            'wap_redirect' => '/addon/tk_jhkd/pages/ordersubmit'
                        ]
                    ];
                }
            ]
        ],
    ],
    'consume' => [
//        'shop_order_deduction' => [
//            'key' => 'shop_order_deduction',
//            'name' => '积分抵现',
//            'desc' => '订单交易时积分可抵部分现金',
//            'component' => '/src/addon/shop/views/member/components/point-rule-orderdeduction.vue',
//            'content' => [
//                'admin' => function($site_id, $config) {
//                    return "订单交易时{$config['point']}积分可抵{$config['money']}元";
//                }
//            ]
//        ]
    ]
];
