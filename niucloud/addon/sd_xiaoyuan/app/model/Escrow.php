<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 担保交易模型
 */
class Escrow extends BaseModel
{
    protected $name = 'xiaoyuan_escrow';
    protected $pk = 'id';

    // 状态
    const STATUS_UNPAID = 0;        // 待支付
    const STATUS_HOLDING = 1;       // 担保中
    const STATUS_RELEASED = 2;      // 已释放
    const STATUS_REFUNDED = 3;      // 已退款
    const STATUS_DISPUTE = 4;       // 争议中

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_UNPAID => '待支付',
            self::STATUS_HOLDING => '担保中',
            self::STATUS_RELEASED => '已释放',
            self::STATUS_REFUNDED => '已退款',
            self::STATUS_DISPUTE => '争议中',
        ];
    }

    /**
     * 搜索器:业务类型
     */
    public function searchBizTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('biz_type', $value);
        }
    }

    /**
     * 搜索器:状态
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }
}
