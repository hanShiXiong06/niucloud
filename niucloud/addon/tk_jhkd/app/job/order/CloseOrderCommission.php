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
 * 关闭佣金结算订单
 */
class CloseOrderCommission extends BaseJob
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
                    $order_info = $orderModel->where(['site_id' => $v['site_id'], 'order_id' => $v['order_id'], 'order_status' => JhkdOrderDict::CLOSE])->findOrEmpty();
                    if (!$order_info->isEmpty()) {
                        $fxModel->where(['order_id' => $v['order_id'], 'site_id' => $v['site_id']])->update(['status' => CommissionStatusDict::CLOSE]);
                        (new OrderLogService())->writeOrderLog(
                            $v['site_id'],
                            $v['order_id'],
                            JhkdOrderDict::CLOSE,
                            '定时任务关闭佣金订单'
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
