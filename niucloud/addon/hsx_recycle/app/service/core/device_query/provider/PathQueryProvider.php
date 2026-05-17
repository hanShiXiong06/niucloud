<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query\provider;

class PathQueryProvider extends AbstractDeviceQueryProvider
{
    public function query(array $channel, array $mapping, string $queryCode, string $queryType): array
    {
        $baseUrl = rtrim((string)($channel['base_url'] ?? ''), '/');
        $path = '/' . ltrim((string)($mapping['endpoint_value'] ?? ''), '/');
        if ($baseUrl === '' || $path === '/') {
            throw new \Exception('设备查询渠道地址或接口路径未配置');
        }

        $params = $this->authQueryParams($channel);
        $params[(string)($mapping['query_param'] ?? $queryType ?: 'sn')] = $queryCode;

        $url = $this->appendQuery($baseUrl . $path, $params);
        $response = $this->request((string)($channel['method'] ?? 'GET'), $url, [], $this->buildHeaders($channel), $channel);

        return $this->normalizeProviderResponse($response);
    }

    public function getProviderName(): string
    {
        return 'path_query';
    }
}
