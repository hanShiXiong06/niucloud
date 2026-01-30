<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址:https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\adminapi\controller\order;

use addon\phone_shop\app\service\admin\order\OfflineOrderService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 线下订单控制器
 */
class OfflineOrder extends BaseAdminController
{
    /**
     * 创建线下订单
     * @return Response
     */
    public function create()
    {
        $data = $this->request->params([
            ['member_id', 0],
            ['goods_list', []],
            ['pay_status', 'paid'],
            ['offline_pay_account', ''],
            ['remark', ''],
            ['delivery_type', 'store'],
            ['sku_name',''],
            ['taker_name', ''],
            ['taker_mobile', ''],
        ]);

        $result = (new OfflineOrderService())->create($data);
        return success('创建成功', $result);
    }

    /**
     * 获取线下订单列表
     * @return Response
     */
    public function lists()
    {
        $where = $this->request->params([
            ['order_no', ''],
            ['member_id', ''],
            ['pay_status', ''],
            ['create_time', []],
        ]);

        return success((new OfflineOrderService())->getPage($where));
    }

    /**
     * 挂单确认收款 - 逐步推进订单状态
     * 用于财务人员确认挂单客户已付款，并逐步推进订单状态
     *
     * 状态流转：
     * 1. HOLD(10) → WAIT_DELIVERY(2) - 确认收款
     * 2. WAIT_DELIVERY(2) → WAIT_TAKE(3) - 确认发货
     * 3. WAIT_TAKE(3) → FINISH(5) - 确认收货
     *
     * @return Response
     */
    public function confirmPayment()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['order_money', ''],        // 订单金额(可修改)
            ['pay_type', ''],           // 支付方式(仅在HOLD→WAIT_DELIVERY时需要)
            ['offline_pay_account', ''], // 收款账户(仅在HOLD→WAIT_DELIVERY时需要)
            ['order_goods', []],        // 订单商品价格列表(可修改单价)
        ]);

        $result = (new OfflineOrderService())->confirmHoldOrderPayment($data);
        return success($result['message'], $result);
    }
}
