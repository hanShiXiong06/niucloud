<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\listener\ai;

final class AiSkillRegistryRequested
{
    public function handle(array $event): array
    {
        $common = [
            'source_plugin' => 'recycle_daheng_quote',
            'integration_key' => 'recycle_daheng_quote',
            'scenes' => ['phone_shop.customer_assistant'],
            'audiences' => ['member'],
            'submit_mode' => 'fill',
        ];
        return [
            array_merge($common, [
                'key' => 'apple_quote',
                'label' => '查苹果回收价',
                'icon' => 'rmb-circle',
                'prompt' => '查询今天的苹果回收报价，型号、内存和等级是：',
                'sort' => 110,
            ]),
            array_merge($common, [
                'key' => 'seven_day_trend',
                'label' => '看7天行情',
                'icon' => 'map',
                'prompt' => '查看苹果机型连续7天的回收行情，型号、内存和等级是：',
                'sort' => 120,
            ]),
            array_merge($common, [
                'key' => 'historical_quote',
                'label' => '查历史报价',
                'icon' => 'calendar',
                'prompt' => '查询指定日期的苹果回收报价，日期、型号、内存和等级是：',
                'sort' => 130,
            ]),
        ];
    }
}
