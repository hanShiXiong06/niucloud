<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;

final class GuzzleJsonTransport implements TransportContract
{
    private const ENDPOINTS = [
        'https://lite.wanjisaas.com/lite-api/auth/login',
        'https://lite.wanjisaas.com/lite-api/erp/stock/selectMachineDetailV2',
        'https://www.dianddnet.cn/sjk-erp-service/api/sku/homePage',
    ];
    private const MAX_BYTES = 20 * 1024 * 1024;
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        // A dedicated default stack has no retry middleware or application HTTP hooks.
        $this->client = $client ?? new Client(['handler' => HandlerStack::create()]);
    }

    public function post(string $endpoint, #[\SensitiveParameter] array $headers, #[\SensitiveParameter] array $body): array
    {
        if (!in_array($endpoint, self::ENDPOINTS, true)) {
            throw new ProviderException('forbidden_endpoint', '未允许的 ERP 接口地址');
        }
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value) || preg_match('/[\r\n]/', $name . $value)) {
                throw new ProviderException('invalid_credentials', 'ERP 请求头不合法');
            }
        }
        try {
            $response = $this->client->request('POST', $endpoint, [
                'headers' => $headers + ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'json' => $body,
                'allow_redirects' => false,
                'http_errors' => false,
                'verify' => true,
                'cookies' => false,
                'debug' => false,
                'connect_timeout' => 10,
                'timeout' => 30,
                'read_timeout' => 30,
                'stream' => true,
            ]);
            $status = $response->getStatusCode();
            if ($status === 401 || $status === 403) {
                $response->getBody()->close();
                throw new ProviderException('auth_required', 'ERP 授权已失效，请重新授权');
            }
            if ($status !== 200) {
                $response->getBody()->close();
                throw new ProviderException('http_error', 'ERP 接口 HTTP ' . $status . '，未更新库存');
            }
            $stream = $response->getBody();
            $json = '';
            try {
                while (!$stream->eof()) {
                    $json .= $stream->read(8192);
                    if (strlen($json) > self::MAX_BYTES) {
                        throw new ProviderException('response_too_large', 'ERP 单页响应超过安全大小');
                    }
                }
            } finally {
                $stream->close();
            }
            return self::decode($json);
        } catch (ProviderException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            // No previous exception: Guzzle exceptions can contain tokens, passwords and bodies.
            throw new ProviderException('http_error', 'ERP 只读请求失败，请检查网络或授权状态');
        }
    }

    public static function decode(#[\SensitiveParameter] string $json): array
    {
        try {
            // Validate the original JSON first; keep decimal lexemes as strings to avoid
            // binary-float rounding of display prices. Integer IDs use BIGINT_AS_STRING.
            json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            $exact = preg_replace_callback(
                '/"(?:\\\\.|[^"\\\\])*"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+\-]?[0-9]+)?/',
                static fn(array $match): string => strpbrk($match[0], '.eE') === false ? $match[0] : '"' . $match[0] . '"',
                $json
            );
            $decoded = json_decode($exact ?? '', true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\Throwable $exception) {
            throw new ProviderException('schema_changed', 'ERP 接口未返回有效 JSON');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new ProviderException('schema_changed', 'ERP 接口返回结构变化');
        }
        return $decoded;
    }
}
