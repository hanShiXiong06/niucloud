<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 班级课表模型
 */
class ClassSchedule extends BaseModel
{
    protected $name = 'xiaoyuan_class_schedule';
    protected $pk = 'id';

    public function searchClassIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('class_id', intval($value));
        }
    }

    public function searchSemesterAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('semester', $value);
        }
    }

    public function searchSiteIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('site_id', intval($value));
        }
    }

    public function searchSchoolIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('school_id', intval($value));
        }
    }
}
