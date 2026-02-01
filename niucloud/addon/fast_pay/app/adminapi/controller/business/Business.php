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

namespace addon\fast_pay\app\adminapi\controller\business;

use core\base\BaseAdminController;
use addon\fast_pay\app\service\admin\business\BusinessService;


/**
 * 商户列控制器
 * Class Business
 * @package addon\fast_pay\app\adminapi\controller\business
 */
class Business extends BaseAdminController
{
   /**
    * 获取商户列列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["mch_id",""],
             ["name",""],
             ["status",""],
             ["over_time",["",""]]
        ]);
        return success((new BusinessService())->getPage($data));
    }

    /**
     * 商户列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new BusinessService())->getInfo($id));
    }

    /**
     * 添加商户列
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["admin_id",0],
             ["mch_id",0],
             ["name",""],
             ["banner",""],
             ["desc",""],
             ["address",""],
             ["content",""],
             ["lat",""],
             ["lng",""],
             ["type",""],
             ["finderUserName",""],
             ["feedId",""],
             ["page",""],
             ["mini_appid",""],
             ["mini_page",""],
             ["active_num",0],
             ["status",""],
             ["over_time","2024-09-11 15:54:19"],

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\business\Business.add');
        $id = (new BusinessService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 商户列编辑
     * @param $id  商户列id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["admin_id",0],
             ["mch_id",0],
             ["name",""],
             ["banner",""],
             ["desc",""],
             ["address",""],
             ["content",""],
             ["lat",""],
             ["lng",""],
             ["type",""],
             ["finderUserName",""],
             ["feedId",""],
             ["page",""],
             ["mini_appid",""],
             ["mini_page",""],
             ["active_num",0],
             ["status",""],
             ["over_time","2024-09-11 15:54:19"],

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\business\Business.edit');
        (new BusinessService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 商户列删除
     * @param $id  商户列id
     * @return \think\Response
     */
    public function del(int $id){
        (new BusinessService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
         return success(( new BusinessService())->getMemberAll());
    }

}
