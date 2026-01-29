<?php
declare (strict_types=1);

namespace addon\phone_shop\app\listener\pay;

/**
 * 线下支付类型监听器
 */
class OfflinePayType
{
    public function handle()
    {
        return [
            'hsx_offlinepay' => [
                'name' => '线下支付',
                'key' => 'hsx_offlinepay',
                'icon' => 'addon/phone_shop/offlinepay.png',
                'setting_component' => '/src/addon/phone_shop/views/site/pay-offlinepay.vue'
            ]
        ];
    }
}
