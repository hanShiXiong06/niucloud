<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 课表模型
 */
class Schedule extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'xiaoyuan_schedule';

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

    public function searchSemesterAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("semester", $value);
        }
    }

    public function searchWeekDayAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("week_day", $value);
        }
    }
}
