<?php

use app\dict\member\MemberAccountTypeDict;

return [
    MemberAccountTypeDict::POINT => [
       
        'hsx_phone_query'=>[
            'name'=>'手机信息查询消费',
            'inc'=>0,
            'dec'=>1,
        ],
        'hsx_phone_query_refund'=>[
            'name'=>'手机信息查询失败返还',
            'inc'=>1,
            'dec'=>0,
        ],
        'query'=>[
            'name'=>'手机信息查询消费',
            'inc'=>0,
            'dec'=>1,
        ],
    ],
    MemberAccountTypeDict::BALANCE => [
       
        'hsx_phone_query'=>[
            'name'=>'手机信息查询消费',
            'inc'=>0,
            'dec'=>1,
        ],'query'=>[
            'name'=>'手机信息查询消费',
            'inc'=>0,
            'dec'=>1,
        ],
        
    ],
  
];
