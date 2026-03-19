<?php

namespace addon\sd_xiaoyuan\app\validate;

use think\Validate;

class OrderValidate extends Validate
{
    protected $rule = [
        'task_type' => 'require|in:EXPRESS,BUY,ERRAND,QUEUE,PRINT,SEAT',
        'pickup_name' => 'require|max:50',
        'pickup_mobile' => 'require|mobile',
        'pickup_address' => 'require|max:200',
        'receive_name' => 'require|max:50',
        'receive_mobile' => 'require|mobile',
        'receive_address' => 'require|max:200'
    ];

    protected $message = [
        'task_type.require' => '请选择服务类型',
        'task_type.in' => '服务类型不正确',
        'pickup_name.require' => '请输入取件人姓名',
        'pickup_mobile.require' => '请输入取件人手机号',
        'pickup_mobile.mobile' => '取件人手机号格式不正确',
        'pickup_address.require' => '请输入取件地址',
        'receive_name.require' => '请输入收件人姓名',
        'receive_mobile.require' => '请输入收件人手机号',
        'receive_mobile.mobile' => '收件人手机号格式不正确',
        'receive_address.require' => '请输入收件地址'
    ];

    protected $scene = [
        'create' => ['task_type', 'pickup_name', 'pickup_mobile', 'pickup_address', 'receive_name', 'receive_mobile', 'receive_address']
    ];
}
