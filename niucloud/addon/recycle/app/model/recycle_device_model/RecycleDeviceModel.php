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

namespace addon\recycle\app\model\recycle_device_model;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 回收设备型号模型
 * Class RecycleDeviceModel
 * @package addon\recycle\app\model\recycle_device_model
 */
class RecycleDeviceModel extends BaseModel
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
    protected $name = 'recycle_device_model';

    

    

    /**
     * 搜索器:回收设备型号
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
     * 搜索器:回收设备型号品牌ID
     * @param $value
     * @param $data
     */
    public function searchBrandIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("brand_id", $value);
        }
    }
    
    /**
     * 搜索器:回收设备型号型号名称
     * @param $value
     * @param $data
     */
    public function searchModelNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("model_name", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:回收设备型号网络型号
     * @param $value
     * @param $data
     */
    public function searchNetworkModelAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("network_model", $value);
        }
    }
    
    /**
     * 搜索器:回收设备型号容量
     * @param $value
     * @param $data
     */
    public function searchCapacityAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("capacity", $value);
        }
    }
    
    /**
     * 搜索器:回收设备型号设备类型
     * @param $value
     * @param $data
     */
    public function searchDeviceTypeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("device_type", $value);
        }
    }
    
    /**
     * 搜索器:回收设备型号状态
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
     * 搜索器:回收设备型号
     * @param $value
     * @param $data
     */
    public function searchCreateAtAttr($query, $value, $data)
    {
        $start = empty($value[0]) ? 0 : strtotime($value[0]);
        $end = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start > 0 && $end > 0) {
             $query->where([["create_at", "between", [$start, $end]]]);
        } else if ($start > 0 && $end == 0) {
            $query->where([["create_at", ">=", $start]]);
        } else if ($start == 0 && $end > 0) {
            $query->where([["create_at", "<=", $end]]);
        }
    }
    
    /**
     * 搜索器:回收设备型号
     * @param $value
     * @param $data
     */
    public function searchUpdateAtAttr($query, $value, $data)
    {
        $start = empty($value[0]) ? 0 : strtotime($value[0]);
        $end = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start > 0 && $end > 0) {
             $query->where([["update_at", "between", [$start, $end]]]);
        } else if ($start > 0 && $end == 0) {
            $query->where([["update_at", ">=", $start]]);
        } else if ($start == 0 && $end > 0) {
            $query->where([["update_at", "<=", $end]]);
        }
    }
    
    

    

    
}
