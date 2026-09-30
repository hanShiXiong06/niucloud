<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

/** 商城可向同一 AI 场景注册多个快捷能力入口。 */
final class AiSkillRegistryRequested
{
    public function handle(array $event): array
    {
        $common = [
            'source_plugin' => 'phone_shop',
            'integration_key' => 'phone_shop',
            'scenes' => ['phone_shop.customer_assistant'],
            'audiences' => ['member'],
            'submit_mode' => 'fill',
        ];
        return [
            array_merge($common, [
                'key' => 'choose_phone',
                'label' => '帮我选手机',
                'icon' => 'search',
                'prompt' => '我想买一台手机，请先问我预算和主要用途：',
                'sort' => 10,
            ]),
            array_merge($common, [
                'key' => 'apple_in_stock',
                'label' => '查在售苹果',
                'icon' => 'shopping-cart',
                'prompt' => '帮我查询本站当前在售的苹果手机，我的要求是：',
                'sort' => 20,
            ]),
            array_merge($common, [
                'key' => 'value_recommendation',
                'label' => '性价比推荐',
                'icon' => 'thumb-up',
                'prompt' => '结合本站实时库存，帮我推荐性价比高的手机。我的预算是：',
                'sort' => 30,
            ]),
        ];
    }
}
