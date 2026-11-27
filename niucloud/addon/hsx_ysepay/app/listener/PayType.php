<?php
declare(strict_types=1);

namespace addon\hsx_ysepay\app\listener;

use app\dict\pay\PayDict;

class PayType
{
    public function handle(): array
    {
        return $this->__invoke();
    }

    public function __invoke(): array
    {
        return [
            'hsx_ysepay' => [
                'name' => '银盛支付',
                'key' => 'hsx_ysepay',
                'icon' => PayDict::OFFLINEPAY_ICON,
                'setting_component' => '/src/addon/hsx_ysepay/views/setting/components/pay-hsx-ysepay.vue',
                'encrypt_params' => [
                    'merchant_private_cert',
                    'merchant_private_cert_password',
                    'ysepay_public_cert',
                ],
            ],
        ];
    }
}
