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

namespace addon\tk_jhkd\app\adminapi\controller\tkjhkdorder;

use app\service\admin\member\MemberService;
use core\base\BaseAdminController;
use addon\tk_jhkd\app\service\admin\tkjhkdorder\TkjhkdorderService;


/**
 * 订单列控制器
 * Class Tkjhkdorder
 * @package addon\tk_jhkd\app\adminapi\controller\tkjhkdorder
 */
class Tkjhkdorder extends BaseAdminController
{
   /**
    * 获取订单列列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
            ["member_id",""],
            ["order_from",""],
            ["order_id",""],
            ["is_send",""],
            ["order_status",""],
            ["refund_status",""],
            ["remark",""],
            ["create_time",["",""]]
        ]);
        return success((new TkjhkdorderService())->getPage($data));
    }

    /**
     * 订单列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new TkjhkdorderService())->getInfo($id));
    }

    /**
     * 添加订单列
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["order_from",""],
             ["order_id",""],
             ["order_money",0.00],
             ["order_discount_money",0.00],
             ["is_send",""],
             ["is_pick",0],
             ["order_status",0],
             ["out_trade_no",""],
             ["remark",""],
             ["pay_time",0],
             ["close_reason",""],
             ["is_enable_refund",""],
             ["close_time",0],
             ["ip",""],
             ["delete_time",0]
        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\tkjhkdorder\Tkjhkdorder.add');
        $id = (new TkjhkdorderService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 订单列编辑
     * @param $id  订单列id
     * @return \think\Response
     */
    public function edit($id){
        $data = $this->request->params([
             ["member_id",0],
             ["order_from",""],
             ["order_id",""],
             ["order_money",0.00],
             ["order_discount_money",0.00],
             ["is_send",""],
             ["is_pick",0],
             ["order_status",0],
             ["out_trade_no",""],
             ["remark",""],
             ["pay_time",0],
             ["close_reason",""],
             ["is_enable_refund",""],
             ["close_time",0],
             ["ip",""],
             ["delete_time",0]
        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\tkjhkdorder\Tkjhkdorder.edit');
        (new TkjhkdorderService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 订单列删除
     * @param $id  订单列id
     * @return \think\Response
     */
    public function del(int $id){
        (new TkjhkdorderService())->del($id);
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
