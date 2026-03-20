<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 班级模型
 */
class SchoolClass extends BaseModel
{
    protected $name = 'xiaoyuan_class';
    protected $pk = 'id';

    public function searchNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->whereLike('name', '%' . $value . '%');
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', intval($value));
        }
    }

    public function searchGradeAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('grade', $value);
        }
    }

    public function searchSchoolIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('school_id', intval($value));
        }
    }
}
