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

namespace addon\phone_shop\app\service\api\goods;

use addon\phone_shop\app\model\goods\Brand;
use core\base\BaseApiService;
use core\exception\AdminException;


/**
 * 商品品牌服务层
 * Class BrandService
 * @package addon\phone_shop\app\service\admin\goods
 */
class BrandService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Brand();
    }

    /**
     * 获取商品品牌列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'brand_id,brand_name,logo,color_json,desc,sort,create_time';
        $order = 'brand_id desc';
        if (!empty($where[ 'order' ])) {
            $order = $where[ 'order' ] . ' ' . $where[ 'sort' ];
        }

        $search_model = $this->model->where([ [ 'site_id', '=', $this->site_id ] ])->withSearch([ "brand_name" ], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商品品牌列表
     * @param array $where
     * @param string $field
     * @return array
     */
    public function getList(array $where = [], $field = 'brand_id,brand_name,logo,color_json,desc,sort,create_time')
    {
        $order = 'sort desc';
        return $this->model->where([ [ 'site_id', '=', $this->site_id ] ])->withSearch([ "brand_name" ], $where)->field($field)->limit(10)->order($order)->select()->toArray();
    }

    /**
     * 获取商品品牌信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'brand_id,brand_name,logo,color_json,desc,sort,create_time';
        $info = $this->model->field($field)->where([ [ 'brand_id', '=', $id ], [ 'site_id', '=', $this->site_id ] ])->findOrEmpty()->toArray();
        return $info;
    }

  
  

}
