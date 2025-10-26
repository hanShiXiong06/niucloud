<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\home_service\app\job\order;


use addon\home_service\app\service\core\order\CoreOrderRefundService;
use addon\home_service\app\model\order\Order;
use core\base\BaseJob;
use addon\home_service\app\dict\order\OrderDict;

/**
 * 队列异步调用未抢订单自动退
 */
class OrderAutoRefund extends BaseJob
{
    /**
     * 消费
     * @param $data
     * @return true
     */
    protected function doJob()
    {
        try {
            $list = (new Order())->where([
                ['order_status', '=', OrderDict::WAIT_DISPATCH],
                ['reserve_service_time_stamp', '<', time()],
                ['is_auto_refund', '=', 1],
                ['store_id', '<=', 0],
            ])->select();
            if (!$list->isEmpty()) {
                foreach ($list as $v) {
                    $res = (new CoreOrderRefundService())->autoRefund(['order_id' =>$v['order_id']]);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
