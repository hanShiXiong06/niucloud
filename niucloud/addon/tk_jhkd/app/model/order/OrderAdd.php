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

namespace addon\tk_jhkd\app\model\order;

use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use app\model\member\Member;
use app\model\pay\Pay;
use core\base\BaseModel;

/**
 * 订单列模型
 * Class OrderAdd
 * @package addon\tk_jhkd\app\model\order
 */
class OrderAdd extends BaseModel
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
    protected $name = 'tkjhkd_order_add';


    /**
     * 搜索器:订单列会员id
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
     * 搜索器:订单列订单id
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
     * 搜索器:订单列订单状态
     * @param $value
     * @param $data
     */
    public function searchOrderStatusAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("order_status", $value);
        }
    }

    /**
     * 搜索器:订单列创建时间
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


    public function orderInfo()
    {
        return $this->hasOne(Order::class, 'order_id', 'order_id');
    }

    public function deliveryRealInfo()
    {
        return $this->hasOne(\addon\tk_jhkd\app\model\OrderDeliveryReal::class, 'order_id', 'order_id');
    }
    public function memberInfo()
    {
        return $this->hasOne(\app\model\member\Member::class, 'member_id', 'member_id');
    }

    public function deliveryInfo()
    {
        return $this->hasOne(OrderDelivery::class, 'order_id', 'order_id');
    }
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('nickname,member_id')->bind(['member_id_name' => 'nickname']);
    }


}
