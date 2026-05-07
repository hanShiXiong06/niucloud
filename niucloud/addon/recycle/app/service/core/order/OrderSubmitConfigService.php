<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\order;

use addon\recycle\app\dict\config\RecycleConfigKeyDict;
use app\service\core\sys\CoreConfigService;

/**
 * 回收下单配置
 */
class OrderSubmitConfigService
{
    private CoreConfigService $configService;

    public function __construct()
    {
        $this->configService = new CoreConfigService();
    }

    public function getConfig(int $siteId): array
    {
        $saved = $this->configService->getConfigValue($siteId, RecycleConfigKeyDict::ORDER_SUBMIT);
        return $this->sanitizeConfig(is_array($saved) ? $saved : []);
    }

    public function setConfig(int $siteId, array $data): bool
    {
        return (bool)$this->configService->setConfig($siteId, RecycleConfigKeyDict::ORDER_SUBMIT, $this->sanitizeConfig($data));
    }

    public function defaultConfig(): array
    {
        return [
            'device_add_enabled' => 1,
            'notice' => [
                'enabled' => 0,
                'title' => '下单提示',
                'content' => '',
            ],
            'default_count' => 1,
            'delivery_modes' => [
                'mail' => 1,
                'self' => 1,
            ],
            'profile' => [
                'enabled' => 1,
                'payment_required' => 1,
                'payment_min_count' => 1,
                'id_card_required' => 1,
            ],
            'platform_delivery' => [
                'display_name' => '京东快递',
                'free_shipping_min_count' => 1,
            ],
            'allow_user_reject_sale' => 1,
        ];
    }

    public function sanitizeConfig(array $data): array
    {
        $default = $this->defaultConfig();
        $deliveryModes = is_array($data['delivery_modes'] ?? null) ? $data['delivery_modes'] : [];
        $notice = is_array($data['notice'] ?? null) ? $data['notice'] : [];
        $profile = is_array($data['profile'] ?? null) ? $data['profile'] : [];
        $platformDelivery = is_array($data['platform_delivery'] ?? null) ? $data['platform_delivery'] : [];
        $defaultCount = max(1, min(99, (int)($data['default_count'] ?? $default['default_count'])));
        $paymentMinCount = max(1, min(5, (int)($profile['payment_min_count'] ?? $default['profile']['payment_min_count'])));
        $freeShippingMinCount = max(1, min(99, (int)($platformDelivery['free_shipping_min_count'] ?? $default['platform_delivery']['free_shipping_min_count'])));
        $platformDeliveryDisplayName = mb_substr(trim((string)($platformDelivery['display_name'] ?? $default['platform_delivery']['display_name'])), 0, 20);

        $config = [
            'device_add_enabled' => !empty($data['device_add_enabled']) ? 1 : 0,
            'notice' => [
                'enabled' => !empty($notice['enabled']) ? 1 : 0,
                'title' => mb_substr(trim((string)($notice['title'] ?? $default['notice']['title'])), 0, 30),
                'content' => mb_substr(trim((string)($notice['content'] ?? '')), 0, 500),
            ],
            'default_count' => $defaultCount,
            'delivery_modes' => [
                'mail' => !empty($deliveryModes['mail']) ? 1 : 0,
                'self' => !empty($deliveryModes['self']) ? 1 : 0,
            ],
            'profile' => [
                'enabled' => !empty($profile['enabled']) ? 1 : 0,
                'payment_required' => !empty($profile['payment_required']) ? 1 : 0,
                'payment_min_count' => $paymentMinCount,
                'id_card_required' => !empty($profile['id_card_required']) ? 1 : 0,
            ],
            'platform_delivery' => [
                'display_name' => $platformDeliveryDisplayName ?: $default['platform_delivery']['display_name'],
                'free_shipping_min_count' => $freeShippingMinCount,
            ],
            'allow_user_reject_sale' => array_key_exists('allow_user_reject_sale', $data)
                ? (!empty($data['allow_user_reject_sale']) ? 1 : 0)
                : $default['allow_user_reject_sale'],
        ];

        if ($config['notice']['title'] === '') {
            $config['notice']['title'] = $default['notice']['title'];
        }

        if ($config['notice']['content'] === '') {
            $config['notice']['enabled'] = 0;
        }

        if (empty($config['delivery_modes']['mail']) && empty($config['delivery_modes']['self'])) {
            $config['delivery_modes'] = $default['delivery_modes'];
        }

        return $config;
    }

    public function isDeliveryModeEnabled(int $siteId, int $deliveryType): bool
    {
        $config = $this->getConfig($siteId);
        $key = $deliveryType === 2 ? 'self' : 'mail';
        return !empty($config['delivery_modes'][$key]);
    }

    public function canUsePlatformDelivery(int $siteId, int $count): bool
    {
        $config = $this->getConfig($siteId);
        $minCount = max(1, (int)($config['platform_delivery']['free_shipping_min_count'] ?? 1));
        return $count >= $minCount;
    }
}
