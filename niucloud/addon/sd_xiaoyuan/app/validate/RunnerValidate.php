<?php

namespace addon\sd_xiaoyuan\app\validate;

use think\Validate;

class RunnerValidate extends Validate
{
    protected $rule = [
        'real_name' => 'require|max:50',
        'mobile' => 'require|mobile',
        'student_cert' => 'require'
    ];

    protected $message = [
        'real_name.require' => '请输入真实姓名',
        'real_name.max' => '姓名不能超过50个字符',
        'mobile.require' => '请输入手机号',
        'mobile.mobile' => '手机号格式不正确',
        'student_cert.require' => '请上传学生证照片'
    ];

    protected $scene = [
        'apply' => ['real_name', 'mobile', 'student_cert']
    ];
}
