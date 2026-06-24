<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 信誉分变动记录模型
 */
class CreditLog extends BaseModel
{
    protected $name = 'xiaoyuan_credit_log';
    protected $pk = 'id';

    // 变动类型
    const TYPE_COMPLETE = 'COMPLETE';   // 订单完成
    const TYPE_CANCEL = 'CANCEL';       // 取消订单
    const TYPE_COMPLAINT = 'COMPLAINT'; // 投诉成立
    const TYPE_ADMIN = 'ADMIN';         // 管理员调整

    /**
     * 获取类型列表
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_COMPLETE => '订单完成',
            self::TYPE_CANCEL => '取消订单',
            self::TYPE_COMPLAINT => '投诉成立',
            self::TYPE_ADMIN => '管理员调整',
        ];
    }

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
     * 搜索器:类型
     */
    public function searchTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('type', $value);
        }
    }
}
