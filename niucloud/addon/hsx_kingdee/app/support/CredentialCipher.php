<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\support;

final class CredentialCipher
{
    public static function seal(string $value, string $key, int $siteId): string
    {
        if ($key === '') throw new \RuntimeException('缺少 app.auth_key，不能保存凭据');
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag, 'hsx_kingdee:' . $siteId);
        if ($cipher === false) throw new \RuntimeException('凭据加密失败');
        return base64_encode($iv . $tag . $cipher);
    }

    public static function open(string $value, string $key, int $siteId): string
    {
        $raw = base64_decode($value, true);
        if ($key === '' || $raw === false || strlen($raw) < 28) throw new \RuntimeException('凭据损坏');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'hsx_kingdee:' . $siteId);
        if ($plain === false) throw new \RuntimeException('凭据解密失败或站点不匹配');
        return $plain;
    }
}
