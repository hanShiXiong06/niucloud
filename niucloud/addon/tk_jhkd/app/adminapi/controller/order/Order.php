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

namespace addon\tk_jhkd\app\adminapi\controller\order;

use addon\tk_jhkd\app\listener\notice_template\OrderSign;
use addon\tk_jhkd\app\service\api\OrderService as ApiOrderService;
use addon\tk_jhkd\app\service\core\TranceService;
use addon\tk_jhkd\app\service\core\YidaService;
use app\dict\common\ChannelDict;
use app\dict\pay\PayDict;
use app\service\admin\member\MemberService;
use core\base\BaseAdminController;
use addon\tk_jhkd\app\service\admin\order\OrderService;
use think\Response;


/**
 * 订单列控制器
 * Class Order
 * @package addon\tk_jhkd\app\adminapi\controller\order
 */
class Order extends BaseAdminController
{
    public function getStat(){
        return success((new OrderService())->getStat());
    }
    public function uploadWeappDelivery()
    {
        return success('操作成功', (new OrderService())->uploadWeappDelivery());
    }
    public function commissionOrder($id)
    {
        return success('操作成功', (new OrderService())->commissionOrder($id));
    }
    public function getLink()
    {
        return success((new OrderService())->getLink());
    }
    public function changeStatus()
    {
        $data = $this->request->params([

            ["order_id", ""],
            ["order_status", 0],
        ]);
        return success('操作成功', (new OrderService())->changeStatus($data));
    }
    /**
     * 获取订单列列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["member_id", ""],
            ["order_from", ""],
            ["order_id", ""],
            ["out_trade_no", ""],
            ["is_send", ""],
            ["order_status", ""],
            ["refund_status", ""],
            ["remark", ""],
            ["create_time", ["", ""]],
            ["keyword", ""]
        ]);
        return success((new OrderService())->getPage($data));
    }

    /**
     * 订单列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new OrderService())->getInfo($id));
    }
    public function fxinfo($id)
    {
        return success((new OrderService())->getFxInfo($id));
    }
    /**
     * 添加订单列
     * @return \think\Response
     */

    public function remark(int $id)
    {
        $data = $this->request->params([
            ["id", ""],
            ["remark", ""],
        ]);
        (new OrderService())->edit($data['id'], $data);
        return success('EDIT_SUCCESS');
    }
    /**
     * 订单列编辑
     * @param $id  订单列id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ["member_id", 0],
            ["order_from", ""],
            ["order_id", ""],
            ["order_money", 0.00],
            ["order_discount_money", 0.00],
            ["is_send", 0],
            ["order_status", 0],
            ["refund_status", 0],
            ["out_trade_no", ""],
            ["remark", ""],
            ["pay_time", 0],
            ["close_reason", ""],
            ["is_enable_refund", ""],
            ["close_time", 0],
            ["ip", ""],
            ["delete_time", 0],
            ["send_log", ""]
        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\order\Order.edit');
        (new OrderService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }
    public function cancelOrder()
    {
        $data = $this->request->params([
            ["id", ""],
            ["close_reason", "后台退款"]
        ]);
        return success('操作成功',  event('CancelOrder', $data));
    }
    /**
     * 发单
     * @param int $id
     * @return \think\Response
     */
    public function sendOrder($order_id)
    {
        return success('操作成功', (new OrderService())->sendOrder($order_id));
    }
    /**
     * 订单列删除
     * @param $id  订单列id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new OrderService())->del($id);
        return success('DELETE_SUCCESS');
    }


    public function getMemberAll()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['register_type', ''],
            ['register_channel', ''],
            ['create_time', []],
            ['member_label', 0],
            ['member_level', 0],
        ]);
        return success((new MemberService())->getPage($data));
    }
    /**
     * 获取支付方式
     * @return Response
     */
    public function getPayType()
    {
        return success(PayDict::getPayType());
    }

    /**
     * 获取订单来源
     */
    public function getOrderFrom()
    {
        return success(ChannelDict::getType());
    }
    /**
     * 物流信息查询
     */
    public function deliveryInfo($deliveryid)
    {
        return success((new TranceService())->deliveryTrance(['delivery_id' => $deliveryid]));
    }
}
