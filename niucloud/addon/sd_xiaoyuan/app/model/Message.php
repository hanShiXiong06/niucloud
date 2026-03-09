<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 系统消息模型
 */
class Message extends BaseModel
{
    protected $name = 'xiaoyuan_message';
    protected $pk = 'id';

    // 消息类型
    const TYPE_SYSTEM = 'SYSTEM';           // 系统消息
    const TYPE_ORDER = 'ORDER';             // 订单消息
    const TYPE_COMMUNITY = 'COMMUNITY';     // 社区消息
    const TYPE_COMMENT = 'COMMENT';         // 评论消息
    const TYPE_TASK = 'TASK';               // 任务消息
    const TYPE_GROUP = 'GROUP';             // 拼单消息
    const TYPE_SECONDHAND = 'SECONDHAND';   // 二手交易消息
    const TYPE_LOSTFOUND = 'LOSTFOUND';     // 失物招领消息
    const TYPE_WALLET = 'WALLET';           // 钱包消息

    /**
     * 获取消息类型列表
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_SYSTEM => '系统消息',
            self::TYPE_ORDER => '订单消息',
            self::TYPE_COMMUNITY => '社区消息',
            self::TYPE_COMMENT => '评论消息',
            self::TYPE_TASK => '任务消息',
            self::TYPE_GROUP => '拼单消息',
            self::TYPE_SECONDHAND => '二手交易',
            self::TYPE_LOSTFOUND => '失物招领',
            self::TYPE_WALLET => '钱包消息',
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
     * 搜索器:消息类型
     */
    public function searchTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('type', $value);
        }
    }

    /**
     * 搜索器:是否已读
     */
    public function searchIsReadAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('is_read', $value);
        }
    }
}
