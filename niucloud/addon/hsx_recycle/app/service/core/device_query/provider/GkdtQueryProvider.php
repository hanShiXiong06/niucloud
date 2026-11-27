<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query\provider;

/**
 * 爱查助手设备查询适配器。
 *
 * 爱查以服务 ID 作为 key，并要求 appid/code/key/style/time 参与 MD5 签名；
 * 该协议与使用 Header API Key 的 3023 完全不同，因此不能复用通用 Token 拼接逻辑。
 */
class GkdtQueryProvider extends AbstractDeviceQueryProvider
{
    public function query(array $channel, array $mapping, string $queryCode, string $queryType): array
    {
        $baseUrl = trim((string)($channel['base_url'] ?? ''));
        $serviceId = trim((string)($mapping['endpoint_value'] ?? ''));
        $appid = trim((string)($channel['appid'] ?? ''));
        $secret = trim((string)($channel['secret'] ?? ''));

        if ($baseUrl === '' || $serviceId === '') {
            throw new \Exception('爱查助手请求地址或服务 ID 未配置');
        }
        if ($appid === '' || $secret === '') {
            throw new \Exception('爱查助手 AppID 或 Secret 未配置');
        }

        $params = $this->buildSignedParams($channel, $serviceId, $queryCode, time());
        $url = $this->appendQuery($baseUrl, $params);
        $response = $this->request(
            (string)($channel['method'] ?? 'GET'),
            $url,
            [],
            $this->buildHeaders($channel),
            $channel
        );

        return $this->normalizeGkdtResponse($response);
    }

    public function getProviderName(): string
    {
        return 'gkdt_query';
    }

    /**
     * @return array<string, string|int>
     */
    protected function buildSignedParams(array $channel, string $serviceId, string $queryCode, int $timestamp): array
    {
        $params = [
            'appid' => trim((string)($channel['appid'] ?? '')),
            'code' => trim($queryCode),
            (string)($channel['service_id_key'] ?? 'key') => trim($serviceId),
            'style' => (string)($channel['style'] ?? '11'),
            'time' => $timestamp,
        ];

        $params['sign'] = $this->generateSignature($params, (string)($channel['secret'] ?? ''));

        return $params;
    }

    protected function generateSignature(array $params, string $secret): string
    {
        $params = array_filter($params, static fn($value) => $value !== '' && $value !== null);
        ksort($params, SORT_STRING);

        return md5(http_build_query($params) . '&secret=' . trim($secret));
    }

    protected function normalizeGkdtResponse(array $response): array
    {
        $code = (int)($response['code'] ?? -1);
        $success = in_array($code, [0, 200], true);
        $data = $response['data'] ?? $response['result'] ?? [];
        $message = (string)($response['message'] ?? $response['msg'] ?? ($success ? '查询成功' : '查询失败'));

        // 爱查 code=400100 的“签名错误”对店员没有可操作性，在适配层转换为可处理的配置提示。
        if (!$success && ($code === 400100 || str_contains($message, '签名错误'))) {
            $message = '爱查签名校验失败，请确认 AppID 与 Secret 来自同一个爱查账号，并重新保存服务商设置';
        } elseif (!$success && $code === 500001) {
            $message = '爱查未能识别该序列号或 IMEI（错误码 500001），请核对号码后重试，或稍后再查';
        }

        if (!is_array($data)) {
            $data = $data === null || $data === '' ? [] : ['value' => $data];
        }

        return [
            'success' => $success,
            'third_code' => $code,
            'message' => $message,
            'data' => $data,
            'cost' => (float)($response['cost'] ?? 0),
            'balance' => (float)($response['balance'] ?? 0),
            'raw_response' => $response,
        ];
    }
}
