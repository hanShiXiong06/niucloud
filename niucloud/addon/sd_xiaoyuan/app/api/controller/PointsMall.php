<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\PointsMallService;
use core\base\BaseApiController;

/**
 * 积分商城API控制器
 */
class PointsMall extends BaseApiController
{
    /**
     * 商品列表
     */
    public function goodsList()
    {
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);

        $service = new PointsMallService();
        $data = $service->getGoodsList((int)$page, (int)$limit);
        return success($data);
    }

    /**
     * 商品详情
     */
    public function goodsDetail()
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        $service = new PointsMallService();
        $data = $service->getGoodsDetail((int)$id);
        return success($data);
    }

    /**
     * 兑换商品
     */
    public function exchange()
    {
        $goods_id = $this->request->param('goods_id', 0);
        $receiver_name = $this->request->param('receiver_name', '');
        $receiver_phone = $this->request->param('receiver_phone', '');
        $receiver_address = $this->request->param('receiver_address', '');
        $remark = $this->request->param('remark', '');

        if (empty($goods_id)) {
            return fail('请选择商品');
        }
        if (empty($receiver_name) || empty($receiver_phone) || empty($receiver_address)) {
            return fail('请填写完整的收货信息');
        }

        $service = new PointsMallService();
        $data = $service->exchange((int)$goods_id, $receiver_name, $receiver_phone, $receiver_address, $remark);
        return success($data);
    }

    /**
     * 我的兑换订单
     */
    public function myOrders()
    {
        $status = $this->request->param('status', -1);
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);

        $service = new PointsMallService();
        $data = $service->getMyOrders((int)$status, (int)$page, (int)$limit);
        return success($data);
    }

    /**
     * 订单详情
     */
    public function orderDetail()
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        $service = new PointsMallService();
        $data = $service->getOrderDetail((int)$id);
        return success($data);
    }

    /**
     * 物流查询
     */
    public function logistics()
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        $service = new PointsMallService();
        $data = $service->getLogistics((int)$id);
        return success($data);
    }
}
