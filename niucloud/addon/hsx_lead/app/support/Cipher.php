<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;
use core\exception\CommonException;

final class Cipher
{
    private static function key(): string
    {
        $key = (string)env('app.auth_key', '');
        if ($key === '' || !function_exists('openssl_encrypt')) throw new CommonException('请平台管理员先配置 app.auth_key，才能安全保存ERP凭据');
        return hash('sha256', 'hsx_lead.credentials.v1|'.$key, true);
    }
    public static function encrypt(#[\SensitiveParameter] array $data, int $site, int $connection): string
    {
        $iv = random_bytes(12); $tag = '';
        $value = openssl_encrypt(Data::json($data), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag,
            'hsx_lead|'.$site.'|'.$connection);
        if ($value === false) throw new CommonException('ERP凭据加密失败，未保存');
        return base64_encode($iv.$tag.$value);
    }
    public static function decrypt(#[\SensitiveParameter] string $value, int $site, int $connection): array
    {
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 29) throw new CommonException('ERP凭据不可读取，请重新保存账号配置');
        $plain = openssl_decrypt(substr($raw,28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw,0,12),
            substr($raw,12,16), 'hsx_lead|'.$site.'|'.$connection);
        if ($plain === false) throw new CommonException('ERP凭据无法解密，请核对原部署密钥；不要随意修改 app.auth_key');
        return Data::decode($plain);
    }
}
