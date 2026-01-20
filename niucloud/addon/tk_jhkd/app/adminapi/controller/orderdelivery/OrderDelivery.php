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

namespace addon\tk_jhkd\app\adminapi\controller\orderdelivery;

use core\base\BaseAdminController;
use addon\tk_jhkd\app\service\admin\orderdelivery\OrderDeliveryService;


/**
 * 订单快递信息控制器
 * Class OrderDelivery
 * @package addon\tk_jhkd\app\adminapi\controller\orderdelivery
 */
class OrderDelivery extends BaseAdminController
{
   /**
    * 获取订单快递信息列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["order_no",""],
             ["start_address",""],
             ["end_address",""],
             ["goods",""],
             ["long",""],
             ["width",""],
             ["height",""],
             ["weight",""],
             ["package_count",""],
             ["delivery_id",""],
             ["delivery_status",""],
             ["delivery_type",""],
             ["customer_type",""],
             ["delivery_Business",""],
             ["delivery_info",""],
             ["online_pay",""],
             ["start_time",""],
             ["end_time",""],
             ["order_status_desc",""],
             ["order_status",""],
             ["courier_context",""],
             ["remark",""],
             ["create_time",""],
             ["update_time",""],
             ["delete_time",""]
        ]);
        return success((new OrderDeliveryService())->getPage($data));
    }

    /**
     * 订单快递信息详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new OrderDeliveryService())->getInfo($id));
    }

    /**
     * 添加订单快递信息
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["order_no",""],
             ["start_address",""],
             ["end_address",""],
             ["goods",""],
             ["long",0.00],
             ["width",0.00],
             ["height",0.00],
             ["weight",0.00],
             ["package_count",0],
             ["delivery_id",""],
             ["delivery_status",0],
             ["delivery_type",""],
             ["customer_type",""],
             ["delivery_Business",""],
             ["delivery_info",""],
             ["online_pay",""],
             ["start_time",""],
             ["end_time",""],
             ["order_status_desc",""],
             ["order_status",""],
             ["courier_context",""],
             ["remark",""],
             ["delete_time",0]
        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\orderdelivery\OrderDelivery.add');
        $id = (new OrderDeliveryService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 订单快递信息编辑
     * @param $id  订单快递信息id
     * @return \think\Response
     */
    public function edit($id){
        $data = $this->request->params([
             ["order_no",""],
             ["start_address",""],
             ["end_address",""],
             ["goods",""],
             ["long",0.00],
             ["width",0.00],
             ["height",0.00],
             ["weight",0.00],
             ["package_count",0],
             ["delivery_id",""],
             ["delivery_status",0],
             ["delivery_type",""],
             ["customer_type",""],
             ["delivery_Business",""],
             ["delivery_info",""],
             ["online_pay",""],
             ["start_time",""],
             ["end_time",""],
             ["order_status_desc",""],
             ["order_status",""],
             ["courier_context",""],
             ["remark",""],
             ["delete_time",0]
        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\orderdelivery\OrderDelivery.edit');
        (new OrderDeliveryService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 订单快递信息删除
     * @param $id  订单快递信息id
     * @return \think\Response
     */
    public function del(int $id){
        (new OrderDeliveryService())->del($id);
        return success('DELETE_SUCCESS');
    }


}
