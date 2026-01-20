<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\model\TkjhkdBrand;
use core\base\BaseAdminService;
/**
 * 聚合快递公共服务层
 * Class ConfigService
 * @package addon\tk_jhkd\service\core\jhkd
 */
class JhkdService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new TkjhkdBrand();
    }

    /**
     * 获取聚合快递品牌列表列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $where[] = ['site_id', '=', $this->site_id];
        $field = 'site_id,delivery_type,name,logo,status,addon';
        $order = '';

        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(["id","site_id","delivery_type","name","logo","status","create_time","update_time","addon"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取聚合快递品牌列表信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'site_id,delivery_type,name,logo,status,addon';

        $info = $this->model->field($field)->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加聚合快递品牌列表
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
     * 聚合快递品牌列表编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除聚合快递品牌列表
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $res = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
        return $res;
    }
}