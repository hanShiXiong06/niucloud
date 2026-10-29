<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;
final class Secret
{
    public static function seal(string $plain, string $key, int $site, string $connection): string
    {
        if ($key === '') throw new Problem('ENCRYPTION_REQUIRED', '缺少系统凭据加密配置');
        $iv = random_bytes(12); $tag = '';
        $data = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag, 'boyuan:' . $site . ':' . $connection);
        if ($data === false) throw new Problem('ENCRYPTION_FAILED', '凭据加密失败');
        return base64_encode($iv . $tag . $data);
    }
    public static function open(string $cipher, string $key, int $site, string $connection): string
    {
        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) < 28 || $key === '') throw new Problem('CREDENTIAL_INVALID', '请重新保存博远授权');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'boyuan:' . $site . ':' . $connection);
        if ($plain === false) throw new Problem('CREDENTIAL_INVALID', '授权与当前站点或连接不匹配');
        return $plain;
    }
}
