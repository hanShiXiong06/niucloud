<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

final class EndpointPolicy
{
    public string $url;
    public string $host;

    public function __construct(string $endpoint, array $allowedHosts)
    {
        $parts = parse_url($endpoint);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (int)($parts['port'] ?? 443) !== 443
            || preg_match('/[\x00-\x20\\\\]/', $endpoint)) {
            throw new ApiFailure('endpoint_invalid', '服务地址必须是 HTTPS、443 端口，且不能含账号、参数或片段');
        }
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        if (!preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[a-z0-9.-]*[a-z0-9])?$/D', $host)
            || filter_var($host, FILTER_VALIDATE_IP) || str_contains($host, '..')
            || !preg_match('#^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]*$#D', $path)) {
            throw new ApiFailure('endpoint_invalid', '请使用正式服务域名和应用路径，不支持 IP 地址或特殊路径');
        }
        $allow = array_map(static fn($item): string => strtolower(trim((string)$item)), $allowedHosts);
        if (!in_array($host, $allow, true)) {
            throw new ApiFailure('host_not_allowed', '服务域名尚未加入服务器金蝶连接白名单，请联系系统管理员');
        }
        $this->host = $host;
        $this->url = 'https://' . $host . rtrim($path, '/') . '/';
    }

    public static function publicAddress(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        if (str_contains($ip, ':')) {
            // Only global unicast; reject IPv4-mapped, NAT64, Teredo and 6to4 routes.
            $bytes = inet_pton($ip);
            if (!$bytes || (ord($bytes[0]) & 0xe0) !== 0x20) return false;
            if (substr($bytes, 0, 2) === "\x20\x02") return false;
            if (substr($bytes, 0, 3) === "\x20\x01\x00" || substr($bytes, 0, 3) === "\x20\x01\x0d") return false;
            return true;
        }
        $n = (int)sprintf('%u', ip2long($ip));
        foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['192.0.2.0', 24], ['198.18.0.0', 15],
            ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$network, $bits]) {
            $mask = (0xffffffff << (32 - $bits)) & 0xffffffff;
            if (($n & $mask) === (((int)sprintf('%u', ip2long($network))) & $mask)) return false;
        }
        return true;
    }
}
