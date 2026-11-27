<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\core\quotation;

use think\facade\Cache;

/**
 * 报价 2.0 移动端展示缓存
 */
class QuotationV2CacheService
{
    private const VERSION_KEY_PREFIX = 'recycle_daheng_quote:v2:version:';
    private const DATA_KEY_PREFIX = 'recycle_daheng_quote:v2:data:';
    private const TYPES_TTL = 600;
    private const LIST_TTL = 600;

    public function rememberTypes(int $siteId, array $where, callable $callback): array
    {
        return $this->remember($siteId, 'types', $where, self::TYPES_TTL, $callback);
    }

    public function rememberList(int $siteId, array $where, callable $callback): array
    {
        return $this->remember($siteId, 'list', $where, self::LIST_TTL, $callback);
    }

    public function refresh(int $siteId): void
    {
        Cache::set($this->versionKey($siteId), $this->makeVersion(), 86400 * 30);
    }

    private function remember(int $siteId, string $scene, array $where, int $ttl, callable $callback): array
    {
        $key = $this->dataKey($siteId, $scene, $where);
        $cached = Cache::get($key);
        if (is_array($cached) && array_key_exists('data', $cached)) {
            return is_array($cached['data']) ? $cached['data'] : [];
        }

        $data = $callback();
        Cache::set($key, ['data' => is_array($data) ? $data : []], $ttl);
        return is_array($data) ? $data : [];
    }

    private function dataKey(int $siteId, string $scene, array $where): string
    {
        ksort($where);
        return self::DATA_KEY_PREFIX . $siteId . ':' . $scene . ':' . $this->version($siteId) . ':' . md5(json_encode($where, JSON_UNESCAPED_UNICODE));
    }

    private function version(int $siteId): string
    {
        $key = $this->versionKey($siteId);
        $version = Cache::get($key);
        if (!is_string($version) || $version === '') {
            $version = $this->makeVersion();
            Cache::set($key, $version, 86400 * 30);
        }
        return $version;
    }

    private function versionKey(int $siteId): string
    {
        return self::VERSION_KEY_PREFIX . $siteId;
    }

    private function makeVersion(): string
    {
        return (string)time() . '_' . (string)random_int(1000, 9999);
    }
}
