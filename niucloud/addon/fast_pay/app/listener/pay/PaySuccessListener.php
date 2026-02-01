<?php

// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------

namespace addon\fast_pay\app\listener\pay;

use addon\fast_pay\app\service\core\order\OrderService;
use addon\fast_pay\app\dict\order\OrderDict;

/**
 * 支付异步回调事件
 */
class PaySuccessListener
{
    public function handle(array $pay_info)
    {
        $trade_type = $pay_info['trade_type'] ?? '';
        if ($trade_type == OrderDict::getOrderType()['type']) {
            (new OrderService())->paySuccess($pay_info);
        }

    }
}