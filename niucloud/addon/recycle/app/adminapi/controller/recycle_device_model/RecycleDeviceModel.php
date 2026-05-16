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

namespace addon\recycle\app\adminapi\controller\recycle_device_model;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\recycle_device_model\RecycleDeviceModelService;


/**
 * 回收设备型号控制器
 * Class RecycleDeviceModel
 * @package addon\recycle\app\adminapi\controller\recycle_device_model
 */
class RecycleDeviceModel extends BaseAdminController
{
   /**
    * 获取回收设备型号列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["brand_id",""],
             ["model_name",""],
             ["network_model",""],
             ["capacity",""],
             ["device_type",""],
             ["status",""],
             ["create_at",["",""]],
             ["update_at",["",""]]
        ]);
        return success((new RecycleDeviceModelService())->getPage($data));
    }

    /**
     * 回收设备型号详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new RecycleDeviceModelService())->getInfo($id));
    }

    /**
     * 添加回收设备型号
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["brand_id",0],
             ["model_name",""],
             ["network_model",""],
             ["capacity",""],
             ["device_type",""],
             ["status",0],

        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_device_model\RecycleDeviceModel.add');
        $id = (new RecycleDeviceModelService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 回收设备型号编辑
     * @param $id  回收设备型号id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["brand_id",0],
             ["model_name",""],
             ["network_model",""],
             ["capacity",""],
             ["device_type",""],
             ["status",0],

        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_device_model\RecycleDeviceModel.edit');
        (new RecycleDeviceModelService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 回收设备型号删除
     * @param $id  回收设备型号id
     * @return \think\Response
     */
    public function del(int $id){
        (new RecycleDeviceModelService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
}
