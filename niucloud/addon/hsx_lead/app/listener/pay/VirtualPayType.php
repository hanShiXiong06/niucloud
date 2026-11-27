<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;
use addon\hsx_lead\app\service\virtualpay\VirtualPaySettings;
use addon\hsx_lead\app\service\cooperation\PaymentService;
use app\dict\pay\PayDict;
final class VirtualPayType
{
    public function handle(): array
    {
        return [VirtualPaySettings::TYPE => ['key' => VirtualPaySettings::TYPE, 'name' => '微信虚拟支付', 'icon' => PayDict::WECHATPAY_ICON,
            'setting_component' => '/src/addon/hsx_lead/views/setting/components/pay-hsx-lead-virtualpay.vue',
            'encrypt_params' => ['app_key', 'sandbox_app_key', 'callback_token', 'encoding_aes_key'],
            'config_normalizer' => [\addon\hsx_lead\app\service\cooperation\MembershipVirtualProductService::class, 'normalizeConfig'],
            'channels' => ['weapp'], 'trade_types' => [PaymentService::TRADE_TYPE], 'reuse_pending' => true, 'manages_receipt' => true, 'provider_receipt_time' => true,
            'client_action' => VirtualPaySettings::CLIENT_ACTION]];
    }
}
