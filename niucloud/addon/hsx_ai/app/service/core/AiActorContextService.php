<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use core\exception\CommonException;

/**
 * 统一 AI 身份上下文。业务服务负责从已鉴权请求构建，模型不能修改。
 */
final class AiActorContextService
{
    public function member(int $siteId, int $memberId, string $channel = ''): array
    {
        return $this->make($siteId, [
            'type' => $memberId > 0 ? 'member' : 'guest',
            'id' => max(0, $memberId),
            'name' => '',
            'permissions' => [],
            'roles' => [],
            'data_scope' => $memberId > 0 ? 'self' : 'public',
            'channel' => trim($channel),
        ]);
    }

    public function admin(
        int $siteId,
        int $uid,
        string $name,
        array $permissions = [],
        array $roles = [],
        string $dataScope = 'site'
    ): array {
        return $this->make($siteId, [
            'type' => 'admin',
            'id' => $uid,
            'name' => trim($name),
            'permissions' => $permissions,
            'roles' => $roles,
            'data_scope' => $dataScope,
            'channel' => 'admin',
        ]);
    }

    public function system(int $siteId, string $name = 'system', array $permissions = []): array
    {
        return $this->make($siteId, [
            'type' => 'system',
            'id' => 0,
            'name' => trim($name) ?: 'system',
            'permissions' => $permissions,
            'roles' => [],
            'data_scope' => 'site',
            'channel' => 'system',
        ]);
    }

    public function make(int $siteId, array $actor): array
    {
        if ($siteId <= 0) throw new CommonException('AI身份缺少有效站点');
        $type = trim((string)($actor['type'] ?? 'guest')) ?: 'guest';
        if (!in_array($type, ['guest', 'member', 'admin', 'system'], true)) {
            throw new CommonException('AI身份类型无效');
        }
        $id = max(0, (int)($actor['id'] ?? 0));
        if (in_array($type, ['member', 'admin'], true) && $id <= 0) {
            throw new CommonException('AI身份缺少有效用户');
        }
        return $this->enrich([
            'site_id' => $siteId,
            'type' => $type,
            'id' => $id,
            'name' => mb_substr(trim((string)($actor['name'] ?? '')), 0, 80),
            'permissions' => array_values(array_unique(array_filter(
                array_map('strval', (array)($actor['permissions'] ?? []))
            ))),
            'roles' => array_values(array_unique(array_filter(
                array_map('strval', (array)($actor['roles'] ?? []))
            ))),
            'data_scope' => trim((string)($actor['data_scope'] ?? 'public')) ?: 'public',
            'channel' => mb_substr(trim((string)($actor['channel'] ?? '')), 0, 40),
            'attributes' => (array)($actor['attributes'] ?? []),
        ]);
    }

    private function enrich(array $actor): array
    {
        foreach ((array)event('HsxAiActorContextEnrichRequested', ['site_id' => $actor['site_id'], 'actor' => $actor]) as $response) {
            foreach ($this->rows($response) as $row) {
                $sourcePlugin = trim((string)($row['source_plugin'] ?? ''));
                if ($sourcePlugin !== '' && $sourcePlugin !== 'hsx_ai'
                    && !(new AiIntegrationService())->isEnabled((int)$actor['site_id'], $sourcePlugin)) continue;
                $actor['permissions'] = array_values(array_unique(array_merge(
                    $actor['permissions'],
                    array_values(array_filter(array_map('strval', (array)($row['permissions'] ?? []))))
                )));
                $actor['roles'] = array_values(array_unique(array_merge(
                    $actor['roles'],
                    array_values(array_filter(array_map('strval', (array)($row['roles'] ?? []))))
                )));
                $actor['attributes'] = array_replace($actor['attributes'], (array)($row['attributes'] ?? []));
                if (trim((string)($row['data_scope'] ?? '')) !== '') {
                    $actor['data_scope'] = trim((string)$row['data_scope']);
                }
            }
        }
        $actor['permissions'] = array_slice($actor['permissions'], 0, 500);
        $actor['roles'] = array_slice($actor['roles'], 0, 100);
        $metadata = json_encode($actor['attributes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($metadata) || strlen($metadata) > 20000) $actor['attributes'] = [];
        return $actor;
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }
}
