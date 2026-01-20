<?php

use app\dict\member\MemberAccountTypeDict;

return [
    MemberAccountTypeDict::POINT => [
        //调整
        'tk_jhkd_order' => [
            //名称
            'name' => '快递激励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
    ],
    MemberAccountTypeDict::BALANCE => [
        //调整
        'tk_jhkd_order' => [
            //名称
            'name' => '快递激励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
    ],
    MemberAccountTypeDict::MONEY => [
           //调整
        'tk_jhkd_order' => [
            //名称
            'name' => '快递激励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
    ],
    //会员佣金
    MemberAccountTypeDict::COMMISSION => [
        'jhkd_award' => [
            //名称
            'name' => '快递分销佣金',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],

    ]
];
