<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\gateway;

use addon\hsx_kingdee_jdy\app\support\Problem;

final class ReadOnlyTransport implements Transport
{
    public const TOKEN_PATH = '/jdyconnector/app_management/kingdee_auth_token';
    public const READ_PATHS = [
        '/jdyscm/purchase/list', '/jdyscm/delivery/list', '/jdyscm/purchaseReturn/list',
        '/jdyscm/saleReturn/list', '/jdyscm/customer/list',
    ];

    public static function assertAllowed(string $method, string $path): void
    {
        if (($method === 'GET' && $path === self::TOKEN_PATH)
            || ($method === 'POST' && in_array($path, self::READ_PATHS, true))) return;
        // GET /bill/check changes accounting state and is intentionally NOT a read operation.
        throw new Problem('WRITE_DISABLED', '真实账套验收前禁止写单、审核、反审核和删除');
    }

    public static function router(string $value): string
    {
        if (!preg_match('~^https?://[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.jdy\.com/?$~D', $value)) {
            throw new Problem('ROUTER_INVALID', '请填写授权返回的 IDC 域名，例如 https://vip1-gd.jdy.com，不是云星空地址');
        }
        return rtrim($value, '/');
    }

    public function send(string $method, string $path, array $query, array $headers, array $body): array
    {
        self::assertAllowed($method, $path);
        ksort($query, SORT_STRING);
        $url = 'https://api.kingdee.com' . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $lines = [];
        foreach ($headers as $name => $value) {
            if (!preg_match('/^[A-Za-z0-9-]+$/D', (string)$name) || preg_match('/[\r\n\x00]/', (string)$value)) {
                throw new Problem('HEADER_INVALID', '请求头配置不合法');
            }
            $lines[] = $name . ': ' . $value;
        }
        $curl = curl_init($url);
        $response = '';
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $lines,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $data) use (&$response): int {
                if (strlen($response) + strlen($data) > 4 * 1024 * 1024) return 0;
                $response .= $data;
                return strlen($data);
            },
        ]);
        if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body ?: (object)[], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($ok === false) throw new Problem('TRANSPORT_FAILED', '精斗云连接失败，请检查网络；未执行写单', true);
        if ($status === 502) throw new Problem('ROUTER_REFRESH', '精斗云返回502，请在开放平台重新获取授权并核对IDC域名');
        if ($status === 429 || $status >= 500) throw new Problem('REMOTE_BUSY', '精斗云暂时繁忙，请稍后重试', true);
        if ($status < 200 || $status >= 300) throw new Problem('HTTP_REJECTED', '精斗云拒绝请求，请检查应用授权与服务状态');
        try { $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING); }
        catch (\JsonException $e) { throw new Problem('RESPONSE_INVALID', '精斗云响应格式异常，不能判定业务成功'); }
        if (!is_array($data)) throw new Problem('RESPONSE_INVALID', '精斗云响应格式异常，不能判定业务成功');
        return $data;
    }
}
