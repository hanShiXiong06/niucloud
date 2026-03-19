<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\PointsAdminService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 积分商城后台管理控制器
 */
class PointsMall extends BaseAdminController
{
    // ========== 商品管理 ==========

    /**
     * 商品列表
     */
    public function goodsList(): Response
    {
        $data = $this->request->params([
            ['name', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        $list = (new PointsAdminService())->getGoodsPage($data);
        return success($list);
    }

    /**
     * 添加商品
     */
    public function addGoods(): Response
    {
        $data = $this->request->params([
            ['name', ''],
            ['image', ''],
            ['description', ''],
            ['points_price', 0],
            ['stock', 0],
            ['sort', 0],
            ['status', 1],
        ]);

        if (empty($data['name'])) {
            return fail('请填写商品名称');
        }
        if (empty($data['points_price'])) {
            return fail('请填写积分价格');
        }

        $id = (new PointsAdminService())->addGoods($data);
        return success(['id' => $id]);
    }

    /**
     * 编辑商品
     */
    public function editGoods(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['name', ''],
            ['image', ''],
            ['description', ''],
            ['points_price', 0],
            ['stock', 0],
            ['sort', 0],
            ['status', 1],
        ]);

        if (empty($id)) {
            return fail('参数错误');
        }

        (new PointsAdminService())->editGoods((int)$id, $data);
        return success('编辑成功');
    }

    /**
     * 删除商品
     */
    public function delGoods(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        (new PointsAdminService())->delGoods((int)$id);
        return success('删除成功');
    }

    // ========== 订单管理 ==========

    /**
     * 订单列表
     */
    public function orderList(): Response
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        $list = (new PointsAdminService())->getOrderPage($data);
        return success($list);
    }

    /**
     * 修改订单状态
     */
    public function setOrderStatus(): Response
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);

        if (empty($id)) {
            return fail('参数错误');
        }

        (new PointsAdminService())->setOrderStatus((int)$id, (int)$status);
        return success('操作成功');
    }

    /**
     * 发货
     */
    public function shipOrder(): Response
    {
        $id = $this->request->param('id', 0);
        $express_company = $this->request->param('express_company', '');
        $express_no = $this->request->param('express_no', '');

        if (empty($id)) {
            return fail('参数错误');
        }
        if (empty($express_company)) {
            return fail('请选择快递公司');
        }
        if (empty($express_no)) {
            return fail('请填写快递单号');
        }

        (new PointsAdminService())->shipOrder((int)$id, $express_company, $express_no);
        return success('发货成功');
    }

    /**
     * 物流查询
     */
    public function logistics(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        $data = (new PointsAdminService())->getLogistics((int)$id);
        return success($data);
    }
}
