<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\adminapi\controller\kdapi_order;

use core\base\BaseAdminController;
use addon\kd_api\app\service\admin\kdapi_order\KdapiOrderService;


/**
 * 订单列控制器
 * Class KdapiOrder
 * @package addon\kd_api\app\adminapi\controller\kdapi_order
 */
class KdapiOrder extends BaseAdminController
{
   /**
    * 获取订单列列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["order_id",""],
             ["title",""],
             ["status",""],
             ["is_js",""],
             ["sid",""],
             ["start_time",""],
             ["end_time",""],
        ]);
        $data['create_time']=[
            $data['start_time'],
            $data['end_time'],
        ];
        unset($data['start_time']);
        unset($data['end_time']);
        return success((new KdapiOrderService())->getPage($data));
    }

    /**
     * 订单列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new KdapiOrderService())->getInfo($id));
    }

    /**
     * 添加订单列
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["order_id",""],
             ["title",""],
             ["order_money",0.00],
             ["pay_money",0.00],
             ["commission",0.00],
             ["status",0],
             ["is_js",0],
             ["sid",""],
             ["pub_id",""],

        ]);
        $this->validate($data, 'addon\kd_api\app\validate\kdapi_order\KdapiOrder.add');
        $id = (new KdapiOrderService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 订单列编辑
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["order_id",""],
             ["title",""],
             ["order_money",0.00],
             ["pay_money",0.00],
             ["commission",0.00],
             ["status",0],
             ["is_js",0],
             ["sid",""],
             ["pub_id",""],

        ]);
        $this->validate($data, 'addon\kd_api\app\validate\kdapi_order\KdapiOrder.edit');
        (new KdapiOrderService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 订单列删除
     * @return \think\Response
     */
    public function del(int $id){
        (new KdapiOrderService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
         return success(( new KdapiOrderService())->getMemberAll());
    }

}
