<?php

use app\dict\member\MemberAccountTypeDict;

return [
    // 可提现金额（跑腿员收益）
    MemberAccountTypeDict::MONEY => [
        'sd_xiaoyuan_runner_income' => [
            'name' => '跑腿订单收入',
            'inc' => 1,
            'dec' => 0,
            'is_change_get' => 1,
        ],
        'sd_xiaoyuan_runner_refund' => [
            'name' => '跑腿订单退款',
            'inc' => 0,
            'dec' => 1,
        ],
        'sd_xiaoyuan_invite_reward' => [
            'name' => '邀请奖励',
            'inc' => 1,
            'dec' => 0,
            'is_change_get' => 1,
        ]
    ],
    // 余额
    MemberAccountTypeDict::BALANCE => [
        'sd_xiaoyuan_refund' => [
            'name' => '校园帮退款',
            'inc' => 1,
            'dec' => 0,
        ],
        'sd_xiaoyuan_pay' => [
            'name' => '校园帮支付',
            'inc' => 0,
            'dec' => 1,
        ]
    ],
    // 佣金（分销佣金）
    MemberAccountTypeDict::COMMISSION => [
        'sd_xiaoyuan_commission' => [
            'name' => '校园帮佣金收入',
            'inc' => 1,
            'dec' => 0,
            'is_change_get' => 1,
        ],
        'sd_xiaoyuan_commission_refund' => [
            'name' => '校园帮佣金退款',
            'inc' => 0,
            'dec' => 1,
        ]
    ],
    // 积分
    MemberAccountTypeDict::POINT => [
        'sd_xiaoyuan_sign' => [
            'name' => '签到奖励',
            'inc' => 1,
            'dec' => 0,
        ],
        'sd_xiaoyuan_invite' => [
            'name' => '邀请奖励积分',
            'inc' => 1,
            'dec' => 0,
        ],
        'points_exchange' => [
            'name' => '积分商城兑换',
            'inc' => 0,
            'dec' => 1,
        ]
    ]
];
