<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query\provider;

use addon\hsx_recycle\app\service\core\device_query\contract\DeviceQueryProviderInterface;

abstract class AbstractDeviceQueryProvider implements DeviceQueryProviderInterface
{
    protected function request(string $method, string $url, array $data, array $headers, array $channel): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, max(1, (int)($channel['timeout'] ?? 300)));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(1, (int)($channel['connect_timeout'] ?? 10)));
        $verifySsl = !array_key_exists('verify_ssl', $channel) || (bool)$channel['verify_ssl'];
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 28800);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (strtoupper($method) !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \Exception('设备查询请求失败: ' . $error);
        }

        $decoded = json_decode((string)$body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception($this->buildInvalidJsonMessage((string)$body, $httpCode, $url));
        }
        if (!is_array($decoded)) {
            throw new \Exception('设备查询响应格式错误');
        }

        $decoded['_http_code'] = $httpCode;

        if ($httpCode >= 400 && !isset($decoded['code'])) {
            $decoded['code'] = $httpCode;
            $decoded['message'] = (string)($decoded['message'] ?? $decoded['msg'] ?? ('第三方接口 HTTP ' . $httpCode));
        }

        return $decoded;
    }

    private function buildInvalidJsonMessage(string $body, int $httpCode, string $url): string
    {
        $preview = trim(strip_tags($body));
        $preview = preg_replace('/\s+/', ' ', $preview ?? '');
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 120) . '...';
        }

        $message = '第三方接口没有返回 JSON，通常是接口地址、接口路径、鉴权方式或密钥配置不正确';
        if ($httpCode > 0) {
            $message .= '，HTTP状态码：' . $httpCode;
        }
        if ($preview !== '') {
            $message .= '，返回内容：' . $preview;
        }

        $message .= '。请在设备查询配置中检查渠道地址、接口映射和服务商凭证';

        return $message;
    }

    protected function buildHeaders(array $channel): array
    {
        $headers = [];
        if (($channel['auth_type'] ?? 'header') === 'header') {
            $token = (string)($channel['token'] ?? $channel['api_key'] ?? '');
            if ($token !== '') {
                $headers[] = (string)($channel['auth_key'] ?? 'key') . ': ' . $token;
            }
        }

        return $headers;
    }

    protected function appendQuery(string $url, array $params): string
    {
        $params = array_filter($params, static fn($value) => $value !== '' && $value !== null);
        if (empty($params)) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }

    protected function authQueryParams(array $channel): array
    {
        if (($channel['auth_type'] ?? 'header') !== 'query') {
            return [];
        }

        $token = (string)($channel['token'] ?? $channel['api_key'] ?? '');
        if ($token === '') {
            return [];
        }

        return [(string)($channel['auth_key'] ?? 'key') => $token];
    }

    protected function normalizeProviderResponse(array $response): array
    {
        $code = (int)($response['code'] ?? -1);
        $success = $code === 0;

        return [
            'success' => $success,
            'third_code' => $code,
            'message' => (string)($response['message'] ?? $response['msg'] ?? ($success ? '查询成功' : '查询失败')),
            'data' => is_array($response['data'] ?? null) ? $response['data'] : [],
            'cost' => (float)($response['cost'] ?? 0),
            'balance' => (float)($response['balance'] ?? 0),
            'raw_response' => $response,
        ];
    }

    /**
     * 仅保留可审计但不泄露凭证的请求地址。
     */
    protected function sanitizeRequestUrl(string $url, array $sensitiveKeys = ['key', 'token', 'sign', 'appid']): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['query'])) {
            return $url;
        }

        parse_str((string)$parts['query'], $query);
        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $query)) {
                $query[$key] = '***';
            }
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string)($parts['path'] ?? '');

        return $scheme . $host . $port . $path . ($query ? '?' . http_build_query($query) : '');
    }
}
