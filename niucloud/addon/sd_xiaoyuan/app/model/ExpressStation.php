<?php
namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

class ExpressStation extends BaseModel
{
    protected $name = 'xiaoyuan_express_station';
    protected $pk = 'id';

    protected $type = [
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    public function searchSchoolIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('school_id', '=', $value);
        }
    }

    public function searchNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', '=', $value);
        }
    }
}
