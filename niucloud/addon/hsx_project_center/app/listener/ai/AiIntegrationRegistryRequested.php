<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\listener\ai;

final class AiIntegrationRegistryRequested
{
    public function handle(array $event): array
    {
        return [[
            'key' => 'hsx_project_center',
            'name' => '项目中心',
            'description' => '允许 AI 在当前站点、当前项目范围内读取已发布的项目介绍、办理流程、常见问题和项目知识。',
            'source_plugin' => 'hsx_project_center',
            'scenes' => ['hsx_project_center.customer_assistant'],
            'capabilities' => ['项目介绍问答', '办理流程问答', '资料要求说明', '付款与退款规则说明', '无法解答转人工'],
        ]];
    }
}
