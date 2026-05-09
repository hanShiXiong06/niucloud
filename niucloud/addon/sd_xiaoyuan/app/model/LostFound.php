<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 失物招领模型
 */
class LostFound extends BaseModel
{
    protected $name = 'xiaoyuan_lost_found';
    protected $pk = 'id';

    // 类型
    const TYPE_LOST = 'LOST';       // 我丢了
    const TYPE_FOUND = 'FOUND';     // 我捡到了

    // 状态常量
    const STATUS_PENDING = 0;       // 待审核
    const STATUS_ACTIVE = 1;        // 进行中
    const STATUS_RESOLVED = 2;      // 已找到/已归还
    const STATUS_CLOSED = 3;        // 已关闭

    /**
     * 获取类型列表
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_LOST => '我丢了',
            self::TYPE_FOUND => '我捡到了',
        ];
    }

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_ACTIVE => '进行中',
            self::STATUS_RESOLVED => '已找到/已归还',
            self::STATUS_CLOSED => '已关闭',
        ];
    }

    /**
     * 搜索器:类型
     */
    public function searchTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('type', $value);
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
        if ($value !== '' && $value > 0) {
            $query->where('school_id', $value);
        }
    }

    /**
     * 搜索器:校区
     */
    public function searchCampusAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('campus', $value);
        }
    }

    /**
     * 搜索器:分类
     */
    public function searchCategoryAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('category', $value);
        }
    }

    /**
     * 搜索器:关键词
     */
    public function searchKeywordAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->whereLike('title|content', '%' . $value . '%');
        }
    }

    /**
     * 搜索器:会员ID
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value > 0) {
            $query->where('member_id', $value);
        }
    }
}
