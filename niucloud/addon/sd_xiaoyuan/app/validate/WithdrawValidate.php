<?php

namespace addon\sd_xiaoyuan\app\validate;

use think\Validate;

class WithdrawValidate extends Validate
{
    protected $rule = [
        'amount' => 'require|float|gt:0',
        'withdraw_type' => 'require|in:WECHAT,ALIPAY,BANK'
    ];

    protected $message = [
        'amount.require' => '请输入提现金额',
        'amount.float' => '提现金额格式不正确',
        'amount.gt' => '提现金额必须大于0',
        'withdraw_type.require' => '请选择提现方式',
        'withdraw_type.in' => '提现方式不正确'
    ];

    protected $scene = [
        'apply' => ['amount', 'withdraw_type']
    ];
}
