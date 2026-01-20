<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\order;

use addon\tk_jhkd\app\service\core\OrderService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 订单关闭后事件
 */
class SendOrder extends BaseJob
{
    public function doJob($order_id)
    {
        try {
            Log::write('发单任务'.'-----'.date('Y-m-d H:i:s'));
            Log::write($order_id);
            (new OrderService())->sendOrder($order_id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
