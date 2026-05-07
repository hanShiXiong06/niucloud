<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\third_party;

use addon\recycle\app\dict\config\RecycleConfigKeyDict;
use addon\recycle\app\dict\third_party\ThirdPartyDict;
use addon\recycle\app\service\core\device_query\DeviceQueryConfigService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;

/**
 * 回收第三方配置中心
 */
class RecycleThirdPartyConfigService extends BaseCoreService
{
    const MASK_VALUE = '******';

    private $configService;
    private $deviceQueryConfigService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new CoreConfigService();
        $this->deviceQueryConfigService = new DeviceQueryConfigService();
    }

    public function getConfig(int $siteId, bool $maskSecret = false): array
    {
        $saved = $this->configService->getConfigValue($siteId, RecycleConfigKeyDict::THIRD_PARTY);
        $config = $this->mergeConfig($this->getDefaultConfig(), is_array($saved) ? $saved : []);
        $config['device_query'] = $this->deviceQueryConfigService->getConfig($siteId, $maskSecret);

        return $maskSecret ? $this->maskSecret($config) : $config;
    }

    public function hasSavedConfig(int $siteId): bool
    {
        return !empty($this->configService->getConfig($siteId, RecycleConfigKeyDict::THIRD_PARTY));
    }

    public function isServiceEnabled(int $siteId, string $serviceType): bool
    {
        $map = $this->getServiceMap();
        if (!isset($map[$serviceType])) {
            return false;
        }

        $config = $this->getConfig($siteId, false);
        $section = $config[$map[$serviceType]['section']] ?? [];

        return !empty($section['enabled']);
    }

    public function setConfig(int $siteId, array $data): bool
    {
        $old = $this->getConfig($siteId, false);
        if (isset($data['device_query']) && is_array($data['device_query'])) {
            $this->deviceQueryConfigService->setConfig($siteId, $data['device_query']);
            unset($data['device_query']);
        }
        $config = $this->mergeConfig($this->getDefaultConfig(), $data);
        $config = $this->keepMaskedSecret($config, $old);
        $this->configService->setConfig($siteId, RecycleConfigKeyDict::THIRD_PARTY, $config);

        return true;
    }

    public function getProviderConfig(int $siteId, string $serviceType, string $provider = ''): array
    {
        $map = $this->getServiceMap();
        if (!isset($map[$serviceType])) {
            return [];
        }

        if ($serviceType === ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY) {
            return $this->deviceQueryConfigService->getConfig($siteId);
        }

        $sectionKey = $map[$serviceType]['section'];
        $defaultProvider = $map[$serviceType]['provider'];
        $config = $this->getConfig($siteId, false);
        $section = $config[$sectionKey] ?? [];

        if (empty($section['enabled'])) {
            return [];
        }

        $provider = $provider !== '' ? $provider : (string)($section['provider'] ?? $defaultProvider);
        if ($provider !== $defaultProvider) {
            return [];
        }

        $providerConfig = $section[$provider] ?? [];
        if (!is_array($providerConfig)) {
            return [];
        }

        return $providerConfig;
    }

    public function isProviderConfigComplete(int $siteId, string $serviceType, string $provider = ''): bool
    {
        if ($serviceType === ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY) {
            $config = $this->deviceQueryConfigService->getConfig($siteId);
            return !empty($config['enabled']) && !empty($config['services']) && !empty($config['channels']) && !empty($config['mappings']);
        }

        $config = $this->getProviderConfig($siteId, $serviceType, $provider);
        if (empty($config)) {
            return false;
        }

        $map = $this->getServiceMap();
        $provider = $provider !== '' ? $provider : (string)($map[$serviceType]['provider'] ?? '');
        $requiredFields = $this->getRequiredFields($serviceType, $provider);

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || trim((string)$config[$field]) === '') {
                return false;
            }
        }

        return true;
    }

    public function getPrinterConfig(int $siteId): array
    {
        $config = $this->getConfig($siteId, false);
        $section = $config['printer'] ?? [];
        if (empty($section['enabled'])) {
            return [];
        }

        $provider = (string)($section['provider'] ?? 'xpyun');
        if ($provider !== 'xpyun') {
            return [];
        }

        return is_array($section['xpyun'] ?? null) ? $section['xpyun'] : [];
    }

    public function isPrinterConfigComplete(int $siteId): bool
    {
        $config = $this->getPrinterConfig($siteId);
        foreach ($this->getRequiredFields('printer', 'xpyun') as $field) {
            if (!isset($config[$field]) || trim((string)$config[$field]) === '') {
                return false;
            }
        }

        return true;
    }

    public function getDefaultConfig(): array
    {
        return [
            'express_order' => [
                'enabled' => 1,
                'provider' => ThirdPartyDict::PROVIDER_YISU,
                ThirdPartyDict::PROVIDER_YISU => [
                    'base_url' => 'http://open.yisuopen.com',
                    'appid' => '',
                    'app_secret' => '',
                    'version' => 'V1.0',
                    'timeout' => 30,
                    'callback_url' => 'https://gl.hsxbk.top/api/tk_jhkd/yisunotice',
                    'api_paths' => [
                        'quote' => '/openApi/getPrice',
                        'create' => '/openApi/doOrder',
                        'cancel' => '/openApi/doCancel',
                        'modify' => '/openApi/doModify',
                        'detail' => '/openApi/getOrderDetail',
                        'waybillPdf' => '/openApi/getWaybillPdf',
                        'fund' => '/openApi/fund',
                    ],
                ],
            ],
            'express_query' => [
                'enabled' => 1,
                'provider' => ThirdPartyDict::PROVIDER_ALI_EXPRESS,
                ThirdPartyDict::PROVIDER_ALI_EXPRESS => [
                    'base_url' => 'https://kzexpress.market.alicloudapi.com',
                    'api_key' => '',
                    'api_path' => '/api-mall/api/express/query',
                    'timeout' => 30,
                ],
            ],
            'address_parse' => [
                'enabled' => 1,
                'provider' => ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS,
                ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS => [
                    'base_url' => 'https://ap-guangzhou.cloudmarket-apigw.com',
                    'api_path' => '/service-3qtg8hpi/identify_address',
                    'secret_id' => '',
                    'secret_key' => '',
                    'timeout' => 10,
                ],
            ],
            'device_query' => [
                'enabled' => 1,
                'provider' => ThirdPartyDict::PROVIDER_3023,
                'services' => $this->deviceQueryConfigService->defaultConfig()['services'],
                'channels' => $this->deviceQueryConfigService->defaultConfig()['channels'],
                'mappings' => $this->deviceQueryConfigService->defaultConfig()['mappings'],
            ],
            'printer' => [
                'enabled' => 1,
                'provider' => 'xpyun',
                'xpyun' => [
                    'base_url' => 'https://open.xpyun.net/api/openapi',
                    'print_label_path' => '/xprinter/printLabel',
                    'timeout' => 30,
                    'connect_timeout' => 10,
                ],
            ],
        ];
    }

    public function getServiceMap(): array
    {
        return [
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER => [
                'section' => 'express_order',
                'provider' => ThirdPartyDict::PROVIDER_YISU,
            ],
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY => [
                'section' => 'express_query',
                'provider' => ThirdPartyDict::PROVIDER_ALI_EXPRESS,
            ],
            ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE => [
                'section' => 'address_parse',
                'provider' => ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS,
            ],
            ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY => [
                'section' => 'device_query',
                'provider' => ThirdPartyDict::PROVIDER_3023,
            ],
        ];
    }

    private function mergeConfig(array $default, array $data): array
    {
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $default)) {
                continue;
            }

            if ($key === 'device_query' && is_array($value)) {
                $default[$key] = $this->deviceQueryConfigService->sanitizeConfig(array_merge(
                    is_array($default[$key] ?? null) ? $default[$key] : [],
                    $value
                ));
            } elseif (is_array($value) && isset($default[$key]) && is_array($default[$key])) {
                $default[$key] = $this->mergeConfig($default[$key], $value);
            } else {
                $default[$key] = $value;
            }
        }

        return $this->normalizeConfig($default);
    }

    private function normalizeConfig(array $config): array
    {
        foreach (['express_order', 'express_query', 'address_parse', 'printer'] as $sectionKey) {
            if (isset($config[$sectionKey]['enabled'])) {
                $config[$sectionKey]['enabled'] = (int)(bool)$config[$sectionKey]['enabled'];
            }
        }

        foreach ([
            ['express_order', ThirdPartyDict::PROVIDER_YISU],
            ['express_query', ThirdPartyDict::PROVIDER_ALI_EXPRESS],
            ['address_parse', ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS],
            ['printer', 'xpyun'],
        ] as $path) {
            $sectionKey = $path[0];
            $provider = $path[1];
            if (!isset($config[$sectionKey][$provider]) || !is_array($config[$sectionKey][$provider])) {
                continue;
            }
            foreach (['timeout', 'connect_timeout'] as $field) {
                if (isset($config[$sectionKey][$provider][$field])) {
                    $config[$sectionKey][$provider][$field] = max(1, (int)$config[$sectionKey][$provider][$field]);
                }
            }
        }

        return $config;
    }

    private function maskSecret(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->maskSecret($value);
                continue;
            }

            if ($this->isSecretField((string)$key) && (string)$value !== '') {
                $config[$key] = self::MASK_VALUE;
            }
        }

        return $config;
    }

    private function keepMaskedSecret(array $config, array $old): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->keepMaskedSecret($value, is_array($old[$key] ?? null) ? $old[$key] : []);
                continue;
            }

            if ($this->isSecretField((string)$key) && $value === self::MASK_VALUE) {
                $config[$key] = $old[$key] ?? '';
            }
        }

        return $config;
    }

    private function isSecretField(string $field): bool
    {
        return in_array(strtolower($field), [
            'api_key',
            'app_secret',
            'secret',
            'secret_id',
            'secret_key',
            'token',
            'user_key',
            'authorization',
        ], true);
    }

    private function getRequiredFields(string $serviceType, string $provider): array
    {
        $key = $serviceType . ':' . $provider;
        $map = [
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER . ':' . ThirdPartyDict::PROVIDER_YISU => [
                'base_url',
                'appid',
                'app_secret',
            ],
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY . ':' . ThirdPartyDict::PROVIDER_ALI_EXPRESS => [
                'base_url',
                'api_key',
                'api_path',
            ],
            ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE . ':' . ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS => [
                'base_url',
                'api_path',
                'secret_id',
                'secret_key',
            ],
            'printer:xpyun' => [
                'base_url',
                'print_label_path',
            ],
        ];

        return $map[$key] ?? [];
    }
}
