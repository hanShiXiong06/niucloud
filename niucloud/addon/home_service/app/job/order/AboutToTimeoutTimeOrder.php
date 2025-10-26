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


use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use addon\home_service\app\service\core\order\CoreOrderService;
use core\base\BaseJob;

/**
 * 队列异步调用处理即将超时订单
 */
class AboutToTimeoutTimeOrder extends BaseJob
{
    /**
     * 处理异常订单
     * @return true
     */
    protected function doJob()
    {
        try {
            $orderConfig = (new CoreOrderConfigService())->getOrderConfig($data['site_id'] ?? 0) ?? [];
            $aboutToTimeout = $orderConfig['order_time']['about_to_timeout_time'] ?? 0;

            $now = time();
            $list = (new Order())->where([
                ['order_status', '=', OrderDict::WAIT_SERVICE],
                ['reserve_service_time_stamp', '>', $now],
                ['reserve_service_time_stamp', '<=', $now + $aboutToTimeout],
            ])->select();
            if (!$list->isEmpty()) {
                foreach ($list as $v) {
                    event('NotificationEvent', [
                        'identity' => [NoticeDict::TECHNICIAN],
                        'type' => NoticeDict::ABOUT_TO_TIMEOUT,
                        'notice_source' => NoticeDict::ORDER,
                        'order_id' => $v->order_id,
                        'technician_id' => $v->technician_id,
                        'member_id' => $v->member_id,
                        'site_id' => $v->site_id,
                    ]);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
