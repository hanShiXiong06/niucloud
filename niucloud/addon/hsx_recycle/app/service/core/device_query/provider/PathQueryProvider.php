<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query\provider;

class PathQueryProvider extends AbstractDeviceQueryProvider
{
    public function query(array $channel, array $mapping, string $queryCode, string $queryType): array
    {
        $request = $this->buildRequestConfig($channel, $mapping, $queryCode, $queryType);
        $response = $this->request(
            $request['method'],
            $request['url'],
            [],
            $request['headers'],
            $channel
        );

        return $this->normalizeProviderResponse($response);
    }

    /**
     * 把 3023 类“路径 + Header API Key”协议组装集中在适配器内，
     * 业务层只传标准查询号码和映射，不拼 URL、Header 或参数名。
     *
     * @return array{method:string,url:string,headers:array<int,string>}
     */
    protected function buildRequestConfig(array $channel, array $mapping, string $queryCode, string $queryType): array
    {
        $baseUrl = rtrim((string)($channel['base_url'] ?? ''), '/');
        $path = '/' . ltrim((string)($mapping['endpoint_value'] ?? ''), '/');
        if ($baseUrl === '' || $path === '/') {
            throw new \Exception('设备查询渠道地址或接口路径未配置');
        }

        $params = $this->authQueryParams($channel);
        $params[(string)($mapping['query_param'] ?? $queryType ?: 'sn')] = $queryCode;

        return [
            'method' => strtoupper((string)($channel['method'] ?? 'GET')),
            'url' => $this->appendQuery($baseUrl . $path, $params),
            'headers' => $this->buildHeaders($channel),
        ];
    }

    public function getProviderName(): string
    {
        return 'path_query';
    }
}
