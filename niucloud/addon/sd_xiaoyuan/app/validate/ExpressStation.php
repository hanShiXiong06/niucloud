<?php
namespace addon\sd_xiaoyuan\app\validate;

use core\base\BaseValidate;

class ExpressStation extends BaseValidate
{
    protected $rule = [
        'name' => 'require',
        'address' => 'require',
    ];

    protected $message = [
        'name.require' => '站点名称不能为空',
        'address.require' => '站点地址不能为空',
    ];

    protected $scene = [
        'add' => ['name', 'address'],
        'edit' => ['name', 'address'],
    ];
}
