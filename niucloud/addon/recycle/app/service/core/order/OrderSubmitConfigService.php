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
            'price_detail_theme' => $this->defaultPriceDetailTheme(),
        ];
    }

    public function sanitizeConfig(array $data): array
    {
        $default = $this->defaultConfig();
        $deliveryModes = is_array($data['delivery_modes'] ?? null) ? $data['delivery_modes'] : [];
        $notice = is_array($data['notice'] ?? null) ? $data['notice'] : [];
        $profile = is_array($data['profile'] ?? null) ? $data['profile'] : [];
        $platformDelivery = is_array($data['platform_delivery'] ?? null) ? $data['platform_delivery'] : [];
        $priceDetailTheme = is_array($data['price_detail_theme'] ?? null) ? $data['price_detail_theme'] : [];
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
            'price_detail_theme' => $this->sanitizePriceDetailTheme($priceDetailTheme),
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

    private function defaultPriceDetailTheme(): array
    {
        return [
            'template_key' => 'classic_blue',
            'theme_name' => '默认蓝',
            'colors' => $this->priceDetailThemePresets()['classic_blue']['colors'],
        ];
    }

    private function sanitizePriceDetailTheme(array $theme): array
    {
        $presets = $this->priceDetailThemePresets();
        $templateKey = trim((string)($theme['template_key'] ?? 'classic_blue'));
        if (!isset($presets[$templateKey])) {
            $templateKey = 'classic_blue';
        }

        $preset = $presets[$templateKey];
        $colors = is_array($theme['colors'] ?? null) ? $theme['colors'] : [];
        $resultColors = $preset['colors'];
        foreach ($resultColors as $key => $defaultValue) {
            $resultColors[$key] = $this->sanitizeColor((string)($colors[$key] ?? $defaultValue), $defaultValue);
        }

        return [
            'template_key' => $templateKey,
            'theme_name' => mb_substr(trim((string)($theme['theme_name'] ?? $preset['name'])), 0, 20) ?: $preset['name'],
            'colors' => $resultColors,
        ];
    }

    private function sanitizeColor(string $value, string $default): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
            return strtoupper($value);
        }
        return strtoupper($default);
    }

    private function priceDetailThemePresets(): array
    {
        return [
            'classic_blue' => [
                'name' => '默认蓝',
                'colors' => [
                    'page_bg' => '#F3F4F6',
                    'card_bg' => '#FFFFFF',
                    'soft_bg' => '#F7F7F8',
                    'line' => '#E5E7EB',
                    'text_main' => '#1F2937',
                    'text_sub' => '#6B7280',
                    'brand' => '#3B82F6',
                    'brand_deep' => '#4F46E5',
                    'price' => '#2563EB',
                    'notice_bg' => '#FFF8ED',
                    'notice_text' => '#F59E0B',
                    'toolbar_bg' => '#FFFFFF',
                    'button_bg' => '#111827',
                    'button_text' => '#FFFFFF',
                    'series_active_bg' => '#111827',
                    'series_active_text' => '#FFFFFF',
                    'series_inactive_bg' => '#F8FAFC',
                    'series_inactive_text' => '#475569',
                    'model_head_bg' => '#F8FAFC',
                    'model_brand_bg' => '#111827',
                    'model_brand_text' => '#FFFFFF',
                ],
            ],
            'eco_green' => [
                'name' => '绿色环保',
                'colors' => [
                    'page_bg' => '#F0FDF4',
                    'card_bg' => '#FFFFFF',
                    'soft_bg' => '#DCFCE7',
                    'line' => '#BBF7D0',
                    'text_main' => '#14532D',
                    'text_sub' => '#4B7560',
                    'brand' => '#16A34A',
                    'brand_deep' => '#15803D',
                    'price' => '#15803D',
                    'notice_bg' => '#ECFDF5',
                    'notice_text' => '#047857',
                    'toolbar_bg' => '#FFFFFF',
                    'button_bg' => '#166534',
                    'button_text' => '#FFFFFF',
                    'series_active_bg' => '#166534',
                    'series_active_text' => '#FFFFFF',
                    'series_inactive_bg' => '#ECFDF5',
                    'series_inactive_text' => '#166534',
                    'model_head_bg' => '#F0FDF4',
                    'model_brand_bg' => '#166534',
                    'model_brand_text' => '#FFFFFF',
                ],
            ],
            'warm_orange' => [
                'name' => '橙色回收',
                'colors' => [
                    'page_bg' => '#FFF7ED',
                    'card_bg' => '#FFFFFF',
                    'soft_bg' => '#FFEDD5',
                    'line' => '#FED7AA',
                    'text_main' => '#431407',
                    'text_sub' => '#9A5B21',
                    'brand' => '#F97316',
                    'brand_deep' => '#EA580C',
                    'price' => '#EA580C',
                    'notice_bg' => '#FFFBEB',
                    'notice_text' => '#D97706',
                    'toolbar_bg' => '#FFFFFF',
                    'button_bg' => '#C2410C',
                    'button_text' => '#FFFFFF',
                    'series_active_bg' => '#C2410C',
                    'series_active_text' => '#FFFFFF',
                    'series_inactive_bg' => '#FFEDD5',
                    'series_inactive_text' => '#9A3412',
                    'model_head_bg' => '#FFF7ED',
                    'model_brand_bg' => '#C2410C',
                    'model_brand_text' => '#FFFFFF',
                ],
            ],
            'dark_business' => [
                'name' => '深色商务',
                'colors' => [
                    'page_bg' => '#111827',
                    'card_bg' => '#1F2937',
                    'soft_bg' => '#374151',
                    'line' => '#4B5563',
                    'text_main' => '#F9FAFB',
                    'text_sub' => '#CBD5E1',
                    'brand' => '#60A5FA',
                    'brand_deep' => '#818CF8',
                    'price' => '#93C5FD',
                    'notice_bg' => '#1E3A8A',
                    'notice_text' => '#DBEAFE',
                    'toolbar_bg' => '#1F2937',
                    'button_bg' => '#60A5FA',
                    'button_text' => '#0F172A',
                    'series_active_bg' => '#60A5FA',
                    'series_active_text' => '#0F172A',
                    'series_inactive_bg' => '#374151',
                    'series_inactive_text' => '#E5E7EB',
                    'model_head_bg' => '#1F2937',
                    'model_brand_bg' => '#60A5FA',
                    'model_brand_text' => '#0F172A',
                ],
            ],
            'premium_red' => [
                'name' => '红色高价',
                'colors' => [
                    'page_bg' => '#FFF1F2',
                    'card_bg' => '#FFFFFF',
                    'soft_bg' => '#FFE4E6',
                    'line' => '#FECDD3',
                    'text_main' => '#4C0519',
                    'text_sub' => '#9F1239',
                    'brand' => '#E11D48',
                    'brand_deep' => '#BE123C',
                    'price' => '#E11D48',
                    'notice_bg' => '#FFF1F2',
                    'notice_text' => '#BE123C',
                    'toolbar_bg' => '#FFFFFF',
                    'button_bg' => '#BE123C',
                    'button_text' => '#FFFFFF',
                    'series_active_bg' => '#BE123C',
                    'series_active_text' => '#FFFFFF',
                    'series_inactive_bg' => '#FFE4E6',
                    'series_inactive_text' => '#BE123C',
                    'model_head_bg' => '#FFF1F2',
                    'model_brand_bg' => '#BE123C',
                    'model_brand_text' => '#FFFFFF',
                ],
            ],
        ];
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
