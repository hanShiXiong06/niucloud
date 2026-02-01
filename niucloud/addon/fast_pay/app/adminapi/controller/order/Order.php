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

namespace addon\fast_pay\app\adminapi\controller\order;

use app\service\admin\member\MemberService;
use core\base\BaseAdminController;
use addon\fast_pay\app\service\admin\order\OrderService;


/**
 * 快速支付订单控制器
 * Class Order
 * @package addon\fast_pay\app\adminapi\controller\order
 */
class Order extends BaseAdminController
{
   /**
    * 获取快速支付订单列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["order_from",""],
             ["order_id",""],
             ["out_trade_no",""],
             ["pay_time",["",""]],
             ["is_enable_refund",""],
             ["close_time",""],
             ["ip",""],
             ["update_time",""],
             ["delete_time",""]
        ]);
        return success((new OrderService())->getPage($data));
    }

    /**
     * 快速支付订单详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new OrderService())->getInfo($id));
    }

    /**
     * 添加快速支付订单
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["order_from",""],
             ["order_id",""],
             ["order_money",0.00],
             ["order_discount_money",0.00],
             ["order_status",0],
             ["refund_status",0],
             ["out_trade_no",""],
             ["remark",""],
             ["pay_time",0],
             ["close_reason",""],
             ["is_enable_refund",""],
             ["close_time",0],
             ["ip",""],
             ["delete_time",0]
        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\order\Order.add');
        $id = (new OrderService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 快速支付订单编辑
     * @param $id  快速支付订单id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["order_from",""],
             ["order_id",""],
             ["order_money",0.00],
             ["order_discount_money",0.00],
             ["order_status",0],
             ["refund_status",0],
             ["out_trade_no",""],
             ["remark",""],
             ["pay_time",0],
             ["close_reason",""],
             ["is_enable_refund",""],
             ["close_time",0],
             ["ip",""],
             ["delete_time",0]
        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\order\Order.edit');
        (new OrderService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 快速支付订单删除
     * @param $id  快速支付订单id
     * @return \think\Response
     */
    public function del(int $id){
        (new OrderService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
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

}
