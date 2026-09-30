<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use GuzzleHttp\Client;

/** 只做签名与安全传输，不含商城或回收状态，不自动重试。 */
class Kuaidi100Client
{
    public const WAYBILL_URL = 'https://api.kuaidi100.com/label/order';
    public const DEVICE_URL = 'https://poll.kuaidi100.com/printapi/printtask.do';
    private $transport;
    public function __construct(?callable $transport = null) { $this->transport = $transport; }
    public function request(string $endpoint, string $method, array $param, array $credentials): array
    {
        $allowlist = [self::WAYBILL_URL => ['order', 'printOld', 'cancel'], self::DEVICE_URL => ['devstatus'],
            'https://poll.kuaidi100.com/order/borderapi.do' => ['bOrder', 'cancel', 'price', 'detail', 'modifyOrder'],
            'https://order.kuaidi100.com/order/corderapi.do' => ['cOrder', 'cancel', 'price', 'detail', 'modifyOrder']];
        if (!in_array($method, $allowlist[$endpoint] ?? [], true)) throw new ProviderException('不支持的物流接口', false);
        $key = trim((string)($credentials['key'] ?? $credentials['api_key'] ?? ''));
        $secret = trim((string)($credentials['secret'] ?? ''));
        if ($key === '' || $secret === '') throw new ProviderException('物流接口凭据未配置', false);
        $json = json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string)(int)floor(microtime(true) * 1000);
        $form = ['method' => $method, 'key' => $key, 't' => $timestamp, 'param' => $json,
            'sign' => strtoupper(md5($json . $timestamp . $key . $secret))];
        try {
            if ($this->transport) {
                $result = ($this->transport)($endpoint, $form);
                if (!is_array($result)) throw new ProviderException('服务商响应格式异常，结果待核实');
                return $result;
            }
            $response = (new Client())->post($endpoint, ['form_params' => $form, 'connect_timeout' => 5, 'timeout' => max(5, min(30, (int)($credentials['timeout'] ?? 25))),
                'verify' => true, 'http_errors' => false, 'allow_redirects' => false]);
            if ($response->getStatusCode() !== 200) throw new ProviderException('服务商网络响应异常，结果待核实，请勿重复取号');
            $body = $response->getBody()->read(2097153);
            if (strlen($body) > 2097152) throw new ProviderException('服务商响应过长，结果待核实');
            $result = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($result)) throw new ProviderException('服务商响应格式异常，结果待核实');
            return $result;
        } catch (ProviderException $e) { throw $e; }
        catch (\Throwable $e) {
            // 不能将含凭据、地址的请求异常直接记日志，也不能将超时当作确定未下单。
            throw new ProviderException('服务商未返回可确认结果，请到快递100后台核实原任务；不要重复下单或切换服务商');
        }
    }
}
