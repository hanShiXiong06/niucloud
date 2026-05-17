<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
use app\service\core\sys\CoreConfigService;

class DeviceQueryConfigService
{
    private CoreConfigService $configService;
    private DeviceQueryCatalogService $catalog;

    public function __construct()
    {
        $this->configService = new CoreConfigService();
        $this->catalog = new DeviceQueryCatalogService();
    }

    public function getConfig(int $siteId, bool $maskSecret = false): array
    {
        $saved = $this->configService->getConfigValue($siteId, RecycleConfigKeyDict::DEVICE_QUERY);
        $deviceConfig = is_array($saved) ? $saved : [];

        if (empty($deviceConfig)) {
            $thirdPartyConfig = $this->configService->getConfigValue($siteId, RecycleConfigKeyDict::THIRD_PARTY);
            $thirdPartyConfig = is_array($thirdPartyConfig) ? $thirdPartyConfig : [];
            $deviceConfig = is_array($thirdPartyConfig['device_query'] ?? null) ? $thirdPartyConfig['device_query'] : [];
        }

        $config = $this->sanitizeConfig($deviceConfig);

        return $maskSecret ? $this->maskSecret($config) : $config;
    }

    public function setConfig(int $siteId, array $data): bool
    {
        $old = $this->getConfig($siteId, false);
        $config = $this->sanitizeConfig($data);
        $config = $this->keepMaskedSecret($config, $old);

        return (bool)$this->configService->setConfig($siteId, RecycleConfigKeyDict::DEVICE_QUERY, $config);
    }

    public function getServices(int $siteId): array
    {
        $config = $this->getConfig($siteId);
        return $config['services'] ?? [];
    }

    public function getChannels(int $siteId): array
    {
        $config = $this->getConfig($siteId);
        return $config['channels'] ?? [];
    }

    public function getMappings(int $siteId): array
    {
        $config = $this->getConfig($siteId);
        return $config['mappings'] ?? [];
    }

    public function getDefaultConfig(): array
    {
        return $this->sanitizeConfig($this->defaultConfig());
    }

    public function sanitizeConfig(array $config): array
    {
        $default = $this->defaultConfig();
        $config = $this->mergeConfig($default, $config);

        if (empty($config['channels']) && !empty($config['3023'])) {
            $legacy = is_array($config['3023']) ? $config['3023'] : [];
            $config['channels'][] = array_merge($this->default3023Channel(), [
                'base_url' => $legacy['base_url'] ?? 'http://api.3023data.com',
                'token' => $legacy['api_key'] ?? $legacy['token'] ?? '',
                'timeout' => (int)($legacy['timeout'] ?? 300),
            ]);
        }

        $config['enabled'] = (int)(bool)($config['enabled'] ?? 1);
        $config['cache_enabled'] = (int)(bool)($config['cache_enabled'] ?? 1);
        $config['default_cache_ttl'] = max(0, (int)($config['default_cache_ttl'] ?? 2592000));
        $config['services'] = $this->mergeServices($config['services'] ?? []);
        $config['channels'] = $this->normalizeChannels($config['channels'] ?? []);
        $config['mappings'] = $this->normalizeMappings($config['mappings'] ?? [], $config['services']);

        return $config;
    }

    public function defaultConfig(): array
    {
        return [
            'enabled' => 1,
            'strategy' => 'priority',
            'cache_enabled' => 1,
            'default_cache_ttl' => 2592000,
            'default_channel_key' => '3023_main',
            'services' => $this->catalog->defaultServices(),
            'channels' => [
                $this->default3023Channel(),
                [
                    'key' => 'gkdt_main',
                    'name' => '爱查助手',
                    'provider' => 'service_id_query',
                    'enabled' => 0,
                    'priority' => 90,
                    'base_url' => 'https://api-srv.gkdt.com/inquiry/async',
                    'method' => 'GET',
                    'token' => '',
                    'auth_type' => 'query',
                    'auth_key' => 'token',
                    'service_id_key' => 'key',
                    'timeout' => 300,
                    'connect_timeout' => 10,
                    'balance_warning' => 20,
                ],
            ],
            'mappings' => $this->defaultMappings(),
        ];
    }

    private function default3023Channel(): array
    {
        return [
            'key' => '3023_main',
            'name' => '3023主渠道',
            'provider' => 'path_query',
            'enabled' => 1,
            'priority' => 100,
            'base_url' => 'http://api.3023data.com',
            'method' => 'GET',
            'token' => '',
            'auth_type' => 'header',
            'auth_key' => 'key',
            'timeout' => 300,
            'connect_timeout' => 10,
            'balance_warning' => 20,
        ];
    }

    private function defaultMappings(): array
    {
        $priceMap = [];
        foreach ($this->catalog->defaultServices() as $service) {
            $priceMap[$service['code']] = (float)($service['cost_price'] ?? 0);
        }

        $mappings = [];
        foreach ($this->catalog->endpointMap() as $endpoint => $serviceCode) {
            $mappings[] = [
                'service_code' => $serviceCode,
                'channel_key' => '3023_main',
                'enabled' => 1,
                'endpoint_type' => 'path',
                'endpoint_value' => $endpoint,
                'query_param' => str_starts_with($endpoint, '/apple/') ? 'sn' : $this->inferQueryParam($endpoint),
                'cost_price' => $priceMap[$serviceCode] ?? 0,
                'retry_on' => [410, 502, 503],
                'switch_on_404' => 0,
                'switch_on_no_data' => 0,
            ];
        }

        $mappings[] = [
            'service_code' => 'apple_coverage',
            'channel_key' => 'gkdt_main',
            'enabled' => 1,
            'endpoint_type' => 'service_id',
            'endpoint_value' => '10101',
            'query_param' => 'sn',
            'cost_price' => 0,
            'retry_on' => [410, 502, 503],
            'switch_on_404' => 0,
            'switch_on_no_data' => 0,
        ];
        $mappings[] = [
            'service_code' => 'apple_coverage_capacity',
            'channel_key' => 'gkdt_main',
            'enabled' => 1,
            'endpoint_type' => 'service_id',
            'endpoint_value' => '10102',
            'query_param' => 'sn',
            'cost_price' => 0,
            'retry_on' => [410, 502, 503],
            'switch_on_404' => 0,
            'switch_on_no_data' => 0,
        ];

        return $mappings;
    }

    private function mergeConfig(array $default, array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($default[$key]) && is_array($default[$key]) && !in_array($key, ['services', 'channels', 'mappings'], true)) {
                $default[$key] = $this->mergeConfig($default[$key], $value);
            } else {
                $default[$key] = $value;
            }
        }

        return $default;
    }

    private function mergeServices(array $services): array
    {
        $merged = [];
        foreach ($this->catalog->defaultServices() as $service) {
            $merged[$service['code']] = $service;
        }
        foreach ($services as $service) {
            if (!is_array($service) || empty($service['code'])) {
                continue;
            }
            $merged[(string)$service['code']] = array_merge($merged[(string)$service['code']] ?? [], $service);
        }

        foreach ($merged as $code => $service) {
            $service['show_in_check'] = (int)(bool)($service['show_in_check'] ?? $this->defaultShowInCheck((string)$code));
            $service['result_handler'] = (string)($service['result_handler'] ?? $this->defaultResultHandler((string)$code));
            $merged[$code] = $service;
        }

        return array_values($merged);
    }

    private function defaultShowInCheck(string $serviceCode): int
    {
        return in_array($serviceCode, [
            'apple_coverage_capacity',
            'apple_coverage',
            'apple_activationlock',
        ], true) ? 1 : 0;
    }

    private function defaultResultHandler(string $serviceCode): string
    {
        if (str_contains($serviceCode, 'coverage')) {
            return 'coverage';
        }
        if (str_contains($serviceCode, 'activationlock')) {
            return 'activationlock';
        }
        if (str_contains($serviceCode, 'mdm')) {
            return 'mdm';
        }

        return 'generic';
    }

    private function normalizeChannels(array $channels): array
    {
        $result = [];
        foreach ($channels as $channel) {
            if (!is_array($channel) || empty($channel['key'])) {
                continue;
            }
            $channel['enabled'] = (int)(bool)($channel['enabled'] ?? 1);
            $channel['priority'] = (int)($channel['priority'] ?? 0);
            $channel['timeout'] = max(1, (int)($channel['timeout'] ?? 300));
            $channel['connect_timeout'] = max(1, (int)($channel['connect_timeout'] ?? 10));
            $result[] = $channel;
        }

        return $result;
    }

    private function normalizeMappings(array $mappings, array $services): array
    {
        $serviceMap = [];
        foreach ($services as $service) {
            if (!empty($service['code'])) {
                $serviceMap[(string)$service['code']] = $service;
            }
        }

        $result = [];
        foreach ($mappings as $mapping) {
            if (!is_array($mapping) || empty($mapping['service_code']) || empty($mapping['channel_key'])) {
                continue;
            }
            $service = $serviceMap[(string)$mapping['service_code']] ?? [];
            $mapping['enabled'] = (int)(bool)($mapping['enabled'] ?? 1);
            $mapping['cost_price'] = (float)($mapping['cost_price'] ?? $service['cost_price'] ?? 0);
            $mapping['retry_on'] = is_array($mapping['retry_on'] ?? null) ? $mapping['retry_on'] : [410, 502, 503];
            $mapping['switch_on_404'] = (int)(bool)($mapping['switch_on_404'] ?? 0);
            $mapping['switch_on_no_data'] = (int)(bool)($mapping['switch_on_no_data'] ?? 0);
            $result[] = $mapping;
        }

        return $result;
    }

    private function maskSecret(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->maskSecret($value);
                continue;
            }

            if ($this->isSecretField((string)$key) && (string)$value !== '') {
                $config[$key] = '******';
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

            if ($this->isSecretField((string)$key) && $value === '******') {
                $config[$key] = $old[$key] ?? '';
            }
        }

        return $config;
    }

    private function isSecretField(string $field): bool
    {
        return in_array(strtolower($field), [
            'token',
            'open_id',
            'openid',
            'api_key',
            'secret',
            'secret_id',
            'secret_key',
            'authorization',
            'authorization_token',
        ], true);
    }

    private function inferQueryParam(string $endpoint): string
    {
        if (str_starts_with($endpoint, '/ip/')) {
            return 'ip';
        }
        if (str_starts_with($endpoint, '/phone/')) {
            return 'phone';
        }
        if (str_starts_with($endpoint, '/item/')) {
            return 'barcode';
        }

        return 'imei';
    }
}
