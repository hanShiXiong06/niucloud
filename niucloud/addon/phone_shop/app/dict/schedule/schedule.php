<?php

return [
    [
        'key' => 'phone_shop_order_close',
        'name' => '未支付订单自动关闭',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\order\OrderClose',
        'function' => ''
    ],
    [
        'key' => 'phone_shop_order_close_allow_refund',
        'name' => '订单完成自动关闭售后',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\order\OrderCloseAllowRefund',
        'function' => ''
    ],
    [
        'key' => 'phone_shop_order_finish',
        'name' => '待收货订单自动完成',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\order\OrderFinish',
        'function' => ''
    ],
    [
        'key' => 'phone_shop_coupon_member_expire',
        'name' => '优惠券到期自动过期',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\marketing\CouponMemberExpire',
        'function' => ''
    ],
    [
        'key' => 'phone_shop_coupon_start',
        'name' => '优惠券限时自动开启',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\marketing\CouponStart',
        'function' => ''
    ],
    [
        'key' => 'phone_shop_coupon_end',
        'name' => '优惠券限时自动结束',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\marketing\CouponEnd',
        'function' => ''
    ],
    [
        'key' => 'phone_shop_active_start',
        'name' => '营销活动自动开启',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\marketing\ActiveStart',
        'function' => ''
    ],
    [
        'key' => 'phone_shop_active_end',
        'name' => '营销活动自动结束',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\marketing\ActiveEnd',
        'function' => ''
    ],
    // 同步商品
    [
        'key' => 'phone_shop_sync_goods',
        'name' => '同步100005店铺商品',
        'desc' => '',
        'time' => [
            'type' => 'hour',
            'min' => 1
        ],
        'class' => 'addon\phone_shop\app\job\goods\SyncGoods',
        'function' => ''
    ],
];
