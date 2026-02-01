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

namespace addon\fast_pay\app\adminapi\controller\businessorder;

use core\base\BaseAdminController;
use addon\fast_pay\app\service\admin\businessorder\BusinessOrderService;


/**
 * 商户订单列控制器
 * Class BusinessOrder
 * @package addon\fast_pay\app\adminapi\controller\businessorder
 */
class BusinessOrder extends BaseAdminController
{
   /**
    * 获取商户订单列列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["business_id",""],
             ["order_from",""],
             ["order_id",""],
             ["order_status",""],
             ["out_trade_no",""],
             ["pay_time",["",""]]
        ]);
        return success((new BusinessOrderService())->getPage($data));
    }

    /**
     * 商户订单列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new BusinessOrderService())->getInfo($id));
    }

    /**
     * 添加商户订单列
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["business_id",0],
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

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\businessorder\BusinessOrder.add');
        $id = (new BusinessOrderService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 商户订单列编辑
     * @param $id  商户订单列id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["business_id",0],
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

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\businessorder\BusinessOrder.edit');
        (new BusinessOrderService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 商户订单列删除
     * @param $id  商户订单列id
     * @return \think\Response
     */
    public function del(int $id){
        (new BusinessOrderService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
         return success(( new BusinessOrderService())->getMemberAll());
    }

    public function getBusinessAll(){
         return success(( new BusinessOrderService())->getBusinessAll());
    }

}
