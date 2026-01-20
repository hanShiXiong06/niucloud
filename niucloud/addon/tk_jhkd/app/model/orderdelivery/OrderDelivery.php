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

namespace addon\tk_jhkd\app\model\orderdelivery;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;
use addon\tk_jhkd\app\model\TkjhkdBrand;
/**
 * 订单快递信息模型
 * Class OrderDelivery
 * @package addon\tk_jhkd\app\model\orderdelivery
 */
class OrderDelivery extends BaseModel
{

    

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'order_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tkjhkd_order_delivery';

    

    

    /**
     * 搜索器:订单快递信息
     * @param $value
     * @param $data
     */
    public function searchOrderIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_id", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息易达订单号
     * @param $value
     * @param $data
     */
    public function searchOrderNoAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_no", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息开始地址
     * @param $value
     * @param $data
     */
    public function searchStartAddressAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("start_address", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息结束地址
     * @param $value
     * @param $data
     */
    public function searchEndAddressAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("end_address", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息物品
     * @param $value
     * @param $data
     */
    public function searchGoodsAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("goods", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息长
     * @param $value
     * @param $data
     */
    public function searchLongAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("long", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息宽
     * @param $value
     * @param $data
     */
    public function searchWidthAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("width", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息高
     * @param $value
     * @param $data
     */
    public function searchHeightAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("height", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息重量
     * @param $value
     * @param $data
     */
    public function searchWeightAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("weight", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息包裹数
     * @param $value
     * @param $data
     */
    public function searchPackageCountAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("package_count", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息运送单号
     * @param $value
     * @param $data
     */
    public function searchDeliveryIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("delivery_id", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息订单状态
     * @param $value
     * @param $data
     */
    public function searchDeliveryStatusAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("delivery_status", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息快递公司
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
     * 搜索器:订单快递信息寄件通道
     * @param $value
     * @param $data
     */
    public function searchCustomerTypeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("customer_type", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息产品类型
     * @param $value
     * @param $data
     */
    public function searchDeliveryBusinessAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("delivery_Business", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息快递信息
     * @param $value
     * @param $data
     */
    public function searchDeliveryInfoAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("delivery_info", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息是否在线支付YNALL
     * @param $value
     * @param $data
     */
    public function searchOnlinePayAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("online_pay", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息预约开始时间
     * @param $value
     * @param $data
     */
    public function searchStartTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("start_time", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息预约结束时间
     * @param $value
     * @param $data
     */
    public function searchEndTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("end_time", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息订单状态描述
     * @param $value
     * @param $data
     */
    public function searchOrderStatusDescAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_status_desc", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息订单状态快递信息状态
     * @param $value
     * @param $data
     */
    public function searchOrderStatusAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_status", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息揽收信息
     * @param $value
     * @param $data
     */
    public function searchCourierContextAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("courier_context", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息订单备注
     * @param $value
     * @param $data
     */
    public function searchRemarkAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("remark", $value);
        }
    }
    
    /**
     * 搜索器:订单快递信息创建时间
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
     * 搜索器:订单快递信息更新时间
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
     * 搜索器:订单快递信息删除时间
     * @param $value
     * @param $data
     */
    public function searchDeleteTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("delete_time", $value);
        }
    }
    
    public function getDeliveryArryAttr($value, $data){
        if(isset($data['delivery_type'])&&$data['delivery_type']=='') return ["name"=>'',"logo"=>''];
        $brandInfo=(new TkjhkdBrand())->field(['name,logo'])->where(['delivery_type'=>$data['delivery_type']])->findOrEmpty()->toArray();
        return $brandInfo ?? ["name"=>'',"logo"=>''];
    }

    
}
