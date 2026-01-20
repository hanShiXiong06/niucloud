<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\order_event;
use core\base\BaseJob;

/**
 * 订单完成后事件
 */
class OrderFinishAfter extends BaseJob
{
    public function doJob($order_data)
    {
        try {
            event('AfterKdOrderFinish', [
                'order_data' => $order_data
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
