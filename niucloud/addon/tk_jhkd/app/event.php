<?php

return [
    //文件执行序列号
    'bind' => [
    ],

    'listen' => [
        //应用管理
        'AppManage' => ['addon\tk_jhkd\app\listener\AppManageListener'],
        //订单创建
        'PayCreate' => ['addon\tk_jhkd\app\listener\pay\PayCreateListener'],
        //支付成功
        'PaySuccess' => ['addon\tk_jhkd\app\listener\pay\PaySuccessListener'],
        //退款成功
        'RefundSuccess'=>['addon\tk_jhkd\app\listener\pay\RefundSuccessListener'],
        //发单操作
        'OrderSend'=>['addon\tk_jhkd\app\listener\order\OrderSendListener'],
        //订单完成事件
        'JhkdOrderFinish'=>['addon\tk_jhkd\app\listener\order\OrderFinishListener'],
        //取消订单，已付款情况
        'CancelOrder'=>['addon\tk_jhkd\app\listener\order\OrderCancelListener'],
        //预下单--封装接口
        "DeliveryPreOrder"=>['addon\tk_jhkd\app\listener\order\delivery\DeliveryPreOrderListener'],
        //发送下单--封装接口
        "DeliverySendOrder"=>['addon\tk_jhkd\app\listener\order\delivery\DeliverySendOrderListener'],
        //取消下单--封装接口
        "DeliveryCancelOrder"=>['addon\tk_jhkd\app\listener\order\delivery\DeliveryCancelOrderListener'],
        //上传小程序发货管理
        "DeliveryUploadShipping"=>['addon\tk_jhkd\app\listener\order\delivery\DeliveryUploadListener'],
        "DeliveryUploadShippingAdd"=>['addon\tk_jhkd\app\listener\order\delivery\DeliveryUploadAddListener'],
        //通知用户确认收货
        "DeliveryConfirmReceive"=>['addon\tk_jhkd\app\listener\order\delivery\ConfirmReceiveListener'],
        //首页设置
        'WapIndex' => [ 'addon\tk_jhkd\app\listener\WapIndexListener' ],
        //消息通知
        'NoticeData' => [
            'addon\tk_jhkd\app\listener\notice_template\OrderPay',
            'addon\tk_jhkd\app\listener\notice_template\OrderPick',
            'addon\tk_jhkd\app\listener\notice_template\OrderAdd',
            'addon\tk_jhkd\app\listener\notice_template\OrderSign',
        ],
        //增加导航
        'BottomNavigation' => [ 'addon\tk_jhkd\app\listener\BottomNavigationListener' ],
        //站点创建后事件
        'AddSiteAfter' => ['addon\tk_jhkd\app\listener\AddSiteAfterListener'],
        //获取海报数据
        'GetPosterType' => ['addon\tk_jhkd\app\listener\poster\JhkdPosterType'],
        'GetPosterData' => ['addon\tk_jhkd\app\listener\poster\JhkdPoster'],
        //2.0平台驱动事件
        'JhkdPlatformType' => ['addon\tk_jhkd\app\listener\platform\PlatformListener'],
        //会员注册分销绑定
        'MemberRegister' => ['addon\tk_jhkd\app\listener\member\MemberRegisterListener'],
        //优惠券
        'JhkdCouponReceiveType' => [ 'addon\tk_jhkd\app\listener\coupon\CouponReceiveListener' ],
        'JhkdCouponCheck' => [ 'addon\tk_jhkd\app\listener\coupon\CouponCheckListener' ],
        //主题色
        'ThemeColor' => [ 'addon\tk_jhkd\app\listener\diy\ThemeColorListener' ],
        //导出数据类型
        'ExportDataType' => [
            'addon\tk_jhkd\app\listener\export\JhkdExportTypeListener',
        ],
        //导出数据源
        'ExportData' => [
            'addon\tk_jhkd\app\listener\export\JhkdExportDataListener',
        ],
    ],

    'subscribe' => [
    ],
];
