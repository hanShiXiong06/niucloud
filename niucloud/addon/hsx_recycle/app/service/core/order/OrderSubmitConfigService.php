<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\order;

use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
use addon\hsx_recycle\app\dict\express\ExpressProviderDict;
use addon\hsx_recycle\app\model\express\ExpressProviderConfig;
use addon\hsx_recycle\app\model\yisu\YisuProductConfig;
use app\model\diy\DiyTheme;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

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
        $providers = $this->getPlatformDeliveryProviderOptions($siteId);
        $products = $this->getPlatformDeliveryProductOptions($siteId);
        $config = $this->sanitizeConfig(is_array($saved) ? $saved : [], false, $providers, $products);
        $config['platform_delivery']['provider_options'] = $providers;
        $config['platform_delivery']['product_options'] = $products;
        $config['price_detail_theme'] = $this->getFrameworkPriceDetailTheme($siteId);

        return $config;
    }

    public function setConfig(int $siteId, array $data): bool
    {
        $config = $this->sanitizeConfig(
            $data,
            true,
            $this->getPlatformDeliveryProviderOptions($siteId),
            $this->getPlatformDeliveryProductOptions($siteId)
        );
        $config['price_detail_theme'] = $this->getFrameworkPriceDetailTheme($siteId);

        return (bool)$this->configService->setConfig(
            $siteId,
            RecycleConfigKeyDict::ORDER_SUBMIT,
            $config
        );
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
                'display_name' => '',
                'free_shipping_min_count' => 1,
                'provider' => ExpressProviderDict::PROVIDER_YISU,
                'provider_name' => ExpressProviderDict::getProviderName(ExpressProviderDict::PROVIDER_YISU),
                'product_code' => '',
                'product_name' => '',
            ],
            'follow_official_account' => [
                'enabled' => 0,
                'wechat_name' => '',
                'qr_code' => '',
                'title' => '关注公众号',
                'content' => '关注公众号，及时接收订单状态通知',
            ],
            'customer_service' => [
                'enabled' => 0,
                'type' => 'wechat',
                'qrcode' => '',
                'title' => '联系客服',
                'content' => '如需议价或咨询订单进度，请联系客服处理',
            ],
            'allow_user_reject_sale' => 1,
            'price_detail_theme' => $this->defaultPriceDetailTheme(),
        ];
    }

    public function sanitizeConfig(array $data, bool $strict = false, array $platformDeliveryProviders = [], array $platformDeliveryProducts = []): array
    {
        $default = $this->defaultConfig();
        $deliveryModes = is_array($data['delivery_modes'] ?? null) ? $data['delivery_modes'] : [];
        $notice = is_array($data['notice'] ?? null) ? $data['notice'] : [];
        $profile = is_array($data['profile'] ?? null) ? $data['profile'] : [];
        $platformDelivery = is_array($data['platform_delivery'] ?? null) ? $data['platform_delivery'] : [];
        $followOfficialAccount = is_array($data['follow_official_account'] ?? null) ? $data['follow_official_account'] : [];
        $customerService = is_array($data['customer_service'] ?? null) ? $data['customer_service'] : [];
        $priceDetailTheme = is_array($data['price_detail_theme'] ?? null) ? $data['price_detail_theme'] : [];
        $platformDeliveryProviders = !empty($platformDeliveryProviders)
            ? array_values($platformDeliveryProviders)
            : $this->getDefaultPlatformDeliveryProviders();
        $providerMap = [];
        foreach ($platformDeliveryProviders as $provider) {
            if (!empty($provider['provider'])) {
                $providerMap[(string)$provider['provider']] = $provider;
            }
        }
        $platformDeliveryProducts = array_values($platformDeliveryProducts);
        $productMap = [];
        foreach ($platformDeliveryProducts as $product) {
            $provider = (string)($product['provider'] ?? '');
            $productCode = (string)($product['product_code'] ?? '');
            if ($provider !== '' && $productCode !== '') {
                $productMap[$provider][$productCode] = $product;
            }
        }
        $defaultCount = max(1, min(99, (int)($data['default_count'] ?? $default['default_count'])));
        $paymentMinCount = max(1, min(5, (int)($profile['payment_min_count'] ?? $default['profile']['payment_min_count'])));
        $freeShippingMinCount = max(1, min(99, (int)($platformDelivery['free_shipping_min_count'] ?? $default['platform_delivery']['free_shipping_min_count'])));
        $platformDeliveryDisplayName = mb_substr(trim((string)($platformDelivery['display_name'] ?? '')), 0, 20);
        $platformDeliveryProvider = trim((string)($platformDelivery['provider'] ?? $default['platform_delivery']['provider']));
        if ($platformDeliveryProvider === '' || !isset($providerMap[$platformDeliveryProvider])) {
            $platformDeliveryProvider = (string)($platformDeliveryProviders[0]['provider'] ?? $default['platform_delivery']['provider']);
        }
        $platformDeliveryProviderName = trim((string)($platformDelivery['provider_name'] ?? ($providerMap[$platformDeliveryProvider]['provider_name'] ?? '')));
        if ($platformDeliveryProviderName === '') {
            $platformDeliveryProviderName = (string)($providerMap[$platformDeliveryProvider]['provider_name'] ?? ExpressProviderDict::getProviderName($platformDeliveryProvider));
        }
        $platformDeliveryProductCode = trim((string)($platformDelivery['product_code'] ?? $default['platform_delivery']['product_code']));
        if ($platformDeliveryProductCode === '' || !isset($productMap[$platformDeliveryProvider][$platformDeliveryProductCode])) {
            $platformDeliveryProductCode = (string)array_key_first($productMap[$platformDeliveryProvider] ?? []);
        }
        $platformDeliveryProductName = trim((string)($platformDelivery['product_name'] ?? ($productMap[$platformDeliveryProvider][$platformDeliveryProductCode]['product_name'] ?? '')));
        if ($platformDeliveryProductName === '') {
            $platformDeliveryProductName = (string)($productMap[$platformDeliveryProvider][$platformDeliveryProductCode]['product_name'] ?? '');
        }
        $customerServiceType = (string)($customerService['type'] ?? $default['customer_service']['type']);
        if (!in_array($customerServiceType, ['wechat', 'qrcode'], true)) {
            $customerServiceType = $default['customer_service']['type'];
        }

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
                'display_name' => $platformDeliveryDisplayName ?: ($platformDeliveryProductName ?: '京东快递'),
                'free_shipping_min_count' => $freeShippingMinCount,
                'provider' => $platformDeliveryProvider,
                'provider_name' => $platformDeliveryProviderName ?: $default['platform_delivery']['provider_name'],
                'product_code' => $platformDeliveryProductCode,
                'product_name' => $platformDeliveryProductName,
            ],
            'follow_official_account' => [
                'enabled' => !empty($followOfficialAccount['enabled']) ? 1 : 0,
                'wechat_name' => mb_substr(trim((string)($followOfficialAccount['wechat_name'] ?? '')), 0, 30),
                'qr_code' => trim((string)($followOfficialAccount['qr_code'] ?? '')),
                'title' => mb_substr(trim((string)($followOfficialAccount['title'] ?? $default['follow_official_account']['title'])), 0, 30),
                'content' => mb_substr(trim((string)($followOfficialAccount['content'] ?? $default['follow_official_account']['content'])), 0, 120),
            ],
            'customer_service' => [
                'enabled' => !empty($customerService['enabled']) ? 1 : 0,
                'type' => $customerServiceType,
                'qrcode' => trim((string)($customerService['qrcode'] ?? '')),
                'title' => mb_substr(trim((string)($customerService['title'] ?? $default['customer_service']['title'])), 0, 30),
                'content' => mb_substr(trim((string)($customerService['content'] ?? $default['customer_service']['content'])), 0, 120),
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

        if ($config['follow_official_account']['title'] === '') {
            $config['follow_official_account']['title'] = $default['follow_official_account']['title'];
        }
        if ($config['follow_official_account']['content'] === '') {
            $config['follow_official_account']['content'] = $default['follow_official_account']['content'];
        }
        if ($config['follow_official_account']['enabled'] && $config['follow_official_account']['qr_code'] === '') {
            if ($strict) {
                throw new CommonException('开启公众号关注提醒前，请先上传公众号二维码图片');
            }
            $config['follow_official_account']['enabled'] = 0;
        }

        if ($config['customer_service']['title'] === '') {
            $config['customer_service']['title'] = $default['customer_service']['title'];
        }
        if ($config['customer_service']['content'] === '') {
            $config['customer_service']['content'] = $default['customer_service']['content'];
        }
        if ($config['customer_service']['enabled'] && $config['customer_service']['type'] === 'qrcode' && $config['customer_service']['qrcode'] === '') {
            if ($strict) {
                throw new CommonException('选择客服二维码模式前，请先上传客服二维码图片');
            }
            $config['customer_service']['enabled'] = 0;
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

    private function getFrameworkPriceDetailTheme(int $siteId): array
    {
        $default = $this->defaultPriceDetailTheme();
        $selectedTheme = (new DiyTheme())->where([
            ['site_id', '=', $siteId],
            ['addon', '=', 'hsx_recycle'],
            ['is_selected', '=', 1],
        ])->field('title,theme')->findOrEmpty()->toArray();

        if (!empty($selectedTheme) && is_array($selectedTheme['theme'] ?? null)) {
            return $this->frameworkThemeToPriceDetailTheme(
                (string)($selectedTheme['title'] ?? $default['theme_name']),
                $selectedTheme['theme']
            );
        }

        $themeConfig = array_values(array_filter(event('ThemeColor', ['key' => 'hsx_recycle'])))[0] ?? [];
        $themeColor = $themeConfig['theme_color'][0] ?? [];
        if (!empty($themeColor) && is_array($themeColor['theme'] ?? null)) {
            return $this->frameworkThemeToPriceDetailTheme(
                (string)($themeColor['title'] ?? $default['theme_name']),
                $themeColor['theme']
            );
        }

        return $default;
    }

    private function frameworkThemeToPriceDetailTheme(string $themeName, array $theme): array
    {
        $defaultColors = $this->defaultPriceDetailTheme()['colors'];
        $pick = function (array $keys, string $default) use ($theme): string {
            foreach ($keys as $key) {
                $value = $this->sanitizeCssColor((string)($theme[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
            return $default;
        };

        $brand = $pick(['--primary-color'], $defaultColors['brand']);
        $brandDeep = $pick(['--primary-color-dark', '--primary-color'], $defaultColors['brand_deep']);
        $softBg = $pick(['--primary-color-light2', '--primary-color-light'], $defaultColors['soft_bg']);
        $line = $pick(['--primary-color-light'], $defaultColors['line']);
        $price = $pick(['--price-text-color', '--primary-color'], $defaultColors['price']);

        return [
            'template_key' => 'framework_theme',
            'theme_name' => mb_substr(trim($themeName), 0, 20) ?: '主题风格',
            'colors' => [
                'page_bg' => $pick(['--page-bg-color'], $defaultColors['page_bg']),
                'card_bg' => '#FFFFFF',
                'soft_bg' => $softBg,
                'line' => $line,
                'text_main' => $defaultColors['text_main'],
                'text_sub' => $defaultColors['text_sub'],
                'brand' => $brand,
                'brand_deep' => $brandDeep,
                'price' => $price,
                'notice_bg' => $softBg,
                'notice_text' => $pick(['--primary-help-color2', '--primary-help-color', '--primary-color'], $defaultColors['notice_text']),
                'toolbar_bg' => '#FFFFFF',
                'button_bg' => $brand,
                'button_text' => '#FFFFFF',
                'series_active_bg' => $brand,
                'series_active_text' => '#FFFFFF',
                'series_inactive_bg' => $softBg,
                'series_inactive_text' => $brandDeep,
                'model_head_bg' => $softBg,
                'model_brand_bg' => $brand,
                'model_brand_text' => '#FFFFFF',
            ],
        ];
    }

    private function sanitizeCssColor(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
            return strtoupper($value);
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+))?\s*\)$/i', $value) === 1) {
            return $value;
        }
        return '';
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

    private function getDefaultPlatformDeliveryProviders(): array
    {
        $providers = ExpressProviderDict::getProviders();
        $result = [];

        foreach ($providers as $key => $provider) {
            $result[] = [
                'provider' => $key,
                'provider_name' => $provider['name'] ?? $key,
                'is_default' => 1,
                'support_quote' => !empty($provider['support_quote']),
                'support_cancel' => !empty($provider['support_cancel']),
                'support_track' => !empty($provider['support_track']),
            ];
        }

        return $result;
    }

    private function getPlatformDeliveryProviderOptions(int $siteId): array
    {
        $list = ExpressProviderConfig::getEnabledProviders($siteId);
        if (empty($list)) {
            ExpressProviderConfig::initSiteConfig($siteId);
            $list = ExpressProviderConfig::getEnabledProviders($siteId);
        }

        $providers = ExpressProviderDict::getProviders();
        $result = [];
        foreach ($list as $item) {
            $provider = (string)($item['provider'] ?? '');
            if ($provider === '') {
                continue;
            }
            $providerInfo = $providers[$provider] ?? [];
            $result[] = [
                'provider' => $provider,
                'provider_name' => (string)($item['provider_name'] ?? ($providerInfo['name'] ?? $provider)),
                'is_default' => (int)($item['is_default'] ?? 0),
                'support_quote' => !empty($providerInfo['support_quote']),
                'support_cancel' => !empty($providerInfo['support_cancel']),
                'support_track' => !empty($providerInfo['support_track']),
            ];
        }

        if (empty($result)) {
            return $this->getDefaultPlatformDeliveryProviders();
        }

        return $result;
    }

    private function getPlatformDeliveryProductOptions(int $siteId): array
    {
        $products = YisuProductConfig::getEnabledProducts($siteId);
        $result = [];

        foreach ($products as $product) {
            $productCode = (string)($product['product_code'] ?? '');
            if ($productCode === '') {
                continue;
            }

            $result[] = [
                'provider' => ExpressProviderDict::PROVIDER_YISU,
                'product_code' => $productCode,
                'product_name' => (string)($product['product_name'] ?? $productCode),
                'express_type' => (string)($product['express_type'] ?? ''),
                'logo' => (string)($product['logo'] ?? ''),
            ];
        }

        return $result;
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
