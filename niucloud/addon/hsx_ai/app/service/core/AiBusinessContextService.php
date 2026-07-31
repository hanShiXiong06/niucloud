<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

final class AiBusinessContextService
{
    public function resolve(array $request): array
    {
        $integrationKey = trim((string)($request['integration_key'] ?? ''));
        if ($integrationKey !== '' && !(new AiIntegrationService())->isEnabled((int)($request['site_id'] ?? 0), $integrationKey)) {
            return [
                'handled' => true,
                'allowed' => false,
                'locked' => true,
                'contexts' => [],
                'suggestions' => [],
                'resources' => [],
            ];
        }
        $responses = event('HsxAiBusinessContextRequested', $request);
        $items = [];
        foreach ((array)$responses as $response) {
            if (!is_array($response)) continue;
            if ($response !== [] && array_keys($response) === range(0, count($response) - 1)) {
                foreach ($response as $item) if (is_array($item)) $items[] = $item;
            } else {
                $items[] = $response;
            }
        }

        $handled = array_values(array_filter($items, static fn(array $item): bool => !empty($item['handled'])));
        $allowed = array_values(array_filter($handled, static fn(array $item): bool => !empty($item['allowed'])));
        return [
            'handled' => $handled !== [],
            'allowed' => $allowed !== [],
            'contexts' => array_values(array_filter(array_map(
                static fn(array $item): string => trim((string)($item['context'] ?? '')),
                $allowed
            ))),
            'suggestions' => array_values(array_unique(array_merge(...array_map(
                static fn(array $item): array => array_values(array_filter((array)($item['suggestions'] ?? []), 'is_string')),
                $handled ?: [[]]
            )))),
            'resources' => array_values(array_merge(...array_map(
                static fn(array $item): array => array_values(array_filter((array)($item['resources'] ?? []), 'is_array')),
                $allowed ?: [[]]
            ))),
        ];
    }
}
