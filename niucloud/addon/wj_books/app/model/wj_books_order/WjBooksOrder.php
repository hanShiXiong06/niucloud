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

namespace addon\wj_books\app\model\wj_books_order;

use core\base\BaseModel;
use think\model\relation\HasMany;

/**
 * 二手书籍回收订单模型
 * Class WjBooksOrder
 * @package addon\wj_books\app\model\wj_books_order
 */
class WjBooksOrder extends BaseModel
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
    protected $name = 'wj_books_order';

    /**
     * 软删除
     */
    public function softDelete()
    {
        return ['deleted' => 1];
    }

    /**
     * 搜索器:订单编号
     * @param $query
     * @param $value
     */
    public function searchOrderNoAttr($query, $value)
    {
        if ($value) {
            $query->where("order_no", 'like', "%$value%");
        }
    }

    /**
     * 搜索器:会员ID
     * @param $query
     * @param $value
     */
    public function searchMemberIdAttr($query, $value)
    {
        if ($value) {
            $query->where("member_id", $value);
        }
    }

    /**
     * 搜索器:订单状态
     * @param $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where("status", $value);
        }
    }

    /**
     * 搜索器:创建时间范围
     * @param $query
     * @param $value
     */
    public function searchCreateTimeAttr($query, $value)
    {
        if (!empty($value) && is_array($value)) {
            $start_time = $value[0] ?? null;
            $end_time = $value[1] ?? null;
            if ($start_time && $end_time) {
                $query->whereBetweenTime('create_time', $start_time, $end_time);
            }
        }
    }

    /**
     * 搜索器:物流单号
     * @param $query
     * @param $value
     */
    public function searchExpressWaybillAttr($query, $value)
    {
        if ($value) {
            $query->where("express_waybill", 'like', "%$value%");
        }
    }

    /**
     * 关联订单书籍
     */
    public function orderBooks(): HasMany
    {
        return $this->hasMany('addon\wj_books\app\model\wj_books_order_book\WjBooksOrderBook', 'order_id', 'id');
    }

    /**
     * 关联拒收书籍
     */
    public function rejectedBooks(): HasMany
    {
        return $this->hasMany('addon\wj_books\app\model\wj_books_rejected_book\WjBooksRejectedBook', 'order_id', 'id');
    }

    /**
     * 关联物流日志
     */
    public function expressLogs(): HasMany
    {
        return $this->hasMany('addon\wj_books\app\model\wj_books_express_log\WjBooksExpressLog', 'order_id', 'id');
    }
} 