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

namespace addon\recycle\app\service\admin\recycle_device_model;

use addon\recycle\app\model\recycle_device_model\RecycleDeviceModel;

use core\base\BaseAdminService;


/**
 * 回收设备型号服务层
 * Class RecycleDeviceModelService
 * @package addon\recycle\app\service\admin\recycle_device_model
 */
class RecycleDeviceModelService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceModel();
    }

    /**
     * 获取回收设备型号列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,brand_id,model_name,network_model,capacity,device_type,status,create_at,update_at';
        $order = '';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["id","brand_id","model_name","network_model","capacity","device_type","status","create_at","update_at"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取回收设备型号信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,brand_id,model_name,network_model,capacity,device_type,status,create_at,update_at';

        $info = $this->model->field($field)->where([['id', "=", $id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加回收设备型号
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 回收设备型号编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除回收设备型号
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
}
