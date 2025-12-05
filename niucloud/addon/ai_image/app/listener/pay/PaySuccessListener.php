<?php

// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------

namespace addon\ai_image\app\listener\pay;

use addon\ai_image\app\service\core\OrderService;
use addon\ai_image\app\dict\order\OrderStatusDict;

/**
 * 支付异步回调事件
 */
class PaySuccessListener
{
    public function handle(array $pay_info)
    {
        $trade_type = $pay_info['trade_type'] ?? '';
        if ($trade_type == OrderStatusDict::getOrderType()['type']) {
            (new OrderService())->paySuccess($pay_info);
        }

    }
}