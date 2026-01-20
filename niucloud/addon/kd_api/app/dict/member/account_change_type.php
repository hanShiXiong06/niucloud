<?php

use app\dict\member\MemberAccountTypeDict;

return [
    //会员佣金
    MemberAccountTypeDict::COMMISSION => [
        'kd_api_award' => [
            //名称
            'name' => '快递API推广激励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
    ]
];
