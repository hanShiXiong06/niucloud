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

namespace addon\recycle\app\model\recycle_category;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 二手机分类模型
 * Class RecycleCategory
 * @package addon\recycle\app\model\recycle_category
 */
class RecycleCategory extends BaseModel
{

    

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'category_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_category';

    

    

    /**
     * 搜索器:二手机分类分类名称
     * @param $value
     * @param $data
     */
    public function searchCategoryNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("category_name", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:二手机分类层级
     * @param $value
     * @param $data
     */
    public function searchLevelAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("level", $value);
        }
    }
    
    /**
     * 搜索器:二手机分类上级分类id
     * @param $value
     * @param $data
     */
    public function searchPidAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("pid", $value);
        }
    }
    
    /**
     * 搜索器:二手机分类组装分类名称
     * @param $value
     * @param $data
     */
    public function searchCategoryFullNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("category_full_name", $value);
        }
    }
    
    /**
     * 搜索器:二手机分类是否显示（1：显示，0：不显示）
     * @param $value
     * @param $data
     */
    public function searchIsShowAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("is_show", $value);
        }
    }
    
    /**
     * 搜索器:二手机分类排序号
     * @param $value
     * @param $data
     */
    public function searchSortAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("sort", $value);
        }
    }
    
    /**
     * 搜索器:二手机分类创建时间
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
     * 搜索器:二手机分类修改时间
     * @param $value
     * @param $data
     */
    public function searchUpdateTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("update_time", $value);
        }
    }
    
    

    

    
}
