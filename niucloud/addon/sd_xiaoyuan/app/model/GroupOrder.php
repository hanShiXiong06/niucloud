<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 拼单好饭模型
 */
class GroupOrder extends BaseModel
{
    protected $name = 'xiaoyuan_group_order';
    protected $pk = 'id';

    // 拼单类型
    const TYPE_TEA = 'TEA';         // 拼奶茶
    const TYPE_FOOD = 'FOOD';       // 拼外卖
    const TYPE_FRUIT = 'FRUIT';     // 拼水果
    const TYPE_RIDE = 'RIDE';       // 拼车
    const TYPE_OTHER = 'OTHER';     // 其他

    // 拼单状态
    const STATUS_GROUPING = 0;      // 拼单中
    const STATUS_SUCCESS = 10;      // 已成团
    const STATUS_DELIVERING = 20;   // 配送中
    const STATUS_COMPLETED = 30;    // 已完成
    const STATUS_CANCELLED = 90;    // 已取消
    const STATUS_FAILED = 91;       // 拼单失败

    /**
     * 获取拼单类型列表
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_TEA => '拼奶茶',
            self::TYPE_FOOD => '拼外卖',
            self::TYPE_FRUIT => '拼水果',
            self::TYPE_RIDE => '拼车',
            self::TYPE_OTHER => '其他',
        ];
    }

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_GROUPING => '拼单中',
            self::STATUS_SUCCESS => '已成团',
            self::STATUS_DELIVERING => '配送中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_FAILED => '拼单失败',
        ];
    }

    /**
     * 搜索器:拼单类型
     */
    public function searchGroupTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('group_type', $value);
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
     * 关联拼单成员
     */
    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id', 'id');
    }
}
