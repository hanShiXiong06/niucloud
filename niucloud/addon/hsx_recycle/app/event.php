<?php

return [
    'bind' => [

    ],
    'listen' => [
        // adminapp 旧核心命名空间兼容注册
        'AppInit' => [ 'addon\hsx_recycle\app\listener\system\AdminAppCompatListener' ],
       // 协议
        'AgreementType' => [ 'addon\hsx_recycle\app\listener\AgreementType' ],
        // 主题色
        'ThemeColor' => [ 'addon\hsx_recycle\app\listener\diy\ThemeColorListener' ],
        // 添加wapIndex的监听(添加到首页)
        'WapIndex' => [ 'addon\hsx_recycle\app\listener\WapIndexListener' ],
        // 添加DIY页面配置监听器
        'DiyWapIndex' => [ 'addon\hsx_recycle\app\listener\diy\WapIndexListener' ],
        // 底部导航
        'BottomNavigation' => [ 'addon\hsx_recycle\app\listener\BottomNavigationListener' ],

        //通知
        'NoticeData' => [
            // 签收通知
            'addon\hsx_recycle\app\listener\notice_template\OrderSign',
            // 下单通知
            'addon\hsx_recycle\app\listener\notice_template\OrderAdd',
            // 同意通知
            'addon\hsx_recycle\app\listener\notice_template\OrderAgree',
            // 打款通知
            'addon\hsx_recycle\app\listener\notice_template\OrderPay',
            // 订单完成奖励通知
            'addon\hsx_recycle\app\listener\notice_template\OrderReward',
            // 代卖进度通知
            'addon\hsx_recycle\app\listener\notice_template\ConsignmentStatus',
        ],
        // 应用信息
        'RecyclePromotion' => [ 'addon\hsx_recycle\app\listener\app\RecyclePromotionListener' ],
        
        // 设备质检完成事件
        'AfterDeviceCheckComplete' => [ 'addon\hsx_recycle\app\listener\device\DeviceCheckCompleteListener' ],

        // 下游流转回流：订阅 ERP/数据中台事件，把设备下游生命周期(已入库/转中台/已定价)镜像回回收设备
        'ErpDomainEvent' => [ 'addon\hsx_recycle\app\listener\downstream\ErpAssetDownstreamListener' ],
        'DeviceAssetPriceCompleted' => [ 'addon\hsx_recycle\app\listener\downstream\DeviceAssetPricedListener' ],

        // 代卖转回收：ERP 把代卖设备买断为自有时，回收侧把该设备由代卖标记为回收（成本转移到我方）
        'ErpConsignDeviceBoughtOut' => [ 'addon\hsx_recycle\app\listener\downstream\ConsignDeviceBoughtOutListener' ],

        // ERP 财务中心折账结清回收应付 → 回写设备打款状态(折账)+备注结算单号，形成闭环
        'FinanceSettlementCompleted' => [ 'addon\hsx_recycle\app\listener\downstream\FinanceSettlementCompletedListener' ],

        // 快递回调事件。易速推送统一在此分发，后续 ERP、通知、财务流水可挂载扩展。
        'RecycleExpressEvent' => [ 'addon\hsx_recycle\app\listener\express\RecycleExpressEventListener' ],
        
        // 微信转账场景
        'GetWechatTransferTradeScene' => [ 'addon\hsx_recycle\app\listener\pay\RecycleTransferSceneListener' ],
        
        // 导出数据类型
        'ExportDataType' => [ 'addon\hsx_recycle\app\listener\export\RecycleDeviceExportDataListener' ],
        
        // 导出数据
        'ExportData' => [ 'addon\hsx_recycle\app\listener\export\RecycleDeviceExportListener' ],
    ],
    'subscribe' => [
    ],
];
