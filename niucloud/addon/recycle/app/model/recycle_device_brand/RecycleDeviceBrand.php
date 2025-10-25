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

namespace addon\recycle\app\model\recycle_device_brand;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 回收设备品牌模型
 * Class RecycleDeviceBrand
 * @package addon\recycle\app\model\recycle_device_brand
 */
class RecycleDeviceBrand extends BaseModel
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
    protected $name = 'recycle_device_brand';

    

    

    /**
     * 搜索器:回收设备品牌
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
     * 搜索器:回收设备品牌品牌名称
     * @param $value
     * @param $data
     */
    public function searchBrandNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("brand_name", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:回收设备品牌品牌编码
     * @param $value
     * @param $data
     */
    public function searchBrandCodeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("brand_code", $value);
        }
    }
    
    /**
     * 搜索器:回收设备品牌状态
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
     * 搜索器:回收设备品牌排序
     * @param $value
     * @param $data
     */
    public function searchSortAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("sort", $value);
        }
    }
    
    

    

    
}
