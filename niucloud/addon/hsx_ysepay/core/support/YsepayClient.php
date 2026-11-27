<?php
declare(strict_types=1);

namespace addon\hsx_ysepay\core\support;

use core\exception\PayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Log;

class YsepayClient
{
    private array $config;
    private YsepayCrypto $crypto;
    private Client $http;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->assertConfig();
        $this->crypto = new YsepayCrypto(
            (string)$config['merchant_private_cert'],
            (string)$config['merchant_private_cert_password'],
            (string)$config['ysepay_public_cert']
        );
        $this->http = new Client([
            'timeout' => max(3, (int)($config['timeout'] ?? 15)),
            'connect_timeout' => max(3, min(10, (int)($config['timeout'] ?? 15))),
            'http_errors' => false,
        ]);
    }

    public function request(
        string $path,
        string $method,
        string $version,
        array $businessData,
        string $baseUrl = ''
    ): array
    {
        $aesKey = random_bytes(16);
        $request = [
            'timeStamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'charset' => 'utf-8',
            'check' => $this->crypto->encryptKey($aesKey),
            'bizContent' => $this->crypto->encryptBusiness($businessData, $aesKey),
            'reqId' => $this->requestId(),
            'certId' => (string)$this->config['cert_id'],
            'version' => $version,
        ];
        $request['sign'] = $this->crypto->sign($request);

        try {
            $response = $this->http->post($this->resolveGatewayBaseUrl($baseUrl) . $path, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'body' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (GuzzleException $e) {
            $this->logFailure($method, 'network', $e->getMessage());
            throw new PayException('银盛支付网络请求失败：' . $e->getMessage());
        }

        $raw = trim((string)$response->getBody());
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $this->logFailure($method, 'http_' . $response->getStatusCode(), mb_substr($raw, 0, 200));
            throw new PayException('银盛支付网关 HTTP 状态异常：' . $response->getStatusCode());
        }

        $result = $this->decodeResponse($raw);
        if (!$this->crypto->verify($result, (string)($result['sign'] ?? ''))) {
            $this->logFailure($method, 'invalid_signature', (string)($result['msg'] ?? ''));
            throw new PayException('银盛支付响应验签失败');
        }
        $business = [];
        if (isset($result['businessData']) && is_array($result['businessData'])) {
            $business = $result['businessData'];
        } elseif (!empty($result['businessData'])) {
            $business = $this->crypto->decryptBusiness((string)$result['businessData'], $aesKey);
        }
        $result['_business'] = $business;

        if ((string)($result['code'] ?? '') !== '00000') {
            $message = (string)($result['msg'] ?? '网关处理失败');
            $this->logFailure($method, (string)($result['code'] ?? 'unknown'), $message);
            throw new PayException('银盛支付网关错误：' . $message);
        }
        return $result;
    }

    public function parseNotification(string $raw): array
    {
        $data = $this->decodeResponse(trim($raw));
        if (!$this->crypto->verify($data, (string)($data['sign'] ?? ''))) {
            throw new PayException('银盛支付通知验签失败');
        }
        $business = $data['bizContent'] ?? $data['businessData'] ?? [];
        if (is_string($business)) {
            $business = json_decode($business, true);
        }
        if (!is_array($business)) {
            throw new PayException('银盛支付通知业务数据格式不正确');
        }
        return ['common' => $data, 'business' => $business];
    }

    public function businessSucceeded(array $response): bool
    {
        return in_array((string)($response['subCode'] ?? ''), ['0', '0000'], true);
    }

    public function businessMessage(array $response): string
    {
        return trim((string)($response['subMsg'] ?? $response['msg'] ?? '银盛支付业务处理失败'));
    }

    private function decodeResponse(string $raw): array
    {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
        $decoded = base64_decode($raw, true);
        if ($decoded !== false) {
            $data = json_decode($decoded, true);
        }
        if (!is_array($data)) {
            throw new PayException('银盛支付返回内容不是有效 JSON');
        }
        return $data;
    }

    private function resolveGatewayBaseUrl(string $override = ''): string
    {
        $custom = rtrim(trim($override !== '' ? $override : (string)($this->config['gateway_base_url'] ?? '')), '/');
        if ($custom !== '') {
            if (stripos($custom, 'https://') !== 0) {
                throw new PayException('银盛支付自定义网关必须使用 HTTPS');
            }
            return $custom;
        }
        return (string)($this->config['environment'] ?? 'sandbox') === 'production'
            ? 'https://ysgate.ysepay.com'
            : 'https://appdev.ysepay.com';
    }

    private function requestId(): string
    {
        return bin2hex(random_bytes(10)) . date('ymdHis');
    }

    private function assertConfig(): void
    {
        foreach ([
            'cert_id' => '发起方商户号',
            'merc_id' => '收款商户号',
            'merchant_private_cert' => '商户 PFX 证书',
            'merchant_private_cert_password' => 'PFX 证书密码',
            'ysepay_public_cert' => '银盛平台公钥证书',
        ] as $field => $label) {
            if (trim((string)($this->config[$field] ?? '')) === '') {
                throw new PayException('请先配置银盛支付' . $label);
            }
        }
    }

    private function logFailure(string $method, string $code, string $message): void
    {
        Log::write(sprintf(
            '[hsx_ysepay] site=%d method=%s code=%s message=%s',
            (int)($this->config['site_id'] ?? 0),
            $method,
            $code,
            str_replace(["\r", "\n"], ' ', $message)
        ), 'error');
    }
}
