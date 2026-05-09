<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 积分兑换订单模型
 */
class PointsOrder extends BaseModel
{
    protected $name = 'xiaoyuan_points_order';
    protected $pk = 'id';

    // 状态常量
    const STATUS_PENDING = 0;   // 待发货
    const STATUS_SHIPPED = 1;   // 已发货
    const STATUS_COMPLETED = 2; // 已完成

    /**
     * 状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $map = [
            self::STATUS_PENDING => '待发货',
            self::STATUS_SHIPPED => '已发货',
            self::STATUS_COMPLETED => '已完成',
        ];
        return $map[$data['status']] ?? '未知';
    }

    /**
     * 搜索器:状态
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', intval($value));
        }
    }

    /**
     * 搜索器:订单号
     */
    public function searchOrderNoAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->whereLike('order_no', '%' . $value . '%');
        }
    }
}
