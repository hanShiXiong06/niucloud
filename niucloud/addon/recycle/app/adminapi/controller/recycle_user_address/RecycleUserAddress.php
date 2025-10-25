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

namespace addon\recycle\app\adminapi\controller\recycle_user_address;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\recycle_user_address\RecycleUserAddressService;


/**
 * 用户退货地址控制器
 * Class RecycleUserAddress
 * @package addon\recycle\app\adminapi\controller\recycle_user_address
 */
class RecycleUserAddress extends BaseAdminController
{
   /**
    * 获取用户退货地址列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["address",""],
             ["id_card",""],
             ["mobile",""],
             ["card_pic",""],
             ["name",""],
             ["create_time",["",""]],
             ["update_time",["",""]]
        ]);
        return success((new RecycleUserAddressService())->getPage($data));
    }

    /**
     * 用户退货地址详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new RecycleUserAddressService())->getInfo($id));
    }

    /**
     * 添加用户退货地址
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["address",""],
             ["id_card",""],
             ["mobile",""],
             ["card_pic",""],
             ["name",""],

        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_user_address\RecycleUserAddress.add');
        $id = (new RecycleUserAddressService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 用户退货地址编辑
     * @param $id  用户退货地址id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["address",""],
             ["id_card",""],
             ["mobile",""],
             ["card_pic",""],
             ["name",""],

        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_user_address\RecycleUserAddress.edit');
        (new RecycleUserAddressService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 用户退货地址删除
     * @param $id  用户退货地址id
     * @return \think\Response
     */
    public function del(int $id){
        (new RecycleUserAddressService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
         return success(( new RecycleUserAddressService())->getMemberAll());
    }

}
