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

namespace addon\fast_pay\app\service\admin\businessactive;

use addon\fast_pay\app\model\businessactive\BusinessActive;
use addon\fast_pay\app\model\business\Business;

use core\base\BaseAdminService;


/**
 * 商户活动服务层
 * Class BusinessActiveService
 * @package addon\fast_pay\app\service\admin\businessactive
 */
class BusinessActiveService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new BusinessActive();
    }

    /**
     * 获取商户活动列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,business_id,name,desc,gift,image,contect,create_time,update_time,delete_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["id","business_id","name","create_time"], $where)->with(['business'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商户活动信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,business_id,name,desc,gift,image,contect,create_time,update_time,delete_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['business'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加商户活动
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
     * 商户活动编辑
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
     * 删除商户活动
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
    public function getBusinessAll(){
       $businessModel = new Business();
       return $businessModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

}
