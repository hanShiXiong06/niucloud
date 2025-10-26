<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\model\card;

use addon\home_service\app\dict\card\CardOrderDict;
use app\dict\common\ChannelDict;
use app\model\member\Member;
use app\model\pay\Pay;
use core\base\BaseModel;
/**
 * 次卡订单模型
 * Class CardOrder
 * @package app\model\card_order
 */
class CardOrder extends BaseModel
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
    protected $name = 'home_service_card_order';

    /**
     * 搜索器:订单名称
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('order_name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 订单状态筛选器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderStatusAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('order_status', '=', $value);
        }
    }


    public function searchOrderIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('order_id', '=', $value);
        }
    }


    /**
     * 会员id搜索
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('member_id', '=', $value);
        }
    }

    /**
     * 订单号
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderNoAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('order_no', 'like', '%' . $value . '%');
        }
    }

    /**
     * 订单来源
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderFromAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('order_from', '=', $value);
        }
    }


    /**
     * 创建时间搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchJoinCreateTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('order.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['order.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['order.create_time', '<=', $end_time]]);
        }
    }

    /**
     * 创建时间搜索器
     * @param $value
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['create_time', '<=', $end_time]]);
        }
    }

    /**
     * 支付时间筛选
     * @param $query
     * @param $value
     * @param $data
     * @return void
     */
    public function searchPayTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('pay_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['pay_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['pay_time', '<=', $end_time]]);
        }
    }

    /**
     * 购买会员筛选
     * @param $query
     * @param $value
     * @param $data
     * @return void
     */
    public function searchMemberSearchTextAttr($query, $value, $data)
    {
        if ($value) {
            $member_ids = (new Member)->where([['username|member_no|nickname|mobile', 'like', '%' . $value . '%'], ['site_id', '=', $data['site_id']]])->column('member_id');
            if ($member_ids) $query->where('member_id', 'in', $member_ids);
        }
    }


    /**
     * @param $value
     * @param $data
     * @return mixed|void
     * @throws \Exception
     */
    public function getOrderStatusNameAttr($value, $data)
    {
        if (isset($data['order_status'])) {
            return CardOrderDict::getStatus($data['order_status'])['name'] ?? '';
        }
    }

    /**
     * 登录渠道字段转化
     * @param $value
     * @return mixed
     */
    public function getOrderFromNameAttr($value, $data)
    {
        if (isset($data['order_from'])) {
            return ChannelDict::getType()[$data['order_from']] ?? '';
        }
    }



    public function item()
    {
        return $this->hasMany(CardOrderItem::class, 'order_id', 'order_id');
    }

    /**
     * 关联会员
     * @return \think\model\relation\HasOne
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }
    /**
     * 支付记录
     * @return \think\model\relation\HasOne
     */
    public function pay()
    {
        return $this->hasOne(Pay::class, 'out_trade_no', 'out_trade_no');
    }

}
