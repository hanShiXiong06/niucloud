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

namespace addon\fast_pay\app\adminapi\controller\businessactive;

use core\base\BaseAdminController;
use addon\fast_pay\app\service\admin\businessactive\BusinessActiveService;


/**
 * 商户活动控制器
 * Class BusinessActive
 * @package addon\fast_pay\app\adminapi\controller\businessactive
 */
class BusinessActive extends BaseAdminController
{
   /**
    * 获取商户活动列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["business_id",""],
             ["name",""],
             ["create_time",""]
        ]);
        return success((new BusinessActiveService())->getPage($data));
    }

    /**
     * 商户活动详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new BusinessActiveService())->getInfo($id));
    }

    /**
     * 添加商户活动
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["business_id",0],
             ["name",""],
             ["desc",""],
             ["gift",""],
             ["image",""],
             ["contect",""],

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\businessactive\BusinessActive.add');
        $id = (new BusinessActiveService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 商户活动编辑
     * @param $id  商户活动id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["business_id",0],
             ["name",""],
             ["desc",""],
             ["gift",""],
             ["image",""],
             ["contect",""],

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\businessactive\BusinessActive.edit');
        (new BusinessActiveService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 商户活动删除
     * @param $id  商户活动id
     * @return \think\Response
     */
    public function del(int $id){
        (new BusinessActiveService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getBusinessAll(){
         return success(( new BusinessActiveService())->getBusinessAll());
    }

}
