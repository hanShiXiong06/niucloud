<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

/** 商城自己理解货盘口径参数，AI 中台不感知“今日/本周/本月”的业务含义。 */
final class AiDefaultToolArgumentsRequested
{
    public function handle(array $event): array
    {
        if ((string)($event['tool_key'] ?? '') !== 'phone_shop.listing.summary') return [];
        $prompt = trim((string)($event['prompt'] ?? ''));
        $period = preg_match('/本月|这个月|月度/u', $prompt) ? 'month'
            : (preg_match('/本周|这周|一周|周度/u', $prompt) ? 'week' : 'today');
        return [
            'handled' => true,
            'tool_key' => 'phone_shop.listing.summary',
            'source_plugin' => 'phone_shop',
            'arguments' => ['period' => $period],
        ];
    }
}
