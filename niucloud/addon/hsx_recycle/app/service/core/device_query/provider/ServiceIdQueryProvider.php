<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query\provider;

class ServiceIdQueryProvider extends AbstractDeviceQueryProvider
{
    public function query(array $channel, array $mapping, string $queryCode, string $queryType): array
    {
        $baseUrl = (string)($channel['base_url'] ?? '');
        $serviceId = (string)($mapping['endpoint_value'] ?? '');
        if ($baseUrl === '' || $serviceId === '') {
            throw new \Exception('设备查询渠道地址或服务ID未配置');
        }

        $params = $this->authQueryParams($channel);
        $params[(string)($channel['service_id_key'] ?? 'key')] = $serviceId;
        $params[(string)($mapping['query_param'] ?? $queryType ?: 'sn')] = $queryCode;

        $url = $this->appendQuery($baseUrl, $params);
        $response = $this->request((string)($channel['method'] ?? 'GET'), $url, [], $this->buildHeaders($channel), $channel);

        return $this->normalizeProviderResponse($response);
    }

    public function getProviderName(): string
    {
        return 'service_id_query';
    }
}
