<?php

return [
    [
        'key' => 'jhkd_order_close',
        'name' => '聚合快递未支付订单自动关闭',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\tk_jhkd\app\job\order\OrderClose',
        'function' => ''
    ],
    [
        'key' => 'jhkd_order_addpay',
        'name' => '聚合快递补差价订单催收',
        'desc' => '',
        'time' => [
            'type' => 'day',
            'day' => 1,
            'hour' => 10,
            'min' => 0
        ],
        'class' => 'addon\tk_jhkd\app\job\order\AddPay',
        'function' => ''
    ],
    [
        'key' => 'jhkd_order_commission_order',
        'name' => '聚合快递结算佣金单',
        'desc' => '',
        'time' => [
            'type' => 'day',
            'day' => 1,
            'hour' => 4,
            'min' => 0
        ],
        'class' => 'addon\tk_jhkd\app\job\order\JsOrder',
        'function' => ''
    ],
    [
        'key' => 'jhkd_order_commission_close_order',
        'name' => '聚合快递佣金单关闭',
        'desc' => '',
        'time' => [
            'type' => 'day',
            'day' => 1,
            'hour' => 3,
            'min' => 0
        ],
        'class' => 'addon\tk_jhkd\app\job\order\CloseOrderCommission',
        'function' => ''
    ],
    [
        'key' => 'tk_jhkd_coupon_member_expire',
        'name' => '聚合快递优惠券到期自动过期',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\tk_jhkd\app\job\coupon\CouponMemberExpire',
        'function' => ''
    ],
    [
        'key' => 'tk_jhkd_coupon_start',
        'name' => '聚合快递优惠券限时自动开启',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\tk_jhkd\app\job\coupon\CouponStart',
        'function' => ''
    ],
    [
        'key' => 'tk_jhkd_coupon_end',
        'name' => '聚合快递优惠券限时自动结束',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\tk_jhkd\app\job\coupon\CouponEnd',
        'function' => ''
    ],
];
