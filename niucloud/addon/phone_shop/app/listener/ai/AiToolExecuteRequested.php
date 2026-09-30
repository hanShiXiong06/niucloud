<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

use addon\phone_shop\app\service\core\ai\AiPhoneShopReadService;

final class AiToolExecuteRequested
{
    public function handle(array $event): array
    {
        $toolKey = (string)($event['tool_key'] ?? '');
        if (!in_array($toolKey, ['phone_shop.category.list', 'phone_shop.goods.search', 'phone_shop.listing.summary'], true)) return [];
        if (!AiIntegrationGuard::allowed((int)($event['site_id'] ?? 0))) {
            return [
                'tool_key' => $toolKey,
                'source_plugin' => 'phone_shop',
                'handled' => true,
                'data' => ['allowed' => false, 'locked' => true],
            ];
        }
        if ($toolKey === 'phone_shop.category.list') {
            $arguments = (array)($event['arguments'] ?? []);
            return [
                'tool_key' => $toolKey,
                'source_plugin' => 'phone_shop',
                'handled' => true,
                'data' => [
                    'allowed' => true,
                    'categories' => (new AiMallCategoryQuery())->query(
                        (int)($event['site_id'] ?? 0),
                        trim((string)($arguments['keyword'] ?? '')),
                        (int)($arguments['limit'] ?? 100)
                    ),
                ],
            ];
        }
        if ($toolKey === 'phone_shop.listing.summary') {
            return [
                'tool_key' => $toolKey,
                'source_plugin' => 'phone_shop',
                'handled' => true,
                'data' => (new AiPhoneShopReadService())->listingSummary(
                    (int)($event['site_id'] ?? 0),
                    (array)($event['arguments'] ?? [])
                ),
            ];
        }
        $result = (new AiMallBusinessContextRequested())->handle([
            'site_id' => (int)($event['site_id'] ?? 0),
            'scene' => (string)($event['scene'] ?? ''),
            'actor' => (array)($event['actor'] ?? []),
            'prompt' => (string)($event['arguments']['query'] ?? ''),
        ]);
        return [
            'tool_key' => $toolKey,
            'source_plugin' => 'phone_shop',
            'handled' => true,
            'data' => ['allowed' => !empty($result['allowed']), 'products' => (array)($result['resources'] ?? [])],
        ];
    }
}
