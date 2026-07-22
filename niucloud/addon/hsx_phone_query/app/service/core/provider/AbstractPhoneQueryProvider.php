<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\provider;

use core\exception\CommonException;

abstract class AbstractPhoneQueryProvider
{
    protected function send(array $request): array
    {
        $method = strtoupper((string)($request['method'] ?? 'GET'));
        $url = (string)($request['url'] ?? '');
        $params = is_array($request['params'] ?? null) ? $request['params'] : [];
        $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];
        $timeout = max(1, (int)($request['timeout'] ?? 30));
        $connectTimeout = max(1, (int)($request['connect_timeout'] ?? min(10, $timeout)));
        $verifySsl = !array_key_exists('verify_ssl', $request) || (bool)$request['verify_ssl'];

        if ($url === '') {
            throw new CommonException('第三方查询地址未配置');
        }

        $ch = curl_init();
        if ($method === 'GET') {
            if (!empty($params)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $message = curl_error($ch) ?: '网络请求失败';
            curl_close($ch);
            throw new CommonException('第三方查询请求失败：' . $message);
        }

        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpStatus >= 400) {
            throw new CommonException('第三方查询接口 HTTP ' . $httpStatus);
        }

        $response = json_decode((string)$raw, true);
        if (!is_array($response)) {
            throw new CommonException('第三方查询接口未返回有效 JSON');
        }

        return $response;
    }

    protected function failureResult(
        string $provider,
        array $request,
        float $startedAt,
        string $message,
        array $response = []
    ): array {
        return [
            'success' => false,
            'provider' => $provider,
            'third_code' => (string)($response['code'] ?? $response['status'] ?? ''),
            'message' => $message,
            'data' => [],
            'source' => $response['source'] ?? null,
            'cost' => (float)($response['cost'] ?? 0),
            'balance' => $response['balance'] ?? null,
            'request_method' => strtoupper((string)($request['method'] ?? 'GET')),
            'request_url' => (string)($request['url'] ?? ''),
            'request_params' => (array)($request['log_params'] ?? $request['params'] ?? []),
            'response' => $response,
            'duration_ms' => $this->durationMs($startedAt),
        ];
    }

    protected function durationMs(float $startedAt): int
    {
        return max(0, (int)round((microtime(true) - $startedAt) * 1000));
    }
}
