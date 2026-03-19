<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 树洞点赞模型
 */
class CommunityLike extends BaseModel
{
    protected $name = 'xiaoyuan_community_like';
    protected $pk = 'id';

    protected $type = [
        'create_time' => 'timestamp'
    ];

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
     * 搜索器：会员ID
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('member_id', '=', $value);
        }
    }

    /**
     * 搜索器：帖子ID
     */
    public function searchPostIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('post_id', '=', $value);
        }
    }
}
