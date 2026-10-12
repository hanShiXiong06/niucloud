<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\support;

final class Secret
{
    public static function seal(string $plain, string $key, int $site, string $purpose): string
    {
        if ($key === '' || $site <= 0) throw new Problem('ENCRYPTION_CONFIG', '缺少凭据加密配置，不能保存密钥');
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA,
            $iv, $tag, 'hsx_kingdee_jdy:' . $site . ':' . $purpose);
        if ($cipher === false) throw new Problem('ENCRYPTION_FAILED', '凭据加密失败');
        return base64_encode($iv . $tag . $cipher);
    }

    public static function open(string $cipher, string $key, int $site, string $purpose): string
    {
        $raw = base64_decode($cipher, true);
        if ($key === '' || $raw === false || strlen($raw) < 28) throw new Problem('CREDENTIAL_INVALID', '凭据损坏，请重新保存授权');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16), 'hsx_kingdee_jdy:' . $site . ':' . $purpose);
        if ($plain === false) throw new Problem('CREDENTIAL_INVALID', '凭据解密失败或站点不匹配，请重新保存授权');
        return $plain;
    }
}
