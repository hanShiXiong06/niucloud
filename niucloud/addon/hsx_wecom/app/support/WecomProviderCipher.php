<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\support;

use core\exception\CommonException;

/**
 * 服务商长期凭据加密。
 *
 * SuiteSecret、EncodingAESKey 与永久授权码不能以明文落库。密钥由当前
 * SaaS 部署的 app.auth_key 派生，因此两套部署的数据即使被复制也不能互解。
 */
final class WecomProviderCipher
{
    public static function encrypt(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') return '';

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new CommonException('企业微信服务商凭据加密失败');

        $payload = json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) throw new CommonException('企业微信服务商凭据编码失败');
        return base64_encode($payload);
    }

    public static function decrypt(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') return '';

        $decodedPayload = base64_decode($payload, true);
        $decoded = $decodedPayload === false ? null : json_decode($decodedPayload, true);
        if (!is_array($decoded)) throw new CommonException('企业微信服务商凭据已损坏，请重新保存配置');

        $iv = base64_decode((string)($decoded['iv'] ?? ''), true);
        $tag = base64_decode((string)($decoded['tag'] ?? ''), true);
        $data = base64_decode((string)($decoded['data'] ?? ''), true);
        if ($iv === false || $tag === false || $data === false) {
            throw new CommonException('企业微信服务商凭据已损坏，请重新保存配置');
        }

        $plain = openssl_decrypt($data, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) throw new CommonException('企业微信服务商凭据无法解密，请检查部署密钥');
        return $plain;
    }

    private static function key(): string
    {
        $authKey = trim((string)env('app.auth_key', ''));
        if ($authKey === '') {
            throw new CommonException('系统未配置 app.auth_key，禁止保存企业微信长期凭据');
        }
        // 保持既有密文的派生方式，仅移除公开固定回退密钥。
        return hash('sha256', $authKey, true);
    }
}
