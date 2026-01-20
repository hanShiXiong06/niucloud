<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\order;

use addon\tk_jhkd\app\dict\order\CommissionStatusDict;
use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\model\fenxiao\FenxiaoOrder;
use addon\tk_jhkd\app\service\core\OrderFinishService;
use addon\tk_jhkd\app\service\core\OrderLogService;
use core\base\BaseJob;
use addon\tk_jhkd\app\model\order\Order;

/**
 * 已完成订单佣金结算
 */
class JsOrder extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        try {
            $fxModel = new FenxiaoOrder();
            $list = $fxModel->where([
                ['status', '=', CommissionStatusDict::WAIT_JS],
                ['create_time', '<=', time() - 60 * 60 * 24]
            ])->select();
            $orderModel = new Order();
            if (!$list->isEmpty()) {
                foreach ($list as $v) {
                    $order_info = $orderModel->where(['site_id' => $v['site_id'], 'order_id' => $v['order_id'], 'order_status' => JhkdOrderDict::FINISH])->findOrEmpty();
                    if (!$order_info->isEmpty()) {
                        (new OrderFinishService())->orderFinish($order_info);
                        (new OrderLogService())->writeOrderLog(
                            $v['site_id'],
                            $v['order_id'],
                            JhkdOrderDict::CLOSE,
                            '定时任务结算订单信息'
                        );
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
