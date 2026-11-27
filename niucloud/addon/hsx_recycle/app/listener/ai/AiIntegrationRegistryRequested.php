<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\ai;

final class AiIntegrationRegistryRequested
{
    public function handle(array $event): array
    {
        return [[
            'key' => 'hsx_recycle',
            'name' => '回收业务',
            'description' => '允许经营管理助手按当前管理员权限查询回收流程待办、订单和设备事实。',
            'source_plugin' => 'hsx_recycle',
            'scenes' => ['business.admin_assistant'],
            'capabilities' => ['流程待办总览', '个人待办总览', '回收订单查询', '设备串号查询'],
        ]];
    }
}
