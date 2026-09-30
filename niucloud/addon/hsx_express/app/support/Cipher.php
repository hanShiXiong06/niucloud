<?php
declare(strict_types=1);
namespace addon\hsx_express\app\support;

use core\exception\CommonException;

final class Cipher
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new CommonException('物流凭据加密失败');
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        if ($encoded === '') return '';
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) throw new CommonException('物流凭据已损坏，请联系管理员');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) throw new CommonException('物流凭据无法解密，请检查原部署 app.auth_key，勿更换密钥后重复下单');
        return $plain;
    }

    public static function available(): bool { return trim((string)env('app.auth_key', '')) !== '' && function_exists('openssl_encrypt'); }
    private static function key(): string
    {
        $value = trim((string)env('app.auth_key', ''));
        if ($value === '') throw new CommonException('请先配置部署 app.auth_key，用于安全保存物流凭据；请勿变更已有部署密钥');
        return hash('sha256', 'hsx_express_v1|' . $value, true);
    }
}
