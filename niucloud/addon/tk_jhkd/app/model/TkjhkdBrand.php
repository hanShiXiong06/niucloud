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

namespace addon\tk_jhkd\app\model;

use core\base\BaseModel;


/**
 * 聚合快递品牌列表模型
 * Class TkjhkdBrand
 * @package app\model\tkjhkd_brand
 */
class TkjhkdBrand extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tkjhkd_brand';

    /**
     * 搜索器:聚合快递品牌列表主键
     * @param $value
     * @param $data
     */
    public function searchIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("id", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表站点id
     * @param $value
     * @param $data
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("site_id", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表类型
     * @param $value
     * @param $data
     */
    public function searchDeliveryTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("delivery_type", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表名称
     * @param $value
     * @param $data
     */
    public function searchNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("name", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表logo
     * @param $value
     * @param $data
     */
    public function searchLogoAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("logo", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表是否启用 1启用 0不启用
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("status", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表创建时间
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("create_time", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表修改时间
     * @param $value
     * @param $data
     */
    public function searchUpdateTimeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("update_time", $value);
        }
    }
    
    /**
     * 搜索器:聚合快递品牌列表所属插件
     * @param $value
     * @param $data
     */
    public function searchAddonAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("addon", $value);
        }
    }
    
    

}
