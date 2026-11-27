<?php

return [
    'shop_goods_forward' => [
        'key' => 'shop_goods_forward',
        'name' => '同行商品转发',
        'desc' => '允许下载商品图文素材用于同行转发',
        'component' => '/src/addon/phone_shop/views/member/components/benefits-goods-forward.vue',
        'content' => [
            'admin' => function($site_id, $config) {
                return '可使用同行商品一键转发';
            },
            'member_level' => function($site_id, $config) {
                return [
                    'title' => '同行商品转发',
                    'desc' => '下载商品图文素材',
                    'icon' => '/static/resource/images/member/benefits/benefits_pinkage.png',
                ];
            },
        ],
    ],
    'shop_free_shipping' => [
        'key' => 'shop_free_shipping',
        'name' => '包邮', // 权益名称
        'desc' => '商品购买时可享受免邮服务', // 权益说明
        'component' => '/src/addon/phone_shop/views/member/components/benefits-free-shipping.vue',
        "content" => [
            'admin' => function($site_id, $config) {
                return '下单享受包邮';
            },
            'member_level' => function($site_id, $config) {
                return [
                    'title' => '商品包邮',
                    'desc' => '下单免运费',
                    'icon' => '/static/resource/images/member/benefits/benefits_pinkage.png'
                ];
            }
        ]
    ]
];
