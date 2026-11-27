<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

/** Stable destination, not a credential fingerprint. A rotated secret or display name is not a new ledger. */
final class BridgeTarget
{
    public static function key(array $connection): string
    {
        $cfg = ConnectionFields::values($connection['config'] ?? []);
        $site = (int)($connection['site_id'] ?? 0);
        $adapter = trim((string)($connection['adapter_code'] ?? ''));
        $mode = (string)($cfg['mode'] ?? '');
        $account = trim((string)($cfg['account_code'] ?? $cfg['account_id'] ?? ''));
        $url = parse_url(trim((string)($cfg['api_url'] ?? $cfg['endpoint'] ?? '')));
        $scheme = strtolower($url['scheme'] ?? '');
        if ($site < 1 || $adapter === '' || $account === '' || !in_array($mode, ['test','production'], true) || !in_array($scheme, ['https','http'], true)
            || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || isset($url['query'])) {
            throw new \InvalidArgumentException('连接缺少明确的目标地址和账套，不能接管原单');
        }
        $port = (int)($url['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = rtrim($url['path'] ?? '', '/') . '/' . ltrim((string)($cfg['gateway_path'] ?? ''), '/');
        return hash('sha256', json_encode([$site,$adapter,$mode,$scheme,strtolower($url['host']),$port,rtrim($path, '/'),$account], JSON_THROW_ON_ERROR));
    }
}
