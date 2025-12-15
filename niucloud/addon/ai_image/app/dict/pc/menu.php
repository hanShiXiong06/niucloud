<?php

use addon\ai_image\app\service\core\ConfigService;

$base_menu=[
    [
        'name' => '首页',
        'path' => '/ai_image/index',
        'icon' => 'House',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '创作历史',
        'path' => '/ai_image/history/image',
        'icon' => 'Notification',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '日志记录',
        'path' => '/ai_image/member/point',
        'icon' => 'Lightning',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '帮助中心',
        'path' => '/ai_image/help/index',
        'icon' => 'TakeawayBox',
        'external' => false,
        'badge' => null
    ]
];
$point_menu=[
    [
        'name' => '卡密列表',
        'path' => '/ai_image/card/card',
        'icon' => 'MessageBox',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '卡密兑换',
        'path' => '/ai_image/card/verify',
        'icon' => 'Mug',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '套餐中心',
        'path' => '/ai_image/package/index',
        'icon' => 'Present',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '套餐订单',
        'path' => '/ai_image/package/order',
        'icon' => 'CreditCard',
        'external' => false,
        'badge' => null
    ],
];
$tk_point_menu=[
    [
        'name' => '卡密列表',
        'path' => '/tk_point/card/card',
        'icon' => 'Postcard',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '卡密兑换',
        'path' => '/tk_point/card/verify',
        'icon' => 'ScaleToOriginal',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '套餐中心',
        'path' => '/tk_point/package/index',
        'icon' => 'Present',
        'external' => false,
        'badge' => null
    ],
    [
        'name' => '套餐订单',
        'path' => '/tk_point/package/order',
        'icon' => 'DataLine',
        'external' => false,
        'badge' => null
    ],
];
$is_tk_point=(new ConfigService())->isTkPoint();
if($is_tk_point){
    $point_menu=$tk_point_menu;
}
return array_merge($base_menu,$point_menu);