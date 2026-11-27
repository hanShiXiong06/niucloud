<?php

use app\dict\sys\SmsDict;

return [
    'phone_shop_forward_application_approved' => [
        'is_need_closure_content' => 0,
        'content' => '您的{level_name}申请已通过，同行商品转发功能现已开通。查看商城{url}',
    ],
    'phone_shop_forward_application_rejected' => [
        'is_need_closure_content' => 0,
        'content' => '您的{level_name}申请暂未通过，原因：{review_reason}。如有疑问请联系商家。',
    ],
    'phone_shop_goods_match' => [
        'is_need_closure_content' => 0,
        'content' => '您订阅的“{subscription_name}”有变化：{goods_name}，{change_summary}。查看详情{url}',
    ],
    'shop_order_pay' => [
        'is_need_closure_content' => 1,//是否需要闭包处理content
        'content' => function ($data) {
            $site_id = $data['site_id'];
            $sms_type = $data['sms_type'];
            if ($sms_type == SmsDict::NIUSMS) {
                return "您的订单{order_no}已支付成功";
            }
            return "您购买的“{body}”已支付成功。查看详情{url}";
        }
    ],
    'admin_shop_order_pay' => [
        'is_need_closure_content' => 1,//是否需要闭包处理content
        'content' => function ($data) {
            $site_id = $data['site_id'];
            $sms_type = $data['sms_type'];
            if ($sms_type == SmsDict::NIUSMS) {
                return "您有商品售出，单号：{order_no}，详情请登录后台查看";
            }
            return "您有商品售出，单号：{order_no}，详情请登录后台查看";
        }
    ],
    'phone_shop_order_delivery' => [
        'is_need_closure_content' => 1,//是否需要闭包处理content
        'content' => function ($data) {
            $site_id = $data['site_id'];
            $sms_type = $data['sms_type'];
            if ($sms_type == SmsDict::NIUSMS) {
                return "您的订单{order_no}已于{delivery_time}发货";
            }
            return "您购买的“{body}”已于{delivery_time}发货。查看详情{url}";
        }
    ],
    'admin_shop_refund_agree' => [
        'is_need_closure_content' => 0,
        'content' => '已同意单号为{order_no}的退款申请，详情请登录后台查看。',
    ],
    'shop_refund_agree' => [
        'is_need_closure_content' => 0,
        'content' => '商家已同意您订单编号为{order_no}的退款申请，请关注具体的退款情况',
    ],
    'shop_refund_refuse' => [
        'is_need_closure_content' => 0,
        'content' => '很抱歉，您订单编号为{order_no}的退款申请未被同意，请您联系商家并修改申请',
    ],
];
