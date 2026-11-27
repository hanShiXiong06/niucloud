<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service\virtualpay;

use core\exception\CommonException;

/** Fixed-host, single-attempt xpay transport. An uncertain POST must not be replayed blindly. */
final class VirtualPayHttpClient
{
    private ?\Closure $transport;

    /** PHP-only test transport, never taken from a request or persisted configuration. */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport === null ? null : \Closure::fromCallable($transport);
    }

    /** @return array Official response fields; errcode=0 means accepted, not delivered/refunded. */
    public function request(string $path, array $payload, string $accessToken, string $appKey): array
    {
        if ($accessToken === '' || strlen($accessToken) > 4096 || preg_match('/[\x00-\x20\x7f]/', $accessToken))throw new CommonException('虚拟支付访问凭据未就绪');
        $signed = VirtualPayProtocol::signedBody($path, $payload, $appKey);
        $url = 'https://api.weixin.qq.com'.$path.'?'.http_build_query(['access_token' => $accessToken, 'pay_sig' => $signed['pay_sig']], '', '&', PHP_QUERY_RFC3986);
        $options = ['headers' => ['Content-Type' => 'application/json'], 'timeout' => 8.0, 'connect_timeout' => 3.0, 'http_errors' => false, 'allow_redirects' => false, 'retry' => false];
        try {
            if ($this->transport)$response = ($this->transport)($url, $signed['body'], $options);
            else {
                $client = new \GuzzleHttp\Client(['timeout' => 8.0, 'connect_timeout' => 3.0, 'allow_redirects' => false, 'http_errors' => false]);
                $result = $client->request('POST', $url, ['headers' => $options['headers'], 'body' => $signed['body']]);
                $response = ['status' => $result->getStatusCode(), 'body' => (string) $result->getBody()];
            }
        } catch (\Throwable $error) {
            throw new CommonException('虚拟支付渠道请求结果未确认，请核对原单，勿重复提交');
        }
        if (!is_array($response) || ($response['status'] ?? null) !== 200 || !is_string($response['body'] ?? null) || strlen($response['body']) > 1048576)
            throw new CommonException('虚拟支付渠道响应异常，原请求结果待核对');
        try {
            $body = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\Throwable $error) {
            throw new CommonException('虚拟支付渠道响应无法核实');
        }
        if (!is_array($body) || !is_int($body['errcode'] ?? null))throw new CommonException('虚拟支付渠道回执格式异常');
        if ($body['errcode'] !== 0) {
            // Do not include errmsg/response/URL: they may echo tokens, signatures or business details.
            $code = $body['errcode'];
            if (abs($code) > 9999999999)throw new CommonException('虚拟支付渠道拒绝请求，请核对账号及原单');
            throw new CommonException('虚拟支付渠道拒绝请求，错误码：'.$code, $code);
        }
        return $body;
    }

    public function query(array $settings, array $order): array
    {
        $body = VirtualPayProtocol::queryBody($order['openid'] ?? '', VirtualPayProtocol::environment($settings['env'] ?? null), $order['out_trade_no'] ?? '');
        return VirtualPayProtocol::normalizeQuery($this->request(VirtualPayProtocol::QUERY_PATH, $body, $settings['access_token'] ?? '', $settings['app_key'] ?? ''));
    }

    public function refund(array $settings, array $payload): array
    {
        return $this->request(VirtualPayProtocol::REFUND_PATH, $payload, $settings['access_token'] ?? '', $settings['app_key'] ?? '');
    }
}
