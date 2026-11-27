<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\provider;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryCategoryDict;
use addon\hsx_phone_query\app\service\core\provider\contract\PhoneQueryProviderInterface;

/**
 * 3023Data 路径接口适配器。
 */
class PathPhoneQueryProvider extends AbstractPhoneQueryProvider implements PhoneQueryProviderInterface
{
    public function supports(string $provider): bool
    {
        return $provider === 'path_query';
    }

    public function query(array $channel, array $mapping, string $queryCode): array
    {
        $request = $this->buildRequest($channel, $mapping, $queryCode);
        $startedAt = microtime(true);
        $response = [];

        try {
            $response = $this->send($request);
            $thirdCode = (int)($response['code'] ?? -1);
            $success = $thirdCode === 0;
            $message = (string)($response['message'] ?? $response['msg'] ?? ($success ? '查询成功' : '查询失败'));
            if (!$success) {
                return $this->failureResult('path_query', $request, $startedAt, $message, $response);
            }

            $data = $response['data'] ?? $response['result'] ?? [];
            return [
                'success' => true,
                'provider' => 'path_query',
                'third_code' => (string)$thirdCode,
                'message' => $message,
                'data' => is_array($data) ? $data : ['value' => $data],
                'source' => $response['source'] ?? null,
                'cost' => (float)($response['cost'] ?? 0),
                'balance' => $response['balance'] ?? null,
                'request_method' => $request['method'],
                'request_url' => $request['url'],
                'request_params' => $request['log_params'],
                'response' => $response,
                'duration_ms' => $this->durationMs($startedAt),
            ];
        } catch (\Throwable $e) {
            return $this->failureResult('path_query', $request, $startedAt, $e->getMessage(), $response);
        }
    }

    public function buildRequest(array $channel, array $mapping, string $queryCode): array
    {
        $baseUrl = rtrim((string)($channel['base_url'] ?? 'https://api.3023data.com'), '/');
        if (str_starts_with(strtolower($baseUrl), 'http://api.3023data.com')) {
            $baseUrl = 'https://' . substr($baseUrl, strlen('http://'));
        }
        $endpoint = '/' . ltrim((string)($mapping['endpoint_value'] ?? ''), '/');
        $queryParam = trim((string)($mapping['query_param'] ?? ''));
        if ($queryParam === '') {
            $queryParam = HsxPhoneQueryCategoryDict::infer3023QueryParam($endpoint);
        }

        $params = [$queryParam => trim($queryCode)];
        $headers = [];
        $logParams = $params;
        $authKey = trim((string)($channel['auth_key'] ?? 'key')) ?: 'key';
        $token = (string)($channel['token'] ?? '');
        if (($channel['auth_type'] ?? 'header') === 'query') {
            $params[$authKey] = $token;
            $logParams[$authKey] = '***';
        } else {
            $headers[] = $authKey . ': ' . $token;
        }

        return [
            'method' => strtoupper((string)($channel['method'] ?? 'GET')) ?: 'GET',
            'url' => $baseUrl . $endpoint,
            'params' => $params,
            'log_params' => $logParams,
            'headers' => $headers,
            'timeout' => (int)($channel['timeout'] ?? 300),
            'connect_timeout' => (int)($channel['connect_timeout'] ?? 10),
            'verify_ssl' => !array_key_exists('verify_ssl', $channel) || (bool)$channel['verify_ssl'],
        ];
    }
}
