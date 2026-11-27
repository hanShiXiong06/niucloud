<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\ai;

final class AiToolRegistryRequested
{
    public function handle(array $event): array
    {
        $common = [
            'source_plugin' => 'hsx_erp',
            'integration_key' => 'hsx_erp',
            'scenes' => ['business.admin_assistant'],
            'auth' => 'admin',
            'read_only' => true,
            'risk_level' => 'read',
        ];
        $financeProperties = [
            'keyword' => ['type' => 'string', 'maxLength' => 100],
            'status' => ['type' => 'string', 'enum' => ['open', 'pending', 'partial', 'settled', 'all']],
            'mine' => ['type' => 'boolean'],
            'start_date' => ['type' => 'string', 'maxLength' => 10],
            'end_date' => ['type' => 'string', 'maxLength' => 10],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
        ];
        $salesProperties = [
            'keyword' => ['type' => 'string', 'maxLength' => 100],
            'mine' => ['type' => 'boolean'],
            'start_date' => ['type' => 'string', 'maxLength' => 10],
            'end_date' => ['type' => 'string', 'maxLength' => 10],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
        ];
        return [
            array_merge($common, [
                'key' => 'hsx_erp.stock.summary',
                'name' => '查询 ERP 库存总览',
                'description' => '查询实时在库数量、库存成本、各仓库分布，以及待拍照、待定价、待完善资料和待上架数量。',
                'permissions' => ['hsx_erp_stock'],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['warehouse_id' => ['type' => 'integer', 'minimum' => 0]],
                    'additionalProperties' => false,
                ],
            ]),
            array_merge($common, [
                'key' => 'hsx_erp.payable.summary',
                'name' => '查询 ERP 应付总览',
                'description' => '查询全站或当前管理员负责的待付款笔数、应付原额、已结算额和剩余应付。',
                'permissions' => ['hsx_erp_payable'],
                'input_schema' => ['type' => 'object', 'properties' => ['mine' => ['type' => 'boolean']], 'additionalProperties' => false],
            ]),
            array_merge($common, [
                'key' => 'hsx_erp.payable.search',
                'name' => '查询 ERP 应付款明细',
                'description' => '按供应商、来源单号、业务原因、状态和日期查询应付款，回答“还有谁没打款”等问题。',
                'permissions' => ['hsx_erp_payable'],
                'input_schema' => ['type' => 'object', 'properties' => $financeProperties, 'additionalProperties' => false],
            ]),
            array_merge($common, [
                'key' => 'hsx_erp.receivable.summary',
                'name' => '查询 ERP 应收总览',
                'description' => '查询全站或当前管理员负责的待收款笔数、应收原额、已结算额和剩余应收。',
                'permissions' => ['hsx_erp_receivable'],
                'input_schema' => ['type' => 'object', 'properties' => ['mine' => ['type' => 'boolean']], 'additionalProperties' => false],
            ]),
            array_merge($common, [
                'key' => 'hsx_erp.receivable.search',
                'name' => '查询 ERP 应收款明细',
                'description' => '按客户、来源单号、业务原因、状态和日期查询应收款，回答“哪些客户还没结账”等问题。',
                'permissions' => ['hsx_erp_receivable'],
                'input_schema' => ['type' => 'object', 'properties' => $financeProperties, 'additionalProperties' => false],
            ]),
            array_merge($common, [
                'key' => 'hsx_erp.sales.summary',
                'name' => '查询 ERP 销售经营总览',
                'description' => '查询今天、本周或本月的销售单数、售出数量、销售额、毛利、已收和待收金额。用户问“我卖了多少”时 mine 传 true。',
                'permissions' => ['hsx_erp_sale'],
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
                'key' => 'hsx_erp.sales.search',
                'name' => '查询 ERP 销售明细',
                'description' => '按客户、销售单号、销售员、渠道和日期查询销售单，返回销售额、毛利和待收金额。',
                'permissions' => ['hsx_erp_sale'],
                'input_schema' => ['type' => 'object', 'properties' => $salesProperties, 'additionalProperties' => false],
            ]),
        ];
    }
}
