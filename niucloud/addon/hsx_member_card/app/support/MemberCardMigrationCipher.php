<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

use core\exception\CommonException;

/**
 * 同步任务凭据加密。
 *
 * 旧平台密码只参与登录且从不落库；队列任务仅保存由旧平台签发的加密 Token。
 */
final class MemberCardMigrationCipher
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') throw new CommonException('旧平台未返回登录凭据');
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new CommonException('同步凭据加密失败');
        return base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ], JSON_UNESCAPED_SLASHES));
    }

    public static function decrypt(string $payload): string
    {
        $decoded = json_decode((string)base64_decode($payload, true), true);
        if (!is_array($decoded)) throw new CommonException('同步凭据已损坏，请重新输入旧平台账号密码');
        $iv = base64_decode((string)($decoded['iv'] ?? ''), true);
        $tag = base64_decode((string)($decoded['tag'] ?? ''), true);
        $data = base64_decode((string)($decoded['data'] ?? ''), true);
        if ($iv === false || $tag === false || $data === false) {
            throw new CommonException('同步凭据已损坏，请重新输入旧平台账号密码');
        }
        $plain = openssl_decrypt($data, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false || $plain === '') throw new CommonException('同步凭据已失效，请重新输入旧平台账号密码');
        return $plain;
    }

    private static function key(): string
    {
        return hash('sha256', (string)env('app.auth_key', 'niucloud-member-card-migration'), true);
    }
}
