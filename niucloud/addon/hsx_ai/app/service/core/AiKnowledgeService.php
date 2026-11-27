<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

/**
 * 知识检索扩展口。当前不绑定 MySQL、向量库或第三方 RAG。
 */
final class AiKnowledgeService
{
    public function retrieve(array $context, string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '' || (int)($context['site_id'] ?? 0) <= 0) return [];
        $request = [
            'site_id' => (int)$context['site_id'],
            'scene' => trim((string)($context['scene'] ?? '')),
            'actor' => (array)($context['actor'] ?? []),
            'query' => mb_substr($query, 0, 2000),
            'limit' => max(1, min(20, (int)($options['limit'] ?? 8))),
            'filters' => (array)($options['filters'] ?? []),
        ];
        $items = [];
        foreach ((array)event('HsxAiKnowledgeRetrieveRequested', $request) as $response) {
            foreach ($this->rows($response) as $row) {
                $sourcePlugin = trim((string)($row['source_plugin'] ?? ''));
                if ($sourcePlugin !== '' && $sourcePlugin !== 'hsx_ai'
                    && !(new AiIntegrationService())->isEnabled((int)$context['site_id'], $sourcePlugin)) continue;
                if (!$this->visible($row, (array)($context['actor'] ?? []))) continue;
                $content = trim((string)($row['content'] ?? ''));
                if ($content === '') continue;
                $metadata = (array)($row['metadata'] ?? []);
                $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($encodedMetadata) || strlen($encodedMetadata) > 10000) $metadata = [];
                $items[] = [
                    'id' => mb_substr(trim((string)($row['id'] ?? '')), 0, 120),
                    'source_plugin' => $sourcePlugin,
                    'title' => mb_substr(trim((string)($row['title'] ?? '')), 0, 200),
                    'content' => mb_substr($content, 0, 8000),
                    'score' => max(0, min(1, (float)($row['score'] ?? 0))),
                    'metadata' => $metadata,
                ];
            }
        }
        usort($items, static fn(array $left, array $right): int => $right['score'] <=> $left['score']);
        return array_slice($items, 0, $request['limit']);
    }

    private function visible(array $row, array $actor): bool
    {
        $auth = trim((string)($row['auth'] ?? 'public')) ?: 'public';
        $actorType = (string)($actor['type'] ?? 'guest');
        if (!in_array($auth, ['public', 'member', 'admin', 'system', 'authenticated'], true)) return false;
        if ($auth === 'member' && $actorType !== 'member') return false;
        if ($auth === 'admin' && $actorType !== 'admin') return false;
        if ($auth === 'system' && $actorType !== 'system') return false;
        if ($auth === 'authenticated' && !in_array($actorType, ['member', 'admin', 'system'], true)) return false;
        $required = array_values(array_filter((array)($row['permissions'] ?? []), 'is_string'));
        $granted = array_values(array_filter((array)($actor['permissions'] ?? []), 'is_string'));
        return $required === [] || array_diff($required, $granted) === [];
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }
}
