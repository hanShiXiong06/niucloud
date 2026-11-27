<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;

final class GuzzleJsonTransport implements RequestTransportContract
{
    private const ENDPOINTS = [
        'https://lite.wanjisaas.com/lite-api/auth/login',
        'https://lite.wanjisaas.com/lite-api/erp/stock/selectMachineDetailV2',
        'https://www.dianddnet.cn/sjk-erp-service/api/sku/homePage',
        'https://www.dianddnet.cn/sjk-erp-service/api/sku/page',
        'https://www.dianddnet.cn/sjk-erp-service/api/member/login',
        'https://api.aiguanji.com/api/v1/index/index',
        'https://api.aiguanji.com/api/v1/storage/product/index',
        'https://api.aiguanji.com/api/v1/index/login',
        'https://api.yuanyoukeji.com/api/sms/send',
        'https://api.yuanyoukeji.com/api/user/mobile_register_login',
        'https://api.yuanyoukeji.com/api/product_model/productStockList',
    ];
    private const EXTRA_REQUESTS = [
        'POST form https://jx2.guanql.cn/admin/login/in',
        'GET query https://jx2.guanql.cn/admin',
        'GET query https://jx2.guanql.cn/admin/serial-follow/get-list',
        'GET query https://jx2.guanql.cn/mall/goods/get-list',
        'GET query https://jx2.guanql.cn/mall/imei-goods/get-list',
        'GET query https://api.aiguanji.com/api/v1/common/captcha/index',
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
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!in_array($endpoint, self::ENDPOINTS, true)) {
            throw new ProviderException('forbidden_endpoint', '未允许的 ERP 接口地址');
        }
        $response = $this->request('POST', $endpoint, $headers, $body, 'json');
        // Legacy JSON callers still require HTTP 200. Only the optional raw auth
        // adapter may inspect bounded validation errors returned with non-200 status.
        if ($response['status'] !== 200) {
            $reason = in_array($response['status'], [401, 403], true) ? 'auth_required' : 'http_error';
            throw new ProviderException($reason, 'ERP接口 HTTP '.$response['status'].'，本次授权未完成');
        }
        return self::decode($response['body']);
    }

    public function request(string $method, string $endpoint, #[\SensitiveParameter] array $headers,
        #[\SensitiveParameter] array $data, string $encoding): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!(($method === 'POST' && $encoding === 'json' && in_array($endpoint, self::ENDPOINTS, true))
            || in_array($method.' '.$encoding.' '.$endpoint, self::EXTRA_REQUESTS, true))) {
            throw new ProviderException('forbidden_endpoint', '未允许的 ERP 请求方法或接口地址');
        }
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value) || preg_match('/[\r\n]/', $name.$value)) {
                throw new ProviderException('invalid_credentials', 'ERP 请求头不合法');
            }
        }
        try {
            $options = [
                'headers' => $headers + ['Accept' => 'application/json'] + ($encoding === 'json' ? ['Content-Type' => 'application/json'] : []),
                'allow_redirects' => false,
                'http_errors' => false,
                'verify' => true,
                'cookies' => false,
                'debug' => false,
                'connect_timeout' => 10,
                'timeout' => 30,
                'read_timeout' => 30,
                'stream' => true,
            ];
            $options[match ($encoding) {
                'json' => 'json', 'form' => 'form_params', 'query' => 'query'
            }] =
                $endpoint === 'https://api.aiguanji.com/api/v1/index/index' && $data === [] ? new \stdClass() : $data;
            $response = $this->client->request($method, $endpoint, $options);
            $status = $response->getStatusCode();
            // This provider uses non-200 HTTP statuses for ordinary login validation
            // failures. Preserve those only for this fixed endpoint; redirects and all
            // other providers retain fail-closed semantics. The adapter must reject
            // non-200 "success" and expose only its own safe error classifications.
            $authValidation = $method === 'POST' && $encoding === 'json'
                && $endpoint === 'https://api.aiguanji.com/api/v1/index/login'
                && in_array($status, [400, 401, 403, 422, 429, 500], true);
            if (!$authValidation && ($status === 401 || $status === 403)) {
                $response->getBody()->close();
                throw new ProviderException('auth_required', 'ERP 授权已失效，请重新授权');
            }
            if (!$authValidation && $status !== 200) {
                $response->getBody()->close();
                throw new ProviderException('http_error', 'ERP 接口 HTTP '.$status.'，未更新库存');
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
            return ['status' => $status, 'headers' => $response->getHeaders(), 'body' => $json];
        } catch (ProviderException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            // No previous exception: Guzzle exceptions can contain tokens, passwords and bodies.
            throw new ProviderException('http_error', 'ERP 只读请求失败，请检查网络或授权状态');
        }
    }

    public static function decode(#[\SensitiveParameter] string $json): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        try {
            // Validate the original JSON first; keep decimal lexemes as strings to avoid
            // binary-float rounding of display prices. Integer IDs use BIGINT_AS_STRING.
            json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            $exact = preg_replace_callback(
                '/"(?:\\\\.|[^"\\\\])*"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+\-]?[0-9]+)?/',
                static fn (array $match): string => strpbrk($match[0], '.eE') === false ? $match[0] : '"'.$match[0].'"',
                $json
            );
            $decoded = json_decode($exact ?? '', true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\Throwable $exception) {
            throw new ProviderException('schema_changed', 'ERP 接口未返回有效 JSON');
        }
        if (!is_array($decoded) || \addon\hsx_lead\app\support\PhpRuntime::isList($decoded)) {
            throw new ProviderException('schema_changed', 'ERP 接口返回结构变化');
        }
        return $decoded;
    }
}
