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

use addon\home_service\app\service\api\technician\TechnicianOrderService;
use core\base\BaseApiController;

/**
 * 订单控制器
 * Class GoodsController
 * @package app\adminapi\controller
 */
class TechnicianOrder extends BaseApiController
{



    /**
     * 获取订单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_status', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->getPage($data));
    }





    /**
     * 订单状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', (new OrderService())->getStatus());
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
     * 取消订单
     * @param int $order_id
     * @return \think\Response
     */
    public function cancel(int $order_id)
    {
        return success('SUCCESS', (new OrderService())->cancel($order_id));
    }

    /**
     * 删除订单
     * @param int $order_id
     * @return \think\Response
     */
    public function delete(int $order_id)
    {
        return success('DELETE_SUCCESS', (new OrderService())->delete($order_id));
    }



    /**
     * 获取订单数量
     * @return \think\Response
     */
    public function getNum()
    {
        return success((new OrderService())->num());
    }
}
