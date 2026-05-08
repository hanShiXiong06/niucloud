<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\core;

use think\facade\Cache;

/**
 * 爬虫报价移动端展示缓存
 */
class QuoteApiCacheService
{
    private const VERSION_KEY_PREFIX = 'recycle_quote_spider:api:version:';
    private const DATA_KEY_PREFIX = 'recycle_quote_spider:api:data:';
    private const DEFAULT_TTL = 600;
    private const DETAIL_TTL = 900;

    public function remember(int $siteId, string $scene, array $where, callable $callback, ?int $ttl = null): array
    {
        $key = $this->dataKey($siteId, $scene, $where);
        $cached = Cache::get($key);
        if (is_array($cached) && array_key_exists('data', $cached)) {
            return is_array($cached['data']) ? $cached['data'] : [];
        }

        $data = $callback();
        Cache::set($key, ['data' => is_array($data) ? $data : []], $ttl ?? ($scene === 'detail' ? self::DETAIL_TTL : self::DEFAULT_TTL));
        return is_array($data) ? $data : [];
    }

    public function refresh(int $siteId): void
    {
        Cache::set($this->versionKey($siteId), $this->makeVersion(), 86400 * 30);
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
