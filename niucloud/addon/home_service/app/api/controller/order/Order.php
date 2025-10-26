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

namespace addon\home_service\app\api\controller\order;

use addon\home_service\app\service\api\order\OrderService;
use core\base\BaseApiController;

/**
 * 订单控制器
 * Class GoodsController
 * @package app\adminapi\controller
 */
class Order extends BaseApiController
{


    /**
     * 订单状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', (new OrderService())->getStatus());
    }

    /**
     * 获取订单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_status', ''],
            ['order_id', ''],
        ]);
        return success('SUCCESS', (new OrderService())->getPage($data));
    }

    /**
     * 获取订单详情
     * @param $order_id
     * @return \think\Response
     */
    public function detail($order_id)
    {
        return success('SUCCESS', (new OrderService())->getDetail($order_id));
    }


    /**
     * 订单验收
     * @param $order_id
     * @return \think\Response
     */
    public function check()
    {
        $data = $this->request->params([
            ['order_id', ''],
        ]);
        return success('SUCCESS', (new OrderService())->check($data));
    }


    /**
     * 取消订单
     * @param int $order_id
     * @return \think\Response
     */
    public function cancel(int $order_id)
    {
        return success('SUCCESS', (new OrderService())->cancel($order_id));
    }

    /**
     * 修改预约时间订单
     * @param int $order_id
     * @return \think\Response
     */
    public function editReserveServiceTime(int $order_id)
    {
        $data = $this->request->params([
            ['reserve_service_time', ''],
        ]);
        return success('SUCCESS', (new OrderService())->editReserveServiceTime($order_id,$data));
    }

    //
    //
    //    /**
    //     * 获取订单数量
    //     * @return \think\Response
    //     */
    //    public function getNum()
    //    {
    //        return success((new OrderService())->num());
    //    }
}
