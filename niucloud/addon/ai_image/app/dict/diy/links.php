<?php

use addon\ai_image\app\service\core\ConfigService;

$base_data=[
    [
        'name' => 'AI_IMAGE_DIY',
        'title' => '首页',
        'url' => '/addon/ai_image/pages/index',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'AI_IMAGE_DIY_MEMBER',
        'title' => '个人中心',
        'url' => '/addon/ai_image/pages/member',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'AI_IMAGE_CREATE_LIST',
        'title' => '创建列表',
        'url' => '/addon/ai_image/pages/list',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'AI_IMAGE_MODEL',
        'title' => '智能体',
        'url' => '/addon/ai_image/pages/model',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'AI_IMAGE_HELP',
        'title' => '帮助列表',
        'url' => '/addon/ai_image/pages/help/list',
        'is_share' => 1,
        'action' => 'decorate'
    ],
];
$point_data=[
    [
        'name' => 'AI_IMAGE_PACKAGE',
        'title' => '套餐列表',
        'url' => '/addon/ai_image/pages/package',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'AI_IMAGE_CARD',
        'title' => '我的卡密',
        'url' => '/addon/ai_image/pages/card',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'AI_IMAGE_ORDER',
        'title' => '订单列表',
        'url' => '/addon/ai_image/pages/packageorder',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'AI_IMAGE_VERIFY',
        'title' => '卡密兑换',
        'url' => '/addon/ai_image/pages/verify',
        'is_share' => 0,
        'action' => 'decorate'
    ],
];
$tk_point_data=[
    [
        'name' => 'TK_POINT_PACKAGE',
        'title' => '套餐列表',
        'url' => '/addon/tk_point/pages/package',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_POINT_ORDER',
        'title' => '订单列表',
        'url' => '/addon/tk_point/pages/packageorder',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_POINT_CARD_VERIFY',
        'title' => '卡密兑换',
        'url' => '/addon/tk_point/pages/verify',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_POINT_CARD',
        'title' => '卡密列表',
        'url' => '/addon/tk_point/pages/card',
        'is_share' => 0,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_POINT_LOG',
        'title' => '流水明细',
        'url' => '/addon/tk_point/pages/log',
        'is_share' => 0,
        'action' => 'decorate'
    ],

];
$is_tk_point=(new ConfigService())->isTkPoint();
if($is_tk_point){
    $point_data=$tk_point_data;
}
return [

    'AI_IMAGE_BASE_LINK' => [
        'title' => 'AI设计',
        'addon_info' => [
            'title' => 'AI设计',
            'key' => 'ai_image'
        ],
        'type' => 'folder',
        'child_list' => [
            [
                'name' => 'ai_image_base',
                'title' => '基础链接',
                'child_list' => array_merge($base_data,$point_data)
            ],
            [
                'name' => 'AI_IMAGE_MODEL_SELECT',
                'title' => '智能体',
                'component' => '/src/addon/ai_image/views/components/model-select.vue'
            ],
        ]
    ]
];
