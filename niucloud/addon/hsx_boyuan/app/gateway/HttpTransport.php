<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;
use addon\hsx_boyuan\app\support\Contract;
use addon\hsx_boyuan\app\support\Problem;
final class HttpTransport implements Transport
{
    public function __construct(private array $config, private string $token) {}
    public static function endpoint(string $url): string
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])
            || ($p['port'] ?? 443) !== 443 || preg_match('/[\x00-\x20\\\\]/', $url) || filter_var($p['host'], FILTER_VALIDATE_IP)
            || !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/iD', $p['host']) || str_contains($p['host'], '..')
            || !preg_match('#^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]*$#D', $p['path'] ?? '/')) throw new Problem('ENDPOINT_INVALID', '服务地址需为HTTPS域名和普通路径，不得包含账号、查询参数或非443端口');
        return rtrim($url, '/');
    }
    public static function publicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        if (str_contains($ip, ':')) {
            $bytes = inet_pton($ip);
            return $bytes && (ord($bytes[0]) & 0xe0) === 0x20 && !in_array(substr($bytes, 0, 2), ["\x20\x02"], true)
                && !in_array(substr($bytes, 0, 3), ["\x20\x01\x00", "\x20\x01\x0d"], true);
        }
        $n = (int)sprintf('%u', ip2long($ip));
        foreach ([['100.64.0.0',10], ['192.0.0.0',24], ['192.0.2.0',24], ['198.18.0.0',15], ['198.51.100.0',24], ['203.0.113.0',24], ['224.0.0.0',4], ['240.0.0.0',4]] as [$network,$bits]) {
            $mask = (0xffffffff << (32-$bits)) & 0xffffffff;
            if (($n & $mask) === ((int)sprintf('%u',ip2long($network)) & $mask)) return false;
        }
        return true;
    }
    public function send(string $method, string $path, array $body = []): array
    {
        $base = self::endpoint($this->config['endpoint']);
        if (!preg_match('#^/[a-zA-Z0-9_/{}/.-]+$#D', $path) || str_contains($path, '..') || str_contains($path, '//')) throw new Problem('PATH_INVALID', '接口路径无效');
        if ($this->token === '' || preg_match('/[\r\n]/', $this->token . $this->config['account_id'])) throw new Problem('AUTH_INVALID', '请保存有效博远授权');
        $host = parse_url($base, PHP_URL_HOST); $ips = [];
        foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if (!self::publicIp($ip)) throw new Problem('ADDRESS_DENIED', '博远域名解析到非公网地址，已阻止请求');
            $ips[] = $ip;
        }
        if (!$ips) throw new Problem('DNS_UNAVAILABLE', '暂时无法解析博远服务域名');
        $ip = $ips[0]; $url = $base . $path;
        if ($method === 'GET' && $body) $url .= '?' . http_build_query($body);
        $headers = ['Content-Type: application/json; charset=utf-8', 'Authorization: Bearer ' . $this->token,
            'X-Account-Id: ' . $this->config['account_id'], 'X-Request-Id: ' . bin2hex(random_bytes(16))];
        if ($method === 'POST' && isset($body['operation_id'])) $headers[] = 'Idempotency-Key: ' . Contract::id($body['operation_id']);
        $curl = curl_init($url); $raw = '';
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
                if (strlen($raw) + strlen($chunk) > 2097152) return 0;
                $raw .= $chunk; return strlen($chunk);
            }]);
        if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, Contract::json($body));
        $ok = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
        if ($ok === false || $status >= 500 || $status === 429) throw new Problem('REMOTE_UNCERTAIN', '请求未获得确定结果，将先查询原操作，不能重复建单', 'unknown');
        if ($status >= 300 && $status < 400) throw new Problem('REDIRECT_DENIED', '博远地址发生跳转，已阻止携带授权跳转', 'unknown');
        try { $reply = Contract::decode($raw); } catch (\Throwable $e) { throw new Problem('RESPONSE_INVALID', '博远未返回有效JSON，请核对原操作', 'unknown'); }
        $reply=Contract::redact($reply); $token=$this->token;
        array_walk_recursive($reply,static function (&$value) use($token): void { if (is_string($value)) $value=str_replace($token,'[已隐藏]',$value); });
        return $reply;
    }
}
