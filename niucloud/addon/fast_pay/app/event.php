<?php

return [
    'bind' => [

    ],
    'listen' => [
        //订单创建
        'PayCreate' => ['addon\fast_pay\app\listener\pay\PayCreateListener'],
        //支付成功
        'PaySuccess' => ['addon\fast_pay\app\listener\pay\PaySuccessListener'],
        //增加支付类型
        'PayType' => [
            'addon\fast_pay\app\listener\pay\PayType',
        ],
        //获取海报数据
        'GetPosterType' => ['addon\fast_pay\app\listener\poster\PayPosterType'],
        'GetPosterData' => ['addon\fast_pay\app\listener\poster\PayPoster'],
        //站点创建后/变动事件
        'AddSiteAfter' => ['addon\fast_pay\app\listener\AddSiteAfterListener'],
        //主题色
        'ThemeColor' => ['addon\fast_pay\app\listener\diy\ThemeColorListener'],
    ],
    'subscribe' => [
    ],
];