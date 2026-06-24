<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 用户信誉分模型
 */
class Credit extends BaseModel
{
    protected $name = 'xiaoyuan_credit';
    protected $pk = 'id';

    // 信誉分常量
    const INIT_SCORE = 100;         // 初始分数
    const COMPLETE_SCORE = 2;       // 订单完成加分
    const CANCEL_SCORE = -1;        // 取消订单扣分
    const COMPLAINT_MIN_SCORE = -10; // 投诉成立最小扣分
    const COMPLAINT_MAX_SCORE = -20; // 投诉成立最大扣分
    const RESTRICT_THRESHOLD = 60;  // 限制阈值(低于此分数限制接单与发布)

    /**
     * 搜索器:用户ID
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('member_id', $value);
        }
    }

    /**
     * 搜索器:是否受限
     */
    public function searchIsRestrictedAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('is_restricted', $value);
        }
    }
}
