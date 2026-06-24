<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 跑腿任务模型
 */
class Task extends BaseModel
{
    protected $name = 'xiaoyuan_task';
    protected $pk = 'id';

    // 任务类型
    const TYPE_EXPRESS = 'EXPRESS';     // 代取快递
    const TYPE_TAKEOUT = 'TAKEOUT';     // 代拿外卖
    const TYPE_BUY = 'BUY';             // 代买物品
    const TYPE_QUEUE = 'QUEUE';         // 代排队
    const TYPE_PRINT = 'PRINT';         // 代打印
    const TYPE_SEAT = 'SEAT';           // 代占座
    const TYPE_ERRAND = 'ERRAND';       // 跑腿
    const TYPE_OTHER = 'OTHER';         // 其他

    // 任务状态
    const STATUS_UNPAID = 0;            // 待支付
    const STATUS_PENDING = 10;          // 待接单
    const STATUS_ACCEPTED = 20;         // 已接单
    const STATUS_PROCESSING = 30;       // 进行中
    const STATUS_CONFIRMING = 40;       // 待确认
    const STATUS_COMPLETED = 50;        // 已完成
    const STATUS_CANCELLED = 90;        // 已取消
    const STATUS_REFUNDED = 91;         // 已退款

    /**
     * 获取任务类型列表
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_EXPRESS => '代取快递',
            self::TYPE_TAKEOUT => '代拿外卖',
            self::TYPE_BUY => '代买物品',
            self::TYPE_QUEUE => '代排队',
            self::TYPE_PRINT => '代打印',
            self::TYPE_SEAT => '代占座',
            self::TYPE_ERRAND => '跑腿',
            self::TYPE_OTHER => '其他',
        ];
    }

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_UNPAID => '待支付',
            self::STATUS_PENDING => '待接单',
            self::STATUS_ACCEPTED => '已接单',
            self::STATUS_PROCESSING => '进行中',
            self::STATUS_CONFIRMING => '待确认',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_REFUNDED => '已退款',
        ];
    }

    /**
     * 搜索器:任务类型
     */
    public function searchTaskTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('task_type', $value);
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

    /**
     * 搜索器:学校ID
     */
    public function searchSchoolIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('school_id', $value);
        }
    }

    /**
     * 搜索器:校区
     */
    public function searchCampusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('campus', $value);
        }
    }

    /**
     * 搜索器:发布者ID
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('member_id', $value);
        }
    }

    /**
     * 搜索器:接单员ID
     */
    public function searchRunnerIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('runner_id', $value);
        }
    }
}
