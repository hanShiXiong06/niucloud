<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 树洞帖子模型
 */
class Community extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'xiaoyuan_community';

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("site_id", $value);
        }
    }

    public function searchCategoryIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("category_id", $value);
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("status", $value);
        }
    }

    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("member_id", $value);
        }
    }
}
