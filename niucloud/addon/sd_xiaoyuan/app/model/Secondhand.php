<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 二手交易模型
 */
class Secondhand extends BaseModel
{
    protected $name = 'xiaoyuan_secondhand';
    protected $pk = 'id';

    // 交易方式
    const TRADE_FACE = 'FACE';          // 面交
    const TRADE_EXPRESS = 'EXPRESS';    // 快递
    const TRADE_BOTH = 'BOTH';          // 都可以

    // 状态
    const STATUS_OFF = 0;               // 下架
    const STATUS_ON = 1;                // 在售
    const STATUS_SOLD = 2;              // 已售出
    const STATUS_DELETED = 3;           // 已删除

    /**
     * 获取交易方式列表
     */
    public static function getTradeMethodList(): array
    {
        return [
            self::TRADE_FACE => '面交',
            self::TRADE_EXPRESS => '快递',
            self::TRADE_BOTH => '都可以',
        ];
    }

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_OFF => '下架',
            self::STATUS_ON => '在售',
            self::STATUS_SOLD => '已售出',
            self::STATUS_DELETED => '已删除',
        ];
    }

    /**
     * 搜索器:分类ID (支持字符串类型分类)
     */
    public function searchCategoryIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('category_id', $value);
        }
    }

    /**
     * 获取分类列表
     */
    public static function getCategoryList(): array
    {
        return [
            'DIGITAL' => '数码电子',
            'BOOK' => '图书教材',
            'CLOTHES' => '服饰鞋包',
            'DAILY' => '生活用品',
            'SPORT' => '运动户外',
            'OTHER' => '其他',
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
     * 搜索器:标题关键词
     */
    public function searchKeywordAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->whereLike('title', '%' . $value . '%');
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

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(SecondhandCategory::class, 'category_id', 'id');
    }
}
