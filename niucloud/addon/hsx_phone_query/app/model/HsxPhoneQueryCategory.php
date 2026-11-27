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

namespace addon\hsx_phone_query\app\model;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 分类模型
 * Class HsxPhoneQueryCategory
 * @package addon\hsx_phone_query\app\model\hsx_phone_query
 */
class HsxPhoneQueryCategory extends BaseModel
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
    protected $name = 'hsx_phone_query_category';

    

    

    /**
     * 搜索器:分类
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
     * 搜索器:分类分类id
     * @param $value
     * @param $data
     */
    public function searchTypeIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("type_id", $value);
        }
    }
    
    /**
     * 搜索器:分类分类名称
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
     * 搜索器:分类价格
     * @param $value
     * @param $data
     */
    public function searchPriceAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("price", $value);
        }
    }
    
    /**
     * 搜索器:显示状态
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchIsShowAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("is_show", $value);
        }
    }
    
    

    

    
}
