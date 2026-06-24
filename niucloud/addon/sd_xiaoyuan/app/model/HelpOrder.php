<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

class HelpOrder extends BaseModel
{
    protected $name = 'sd_xiaoyuan_help_order';
    protected $pk = 'id';

    protected $type = [
        'create_time' => 'timestamp',
        'accept_time' => 'timestamp',
        'complete_time' => 'timestamp',
        'cancel_time' => 'timestamp'
    ];

    /**
     * 搜索器 - site_id
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('site_id', '=', $value);
        }
    }

    /**
     * 搜索器 - member_id
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('member_id', '=', $value);
        }
    }

    /**
     * 搜索器 - status
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', '=', $value);
        }
    }

    /**
     * 搜索器 - help_type
     */
    public function searchHelpTypeAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('help_type', '=', $value);
        }
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            0 => '待接单',
            1 => '进行中',
            2 => '已完成',
            3 => '已取消'
        ];
        return $statusMap[$data['status']] ?? '未知';
    }

    /**
     * 获取帮忙类型文本
     */
    public function getHelpTypeTextAttr($value, $data)
    {
        $typeMap = [
            'ERRAND' => '跑腿帮忙',
            'ONLINE' => '线上帮忙'
        ];
        return $typeMap[$data['help_type']] ?? '其他';
    }

    /**
     * 获取性别限制文本
     */
    public function getGenderLimitTextAttr($value, $data)
    {
        $genderMap = [
            'MALE' => '仅限男生',
            'FEMALE' => '仅限女生',
            'ALL' => '不限'
        ];
        return $genderMap[$data['gender_limit']] ?? '不限';
    }
}
