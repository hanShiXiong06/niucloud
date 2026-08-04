<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

/**
 * 聚合已授权业务插件为当前 AI 场景注册的用户快捷能力。
 */
final class AiSkillService
{
    public function quickActions(int $siteId, string $scene, array $actor = []): array
    {
        if ($siteId <= 0 || $scene === '') return [];

        $enabledIntegrations = [];
        foreach ((new AiIntegrationService())->definitions($siteId) as $definition) {
            if (empty($definition['enabled'])) continue;
            $enabledIntegrations[(string)$definition['key']] = true;
            $enabledIntegrations[(string)$definition['source_plugin']] = true;
        }
        if ($enabledIntegrations === []) return [];

        $event = [
            'site_id' => $siteId,
            'scene' => $scene,
            'actor' => $actor,
        ];
        $actions = [];
        foreach ((array)event('HsxAiSkillRegistryRequested', $event) as $response) {
            foreach ($this->rows($response) as $row) {
                $integrationKey = $this->key((string)($row['integration_key'] ?? $row['source_plugin'] ?? ''));
                if ($integrationKey === '' || empty($enabledIntegrations[$integrationKey])) continue;

                $scenes = array_values(array_filter((array)($row['scenes'] ?? []), 'is_string'));
                if ($scenes !== [] && !in_array($scene, $scenes, true)) continue;
                $audiences = array_values(array_filter((array)($row['audiences'] ?? ['member']), 'is_string'));
                $actorType = trim((string)($actor['type'] ?? 'member')) ?: 'member';
                if ($audiences !== [] && !in_array($actorType, $audiences, true)) continue;

                $key = $this->key((string)($row['key'] ?? ''));
                $label = trim((string)($row['label'] ?? ''));
                $prompt = trim((string)($row['prompt'] ?? ''));
                if ($key === '' || $label === '' || $prompt === '') continue;
                $submitMode = (string)($row['submit_mode'] ?? 'fill');
                if (!in_array($submitMode, ['fill', 'send'], true)) $submitMode = 'fill';

                $identity = $integrationKey . ':' . $key;
                $actions[$identity] = [
                    'key' => $key,
                    'label' => mb_substr($label, 0, 12),
                    'icon' => $this->icon((string)($row['icon'] ?? 'chat')),
                    'prompt' => mb_substr($prompt, 0, 300),
                    'submit_mode' => $submitMode,
                    'integration_key' => $integrationKey,
                    'source_plugin' => $this->key((string)($row['source_plugin'] ?? $integrationKey)) ?: $integrationKey,
                    'sort' => (int)($row['sort'] ?? 0),
                ];
            }
        }

        $actions = array_values($actions);
        usort($actions, static fn(array $left, array $right): int => [$left['sort'], $left['label']] <=> [$right['sort'], $right['label']]);
        return array_slice($actions, 0, 20);
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }

    private function key(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9_.]/', '_', $value) ?: '';
    }

    private function icon(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z0-9_-]{1,32}$/', $value) ? $value : 'chat';
    }
}
