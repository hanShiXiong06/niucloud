<?php

return [
    'bind' => [

    ],
    'listen' => [
        //主题色
        'ThemeColor' => ['addon\ai_image\app\listener\diy\ThemeColorListener'],
        //增加导航
        'BottomNavigation' => ['addon\ai_image\app\listener\BottomNavigationListener'],
        //站点创建后事件
        'AddSiteAfter' => ['addon\ai_image\app\listener\AddSiteAfterListener'],
        //会员注册分销绑定
        'MemberRegister' => ['addon\ai_image\app\listener\member\MemberRegisterListener'],
        //订单创建
        'PayCreate' => ['addon\ai_image\app\listener\pay\PayCreateListener'],
        //支付成功
        'PaySuccess' => ['addon\ai_image\app\listener\pay\PaySuccessListener'],
        //导出数据类型
        'ExportDataType' => [
            //卡密导出
            'addon\ai_image\app\listener\export\CardExportTypeListener',
        ],
        //导出数据源
        'ExportData' => [
            //卡密导出
            'addon\ai_image\app\listener\export\CardExportDataListener',
        ],
        //消息通知
//        'NoticeData' => [
//            //创建成功
//            'addon\ai_image\app\listener\notice_template\CreateSuccess',
//            //创建失败
//            'addon\ai_image\app\listener\notice_template\CreateFail',
//        ],
        //获取海报数据
        'GetPosterType' => ['addon\ai_image\app\listener\poster\PosterType'],
        'GetPosterData' => ['addon\ai_image\app\listener\poster\Poster'],
    ],
    'subscribe' => [
    ],
];