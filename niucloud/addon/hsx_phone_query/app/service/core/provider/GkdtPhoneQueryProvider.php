<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\provider;

use addon\hsx_phone_query\app\service\core\provider\contract\PhoneQueryProviderInterface;

/**
 * 爱查助手适配器：appid + key + code + style + time + sign。
 */
class GkdtPhoneQueryProvider extends AbstractPhoneQueryProvider implements PhoneQueryProviderInterface
{
    public function supports(string $provider): bool
    {
        return in_array($provider, ['gkdt_query', 'service_id_query'], true);
    }

    public function query(array $channel, array $mapping, string $queryCode): array
    {
        $request = $this->buildRequest($channel, $mapping, $queryCode);
        $startedAt = microtime(true);
        $response = [];

        try {
            $response = $this->send($request);
            $thirdCode = (int)($response['code'] ?? 0);
            $success = $thirdCode === 200;
            $message = (string)($response['message'] ?? $response['msg'] ?? ($success ? '查询成功' : '查询失败'));
            if (!$success) {
                return $this->failureResult('gkdt_query', $request, $startedAt, $message, $response);
            }

            $data = $response['data'] ?? $response['result'] ?? [];
            return [
                'success' => true,
                'provider' => 'gkdt_query',
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
            return $this->failureResult('gkdt_query', $request, $startedAt, $e->getMessage(), $response);
        }
    }

    public function buildRequest(array $channel, array $mapping, string $queryCode, ?int $timestamp = null): array
    {
        $params = [
            'appid' => trim((string)($channel['appid'] ?? '')),
            'code' => trim($queryCode),
            (string)($channel['service_id_key'] ?? 'key') => trim((string)($mapping['endpoint_value'] ?? '')),
            'style' => (string)($channel['style'] ?? '11'),
            'time' => $timestamp ?? time(),
        ];
        $params['sign'] = self::generateSign($params, (string)($channel['secret'] ?? ''));

        $logParams = $params;
        $logParams['sign'] = '***';

        $baseUrl = (string)($channel['base_url'] ?? 'https://api-srv.gkdt.com/inquiry/async');
        // 服务 ID 由映射统一注入，避免用户粘贴带 ?key= 的文档地址后产生重复参数。
        $baseUrl = explode('?', $baseUrl, 2)[0];

        return [
            'method' => strtoupper((string)($channel['method'] ?? 'GET')) ?: 'GET',
            'url' => $baseUrl,
            'params' => $params,
            'log_params' => $logParams,
            'headers' => [],
            'timeout' => (int)($channel['timeout'] ?? 30),
            'connect_timeout' => (int)($channel['connect_timeout'] ?? 10),
            'verify_ssl' => !array_key_exists('verify_ssl', $channel) || (bool)$channel['verify_ssl'],
        ];
    }

    public static function generateSign(array $params, string $secret): string
    {
        $params = array_filter($params, static fn($value) => $value !== '' && $value !== null);
        ksort($params, SORT_STRING);
        return md5(http_build_query($params) . '&secret=' . $secret);
    }
}
