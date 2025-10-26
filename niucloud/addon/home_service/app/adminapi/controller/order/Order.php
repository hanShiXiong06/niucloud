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

namespace addon\home_service\app\adminapi\controller\order;

use addon\home_service\app\service\admin\order\OrderService;
use app\dict\pay\PayDict;
use core\base\BaseAdminController;
use think\Response;

/**
 * 订单
 * Class Order
 * @description 订单
 * @package addon\home_service\app\adminapi\controller\order
 */
class Order extends BaseAdminController
{


    /**
     * 获取支付方式
     * @description 获取订单支付方式字典
     * @return Response
     */
    public function getPayType()
    {
        return success(PayDict::getPayType());
    }

    /**
     * 订单来源
     * @description 订单列表
     * @ret urn void
     */
    public function getOrderFrom()
    {
        return success((new OrderService())->getOrderFrom());
    }


    /**
     * 订单状态
     * @description 获取订单状态
     * @return void
     */
    public function status()
    {
        return success((new OrderService())->getStatus());
    }


    /**
     * 订单状态 带数字
     * @description 获取订单状态
     * @return void
     */
    public function taskStatus()
    {
        return success((new OrderService())->getTaskStatus());
    }


    /**
     * 订单列表
     * @description 订单列表
     * @ret urn void
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['member_search', ''],
            ['create_time', []],
            ['technician_name', ''],
            ['order_from', ''],
            ['pay_time', []],
            ['order_status', ''],
            ['is_settlement', ''],
            ['label_id', ''],
            ['store_name', '']
        ]);
        return success((new OrderService())->getPage($data));
    }


    /**
     * 订单列表(少关联)
     * @description 订单列表
     * @return void
     */
    public function getSimplePage()
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['technician_id', ''],
            ['order_from', ''],
            ['create_time', []],
        ]);
        return success((new OrderService())->getSimplePage($data));
    }


    public function setLabel()
    {
        $data = $this->request->params([
            ['order_ids', ''],
            ['label_id', ''],
        ]);
        return success((new OrderService())->setLabel($data));
    }








    /**
     * 订单详情
     * @description 订单详情
     * @param int $order_id
     * @return void
     */
    public function detail(int $order_id)
    {
        return success((new OrderService())->getDetail($order_id));
    }


    /**
     * 选择可以配送的师傅
     * @description 获取订单状态
     * @return void
     */
    public function selecttechnician()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['real_name','']
        ]);
        return success((new OrderService())->selecttechnician($data['order_id'],$data));
    }


    /**
     * 订单派单
     * @description 订单派单
     * @return void
     */
    public function dispatch()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['technician_id', 0],
        ]);
        return success('SUCCESS', (new OrderService())->orderDispatch($data));
    }


    /**
     * 转单
     * @param $order_id
     * @return \think\Response
     */
    public function transfer()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['technician_id', ''],
        ]);
        return success('SUCCESS', (new OrderService())->orderTransfer($data));
    }



    /**
     * 订单详情
     * @description 订单详情
     * @param int $order_id
     * @return void
     */
    public function orderClose(int $order_id)
    {
        return success((new OrderService())->orderClose($order_id));
    }


    /**
     * @description 删除订单
     * @return Response
     */
    public function delete()
    {
        $params = $this->request->params([
            ['order_ids', []]
        ]);
        $order_ids = $params['order_ids'];
        $res = (new OrderService)->delete($order_ids);
        return success("DELETE_SUCCESS");

    }

    /**
     * @description 批量催单
     * @return Response
     */
    public function reminder()
    {
        $params = $this->request->params([
            ['order_ids', []]
        ]);
        $order_ids = $params['order_ids'];
        $res = (new OrderService)->reminder($order_ids);
        return success("SUCCESS");

    }


}
