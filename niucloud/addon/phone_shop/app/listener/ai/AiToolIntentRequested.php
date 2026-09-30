<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

/** 管理端商城货盘问题的快速路由。 */
final class AiToolIntentRequested
{
    public function handle(array $event): array
    {
        $available = array_values(array_map('strval', (array)($event['available_tool_keys'] ?? [])));
        if (!in_array('phone_shop.listing.summary', $available, true)) return [];
        $prompt = trim((string)($event['prompt'] ?? ''));
        $aggregate = (string)($event['agent_key'] ?? '') === 'business.owner'
            && preg_match('/经营(?:情况|数据|总览)|经营汇总|全店汇总|汇总.*经营/u', $prompt);
        if (!$aggregate && !preg_match('/商城|货盘|上新|上架|下架|离架|已售|可售/u', $prompt)) return [];
        $arguments = (new AiDefaultToolArgumentsRequested())->handle([
            'tool_key' => 'phone_shop.listing.summary',
            'prompt' => $prompt,
        ]);
        return [
            'handled' => true,
            'tool_key' => 'phone_shop.listing.summary',
            'arguments' => (array)($arguments['arguments'] ?? []),
            'confidence' => $aggregate ? 0.92 : 0.96,
            'aggregate' => (bool)$aggregate,
            'source_plugin' => 'phone_shop',
        ];
    }
}
