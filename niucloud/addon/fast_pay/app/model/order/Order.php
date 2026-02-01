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

namespace addon\fast_pay\app\model\order;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;

/**
 * 快速支付订单模型
 * Class Order
 * @package addon\fast_pay\app\model\order
 */
class Order extends BaseModel
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
    protected $name = 'fastpay_order';

    

    

    /**
     * 搜索器:快速支付订单会员id
     * @param $value
     * @param $data
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("member_id", $value);
        }
    }
    
    /**
     * 搜索器:快速支付订单订单来源
     * @param $value
     * @param $data
     */
    public function searchOrderFromAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_from", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:快速支付订单订单id
     * @param $value
     * @param $data
     */
    public function searchOrderIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_id", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:快速支付订单支付编号
     * @param $value
     * @param $data
     */
    public function searchOutTradeNoAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("out_trade_no", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:快速支付订单支付时间
     * @param $value
     * @param $data
     */
    public function searchPayTimeAttr($query, $value, $data)
    {
        $start = empty($value[0]) ? 0 : strtotime($value[0]);
        $end = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start > 0 && $end > 0) {
             $query->where([["pay_time", "between", [$start, $end]]]);
        } else if ($start > 0 && $end == 0) {
            $query->where([["pay_time", ">=", $start]]);
        } else if ($start == 0 && $end > 0) {
            $query->where([["pay_time", "<=", $end]]);
        }
    }
    
    /**
     * 搜索器:快速支付订单是否允许退款
     * @param $value
     * @param $data
     */
    public function searchIsEnableRefundAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("is_enable_refund", $value);
        }
    }
    
    /**
     * 搜索器:快速支付订单订单关闭时间
     * @param $value
     * @param $data
     */
    public function searchCloseTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("close_time", $value);
        }
    }
    
    /**
     * 搜索器:快速支付订单会员ip
     * @param $value
     * @param $data
     */
    public function searchIpAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("ip", $value);
        }
    }
    
    /**
     * 搜索器:快速支付订单更新时间
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
     * 搜索器:快速支付订单删除时间
     * @param $value
     * @param $data
     */
    public function searchDeleteTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("delete_time", $value);
        }
    }
    
    

    

    
    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('nickname,member_id')->bind(['member_id_name'=>'nickname']);
    }

}
