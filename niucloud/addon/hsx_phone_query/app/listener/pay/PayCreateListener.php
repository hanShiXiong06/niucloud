<?php

namespace addon\hsx_phone_query\app\listener\pay;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryOrderDict;
use addon\hsx_phone_query\app\service\api\hsx_phone_query\HsxPhoneQueryService;
use app\dict\pay\PayDict;

/**
 * 手机查询支付单据创建监听器
 */
class PayCreateListener
{
    public function handle(array $params)
    {
        if (($params['trade_type'] ?? '') != HsxPhoneQueryOrderDict::TRADE_TYPE) {
            return true;
        }

        $order = (new HsxPhoneQueryService())->getPayOrderInfo(
            (int)$params['site_id'],
            (int)$params['trade_id']
        );

        return [
            'main_type' => PayDict::MEMBER,
            'main_id' => $order['member_id'],
            'money' => $order['pay_money'],
            'trade_type' => HsxPhoneQueryOrderDict::TRADE_TYPE,
            'trade_id' => $order['order_id'],
            'body' => '手机查询-' . ($order['service_name'] ?: $order['order_no']),
        ];
    }
}
