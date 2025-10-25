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

namespace addon\recycle\app\adminapi\controller\recycle_device_brand;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\recycle_device_brand\RecycleDeviceBrandService;


/**
 * 回收设备品牌控制器
 * Class RecycleDeviceBrand
 * @package addon\recycle\app\adminapi\controller\recycle_device_brand
 */
class RecycleDeviceBrand extends BaseAdminController
{
   /**
    * 获取回收设备品牌列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["brand_name",""],
             ["brand_code",""],
             ["status",""],
             ["sort",""]
        ]);
        return success((new RecycleDeviceBrandService())->getPage($data));
    }

    /**
     * 回收设备品牌详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new RecycleDeviceBrandService())->getInfo($id));
    }

    /**
     * 添加回收设备品牌
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["brand_name",""],
             ["brand_code",""],
             ["status",0],
             ["sort",0]
        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_device_brand\RecycleDeviceBrand.add');
        $id = (new RecycleDeviceBrandService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 回收设备品牌编辑
     * @param $id  回收设备品牌id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["brand_name",""],
             ["brand_code",""],
             ["status",0],
             ["sort",0]
        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_device_brand\RecycleDeviceBrand.edit');
        (new RecycleDeviceBrandService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 回收设备品牌删除
     * @param $id  回收设备品牌id
     * @return \think\Response
     */
    public function del(int $id){
        (new RecycleDeviceBrandService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
}
