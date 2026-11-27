<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use core\exception\CommonException;

/**
 * 可选业务插件接入锁。业务插件只负责声明能力，是否允许通信由当前站点显式授权。
 */
final class AiIntegrationService
{
    public function definitions(int $siteId, ?array $stored = null): array
    {
        if ($siteId <= 0) return [];
        if ($stored === null) {
            $stored = (array)((new AiConfigService())->get($siteId)['integrations'] ?? []);
        }
        $enabledMap = [];
        foreach ($stored as $row) {
            if (!is_array($row)) continue;
            $key = trim((string)($row['key'] ?? ''));
            if ($key !== '') $enabledMap[$key] = (int)!empty($row['enabled']);
        }

        $definitions = [];
        foreach ((array)event('HsxAiIntegrationRegistryRequested', ['site_id' => $siteId]) as $response) {
            foreach ($this->rows($response) as $row) {
                $key = strtolower(trim((string)($row['key'] ?? '')));
                $key = preg_replace('/[^a-z0-9_]/', '_', $key) ?: '';
                if ($key === '') continue;
                $definitions[$key] = [
                    'key' => $key,
                    'name' => trim((string)($row['name'] ?? $key)) ?: $key,
                    'description' => trim((string)($row['description'] ?? '')),
                    'source_plugin' => trim((string)($row['source_plugin'] ?? $key)) ?: $key,
                    'scenes' => array_values(array_filter((array)($row['scenes'] ?? []), 'is_string')),
                    'capabilities' => array_values(array_filter((array)($row['capabilities'] ?? []), 'is_string')),
                    'enabled' => (int)($enabledMap[$key] ?? 0),
                ];
            }
        }
        return array_values($definitions);
    }

    public function isEnabled(int $siteId, string $integrationKey): bool
    {
        $integrationKey = strtolower(trim($integrationKey));
        if ($siteId <= 0 || $integrationKey === '') return false;
        $config = (new AiConfigService())->get($siteId);
        if (empty($config['enabled'])) return false;
        foreach ($this->definitions($siteId, (array)$config['integrations']) as $definition) {
            if ((string)$definition['key'] === $integrationKey || (string)$definition['source_plugin'] === $integrationKey) {
                return !empty($definition['enabled']);
            }
        }
        return false;
    }

    public function assertEnabled(int $siteId, string $integrationKey): void
    {
        if (!$this->isEnabled($siteId, $integrationKey)) {
            throw new CommonException('AI 与该业务插件的通信尚未开启');
        }
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }
}
