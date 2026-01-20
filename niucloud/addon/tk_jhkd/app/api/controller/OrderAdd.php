<?php

namespace addon\tk_jhkd\app\api\controller;
use core\base\BaseController;
use addon\tk_jhkd\app\dict\order\JhkdOrderAddDict;
use addon\tk_jhkd\app\service\api\OrderAddService;
class OrderAdd extends BaseController
{

    /**
     * 获取订单列表
     */
    public function getOrderList()
    {
        $data = $this->request->params([
            ['order_status', ''],
        ]);
        return success((new OrderAddService())->getPage($data));
    }
    /**
     *获取订单状态数组
     */
    public function getOrderStatus()
    {
        return success(JhkdOrderAddDict::getStatus());
    }
    /**
     * 删除订单
     */
    public function deleteOrder(int $id)
    {
        return success('删除成功',(new OrderAddService())->del($id));
    }

}