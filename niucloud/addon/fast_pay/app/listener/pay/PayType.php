<?php
declare (strict_types=1);

namespace addon\fast_pay\app\listener\pay;

/**
 * 定义支付方式
 */
class PayType
{
    public function handle()
    {
        return [
            'fastpay_wechatpay' => [
                'name' => '微信快速支付',
                'key' => 'fastpay_wechatpay',
                'icon' => 'addon/fast_pay/icon/wechatpay.png',
                'setting_component' => '/src/addon/fast_pay/views/setting/components/wechatpay.vue'
            ],
            'fastpay_partner_wechatpay' => [
                'name' => '微信快捷支付',
                'key' => 'fastpay_partner_wechatpay',
                'icon' => 'addon/fast_pay/icon/wechatpay.png',
                'setting_component' => '/src/addon/fast_pay/views/setting/components/partner_wechatpay.vue'
            ]
        ];
    }
}
