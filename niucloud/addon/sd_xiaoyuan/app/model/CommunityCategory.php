<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 树洞分类模型
 */
class CommunityCategory extends BaseModel
{
    protected $name = 'xiaoyuan_community_category';
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
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', '=', $value);
        }
    }
}
