<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

final class AiIntegrationRegistryRequested
{
    public function handle(array $event): array
    {
        return [[
            'key' => 'phone_shop',
            'name' => '二手机商城',
            'description' => '允许 AI 查询本站商品分类、实时在售商品及管理端一机一品货盘变动。',
            'source_plugin' => 'phone_shop',
            'scenes' => ['phone_shop.customer_assistant', 'business.admin_assistant'],
            'capabilities' => ['商品分类查询', '在售商品查询', '商品推荐', '会员可见价', '货盘上新与离架统计', '到货订阅（待接入）'],
        ]];
    }
}
