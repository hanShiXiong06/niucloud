<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\order_event;

use core\base\BaseJob;
use think\facade\Log;

/**
 * 订单退款取消事件
 */
class OrderDeliveryCancel extends BaseJob
{
    public function doJob($site_id, $data)
    {
        try {
            event('DeliveryCancelOrder', ['site_id' => $site_id, 'data' => $data]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
