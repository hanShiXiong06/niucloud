<?php
declare(strict_types=1);

use app\dict\member\MemberAccountTypeDict;

return [
    MemberAccountTypeDict::COMMISSION => [
        'hsx_lead_cooperation_income' => [
            'name' => '线索协作收益',
            'inc' => 1,
            'dec' => 0,
            'is_change_get' => 1,
        ],
        'hsx_lead_promotion_income' => [
            'name' => '商户一级推广奖励',
            'inc' => 1,
            'dec' => 0,
            'is_change_get' => 1,
        ],
        'hsx_lead_refund_reserve' => ['name'=>'订单退款预留收益','inc'=>0,'dec'=>1,'is_change_get'=>0],
        'hsx_lead_refund_release' => ['name'=>'退款失败释放预留收益','inc'=>1,'dec'=>0,'is_change_get'=>0],
    ],
];
