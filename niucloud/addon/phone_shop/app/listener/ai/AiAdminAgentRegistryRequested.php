<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

final class AiAdminAgentRegistryRequested
{
    public function handle(array $event): array
    {
        return [[
            'key' => 'business.phone_shop',
            'name' => '商城货盘助手',
            'short_name' => '商城上新与离架',
            'description' => '查看二手机商城每日上新、离架、已售和当前可售货盘。',
            'icon' => 'element Shop',
            'tone' => 'warning',
            'tool_keys' => ['phone_shop.listing.summary'],
            'default_tool_key' => 'phone_shop.listing.summary',
            'quick_prompts' => [
                ['text' => '今天商城上架和下架了多少台？', 'tool_keys' => ['phone_shop.listing.summary']],
                ['text' => '本周商城货盘变化怎么样？', 'tool_keys' => ['phone_shop.listing.summary']],
                ['text' => '现在商城还有多少台可售？', 'tool_keys' => ['phone_shop.listing.summary']],
            ],
            'system_prompt' => '你是二手机商城货盘助手。商城是一机一品、每日持续上新的展示货盘，不得套用普通标品商城的长期库存口径。上新、离架、已售和当前可售必须实时调用工具，并清楚说明统计时间与口径。',
        ]];
    }
}
