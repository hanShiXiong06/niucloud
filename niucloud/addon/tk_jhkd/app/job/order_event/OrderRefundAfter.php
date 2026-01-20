<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\order_event;

use core\base\BaseJob;
use think\facade\Log;

/**
 * 订单退款后事件
 */
class OrderRefundAfter extends BaseJob
{
    public function doJob($order_data)
    {
        try {
            event('AfterKdOrderRefundFinish', [
                'refund_data' => $order_data
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
