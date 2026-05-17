<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

use addon\hsx_recycle\app\model\third_party\DeviceQueryResult;
use addon\hsx_recycle\app\service\core\device_query\provider\PathQueryProvider;
use addon\hsx_recycle\app\service\core\device_query\provider\ServiceIdQueryProvider;
use core\exception\CommonException;

class CoreDeviceQueryService
{
    private DeviceQueryConfigService $configService;
    private DeviceQueryCatalogService $catalog;
    private DeviceQueryChannelSelector $selector;
    private DeviceQueryNormalizer $normalizer;
    private DeviceQueryPriceCalculator $priceCalculator;
    private DeviceQueryResultRecorder $recorder;

    public function __construct()
    {
        $this->configService = new DeviceQueryConfigService();
        $this->catalog = new DeviceQueryCatalogService();
        $this->selector = new DeviceQueryChannelSelector();
        $this->normalizer = new DeviceQueryNormalizer();
        $this->priceCalculator = new DeviceQueryPriceCalculator();
        $this->recorder = new DeviceQueryResultRecorder();
    }

    public function query(int $siteId, array $params): array
    {
        $queryCode = trim((string)($params['query_code'] ?? $params['imei'] ?? $params['sn'] ?? ''));
        if ($queryCode === '') {
            throw new CommonException('IMEI或序列号不能为空');
        }

        $config = $this->configService->getConfig($siteId);
        if (empty($config['enabled'])) {
            throw new CommonException('设备查询服务未启用');
        }

        $serviceCode = $this->resolveServiceCode($params);
        $service = $this->findService($config, $serviceCode);
        if (empty($service) || empty($service['enabled'])) {
            throw new CommonException('设备查询项未启用或不存在');
        }

        $queryType = (string)($params['query_type'] ?? $service['query_type'] ?? $this->detectQueryType($queryCode));
        $forceRefresh = (bool)($params['force_refresh'] ?? false);
        if (!$forceRefresh && !empty($config['cache_enabled'])) {
            $cached = $this->getCache($siteId, $queryCode, $service, $config);
            if ($cached) {
                return $cached;
            }
        }

        $candidates = $this->selector->select($config, $serviceCode, (string)($params['channel_key'] ?? ''));
        if (empty($candidates)) {
            throw new CommonException($this->getUnavailableChannelMessage($config, $serviceCode, (string)($params['channel_key'] ?? '')));
        }

        $errors = [];
        foreach ($candidates as $candidate) {
            $channel = $candidate['channel'];
            $mapping = $candidate['mapping'];
            $start = microtime(true);
            try {
                $provider = $this->makeProvider((string)($channel['provider'] ?? ''));
                $providerResult = $provider->query($channel, $mapping, $queryCode, $queryType);
                $duration = (int)round((microtime(true) - $start) * 1000);

                if (empty($providerResult['success']) || empty($providerResult['data'])) {
                    $price = $this->priceCalculator->calculate($providerResult, $service, $mapping, false, false);
                    $this->recorder->record([
                        'site_id' => $siteId,
                        'query_code' => $queryCode,
                        'query_type' => $queryType,
                        'service_code' => $serviceCode,
                        'service' => $service,
                        'channel' => $channel,
                        'mapping' => $mapping,
                        'provider_result' => $providerResult,
                        'normalized_result' => [],
                        'price' => $price,
                        'success' => false,
                        'duration' => $duration,
                    ]);

                    $message = $providerResult['message'] ?? '设备查询失败';
                    $errors[] = $channel['name'] . ': ' . $message;
                    if ($this->shouldTryNext($providerResult, $mapping)) {
                        continue;
                    }
                    throw new CommonException($message);
                }

                $normalized = $this->normalizer->normalize($serviceCode, $providerResult['data']);
                $price = $this->priceCalculator->calculate($providerResult, $service, $mapping, false, true);
                $this->recorder->record([
                    'site_id' => $siteId,
                    'query_code' => $queryCode,
                    'query_type' => $queryType,
                    'service_code' => $serviceCode,
                    'service' => $service,
                    'channel' => $channel,
                    'mapping' => $mapping,
                    'provider_result' => $providerResult,
                    'normalized_result' => $normalized,
                    'price' => $price,
                    'success' => true,
                    'duration' => $duration,
                ]);

                return [
                    'success' => true,
                    'service_code' => $serviceCode,
                    'service_name' => (string)$service['name'],
                    'channel_key' => (string)$channel['key'],
                    'channel_name' => (string)$channel['name'],
                    'provider' => (string)$channel['provider'],
                    'query_code' => $queryCode,
                    'query_type' => $queryType,
                    'from_cache' => false,
                    'data' => $normalized,
                    'cost' => $price['third_cost'],
                    'third_cost' => $price['third_cost'],
                    'cost_price' => $price['cost_price'],
                    'saved_cost' => $price['saved_cost'],
                    'balance' => $price['balance'],
                    'api_name' => (string)$service['name'],
                    'response_time' => $duration,
                    'duration' => $duration,
                ];
            } catch (CommonException $e) {
                throw $e;
            } catch (\Exception $e) {
                $errors[] = ($channel['name'] ?? $channel['key'] ?? '渠道') . ': ' . $e->getMessage();
                continue;
            }
        }

        throw new CommonException('设备查询失败: ' . implode('；', array_slice($errors, 0, 3)));
    }

    public function queryByEndpoint(int $siteId, string $queryCode, string $endpoint, array $extra = []): array
    {
        $endpoint = '/' . ltrim(trim($endpoint), '/');
        $serviceCode = $this->catalog->serviceCodeFromEndpoint($endpoint);

        return $this->query($siteId, array_merge($extra, [
            'query_code' => $queryCode,
            'service_code' => $serviceCode,
        ]));
    }

    private function resolveServiceCode(array $params): string
    {
        $serviceCode = trim((string)($params['service_code'] ?? ''));
        if ($serviceCode !== '') {
            return $serviceCode;
        }

        $api = trim((string)($params['api'] ?? $params['api_endpoint'] ?? ''));
        if ($api !== '') {
            return $this->catalog->serviceCodeFromEndpoint($api);
        }

        return 'apple_model';
    }

    private function findService(array $config, string $serviceCode): array
    {
        foreach ($config['services'] ?? [] as $service) {
            if ((string)($service['code'] ?? '') === $serviceCode) {
                return $service;
            }
        }

        return [];
    }

    private function getCache(int $siteId, string $queryCode, array $service, array $config): ?array
    {
        $serviceCode = (string)$service['code'];
        $cacheTtl = (int)($service['cache_ttl'] ?? $config['default_cache_ttl'] ?? 0);
        if ($cacheTtl <= 0) {
            return null;
        }

        $result = DeviceQueryResult::where('site_id', $siteId)
            ->where('query_code', $queryCode)
            ->where('status', 1)
            ->where('api_name', (string)$service['name'])
            ->where('create_at', '>', time() - $cacheTtl)
            ->order('create_at', 'desc')
            ->find();

        if (!$result) {
            return null;
        }

        $data = $result->toArray();
        $price = $this->priceCalculator->calculate([], $service, ['cost_price' => $data['cost_amount'] ?? $service['cost_price'] ?? 0], true);
        $duration = 0;
        try {
            $this->recorder->record([
                'site_id' => $siteId,
                'query_code' => $queryCode,
                'query_type' => (string)($data['query_type'] ?? $service['query_type'] ?? 'other'),
                'service_code' => $serviceCode,
                'service' => $service,
                'channel' => ['key' => 'cache', 'name' => '本地缓存', 'provider' => 'cache'],
                'mapping' => ['endpoint_type' => 'cache', 'endpoint_value' => $serviceCode, 'query_param' => $service['query_type'] ?? 'other'],
                'provider_result' => ['success' => true, 'third_code' => 0, 'message' => '缓存命中', 'data' => $data['query_result'] ?? [], 'raw_response' => $data['raw_response'] ?? []],
                'normalized_result' => $data['query_result'] ?? [],
                'price' => $price,
                'success' => true,
                'duration' => $duration,
                'from_cache' => true,
            ]);
        } catch (\Exception $e) {
        }

        return [
            'success' => true,
            'service_code' => $serviceCode,
            'service_name' => (string)$service['name'],
            'channel_key' => 'cache',
            'channel_name' => '本地缓存',
            'provider' => 'cache',
            'query_code' => $queryCode,
            'query_type' => (string)($data['query_type'] ?? $service['query_type'] ?? 'other'),
            'from_cache' => true,
            'data' => $data['query_result'] ?? [],
            'cost' => 0,
            'third_cost' => 0,
            'cost_price' => $price['cost_price'],
            'saved_cost' => $price['saved_cost'],
            'balance' => 0,
            'api_name' => (string)$service['name'],
            'response_time' => 0,
            'duration' => 0,
        ];
    }

    private function makeProvider(string $provider)
    {
        switch ($provider) {
            case 'path_query':
            case '3023':
                return new PathQueryProvider();
            case 'service_id_query':
                return new ServiceIdQueryProvider();
            default:
                throw new \Exception('不支持的设备查询渠道类型: ' . $provider);
        }
    }

    private function getUnavailableChannelMessage(array $config, string $serviceCode, string $channelKey = ''): string
    {
        $enabledChannels = [];
        foreach ($config['channels'] ?? [] as $channel) {
            if (!empty($channel['enabled']) && !empty($channel['key'])) {
                $enabledChannels[(string)$channel['key']] = $channel;
            }
        }

        $serviceMappings = [];
        foreach ($config['mappings'] ?? [] as $mapping) {
            if ((string)($mapping['service_code'] ?? '') === $serviceCode) {
                $serviceMappings[] = $mapping;
            }
        }

        if (empty($serviceMappings)) {
            return '当前查询项没有配置接口映射，请到设备查询配置的“接口映射”中绑定渠道和接口值';
        }

        $enabledMappings = array_values(array_filter($serviceMappings, static function ($mapping) {
            return !empty($mapping['enabled']);
        }));
        if (empty($enabledMappings)) {
            return '当前查询项的接口映射未启用，请启用映射后再查询';
        }

        if ($channelKey !== '') {
            foreach ($enabledMappings as $mapping) {
                if ((string)($mapping['channel_key'] ?? '') === $channelKey && !isset($enabledChannels[$channelKey])) {
                    return '指定的设备查询渠道不存在或未启用，请检查渠道配置';
                }
            }
            return '指定渠道没有绑定当前查询项，请检查接口映射';
        }

        $mappingChannelKeys = array_values(array_unique(array_filter(array_map(static function ($mapping) {
            return (string)($mapping['channel_key'] ?? '');
        }, $enabledMappings))));
        if (empty($enabledChannels)) {
            return '没有启用的设备查询渠道，请先启用渠道';
        }
        if (!array_intersect($mappingChannelKeys, array_keys($enabledChannels))) {
            return '当前查询项绑定的渠道不存在或未启用，请检查渠道和接口映射';
        }

        return '没有可用的设备查询渠道';
    }

    private function shouldTryNext(array $result, array $mapping): bool
    {
        $code = (int)($result['third_code'] ?? -1);
        if (empty($result['data']) && !empty($mapping['switch_on_no_data'])) {
            return true;
        }
        if ($code === 404) {
            return !empty($mapping['switch_on_404']);
        }
        if (in_array($code, [400, 401, 402, 403], true)) {
            return false;
        }

        return in_array($code, array_map('intval', $mapping['retry_on'] ?? [410, 502, 503]), true);
    }

    private function detectQueryType(string $queryCode): string
    {
        if (preg_match('/^\d{15}$/', $queryCode)) {
            return 'imei';
        }
        if (preg_match('/^[A-Z0-9]{10,12}$/i', $queryCode)) {
            return 'sn';
        }

        return 'other';
    }
}
