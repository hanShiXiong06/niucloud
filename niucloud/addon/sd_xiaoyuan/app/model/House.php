<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 房屋租赁模型
 */
class House extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'xiaoyuan_house';

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

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("status", $value);
        }
    }

    public function searchHouseTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("house_type", $value);
        }
    }
}
