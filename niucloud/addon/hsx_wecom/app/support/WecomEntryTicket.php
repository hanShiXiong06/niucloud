<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\support;

use core\exception\CommonException;

/**
 * 企业微信管理端小程序入口票据。
 *
 * 票据只携带站点和小程序内部目标，不保存登录态。员工打开入口后仍须通过
 * adminapi 登录、站点归属和角色中间件校验。
 */
final class WecomEntryTicket
{
    public const VERSION = 1;
    public const DEFAULT_TTL = 604800;
    public const MAX_TTL = 2592000;

    public static function issue(int $siteId, string $miniappPath, string $routeKey = '', int $ttl = self::DEFAULT_TTL): string
    {
        if ($siteId <= 0) throw new CommonException('企业微信入口站点无效');

        $now = time();
        $payload = [
            'v' => self::VERSION,
            'site_id' => $siteId,
            'miniapp_path' => self::normalizeMiniappPath($miniappPath),
            'route_key' => self::normalizeRouteKey($routeKey),
            'iat' => $now,
            'exp' => $now + max(60, min(self::MAX_TTL, $ttl)),
            'nonce' => bin2hex(random_bytes(12)),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new CommonException('企业微信入口票据生成失败');

        $body = self::base64UrlEncode($json);
        $signature = hash_hmac('sha256', $body, self::key(), true);
        return $body . '.' . self::base64UrlEncode($signature);
    }

    public static function verify(string $ticket): array
    {
        $parts = explode('.', trim($ticket));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new CommonException('企业微信入口票据格式无效');
        }

        [$body, $signaturePart] = $parts;
        $providedSignature = self::base64UrlDecode($signaturePart);
        $expectedSignature = hash_hmac('sha256', $body, self::key(), true);
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new CommonException('企业微信入口票据签名无效');
        }

        $payload = json_decode(self::base64UrlDecode($body), true);
        if (!is_array($payload)
            || (int)($payload['v'] ?? 0) !== self::VERSION
            || (int)($payload['site_id'] ?? 0) <= 0
            || trim((string)($payload['nonce'] ?? '')) === '') {
            throw new CommonException('企业微信入口票据内容无效');
        }
        if ((int)($payload['exp'] ?? 0) < time()) {
            throw new CommonException('企业微信入口已过期，请从最新通知重新进入');
        }
        if ((int)($payload['iat'] ?? 0) > time() + 300) {
            throw new CommonException('企业微信入口票据时间无效');
        }

        return [
            'site_id' => (int)$payload['site_id'],
            'miniapp_path' => self::normalizeMiniappPath((string)($payload['miniapp_path'] ?? '')),
            'route_key' => self::normalizeRouteKey((string)($payload['route_key'] ?? '')),
            'iat' => (int)($payload['iat'] ?? 0),
            'exp' => (int)$payload['exp'],
            'nonce' => trim((string)$payload['nonce']),
        ];
    }

    private static function normalizeMiniappPath(string $path): string
    {
        $path = ltrim(trim($path), '/');
        if ($path === '') $path = 'app/pages/index/index';
        if (strlen($path) > 1000
            || str_contains($path, '://')
            || str_contains($path, '#')
            || str_contains($path, '..')
            || preg_match('/[\x00-\x1F\x7F]/', $path)
            || !preg_match('#^(?:app|addon)/[A-Za-z0-9_./-]+(?:\?.*)?$#u', $path)) {
            throw new CommonException('企业微信入口小程序路径无效');
        }
        if (strtok($path, '?') === 'app/pages/wecom/entry') {
            throw new CommonException('企业微信入口不能再次跳转到自身');
        }
        return $path;
    }

    private static function normalizeRouteKey(string $routeKey): string
    {
        $routeKey = trim($routeKey);
        if (strlen($routeKey) > 160 || preg_match('/[\x00-\x1F\x7F]/', $routeKey)) {
            throw new CommonException('企业微信入口业务标识无效');
        }
        return $routeKey;
    }

    private static function key(): string
    {
        $authKey = trim((string)env('app.auth_key', ''));
        if ($authKey === '') {
            throw new CommonException('系统未配置 app.auth_key，无法生成安全的企业微信入口');
        }
        return hash('sha256', 'hsx-wecom-entry:' . $authKey, true);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) {
            throw new CommonException('企业微信入口票据编码无效');
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
        if ($decoded === false) throw new CommonException('企业微信入口票据编码无效');
        return $decoded;
    }
}
