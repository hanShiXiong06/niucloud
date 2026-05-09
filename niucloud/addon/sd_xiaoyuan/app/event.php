<?php

return [
    'bind' => [
    ],

    'listen' => [
        // 支付创建
        'PayCreate' => [
            'addon\sd_xiaoyuan\app\listener\pay\PayCreateListener',
        ],
        // 支付成功回调
        'PaySuccess' => [
            'addon\sd_xiaoyuan\app\listener\pay\PaySuccessListener',
        ],
        // 退款成功回调
        'RefundSuccess' => [
            'addon\sd_xiaoyuan\app\listener\pay\RefundSuccessListener',
        ],
        // 手机端首页入口
        'WapIndex' => [
            'addon\sd_xiaoyuan\app\listener\WapIndexListener',
        ],
        // 底部导航
        'BottomNavigation' => [
            'addon\sd_xiaoyuan\app\listener\BottomNavigationListener',
        ],
        // 自定义链接
        'WapLink' => [
            'addon\sd_xiaoyuan\app\listener\WapLinkListener',
        ],
        // 消息通知模板
        'NoticeData' => [
            'addon\sd_xiaoyuan\app\listener\notice\OrderPayNotice',
            'addon\sd_xiaoyuan\app\listener\notice\OrderAcceptNotice',
            'addon\sd_xiaoyuan\app\listener\notice\OrderCompleteNotice',
        ],
        // 导出数据类型
        'ExportDataType' => [
            'addon\sd_xiaoyuan\app\listener\export\OrderExportTypeListener',
        ],
        // 导出数据源
        'ExportData' => [
            'addon\sd_xiaoyuan\app\listener\export\OrderExportDataListener',
        ],
        // 通过支付信息获取手机端订单详情路径
        'WapOrderDetailPath' => [
            'addon\sd_xiaoyuan\app\listener\order\WapOrderDetailPathListener',
        ],
        // 海报类型
        'GetPosterType' => [
            'addon\sd_xiaoyuan\app\listener\poster\InvitePosterType',
        ],
        // 海报数据
        'GetPosterData' => [
            'addon\sd_xiaoyuan\app\listener\poster\InvitePoster',
        ],
        // DIY组件
        'DiyComponent' => [
            'addon\sd_xiaoyuan\app\listener\diy\DiyComponentListener',
        ],
        // DIY页面
        'DiyPages' => [
            'addon\sd_xiaoyuan\app\listener\diy\DiyPagesListener',
        ],
        // DIY模板
        'DiyTemplate' => [
            'addon\sd_xiaoyuan\app\listener\diy\DiyTemplateListener',
        ],
        // DIY链接
        'DiyLink' => [
            'addon\sd_xiaoyuan\app\listener\diy\DiyLinkListener',
        ],
    ],

    'subscribe' => [
    ],
];
