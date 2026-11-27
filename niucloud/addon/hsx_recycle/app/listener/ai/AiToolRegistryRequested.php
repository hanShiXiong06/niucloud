<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\ai;

final class AiToolRegistryRequested
{
    public function handle(array $event): array
    {
        $common = [
            'source_plugin' => 'hsx_recycle',
            'integration_key' => 'hsx_recycle',
            'scenes' => ['business.admin_assistant'],
            'auth' => 'admin',
            'permissions' => ['recycle_order_list'],
            'read_only' => true,
            'risk_level' => 'read',
        ];
        return [
            array_merge($common, [
                'key' => 'hsx_recycle.workflow.summary',
                'name' => '查询回收流程待办',
                'description' => '查询回收业务当前待取货、待签收、待质检、待定价、待客户确认、待打款和异常数量，以及今天/本周/本月已完成工作量。用户问“我还有多少任务”时 mine 传 true。',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month']],
                        'mine' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
            ]),
            array_merge($common, [
                'key' => 'hsx_recycle.order.search',
                'name' => '查询回收订单和设备',
                'description' => '按订单号、客户、手机号、快递号、车牌号、IMEI、SN 或型号查询回收订单及设备摘要，可筛选业务状态和日期。',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'maxLength' => 100],
                        'status' => ['type' => 'string', 'enum' => ['pending_sign', 'checking', 'checked', 'pending_confirm', 'pending_payment', 'completed', 'closed', 'cancelled']],
                        'start_date' => ['type' => 'string', 'maxLength' => 10],
                        'end_date' => ['type' => 'string', 'maxLength' => 10],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'additionalProperties' => false,
                ],
            ]),
        ];
    }
}
