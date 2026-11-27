<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\ai;

/** ERP 只声明自身经营智能体，AI 中台负责权限裁剪与聚合。 */
final class AiAdminAgentRegistryRequested
{
    public function handle(array $event): array
    {
        return [
            [
                'key' => 'business.finance',
                'name' => '财务助手',
                'short_name' => '应收应付',
                'description' => '查询应收、应付、未结账款及责任人。',
                'icon' => 'element Wallet',
                'tone' => 'success',
                'tool_keys' => ['hsx_erp.payable.summary', 'hsx_erp.payable.search', 'hsx_erp.receivable.summary', 'hsx_erp.receivable.search'],
                'quick_prompts' => [
                    ['text' => '还有哪些应付款没处理？', 'tool_keys' => ['hsx_erp.payable.search']],
                    ['text' => '还有哪些应收款没收回？', 'tool_keys' => ['hsx_erp.receivable.search']],
                    ['text' => '只看分配给我的待处理账款。', 'tool_keys' => []],
                ],
                'system_prompt' => '你是财务助手。金额、状态和往来单位必须来自工具；先汇总再列明细。用户表达“我的”时使用本人范围，不得展示无权限账目。',
            ],
            [
                'key' => 'business.inventory_sales',
                'name' => '库存销售助手',
                'short_name' => '库存与销售',
                'description' => '查看 ERP 货盘、上架工作量、销售额和毛利。',
                'icon' => 'element Goods',
                'tone' => 'warning',
                'tool_keys' => ['hsx_erp.stock.summary', 'hsx_erp.sales.summary', 'hsx_erp.sales.search'],
                'quick_prompts' => [
                    ['text' => '当前库存情况怎么样？', 'tool_keys' => ['hsx_erp.stock.summary']],
                    ['text' => '今天卖了多少台，毛利多少？', 'tool_keys' => ['hsx_erp.sales.summary']],
                    ['text' => '哪些设备还没完成上架？', 'tool_keys' => ['hsx_erp.stock.summary']],
                ],
                'system_prompt' => '你是 ERP 库存销售助手。优先回答库存数量、库存成本、销售台数、销售额、毛利和待上架工作量，涉及当前数据必须实时查询。',
            ],
        ];
    }
}
