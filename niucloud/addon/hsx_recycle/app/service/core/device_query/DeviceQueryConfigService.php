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
                'base_url' => $legacy['base_url'] ?? 'https://api.3023data.com',
                'token' => $legacy['api_key'] ?? $legacy['token'] ?? '',
                'timeout' => (int)($legacy['timeout'] ?? 300),
            ]);
        }

        $config['enabled'] = (int)(bool)($config['enabled'] ?? 1);
        $config['cache_enabled'] = (int)(bool)($config['cache_enabled'] ?? 1);
        $config['default_cache_ttl'] = max(0, (int)($config['default_cache_ttl'] ?? 2592000));
        // services 是站点可维护目录。初始化时使用字典默认值，保存后不再强制补回已删除项。
        $config['services'] = $this->normalizeServices($config['services'] ?? []);
        $config['channels'] = $this->normalizeChannels($config['channels'] ?? []);
        $config['mappings'] = $this->normalizeMappings($config['mappings'] ?? [], $config['services'], $config['channels']);

        usort($config['services'], static function (array $a, array $b) {
            return (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0);
        });

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
            'channels' => array_map(static function (array $provider) {
                $provider['token'] = '';
                $provider['appid'] = '';
                $provider['secret'] = '';
                $provider['timeout'] = 300;
                $provider['connect_timeout'] = 10;
                $provider['balance_warning'] = 20;
                return $provider;
            }, $this->catalog->defaultProviders()),
            'mappings' => $this->catalog->defaultMappings(),
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
            'base_url' => 'https://api.3023data.com',
            'method' => 'GET',
            'token' => '',
            'auth_type' => 'header',
            'auth_key' => 'key',
            'timeout' => 300,
            'connect_timeout' => 10,
            'balance_warning' => 20,
        ];
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

    private function normalizeServices(array $services): array
    {
        $merged = [];
        foreach ($services as $service) {
            if (!is_array($service) || empty($service['code'])) {
                continue;
            }
            $code = trim((string)$service['code']);
            $service['code'] = $code;
            $service['name'] = trim((string)($service['name'] ?? $code));
            $service['category'] = (string)($service['category'] ?? 'other');
            $service['query_type'] = (string)($service['query_type'] ?? 'imei');
            $service['enabled'] = (int)(bool)($service['enabled'] ?? 1);
            $service['sort'] = (int)($service['sort'] ?? 0);
            $service['cost_price'] = max(0, (float)($service['cost_price'] ?? 0));
            $service['cache_ttl'] = max(0, (int)($service['cache_ttl'] ?? 0));
            $merged[$code] = array_merge($merged[$code] ?? [], $service);
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
        $providerDefaults = [];
        foreach ($this->catalog->defaultProviders() as $provider) {
            if (!empty($provider['key'])) {
                $providerDefaults[(string)$provider['key']] = $provider;
            }
        }

        $result = [];
        foreach ($channels as $channel) {
            if (!is_array($channel) || empty($channel['key'])) {
                continue;
            }
            $key = (string)$channel['key'];
            $channel = array_merge($providerDefaults[$key] ?? [], $channel);
            if ($key === 'gkdt_main') {
                // 兼容旧版将爱查保存成通用 service_id_query + query token 的站点配置。
                // 爱查实际是 appid/secret 签名协议，不能继续继承旧鉴权字段。
                $channel['provider'] = 'gkdt_query';
                $channel['auth_type'] = 'signed';
                $channel['auth_key'] = '';
                $channel['service_id_key'] = 'key';
                $channel['query_param'] = 'code';
            }
            $channel['enabled'] = (int)(bool)($channel['enabled'] ?? 1);
            $channel['priority'] = (int)($channel['priority'] ?? 0);
            $channel['timeout'] = max(1, (int)($channel['timeout'] ?? 300));
            $channel['connect_timeout'] = max(1, (int)($channel['connect_timeout'] ?? 10));
            $channel['verify_ssl'] = (int)(bool)($channel['verify_ssl'] ?? 1);
            $channel['style'] = (string)($channel['style'] ?? '11');
            $channel['appid'] = trim((string)($channel['appid'] ?? ''));
            $channel['secret'] = trim((string)($channel['secret'] ?? ''));
            $channel['token'] = trim((string)($channel['token'] ?? $channel['api_key'] ?? ''));
            $result[] = $channel;
        }

        return $result;
    }

    private function normalizeMappings(array $mappings, array $services, array $channels = []): array
    {
        $serviceMap = [];
        foreach ($services as $service) {
            if (!empty($service['code'])) {
                $serviceMap[(string)$service['code']] = $service;
            }
        }

        $channelMap = [];
        foreach ($channels as $channel) {
            if (!empty($channel['key'])) {
                $channelMap[(string)$channel['key']] = $channel;
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
            $provider = (string)($channelMap[(string)$mapping['channel_key']]['provider'] ?? '');
            $mapping['query_param'] = in_array($provider, ['gkdt_query', 'service_id_query'], true) ? 'code' : $this->resolveQueryParam(
                (string)($mapping['query_param'] ?? ''),
                (string)($mapping['endpoint_value'] ?? ''),
                (string)($service['query_type'] ?? '')
            );
            $result[] = $mapping;
        }

        return $result;
    }

    private function resolveQueryParam(string $mappingQueryParam, string $endpoint, string $serviceQueryType = ''): string
    {
        $mappingQueryParam = trim($mappingQueryParam);
        if ($mappingQueryParam !== '') {
            return $mappingQueryParam;
        }

        $serviceQueryType = trim($serviceQueryType);
        if ($serviceQueryType !== '') {
            return $serviceQueryType;
        }

        return $this->inferQueryParam($endpoint);
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
