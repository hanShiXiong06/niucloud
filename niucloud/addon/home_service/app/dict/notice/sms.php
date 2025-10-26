<?php
return [
    'o2o_order_pay' => [
        'is_need_closure_content' => 0,
        'content' => '您购买的{goods_name}已经支付成功'
    ],
    'o2o_order_auto_close' => [
        'is_need_closure_content' => 0,
        'content' => '您购买的{goods_name}未能及时支付订单已自动关闭',
    ],
    'o2o_verify_success' => [
        'is_need_closure_content' => 0,
        'content' => '您的核销码{verify_code}已消费成功',
    ],
    'o2o_refund_success' => [
        'is_need_closure_content' => 0,
        'content' => '已退款：订单{order_no}已成功退款',
    ],
    'o2o_refund_refuse' => [
        'is_need_closure_content' => 0,
        'content' => '商家未同意您订单号为{order_no}的订单的退款申请',
    ],
    'o2o_reserve_expire_remind' => [
        'is_need_closure_content' => 0,
        'content' => '您在预约的服务时间是{reserve_time}，{service}，请提前安排好时间哦'
    ],
    'home_service_order_service' => [
        'is_need_closure_content' => 0,
        'content' => '您的订单已开始服务！服务人员：{technician}'
    ],
    'home_service_store_dispatch' => [
        'is_need_closure_content' => 0,
        'content' => '【{store_name}】您有新的派单，客户：{taker_name}，服务时间：{reserve_service_time}，请及时处理'
    ],
    'home_service_refund' => [
        'is_need_closure_content' => 0,
        'content' => '已退款：订单{order_no}已成功退款'
    ]
];
