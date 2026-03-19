<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

class CampusAuth extends BaseModel
{
    protected $name = 'xiaoyuan_campus_auth';
    protected $pk = 'id';

    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            0 => '待审核',
            1 => '已通过',
            2 => '已拒绝'
        ];
        return $statusMap[$data['status']] ?? '未知';
    }
}
