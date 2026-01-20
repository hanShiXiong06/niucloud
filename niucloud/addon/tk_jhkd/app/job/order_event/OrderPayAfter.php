<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\order_event;

use core\base\BaseJob;
use think\facade\Log;

/**
 * 订单支付后事件
 */
class OrderPayAfter extends BaseJob
{
    public function doJob($order_data)
    {
        try {
            event('AfterKdOrderPay', [
                'order_data' => $order_data
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
