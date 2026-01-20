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

use addon\tk_jhkd\app\service\admin\order\OrderAddService;
use addon\tk_jhkd\app\service\admin\order\OrderService;
use app\service\admin\member\MemberService;
use core\base\BaseAdminController;


/**
 * 订单列控制器
 * Class OrderAdd
 * @package addon\tk_jhkd\app\adminapi\controller\order
 */
class OrderAdd extends BaseAdminController
{
    public function sendNotice($id)
    {
        return success((new OrderAddService())->sendNotice($id));
    }

    /**
     * 获取订单列列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["member_id", ""],
            ["order_id", ""],
            ["order_status", ""],
            ["create_time", ["", ""]]
        ]);
        return success((new OrderAddService())->getPage($data));
    }

    /**
     * 订单列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new OrderAddService())->getInfo($id));
    }

    /**
     * 添加订单列
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["member_id", 0],
            ["order_id", ""],
            ["remark", ""],

        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\order\OrderAdd.add');
        $id = (new OrderAddService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
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
            ["order_id", ""],
            ["remark", ""]
        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\order\OrderAdd.edit');
        (new OrderAddService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }
    public function remark(int $id){
        $data = $this->request->params([
            ["id",""],
            ["remark",""],
        ]);
        (new OrderAddService())->edit($data['id'], $data);
        return success('EDIT_SUCCESS');
    }

    public function editOrderMoney()
    {
        $data = $this->request->params([
            ["id", ""],
            ["order_money", ""],
        ]);
        (new OrderAddService())->editOrderMoney($data['id'], $data);
        return success('EDIT_SUCCESS');
    }
    /**
     * 订单列删除
     * @param $id  订单列id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new OrderAddService())->del($id);
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

}
