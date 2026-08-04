<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

final class AiActionService
{
    public function sanitize(int $siteId, array $actions): array
    {
        $registry = [];
        foreach (array_values(array_filter((array)event('HsxAiActionRegistryRequested', ['site_id' => $siteId]), 'is_array')) as $response) {
            foreach ((array)($response['actions'] ?? $response) as $definition) {
                if (!is_array($definition)) continue;
                $id = trim((string)($definition['id'] ?? ''));
                $route = trim((string)($definition['route'] ?? ''));
                if ($id === '' || !str_starts_with($route, '/addon/') || empty($definition['enabled'])) continue;
                $registry[$id] = $definition;
            }
        }
        $result = [];
        foreach (array_slice($actions, 0, 6) as $action) {
            if (!is_array($action)) continue;
            $id = trim((string)($action['id'] ?? ''));
            $definition = $registry[$id] ?? null;
            if (!$definition) continue;
            $allowedParams = array_values(array_filter(array_map('strval', (array)($definition['allowed_params'] ?? []))));
            $params = array_intersect_key((array)($action['params'] ?? []), array_flip($allowedParams));
            $result[] = [
                'id' => $id,
                'type' => 'route',
                'label' => mb_substr(trim((string)($action['label'] ?? $definition['label'] ?? '继续')), 0, 30),
                'route' => (string)$definition['route'],
                'params' => $params,
                'approved' => true,
                'source_plugin' => (string)($definition['source_plugin'] ?? ''),
            ];
        }
        return $result;
    }
}
