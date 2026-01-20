<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\model\coupon;

use addon\tk_jhkd\app\dict\coupon\CouponDict;
use core\base\BaseModel;
use think\db\Query;

/**
 * 优惠券模型
 */
class Coupon extends BaseModel
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
    protected $name = 'tkjhkd_coupon';

    protected $type = [
        'start_time' => 'timestamp',
        'end_time' => 'timestamp',
        'valid_start_time' => 'timestamp',
        'valid_end_time' => 'timestamp',
    ];


    public function getCouponPriceAttr($value, $data)
    {
        if (empty($data['price']))
        {
            return 0;
        }
        return rtrim(rtrim($data['price'], '0'), '.');

    }

    public function getCouponMinPriceAttr($value, $data)
    {
        if (empty($data['min_condition_money']))
        {
            return 0;
        }
        return rtrim(rtrim($data['min_condition_money'], '0'), '.');

    }

    /**
     * 搜索器:标题
     * @param $value
     * @param $data
     */
    public function searchTitleAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("title", 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }

    /**
     * 搜索器:状态
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("status",'=',$value);
        }
    }


    /**
     * 类型
     * @param $value
     * @param $data
     * @return string
     */
    public function getTypeNameAttr($value, $data)
    {
        if (empty($data['type']))
            return '';
        return CouponDict::getType()[$data['type']] ?? '';
    }

    /**
     * 领取类型
     * @param $value
     * @param $data
     * @return string
     */
    public function getReceiveTypeNameAttr($value, $data)
    {
        if (empty($data['receive_type']))
            return '';
        return CouponDict::getReceiveType()[$data['receive_type']] ?? '';
    }

    /**
     * 优惠券状态
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        return CouponDict::getStatus()[$data['status']] ?? '';
    }


    /**
     * 活动开始时间搜索器
     * @param Query $query
     * @param $value
     * @param $data
     */
    public function searchStartTimeAttr(Query $query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('start_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['start_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['start_time', '<=', $end_time]]);
        }
    }

    /**
     * 活动结束时间搜索器
     * @param Query $query
     * @param $value
     * @param $data
     */
    public function searchEndTimeAttr(Query $query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('end_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['end_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['end_time', '<=', $end_time]]);
        }
    }
}
