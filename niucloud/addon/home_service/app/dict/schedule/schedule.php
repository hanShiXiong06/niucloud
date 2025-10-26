<?php

return [
    [
        'key' => 'home_service_auto_order_close',
        'name' => '(家政服务)未支付订单自动关闭',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\order\OrderClose',
        'function' => ''
    ],
    [
        'key' => 'home_service_coupon_member_expire',
        'name' => '(家政服务)优惠券到期自动过期',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\coupon\CouponMemberExpire',
        'function' => ''
    ],
    [
        'key' => 'home_service_coupon_start',
        'name' => '(家政服务)优惠券限时自动开启',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\coupon\CouponStart',
        'function' => ''
    ],
    [
        'key' => 'home_service_coupon_end',
        'name' => '(家政服务)优惠券限时自动结束',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\coupon\CouponEnd',
        'function' => ''
    ],
    [
        'key' => 'home_service_coupon_send',
        'name' => '(家政服务)优惠券自动发放',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\coupon\CouponSend',
        'function' => '',
        'params'=>['record_id'=>'','site_id'=>0]
    ],

    [
        'key' => 'home_service_card_expire',
        'name' => '(家政服务)次卡到期自动过期',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\card\MemberCardExpire',
        'function' => ''
    ],
    [
        'key' => 'home_service_card_order_auto_close',
        'name' => '(家政服务)次卡订单自动关闭',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\card\CardOrderClose',
        'function' => ''
    ],
    [
        'key' => 'home_service_timeout_order_auto_refund',
        'name' => '(家政服务)超时未抢订单自动退',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\order\OrderAutoRefund',
        'function' => ''
    ],
    [
        'key' => 'home_service_timeout_not_dispatch',
        'name' => '(家政服务)门店超时未派单处理',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\order\StoreOrderTimeoutNotAssigned',
        'function' => ''
    ],
    [
        'key' => 'home_service_abnormal_order',
        'name' => '(家政服务)异常订单处理',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\order\AbnormalOrder',
        'function' => ''
    ],
    [
        'key' => 'home_service_evaluate_auto_adopt_examine',
        'name' => '(家政服务)评价自动通过审核',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\order\EvaluateAutoAdoptExamine',
        'function' => ''
    ],
    [
        'key' => 'home_service_about_to_timeout_time_order',
        'name' => '(家政服务)即将超时处理',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\home_service\app\job\order\AboutToTimeoutTimeOrder',
        'function' => ''
    ],

];
