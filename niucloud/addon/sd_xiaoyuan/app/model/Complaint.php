<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 投诉仲裁模型
 */
class Complaint extends BaseModel
{
    protected $name = 'xiaoyuan_complaint';
    protected $pk = 'id';

    // 状态
    const STATUS_PENDING = 0;       // 待处理
    const STATUS_PROCESSING = 1;    // 处理中
    const STATUS_COMPLETED = 2;     // 已完结

    // 处理结果
    const RESULT_VALID = 1;         // 投诉成立
    const RESULT_INVALID = 2;       // 投诉不成立

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完结',
        ];
    }

    /**
     * 获取处理结果列表
     */
    public static function getResultList(): array
    {
        return [
            self::RESULT_VALID => '投诉成立',
            self::RESULT_INVALID => '投诉不成立',
        ];
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

    /**
     * 搜索器:投诉人ID
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('member_id', $value);
        }
    }
}
