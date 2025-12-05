<?php

use app\dict\member\MemberAccountTypeDict;

return [
    MemberAccountTypeDict::POINT => [
        'ai_image_award' => [
            //名称
            'name' => 'AI设计奖励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
        'ai_image_chat' => [
            //名称
            'name' => 'AI设计会话',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
        'ai_image_create' => [
            //名称
            'name' => 'AI设计创作',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
        'ai_image_share' => [
            //名称
            'name' => 'AI设计邀请新用户',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],

    ],
    MemberAccountTypeDict::BALANCE => [

        'ai_image_award'=>[
            //名称
            'name' => 'AI设计激励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],

    ],
    MemberAccountTypeDict::MONEY => [

        'ai_image_award'=>[
            //名称
            'name' => 'AI设计激励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],

    ],
    //会员佣金
    MemberAccountTypeDict::COMMISSION => [
        'ai_image_award'=>[
            //名称
            'name' => 'AI设计激励',
            //是否增加
            'inc' => 1,
            //是否减少
            'dec' => 1,
        ],
    ]
];
