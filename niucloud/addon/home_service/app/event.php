<?php

return [
    //文件执行序列号
    'bind' => [
    ],

    'listen' => [
        'AddSiteAfter' => ['addon\home_service\app\listener\AddSiteAfter'],
        //站点初始化
        'SiteInit' => ['addon\home_service\app\listener\SiteInitListener'],
        'BottomNavigation' => ['addon\home_service\app\listener\BottomNavigationListener'],

        'WapIndex' => ['addon\home_service\app\listener\WapIndexListener'],

        //主题色
        'ThemeColor' => ['addon\home_service\app\listener\diy\ThemeColorListener'],
        //  订单创建后
        'AfterHomeServiceOrderCreate' => ['addon\home_service\app\listener\order\AfterHomeServiceOrderCreate'],
        //  支付创建
        'PayCreate' => ['addon\home_service\app\listener\pay\PayCreateListener'],
        //  支付成功
        'PaySuccess' => ['addon\home_service\app\listener\pay\PaySuccessListener'],
        //订单支付后
        'AfterHomeServiceOrderPay' => ['addon\home_service\app\listener\order\AfterHomeServiceOrderPay'],
        //订单佣金计算
        'ComputeOrderCommission' => ['addon\home_service\app\listener\order\ComputeOrderCommission'],
        //订单增项佣金计算
        'ComputeOrderItemCommission' => ['addon\home_service\app\listener\order\ComputeOrderItemCommission'],
        //订单预结算
        'PreSettlementOrderCommission' => ['addon\home_service\app\listener\order\PreSettlementOrderCommission'],
        //订单 结算释放
        'SettlementOrderCommission' => ['addon\home_service\app\listener\order\SettlementOrderCommission'],

        //技师新增
        'AddHsTechnician' => ['addon\home_service\app\listener\technician\AddHsTechnician'],
        //门店新增
        'AddHsStore' => ['addon\home_service\app\listener\store\AddHsStore'],



        //
        //支付
        'PayTradeInfo' => [ 'addon\home_service\app\listener\order\HomeServiceOrderTradeInfoListener' ],   //订单交易信息
        'RefundSuccess' => ['addon\home_service\app\listener\pay\RefundSuccessListener'],
        //退款佣金计算
        'ComputeOrderRefundCommission' => ['addon\home_service\app\listener\order\ComputeOrderRefundCommission'],
        //退款预结算
        'SettlementOrderRefundCommission' => ['addon\home_service\app\listener\order\SettlementOrderRefundCommission'],
        //
        //        'SiteIndex' => [ 'addon\home_service\app\listener\SiteIndexListener' ],
        'NoticeData' => [
            'addon\home_service\app\listener\notice_template\HomeServiceOrderService',
            'addon\home_service\app\listener\notice_template\HomeServiceRefund',
            'addon\home_service\app\listener\notice_template\HomeServiceStoreDispatch',
        ],
        //
        //        //获取海报数据
                'GetPosterType' => [ 'addon\home_service\app\listener\poster\HomeServicePosterType' ],
                'GetPosterData' => [ 'addon\home_service\app\listener\poster\HomeServicePoster' ],
        //
        //导出数据类型
        'ExportDataType' => [
//            //订单列表导出
            'addon\home_service\app\listener\export\HomeServiceOrderExportTypeListener',
//            //退款售后导出
//            'addon\home_service\app\listener\refund\O2oOrderRefundExportTypeListener',
            //发票列表导出
            'addon\home_service\app\listener\export\HomeServiceInvoiceExportTypeListener',
            //门店结算列表导出
            'addon\home_service\app\listener\export\HomeServiceStoreSettlementExportTypeListener',
            //技师结算列表导出
            'addon\home_service\app\listener\export\HomeServiceTechnicianSettlementExportTypeListener',

        ],
        //导出数据源
        'ExportData' => [
            //订单列表导出
            'addon\home_service\app\listener\export\HomeServiceOrderExportDataListener',
//            //退款售后导出
//            'addon\home_service\app\listener\refund\O2oOrderRefundExportDataListener',
            //发票列表导出
            'addon\home_service\app\listener\export\HomeServiceInvoiceExportDataListener',
            //门店结算列表导出
            'addon\home_service\app\listener\export\HomeServiceStoreSettlementExportDataListener',
            //技师结算列表导出
            'addon\home_service\app\listener\export\HomeServiceTechnicianSettlementExportDataListener',
        ],


        //  次卡订单创建后
        'AfterHomeServiceCardOrderCreate' => ['addon\home_service\app\listener\card\AfterHomeServiceCardOrderCreate'],

        //优惠券
        'HomeServiceCouponReceiveType' => ['addon\home_service\app\listener\coupon\CouponReceiveListener'],
        'HomeServiceCouponCheck' => ['addon\home_service\app\listener\coupon\CouponCheckListener'],

        //消息通知
        'NotificationEvent' => [
            'addon\home_service\app\listener\notice\TechnicianNotificationListener'
        ],

        //转账成功
        'TransferSuccess' => [ 'addon\home_service\app\listener\cash_out\TransferSuccessListener' ],

        //技师升级检测
        'CheckTechnicianLevelUpgrade' => [ 'addon\home_service\app\listener\technician\CheckTechnicianLevelUpgradeListener' ],

        //协议类型加载
        'AgreementType' => [ 'addon\home_service\app\listener\AgreementType' ],
    ],

    'subscribe' => [
    ],
];
