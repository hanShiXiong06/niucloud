<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\ai;

final class AiIntegrationRegistryRequested
{
    public function handle(array $event): array
    {
        return [[
            'key' => 'hsx_erp',
            'name' => '二手机 ERP',
            'description' => '允许经营管理助手按当前管理员权限查询库存、上架工作量以及应收应付事实。',
            'source_plugin' => 'hsx_erp',
            'scenes' => ['business.admin_assistant'],
            'capabilities' => ['库存总览', '上架待办', '应付款查询', '应收款查询', '个人财务待办'],
        ]];
    }
}
