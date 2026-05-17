<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device_query;

use addon\hsx_recycle\app\service\core\device_query\CoreDeviceQueryService;
use addon\hsx_recycle\app\service\core\device_query\DeviceQueryCatalogService;
use addon\hsx_recycle\app\service\core\device_query\DeviceQueryConfigService as CoreDeviceQueryConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 设备查询配置服务类
 * 说明：新版本改为直接读写 sys_config 中的设备查询配置，不再使用老的 device_query_config 表。
 */
class DeviceQueryConfigService extends BaseAdminService
{
    private CoreDeviceQueryConfigService $coreConfigService;
    private DeviceQueryCatalogService $catalog;

    public function __construct()
    {
        parent::__construct();
        $this->coreConfigService = new CoreDeviceQueryConfigService();
        $this->catalog = new DeviceQueryCatalogService();
    }

    public function getPage(array $where = [])
    {
        $config = $this->coreConfigService->getConfig($this->site_id, true);
        $services = $config['services'] ?? [];
        $channels = $config['channels'] ?? [];
        $mappings = $config['mappings'] ?? [];

        $channelMap = [];
        foreach ($channels as $channel) {
            if (!empty($channel['key'])) {
                $channelMap[(string)$channel['key']] = $channel;
            }
        }

        $list = [];
        foreach ($services as $service) {
            if (empty($service['code'])) {
                continue;
            }

            $serviceMappings = array_values(array_filter($mappings, static function ($mapping) use ($service) {
                return (string)($mapping['service_code'] ?? '') === (string)($service['code'] ?? '');
            }));
            $enabledServiceMappings = array_values(array_filter($serviceMappings, static function ($mapping) {
                return !empty($mapping['enabled']);
            }));

            $channelKeys = [];
            foreach ($enabledServiceMappings as $mapping) {
                $channelKey = (string)($mapping['channel_key'] ?? '');
                if ($channelKey !== '' && isset($channelMap[$channelKey])) {
                    $channelKeys[] = $channelKey;
                }
            }

            $list[] = [
                'code' => $service['code'],
                'name' => $service['name'] ?? '',
                'category' => $service['category'] ?? '',
                'query_type' => $service['query_type'] ?? '',
                'enabled' => (int)($service['enabled'] ?? 0),
                'sort' => (int)($service['sort'] ?? 0),
                'cost_price' => (float)($service['cost_price'] ?? 0),
                'cache_ttl' => (int)($service['cache_ttl'] ?? 0),
                'show_in_check' => (int)($service['show_in_check'] ?? 0),
                'result_handler' => (string)($service['result_handler'] ?? 'generic'),
                'channel_count' => count(array_unique($channelKeys)),
                'mapping_count' => count($serviceMappings),
                'enabled_mapping_count' => count($enabledServiceMappings),
                'channels' => array_values(array_unique($channelKeys)),
            ];
        }

        return [
            'list' => $list,
            'total' => count($list),
            'config' => $config,
        ];
    }

    public function getInfo(string $id)
    {
        $config = $this->coreConfigService->getConfig($this->site_id, true);
        $services = $config['services'] ?? [];
        $service = null;
        foreach ($services as $item) {
            if ((string)($item['code'] ?? '') === (string)$id || (string)($item['sort'] ?? '') === (string)$id) {
                $service = $item;
                break;
            }
        }

        if (!is_array($service)) {
            throw new CommonException('DEVICE_QUERY_CONFIG_NOT_EXIST');
        }

        return $service;
    }

    public function add(array $data)
    {
        return $this->saveConfig($data);
    }

    public function saveConfigCenter(array $data): bool
    {
        $config = array_merge(
            $this->coreConfigService->getConfig($this->site_id, false),
            [
                'enabled' => (int)(bool)($data['enabled'] ?? 1),
                'cache_enabled' => (int)(bool)($data['cache_enabled'] ?? 1),
                'default_cache_ttl' => (int)($data['default_cache_ttl'] ?? 2592000),
                'default_channel_key' => (string)($data['default_channel_key'] ?? '3023_main'),
            ]
        );

        if (isset($data['channels']) && is_array($data['channels'])) {
            $config['channels'] = $data['channels'];
        }
        if (isset($data['services']) && is_array($data['services'])) {
            $config['services'] = $data['services'];
        }
        if (isset($data['mappings']) && is_array($data['mappings'])) {
            $config['mappings'] = $data['mappings'];
        }

        return $this->coreConfigService->setConfig($this->site_id, $config);
    }

    public function edit(string $id, array $data)
    {
        return $this->saveConfig($data);
    }

    public function del(string $id)
    {
        $config = $this->coreConfigService->getConfig($this->site_id, false);
        $services = $config['services'] ?? [];
        $mappings = $config['mappings'] ?? [];
        $found = false;
        foreach ($services as $index => $service) {
            if ((string)($service['code'] ?? '') === (string)$id || (string)($service['sort'] ?? '') === (string)$id) {
                unset($services[$index]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new CommonException('DEVICE_QUERY_CONFIG_NOT_EXIST');
        }

        $config['services'] = array_values($services);
        $config['mappings'] = array_values(array_filter($mappings, static function ($mapping) use ($id) {
            return (string)($mapping['service_code'] ?? '') !== (string)$id;
        }));
        $this->coreConfigService->setConfig($this->site_id, $config);

        return true;
    }

    public function modifyStatus(string $id, int $status)
    {
        $config = $this->coreConfigService->getConfig($this->site_id, false);
        $services = $config['services'] ?? [];
        $found = false;
        foreach ($services as $index => $service) {
            if ((string)($service['code'] ?? '') === (string)$id || (string)($service['sort'] ?? '') === (string)$id) {
                $services[$index]['enabled'] = (int)(bool)$status;
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new CommonException('DEVICE_QUERY_CONFIG_NOT_EXIST');
        }

        $config['services'] = array_values($services);
        $this->coreConfigService->setConfig($this->site_id, $config);

        return true;
    }

    public function testConnection(string $id, array $data = [])
    {
        $config = $this->coreConfigService->getConfig($this->site_id, false);
        $services = $config['services'] ?? [];
        $service = null;
        foreach ($services as $item) {
            if ((string)($item['code'] ?? '') === (string)$id || (string)($item['sort'] ?? '') === (string)$id) {
                $service = $item;
                break;
            }
        }

        if (!is_array($service) || empty($service['code'])) {
            throw new CommonException('DEVICE_QUERY_CONFIG_NOT_EXIST');
        }

        $queryCode = trim((string)($data['query_code'] ?? ''));
        if ($queryCode === '') {
            throw new CommonException('请输入用于测试的 IMEI 或序列号');
        }

        $result = (new CoreDeviceQueryService())->query($this->site_id, [
            'query_code' => $queryCode,
            'service_code' => (string)$service['code'],
            'query_type' => trim((string)($data['query_type'] ?? '')),
            'channel_key' => trim((string)($data['channel_key'] ?? '')),
            'force_refresh' => true,
        ]);

        return [
            'success' => true,
            'message' => 'API测试成功',
            'response_time' => $result['response_time'] ?? 0,
            'test_result' => $result,
        ];
    }

    public function getStats(string $id, array $data = [])
    {
        $queryResultService = new DeviceQueryResultService();
        return $queryResultService->getStats(array_merge($data, [
            'site_id' => $this->site_id,
        ]));
    }

    public function getChannelBalance(string $channelKey): array
    {
        $config = $this->coreConfigService->getConfig($this->site_id, false);
        $channel = null;
        foreach ($config['channels'] ?? [] as $item) {
            if ((string)($item['key'] ?? '') === $channelKey) {
                $channel = $item;
                break;
            }
        }

        if (!is_array($channel)) {
            throw new CommonException('设备查询渠道不存在');
        }

        if (($channel['provider'] ?? '') !== 'path_query') {
            throw new CommonException('当前渠道暂未配置余额查询接口');
        }

        $baseUrl = rtrim((string)($channel['base_url'] ?? ''), '/');
        $token = (string)($channel['token'] ?? $channel['api_key'] ?? '');
        if ($baseUrl === '') {
            throw new CommonException('请先配置渠道请求地址');
        }
        if ($token === '') {
            throw new CommonException('请先配置渠道 API Key');
        }

        $headers = [];
        $params = [];
        if (($channel['auth_type'] ?? 'header') === 'query') {
            $params[(string)($channel['auth_key'] ?? 'key')] = $token;
        } else {
            $headers[] = (string)($channel['auth_key'] ?? 'key') . ': ' . $token;
        }

        $url = $baseUrl . '/user/balance';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $start = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, max(1, (int)($channel['timeout'] ?? 60)));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(1, (int)($channel['connect_timeout'] ?? 10)));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $duration = (int)round((microtime(true) - $start) * 1000);

        if ($body === false) {
            throw new CommonException('余额查询请求失败: ' . $error);
        }

        $decoded = json_decode((string)$body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new CommonException('余额接口没有返回有效 JSON，HTTP状态码：' . $httpCode);
        }

        $code = (int)($decoded['code'] ?? -1);
        if ($code !== 0) {
            throw new CommonException((string)($decoded['message'] ?? $decoded['msg'] ?? '余额查询失败'));
        }

        $balance = $decoded['balance'] ?? $decoded['data']['balance'] ?? $decoded['data'] ?? 0;

        return [
            'channel_key' => (string)($channel['key'] ?? ''),
            'channel_name' => (string)($channel['name'] ?? ''),
            'balance' => (float)$balance,
            'response_time' => $duration,
            'raw_response' => $decoded,
        ];
    }

    private function saveConfig(array $data)
    {
        $config = $this->coreConfigService->getConfig($this->site_id, false);
        $services = $config['services'] ?? [];
        $serviceCode = trim((string)($data['code'] ?? ''));
        if ($serviceCode === '') {
            throw new CommonException('请填写查询项编码');
        }

        $record = [
            'code' => $serviceCode,
            'name' => (string)($data['name'] ?? ''),
            'category' => (string)($data['category'] ?? 'other'),
            'query_type' => (string)($data['query_type'] ?? 'other'),
            'enabled' => (int)(bool)($data['enabled'] ?? 1),
            'sort' => (int)($data['sort'] ?? 0),
            'cost_price' => (float)($data['cost_price'] ?? 0),
            'cache_ttl' => (int)($data['cache_ttl'] ?? 0),
            'show_in_check' => (int)(bool)($data['show_in_check'] ?? 0),
            'result_handler' => (string)($data['result_handler'] ?? 'generic'),
        ];

        $found = false;
        foreach ($services as $index => $service) {
            if ((string)($service['code'] ?? '') === $serviceCode) {
                $services[$index] = array_merge($service, $record);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $services[] = $record;
        }

        $config['services'] = array_values($services);
        $this->coreConfigService->setConfig($this->site_id, $config);

        return true;
    }
}
