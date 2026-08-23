<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\ai;

final class AiAdminAgentRegistryRequested
{
    public function handle(array $event): array
    {
        return [[
            'key' => 'business.recycle',
            'name' => '回收作业助手',
            'short_name' => '回收流程',
            'description' => '查看待签收、待质检、待定价、待打款和个人工作量。',
            'icon' => 'element Refresh',
            'tone' => 'info',
            'tool_keys' => ['hsx_recycle.workflow.summary', 'hsx_recycle.order.search'],
            'quick_prompts' => [
                ['text' => '我现在还有哪些待处理工作？', 'tool_keys' => ['hsx_recycle.workflow.summary']],
                ['text' => '全店回收流程堵在哪一步？', 'tool_keys' => ['hsx_recycle.workflow.summary']],
                ['text' => '查询今天待签收的订单。', 'tool_keys' => ['hsx_recycle.order.search']],
            ],
            'system_prompt' => '你是回收作业助手。区分全店与本人范围，围绕取货、签收、质检、定价、客户确认和打款形成下一步建议。',
        ]];
    }
}
