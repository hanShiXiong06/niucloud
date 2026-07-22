<?php

namespace addon\hsx_phone_query\app\dict;

/**
 * 手机查询配置字典
 */
class HsxPhoneQueryConfigDict
{
    public const CONFIG_KEY = 'hsx_phone_query_config';

    public static function defaultConfig(): array
    {
        return [
            'enabled' => 1,
            'default_channel_key' => '3023_main',
            'channels' => [
                [
                    'key' => 'gkdt_main',
                    'name' => '爱查助手',
                    'provider' => 'gkdt_query',
                    'enabled' => 0,
                    'base_url' => 'https://api-srv.gkdt.com/inquiry/async',
                    'method' => 'GET',
                    'appid' => '',
                    'secret' => '',
                    'service_id_key' => 'key',
                    'query_param' => 'code',
                    'style' => '11',
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'verify_ssl' => 1,
                ],
                [
                    'key' => '3023_main',
                    'name' => '3023Data',
                    'provider' => 'path_query',
                    'enabled' => 1,
                    'base_url' => 'https://api.3023data.com',
                    'method' => 'GET',
                    'token' => '',
                    'auth_type' => 'header',
                    'auth_key' => 'key',
                    'query_param' => 'sn',
                    'timeout' => 300,
                    'connect_timeout' => 10,
                    'verify_ssl' => 1,
                ],
            ],
            'mappings' => self::defaultMappings(),
            'display_config' => self::defaultDisplayConfig(),
        ];
    }

    public static function defaultDisplayConfig(): array
    {
        return [
            'brand_name' => '手机查询报告',
            'support_text' => '查询结果仅供交易验机参考',
            'detail' => [
                'show_device_image' => 1,
                'show_empty_fields' => 0,
                'show_raw_result' => 0,
                'mask_query_code' => 0,
            ],
            'watermark' => [
                'enabled' => 1,
                'text' => '仅供参考',
                'color' => 'rgba(18, 24, 38, 0.06)',
                'size' => 88,
                'opacity' => 0.72,
                'rotate' => -24,
            ],
            'share' => [
                'enabled' => 1,
                'poster_enabled' => 1,
                'title' => '设备查询报告',
                'subtitle' => '长按识别或分享给客户查看',
                'footer' => '报告由系统自动生成',
                'customer_phone' => '',
            ],
        ];
    }

    private static function defaultMappings(): array
    {
        $mappings = [];

        foreach (HsxPhoneQueryCategoryDict::providerAichaMappings() as $mapping) {
            $mappings[] = [
                'service_code' => $mapping['service_code'],
                'channel_key' => 'gkdt_main',
                'endpoint_type' => 'service_id',
                'endpoint_value' => $mapping['endpoint_value'],
                'query_param' => 'code',
                'cost_price' => $mapping['cost_price'],
                'enabled' => 1,
            ];
        }

        foreach (HsxPhoneQueryCategoryDict::provider3023Mappings() as $mapping) {
            $mappings[] = [
                'service_code' => $mapping['service_code'],
                'channel_key' => '3023_main',
                'endpoint_type' => 'path',
                'endpoint_value' => $mapping['endpoint_value'],
                'query_param' => (string)($mapping['query_param'] ?? HsxPhoneQueryCategoryDict::infer3023QueryParam((string)$mapping['endpoint_value'])),
                'cost_price' => $mapping['cost_price'],
                'enabled' => 1,
            ];
        }

        return $mappings;
    }

    public static function normalizeConfig(array $data): array
    {
        $default = self::defaultConfig();
        $inputChannels = array_key_exists('channels', $data) && is_array($data['channels']) ? $data['channels'] : null;
        $inputMappings = array_key_exists('mappings', $data) && is_array($data['mappings']) ? $data['mappings'] : null;
        $inputDisplayConfig = array_key_exists('display_config', $data) && is_array($data['display_config']) ? $data['display_config'] : [];
        unset($data['channels'], $data['mappings'], $data['display_config']);
        $data = array_replace_recursive($default, $data);

        $defaultChannels = [];
        foreach ($default['channels'] as $channel) {
            $defaultChannels[$channel['key']] = $channel;
        }
        if ($inputChannels !== null) {
            foreach ($inputChannels as $channel) {
                if (!is_array($channel)) {
                    continue;
                }
                $key = trim((string)($channel['key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $defaultChannels[$key] = array_replace_recursive($defaultChannels[$key] ?? [], $channel);
            }
        }

        $channels = [];
        foreach ($defaultChannels as $channel) {
            if (!is_array($channel)) {
                continue;
            }

            $key = trim((string)($channel['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $channel = array_replace_recursive($defaultChannels[$key] ?? [], $channel);
            $provider = trim((string)($channel['provider'] ?? ''));
            if ($provider === 'service_id_query') {
                $provider = 'gkdt_query';
            }
            $appid = trim((string)($channel['appid'] ?? ''));
            $secret = trim((string)($channel['secret'] ?? ''));
            $token = trim((string)($channel['token'] ?? ''));
            $enabled = (int)(bool)($channel['enabled'] ?? 0);
            $queryParam = trim((string)($channel['query_param'] ?? 'sn'));
            if ($provider === 'gkdt_query') {
                $queryParam = 'code';
            }
            $baseUrl = trim((string)($channel['base_url'] ?? ''));
            if ($provider === 'path_query' && str_starts_with(strtolower($baseUrl), 'http://api.3023data.com')) {
                $baseUrl = 'https://' . substr($baseUrl, strlen('http://'));
            }
            if ($provider === 'gkdt_query' && str_contains($baseUrl, '?')) {
                $baseUrl = explode('?', $baseUrl, 2)[0];
            }

            $channels[] = [
                'key' => $key,
                'name' => trim((string)($channel['name'] ?? $key)),
                'provider' => $provider,
                'enabled' => $enabled,
                'base_url' => $baseUrl,
                'method' => strtoupper(trim((string)($channel['method'] ?? 'GET'))) ?: 'GET',
                'appid' => $appid,
                'secret' => $secret,
                'service_id_key' => trim((string)($channel['service_id_key'] ?? 'key')),
                'style' => trim((string)($channel['style'] ?? '11')),
                'token' => $token,
                'auth_type' => trim((string)($channel['auth_type'] ?? 'header')),
                'auth_key' => trim((string)($channel['auth_key'] ?? 'key')),
                'query_param' => $queryParam,
                'timeout' => max(1, (int)($channel['timeout'] ?? 30)),
                'connect_timeout' => max(1, (int)($channel['connect_timeout'] ?? 10)),
                'verify_ssl' => (int)(bool)($channel['verify_ssl'] ?? 1),
            ];
        }

        $defaultChannelKey = self::resolveSingleActiveChannelKey($channels, trim((string)($data['default_channel_key'] ?? '')));
        foreach ($channels as &$channel) {
            $channel['enabled'] = ((string)($channel['key'] ?? '') === $defaultChannelKey && self::hasChannelCredential($channel)) ? 1 : 0;
        }
        unset($channel);

        $rawMappings = [];
        foreach ($default['mappings'] as $mapping) {
            $key = self::mappingKey($mapping);
            if ($key !== '') {
                $rawMappings[$key] = $mapping;
            }
        }

        if ($inputMappings !== null) {
            foreach ($inputMappings as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }

                $key = self::mappingKey($mapping);
                if ($key === '') {
                    continue;
                }

                $rawMappings[$key] = array_replace_recursive($rawMappings[$key] ?? [], $mapping);
            }
        }

        $mappings = [];
        foreach ($rawMappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $serviceCode = trim((string)($mapping['service_code'] ?? ''));
            $channelKey = trim((string)($mapping['channel_key'] ?? ''));
            $endpointValue = trim((string)($mapping['endpoint_value'] ?? ''));
            if ($serviceCode === '' || $channelKey === '' || $endpointValue === '') {
                continue;
            }

            $endpointType = trim((string)($mapping['endpoint_type'] ?? ''));
            $queryParam = trim((string)($mapping['query_param'] ?? 'sn'));
            if ($channelKey === 'gkdt_main' || $endpointType === 'service_id') {
                $queryParam = 'code';
            }
            if ($channelKey === '3023_main' || $endpointType === 'path') {
                $queryParam = $queryParam ?: HsxPhoneQueryCategoryDict::infer3023QueryParam($endpointValue);
                if ($queryParam === 'sn') {
                    $queryParam = HsxPhoneQueryCategoryDict::infer3023QueryParam($endpointValue);
                }
            }

            $mappings[] = [
                'service_code' => $serviceCode,
                'channel_key' => $channelKey,
                'endpoint_type' => $endpointType,
                'endpoint_value' => $endpointValue,
                'query_param' => $queryParam,
                'cost_price' => round((float)($mapping['cost_price'] ?? 0), 3),
                'enabled' => (int)(bool)($mapping['enabled'] ?? 1),
            ];
        }

        return [
            'enabled' => (int)(bool)($data['enabled'] ?? 1),
            'default_channel_key' => $defaultChannelKey,
            'channels' => $channels,
            'mappings' => $mappings,
            'display_config' => self::normalizeDisplayConfig($inputDisplayConfig ?: ($data['display_config'] ?? [])),
        ];
    }

    public static function normalizeDisplayConfig(array $data): array
    {
        $default = self::defaultDisplayConfig();
        $data = array_replace_recursive($default, $data);

        return [
            'brand_name' => trim((string)($data['brand_name'] ?? $default['brand_name'])),
            'support_text' => trim((string)($data['support_text'] ?? $default['support_text'])),
            'detail' => [
                'show_device_image' => (int)(bool)($data['detail']['show_device_image'] ?? 1),
                'show_empty_fields' => (int)(bool)($data['detail']['show_empty_fields'] ?? 0),
                'show_raw_result' => (int)(bool)($data['detail']['show_raw_result'] ?? 0),
                'mask_query_code' => (int)(bool)($data['detail']['mask_query_code'] ?? 0),
            ],
            'watermark' => [
                'enabled' => (int)(bool)($data['watermark']['enabled'] ?? 1),
                'text' => trim((string)($data['watermark']['text'] ?? $default['watermark']['text'])),
                'color' => trim((string)($data['watermark']['color'] ?? $default['watermark']['color'])),
                'size' => max(20, min(180, (int)($data['watermark']['size'] ?? $default['watermark']['size']))),
                'opacity' => max(0, min(1, (float)($data['watermark']['opacity'] ?? $default['watermark']['opacity']))),
                'rotate' => max(-60, min(60, (int)($data['watermark']['rotate'] ?? $default['watermark']['rotate']))),
            ],
            'share' => [
                'enabled' => (int)(bool)($data['share']['enabled'] ?? 1),
                'poster_enabled' => (int)(bool)($data['share']['poster_enabled'] ?? 1),
                'title' => trim((string)($data['share']['title'] ?? $default['share']['title'])),
                'subtitle' => trim((string)($data['share']['subtitle'] ?? $default['share']['subtitle'])),
                'footer' => trim((string)($data['share']['footer'] ?? $default['share']['footer'])),
                'customer_phone' => trim((string)($data['share']['customer_phone'] ?? $default['share']['customer_phone'])),
            ],
        ];
    }

    public static function isChannelReady(array $channel): bool
    {
        if (empty($channel['enabled'])) {
            return false;
        }

        return self::hasChannelCredential($channel);
    }

    public static function hasChannelCredential(array $channel): bool
    {
        if (in_array(($channel['provider'] ?? ''), ['gkdt_query', 'service_id_query'], true)) {
            return trim((string)($channel['appid'] ?? '')) !== '' && trim((string)($channel['secret'] ?? '')) !== '';
        }

        if (($channel['provider'] ?? '') === 'path_query') {
            return trim((string)($channel['token'] ?? '')) !== '';
        }

        return false;
    }

    private static function resolveSingleActiveChannelKey(array $channels, string $selectedKey): string
    {
        $firstKey = '';
        $selectedChannel = [];
        $firstEnabledReady = '';
        $firstReady = '';

        foreach ($channels as $channel) {
            $key = (string)($channel['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $firstKey = $firstKey ?: $key;
            if ($key === $selectedKey) {
                $selectedChannel = $channel;
            }
            if (!empty($channel['enabled']) && self::hasChannelCredential($channel) && $firstEnabledReady === '') {
                $firstEnabledReady = $key;
            }
            if (self::hasChannelCredential($channel) && $firstReady === '') {
                $firstReady = $key;
            }
        }

        if (!empty($selectedChannel) && self::hasChannelCredential($selectedChannel)) {
            return $selectedKey;
        }
        if ($firstEnabledReady !== '') {
            return $firstEnabledReady;
        }
        if ($firstReady !== '') {
            return $firstReady;
        }
        if (!empty($selectedChannel)) {
            return $selectedKey;
        }

        return $firstKey ?: '3023_main';
    }

    private static function resolveDefaultChannelKey(array $channels, string $selectedKey): string
    {
        $firstKey = '';
        $firstReadyKey = '';
        $selectedReady = false;

        foreach ($channels as $channel) {
            $key = (string)($channel['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $firstKey = $firstKey ?: $key;
            $ready = self::isChannelReady($channel);
            if ($ready && $firstReadyKey === '') {
                $firstReadyKey = $key;
            }
            if ($key === $selectedKey && $ready) {
                $selectedReady = true;
            }
        }

        if ($selectedReady) {
            return $selectedKey;
        }

        return $firstReadyKey ?: ($selectedKey ?: ($firstKey ?: '3023_main'));
    }

    private static function mappingKey(array $mapping): string
    {
        $serviceCode = trim((string)($mapping['service_code'] ?? ''));
        $channelKey = trim((string)($mapping['channel_key'] ?? ''));
        if ($serviceCode === '' || $channelKey === '') {
            return '';
        }

        return $channelKey . '|' . $serviceCode;
    }
}
