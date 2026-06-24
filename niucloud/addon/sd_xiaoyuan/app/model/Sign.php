<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 签到模型
 */
class Sign extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'xiaoyuan_sign';

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

    public function searchSignDateAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("sign_date", $value);
        }
    }
}
