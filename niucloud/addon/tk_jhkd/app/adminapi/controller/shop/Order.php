<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\adminapi\controller\shop;

use addon\tk_jhkd\app\service\admin\shop\OrderService;
use addon\tk_jhkd\app\service\admin\shop\OrderDeliveryService;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Cache;
use think\Response;

class Order extends BaseAdminController
{
    public function cancelOrder()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['order_no', ''],
        ]);
        if (Cache::get((string)$data['order_id'])) throw new \Exception('请发单1分钟后再取消发单');
        return success('操作成功',(new OrderService())->cancelOrder($data));
    }

    /**
     * 订单列表
     * @return Response
     */
    public function lists()
    {
        if (!in_array('shop', (new OrderService())->checkShop())) {
            throw new CommonException('需要搭配niucloud-shop商城使用，暂无使用权限');
        }
        $data = $this->request->params([
            ['search_type', ''],
            ['search_name', ''],
            ['status', 2],
            ['pay_type', ''],
            ['order_from', ''],
            ['create_time', []],
            ['pay_time', []],
        ]);
        return success((new OrderService())->getPage($data));
    }

    /**
     * 订单详情
     * @param int $order_id
     * @return Response
     */
    public function detail(int $id)
    {
        return success((new OrderService())->getDetail($id));
    }

    public function getSendAddress()
    {
        $data = $this->request->params([
            ["is_default_delivery", ''],
            ["mobile", ""],
            ["full_address", ""]
        ]);
        return success((new OrderService())->sendAddress($data));
    }

    public function preOrder()
    {
        $data = $this->request->params([
            ["send_id", ''],
            ["order_id", ''],
            ['weight', 1],
            ['bj_price', '']
        ]);
        return success((new OrderService())->preOrder($data));
    }

    /**
     * 订单发货
     * @param $id
     * @return Response
     */
    public function orderDelivery()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['order_goods_ids', []],
            ['delivery_type', ''],
            ['express_company_id', ''],
            ['express_number', ''],
            ['local_deliver_id', 0],//配送员
            ['remark', ''],//配送员
            ['send_id', ''],
            ['bj_price', ''],
            ['predata', ''],
            ['weight', ''],
        ]);
        Cache::set((string)$data['order_id'], '发单', 60);
        return success("DELIVERY_SUCCESS", (new OrderDeliveryService())->delivery($data));
    }

    /**
     * 获取订单配送方式
     */
    public function getDeliveryType()
    {
        return success([
            'express' => '聚合快递',
            'no_express' => '无需物流'
        ]);
    }

}
