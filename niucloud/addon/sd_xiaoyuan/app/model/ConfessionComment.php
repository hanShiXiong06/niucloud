<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 表白墙评论模型
 */
class ConfessionComment extends BaseModel
{
    protected $name = 'xiaoyuan_confession_comment';
    protected $pk = 'id';

    protected $type = [];

    /**
     * 搜索器：站点ID
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('site_id', '=', $value);
        }
    }

    /**
     * 搜索器：表白ID
     */
    public function searchConfessionIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('confession_id', '=', $value);
        }
    }

    /**
     * 搜索器：会员ID
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('member_id', '=', $value);
        }
    }
}
