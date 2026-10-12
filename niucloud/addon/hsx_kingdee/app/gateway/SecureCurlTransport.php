<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

final class SecureCurlTransport implements Transport
{
    public const QUERY = 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.ExecuteBillQuery';

    public function __construct(private EndpointPolicy $endpoint, private array $services = [self::QUERY]) {}

    public function post(string $service, array $payload, array $headers): array
    {
        $prefix = 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.';
        if (!in_array($service, $this->services, true) || !in_array($service, [self::QUERY,
            $prefix . 'Save', $prefix . 'Submit', $prefix . 'Audit', $prefix . 'View'], true)) {
            throw new ApiFailure('write_disabled', '当前连接不允许此操作');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            if (!preg_match('/^[a-zA-Z0-9-]+$/D', $name) || !is_scalar($value) || preg_match('/[\r\n]/', (string)$value)) {
                throw new ApiFailure('request_invalid', '请求头格式不合法');
            }
            $lines[] = $name . ': ' . $value;
        }
        try { $encoded = json_encode($payload, JSON_THROW_ON_ERROR); }
        catch (\JsonException $error) { throw new ApiFailure('request_invalid', '请求参数无法编码，未发送查询'); }
        if (!function_exists('curl_init')) throw new ApiFailure('curl_missing', '服务器缺少 PHP cURL 扩展');
        $records = @dns_get_record($this->endpoint->host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach ($records ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if ($ip === '') continue;
            if (!EndpointPolicy::publicAddress($ip)) throw new ApiFailure('address_blocked', '服务域名解析到非公网地址，连接已阻止；内网账套需另行确认接入方案');
            $ips[] = $ip;
        }
        if (!$ips) throw new ApiFailure('dns_failed', '无法解析金蝶服务域名，请检查地址或服务器网络');
        $ip = $ips[0];
        $curl = curl_init($this->endpoint->url . $service . '.common.kdsvc');
        if ($curl === false) throw new ApiFailure('transport_unavailable', '无法初始化金蝶连接，未发送查询');
        $body = '';
        $tooLarge = false;
        try {
            $configured = curl_setopt_array($curl, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $encoded,
                CURLOPT_HTTPHEADER => $lines, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_NETRC => CURL_NETRC_IGNORED,
                CURLOPT_RESOLVE => [$this->endpoint->host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
                    if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) { $tooLarge = true; return 0; }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (!$configured) throw new ApiFailure('transport_config', '无法应用安全连接参数，已阻止金蝶请求');
            $ok = curl_exec($curl);
            if ($tooLarge) throw new ApiFailure('response_large', '金蝶响应超过安全大小限制，未处理响应内容');
            if ($ok === false && curl_errno($curl) === CURLE_OPERATION_TIMEDOUT) throw new ApiFailure('transport_timeout', '金蝶请求超时，结果尚未确认；写单必须先查单，不可直接重发');
            if ($ok === false && curl_errno($curl) === CURLE_SSL_CACERT) throw new ApiFailure('certificate_invalid', '金蝶服务证书校验失败，连接已阻止；请检查证书，不要关闭校验');
            if ($ok === false) throw new ApiFailure('transport_failed', '金蝶请求未完成；写单结果必须先查单核对');
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            return self::decodeResponse($status, $body, $service !== self::QUERY);
        } finally {
            curl_close($curl);
        }
    }

    public static function decodeResponse(int $status, string $body, bool $objectResponse = false): array
    {
        if (strlen($body) > 2 * 1024 * 1024) throw new ApiFailure('response_large', '金蝶响应超过安全大小限制，未处理响应内容');
        if (in_array($status, [401, 403], true)) throw new ApiFailure('authorization_denied', '金蝶拒绝访问，请检查应用授权、用户权限和 IP 白名单');
        if ($status === 429) throw new ApiFailure('rate_limited', '金蝶接口请求频率受限；写单结果须查单确认');
        if ($status >= 500 && $status <= 599) throw new ApiFailure('remote_unavailable', '金蝶服务暂时不可用；写单结果须查单确认');
        if ($status >= 300 && $status <= 399) throw new ApiFailure('redirect_blocked', '金蝶地址返回跳转，已阻止自动跟随；请核对实际接口地址');
        if ($status !== 200) throw new ApiFailure('http_failed', '金蝶未返回有效查询响应，请检查服务地址和接口网关');
        try { $result = json_decode($body, false, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new ApiFailure('response_invalid', '金蝶响应不是有效 JSON；请确认地址是接口地址而非登录页面'); }
        if ($objectResponse) {
            if (!$result instanceof \stdClass) throw new ApiFailure('response_invalid', '金蝶操作响应格式异常，结果须查单确认');
            return json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        }
        if (!is_array($result)) throw new ApiFailure('response_invalid', '金蝶查询响应结构异常或查询被拒绝，请检查应用授权及表单权限');
        return $result;
    }
}
