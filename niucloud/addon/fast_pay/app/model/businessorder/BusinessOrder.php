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

namespace addon\fast_pay\app\model\businessorder;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;


use addon\fast_pay\app\model\business\Business;

/**
 * 商户订单列模型
 * Class BusinessOrder
 * @package addon\fast_pay\app\model\businessorder
 */
class BusinessOrder extends BaseModel
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
    protected $name = 'fastpay_business_order';

    

    

    /**
     * 搜索器:商户订单列主键
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
     * 搜索器:商户订单列会员id
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
     * 搜索器:商户订单列关联商户
     * @param $value
     * @param $data
     */
    public function searchBusinessIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("business_id", $value);
        }
    }
    
    /**
     * 搜索器:商户订单列订单来源
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
     * 搜索器:商户订单列订单id
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
     * 搜索器:商户订单列订单状态
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
     * 搜索器:商户订单列支付编号
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
     * 搜索器:商户订单列支付时间
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
    
    

    

    
    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'site_id')->joinType('left')->withField('nickname,member_id')->bind(['site_id_name'=>'nickname']);
    }

    public function business(){
       return $this->hasOne(Business::class, 'id', 'business_id')->joinType('left')->withField('name,id')->bind(['business_id_name'=>'name']);
    }

}
