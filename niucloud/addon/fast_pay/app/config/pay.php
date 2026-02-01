<?php

return [
    'drivers' => [
        // 微信支付
        'fastpay_wechatpay' => [
            'driver' => 'addon\fast_pay\app\service\core\pay\CoreWechatpay',  //反射类的名字
            'app_key' => '',
            'secret_key' => '',
            'sign' => '',
        ],
        //微信服务商支付
        'fastpay_partner_wechatpay' => [
            'driver' => 'addon\fast_pay\app\service\core\pay\CorePartnerWechatpay',  //反射类的名字
            'sub_mch_id' => '',
            //'sub_appid' => '',
            //'site_id' => '',
        ],
    ],

];
