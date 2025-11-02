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


use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use addon\home_service\app\service\core\order\CoreOrderService;
use core\base\BaseJob;

/**
 * 队列异步调用处理门店超时未派订单
 */
class StoreOrderTimeoutNotAssigned extends BaseJob
{
    /**
     * 处理门店超时未派订单
     * @return true
     */
    public function doJob()
    {
        try {
            (new Order())->where([
                ['order_status', '=', OrderDict::WAIT_DISPATCH],
                ['store_id', '>', 0],
                ['dispatch_timeout_time', '>', 0],
                ['dispatch_timeout_time', '<', time()],
                ['technician_id', '=', 0],
            ])->update([
                'store_id' => 0,
                'dispatch_timeout_time' => 0,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
