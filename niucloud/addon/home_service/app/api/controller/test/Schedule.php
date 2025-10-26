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

namespace addon\home_service\app\api\controller\test;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\service\core\order\CoreOrderService;
use core\base\BaseAdminController;
use addon\home_service\app\model\order\Order;
use think\Model;


/**
 * 测试业务
 * Class Reserve
 * @package app\adminapi\controller
 */
class Schedule extends BaseAdminController
{


    /**
     * 队列异步调用处理异常订单
     * @return \think\Response
     */
    public function schedule($publicname = '')
    {

        $res = $this->$publicname();
        dd($res);
    }


    // (家政服务)异常订单处理   ok
    public function home_service_abnormal_order()
    {
        try {
            $list = (new Order())->where([
                ['order_status', '=', OrderDict::WAIT_SERVICE],
                ['reserve_service_time_stamp', '<', time()],
                ['is_abnormal', '=', 0],
            ])->select();
            if (!$list->isEmpty()) {
                foreach ($list as $v) {
                    (new CoreOrderService())->abnormalOrder($v['order_id']);
                }
            }
            return true;
        } catch (\Exception $e) {
            dd($e);
            return false;
        }

    }


}
