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
 * 聚合快递品牌列表服务层
 * Class TkjhkdBrandService
 * @package app\service\admin\tkjhkd_brand
 */
class TkjhkdBrandService extends BaseAdminService
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
        $field = 'delivery_type,name,logo';
        $order = '';
        $search_model = $this->model->where([['site_id', '=', 0]])->withSearch(["id","site_id","delivery_type","name","logo","status","create_time","update_time","addon"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

}