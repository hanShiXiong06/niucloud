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

namespace addon\tk_jhkd\app\model\shop_order;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 商城发单模型
 * Class ShopOrder
 * @package addon\tk_jhkd\app\model\shop_order
 */
class ShopOrder extends BaseModel
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
    protected $name = 'tkjhkd_shop_order';

    

    

    /**
     * 搜索器:商城发单主键
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
     * 搜索器:商城发单商城订单号
     * @param $value
     * @param $data
     */
    public function searchOrderNoAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_no", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:商城发单快递单号
     * @param $value
     * @param $data
     */
    public function searchDeliveryIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("delivery_id", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:商城发单订单状态
     * @param $value
     * @param $data
     */
    public function searchOrderStatusNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_status_name", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:商城发单创建时间
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        $start = empty($value[0]) ? 0 : strtotime($value[0]);
        $end = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start > 0 && $end > 0) {
             $query->where([["create_time", "between", [$start, $end]]]);
        } else if ($start > 0 && $end == 0) {
            $query->where([["create_time", ">=", $start]]);
        } else if ($start == 0 && $end > 0) {
            $query->where([["create_time", "<=", $end]]);
        }
    }
    
    

    

    
}
