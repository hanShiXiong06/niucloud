<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\provider;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryCategoryDict;
use addon\hsx_phone_query\app\dict\HsxPhoneQueryConfigDict;
use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;

/**
 * 手机查询服务商渠道解析服务
 */
class ProviderChannelService extends BaseCoreService
{
    public function getConfig(int $siteId): array
    {
        $saved = (new CoreConfigService())->getConfigValue($siteId, HsxPhoneQueryConfigDict::CONFIG_KEY);
        return HsxPhoneQueryConfigDict::normalizeConfig(is_array($saved) ? $saved : []);
    }

    public function getChannel(array $config, string $channelKey = ''): array
    {
        $channelKey = $channelKey ?: (string)($config['default_channel_key'] ?? '');
        foreach ($config['channels'] ?? [] as $channel) {
            if ((string)($channel['key'] ?? '') === $channelKey) {
                return $channel;
            }
        }

        return [];
    }

    public function getActiveContext(int $siteId): array
    {
        $config = $this->getConfig($siteId);
        if (empty($config['enabled'])) {
            return [];
        }

        $channelKey = (string)($config['default_channel_key'] ?? '');
        $channel = $this->getChannel($config, $channelKey);
        if (!HsxPhoneQueryConfigDict::isChannelReady($channel)) {
            return [];
        }

        $serviceCodes = [];
        foreach ($config['mappings'] ?? [] as $mapping) {
            if ((string)($mapping['channel_key'] ?? '') !== $channelKey || empty($mapping['enabled'])) {
                continue;
            }

            $serviceCode = (string)($mapping['service_code'] ?? '');
            if ($serviceCode !== '') {
                $serviceCodes[] = $serviceCode;
            }
        }

        return [
            'config' => $config,
            'channel' => $channel,
            'channel_key' => $channelKey,
            'channel_name' => (string)($channel['name'] ?? $channelKey),
            'query_param' => (string)($channel['query_param'] ?? 'sn'),
            'provider_item_map' => HsxPhoneQueryCategoryDict::providerItemMap($channelKey),
            'enabled_service_codes' => array_values(array_unique($serviceCodes)),
        ];
    }

    public function getMapping(array $config, int $typeId, string $serviceCode, string $channelKey, bool $includeDisabled = false): array
    {
        foreach ($config['mappings'] ?? [] as $mapping) {
            if (!$includeDisabled && empty($mapping['enabled'])) {
                continue;
            }
            if ((string)($mapping['channel_key'] ?? '') !== $channelKey) {
                continue;
            }
            if ($serviceCode !== '' && (string)($mapping['service_code'] ?? '') === $serviceCode) {
                return $mapping;
            }
            if ((string)($mapping['endpoint_value'] ?? '') === (string)$typeId) {
                return $mapping;
            }
        }

        return [];
    }
}
