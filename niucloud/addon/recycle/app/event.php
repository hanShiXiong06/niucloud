<?php

return [
    'bind' => [

    ],
    'listen' => [
       // 协议
        'AgreementType' => [ 'addon\recycle\app\listener\AgreementType' ],
        // 主题色
        'ThemeColor' => [ 'addon\recycle\app\listener\diy\ThemeColorListener' ],
        // 添加wapIndex的监听(添加到首页)
        'WapIndex' => [ 'addon\recycle\app\listener\WapIndexListener' ],
        // 添加DIY页面配置监听器
        'DiyWapIndex' => [ 'addon\recycle\app\listener\diy\WapIndexListener' ],
        // 底部导航
        'BottomNavigation' => [ 'addon\recycle\app\listener\BottomNavigationListener' ],

        //通知
        'NoticeData' => [
            // 签收通知
            'addon\recycle\app\listener\notice_template\OrderSign',
            // 下单通知
            'addon\recycle\app\listener\notice_template\OrderAdd',
            // 同意通知
            'addon\recycle\app\listener\notice_template\OrderAgree',
            // 打款通知
            'addon\recycle\app\listener\notice_template\OrderPay',
        ],
        // 应用信息
        'RecyclePromotion' => [ 'addon\recycle\app\listener\app\RecyclePromotionListener' ],
        
        // 设备质检完成事件
        'AfterDeviceCheckComplete' => [ 'addon\recycle\app\listener\device\DeviceCheckCompleteListener' ],
        
        // 微信转账场景
        'GetWechatTransferTradeScene' => [ 'addon\recycle\app\listener\pay\RecycleTransferSceneListener' ],
        
        // 导出数据类型
        'ExportDataType' => [ 'addon\recycle\app\listener\export\RecycleDeviceExportDataListener' ],
        
        // 导出数据
        'ExportData' => [ 'addon\recycle\app\listener\export\RecycleDeviceExportListener' ],
    ],
    'subscribe' => [
    ],
];