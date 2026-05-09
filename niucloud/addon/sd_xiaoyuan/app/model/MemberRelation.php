<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 邀请关系模型(分销)
 */
class MemberRelation extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'xiaoyuan_member_relation';

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

    public function searchPidAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("pid", $value);
        }
    }

    public function searchPid2Attr($query, $value, $data)
    {
        if ($value) {
            $query->where("pid2", $value);
        }
    }
}
