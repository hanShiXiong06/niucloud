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


use addon\home_service\app\service\core\order\CoreOrderService;
use addon\home_service\app\model\order\Order;
use core\base\BaseJob;
use addon\home_service\app\dict\order\OrderDict;

/**
 * 队列异步调用支付定时未支付恢复
 */
class OrderClose extends BaseJob
{
    /**
     * 消费
     * @param $data
     * @return true
     */
    public function doJob()
    {
        try {
            $list = (new Order())->where([
                ['order_status', '=', OrderDict::WAIT_PAY],
                ['auto_close_time', '<=', time()],
            ])->select();
            if (!$list->isEmpty()) {
                foreach ($list as $v) {
                    $res = (new CoreOrderService())->autoClose($v['order_id']);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
