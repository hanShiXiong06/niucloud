<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 房屋租赁模型
 */
class House extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'xiaoyuan_house';

    // 状态常量
    const STATUS_PENDING = 0;       // 待审核
    const STATUS_PUBLISHED = 1;     // 已发布
    const STATUS_OFFLINE = 2;       // 已下架
    const STATUS_RENTED = 3;        // 已出租

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_PUBLISHED => '已发布',
            self::STATUS_OFFLINE => '已下架',
            self::STATUS_RENTED => '已出租',
        ];
    }

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("site_id", $value);
        }
    }

    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("member_id", $value);
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("status", $value);
        }
    }

    public function searchHouseTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("house_type", $value);
        }
    }
}
