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
namespace addon\home_service\app\job\card;


use addon\home_service\app\dict\card\CardOrderDict;
use addon\home_service\app\model\card\CardOrder;
use addon\home_service\app\service\core\card\CoreCardOrderService;
use core\base\BaseJob;

/**
 * 队列异步调用支付定时未支付恢复
 */
class CardOrderClose extends BaseJob
{
    /**
     * 消费
     * @param $data
     * @return true
     */
    public function doJob()
    {
        try {
            $list = (new CardOrder())->where([
                ['order_status', '=', CardOrderDict::WAIT_PAY],
                ['auto_close_time', '<=', time()],
            ])->select();
            if (!$list->isEmpty()) {
                foreach ($list as $v) {
                    $res = (new CoreCardOrderService())->autoClose($v['order_id']);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
