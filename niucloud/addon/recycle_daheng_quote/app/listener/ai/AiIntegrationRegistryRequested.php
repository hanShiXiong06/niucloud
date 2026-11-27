<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\listener\ai;

final class AiIntegrationRegistryRequested
{
    public function handle(array $event): array
    {
        return [[
            'key' => 'recycle_daheng_quote',
            'name' => 'DH 速收报价',
            'description' => '允许 AI 查询苹果回收报价、指定日期报价和七天价格行情。',
            'source_plugin' => 'recycle_daheng_quote',
            'scenes' => ['phone_shop.customer_assistant'],
            'capabilities' => ['苹果回收报价', '等级价格', '扣价说明', '指定日期报价', '七天价格行情'],
        ]];
    }
}
