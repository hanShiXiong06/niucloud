<?php

namespace addon\sd_xiaoyuan\app\model\runner;

use core\base\BaseModel;

class Runner extends BaseModel
{
    protected $name = 'xiaoyuan_runner';
    protected $pk = 'id';

    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            0 => '待审核',
            1 => '已通过',
            2 => '已拒绝',
            3 => '已禁用'
        ];
        return $statusMap[$data['status']] ?? '未知';
    }
}
