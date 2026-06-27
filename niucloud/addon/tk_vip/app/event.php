<?php

return [
    'bind' => [

    ],
    'listen' => [
        //订单创建
        'PayCreate' => ['addon\tk_vip\app\listener\pay\PayCreateListener'],
        //支付成功
        'PaySuccess' => ['addon\tk_vip\app\listener\pay\PaySuccessListener'],
        //协议类型加载
        'AgreementType' => [ 'addon\tk_vip\app\listener\AgreementType' ],
        //日志
        'VipLog'=>[ 'addon\tk_vip\app\listener\VipLog'],
        //会员注册赠送会员等级
        'MemberRegister' => ['addon\tk_vip\app\listener\member\MemberRegisterListener'],
        //获取海报数据
        'GetPosterType' => ['addon\tk_vip\app\listener\poster\TkVipPosterType'],
        'GetPosterData' => ['addon\tk_vip\app\listener\poster\TkVipPoster'],
        //主题色
        'ThemeColor' => [ 'addon\tk_vip\app\listener\diy\ThemeColorListener' ],
    ],
    'subscribe' => [
    ],
];