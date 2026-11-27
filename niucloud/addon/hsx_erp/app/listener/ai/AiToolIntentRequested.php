<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\ai;

/** ERP 高频经营意图路由，保持 AI 中台与财务、库存、销售词义解耦。 */
final class AiToolIntentRequested
{
    public function handle(array $event): array
    {
        $prompt = trim((string)($event['prompt'] ?? ''));
        $available = array_values(array_map('strval', (array)($event['available_tool_keys'] ?? [])));
        if ($prompt === '') return [];
        if ((string)($event['agent_key'] ?? '') === 'business.owner'
            && preg_match('/经营(?:情况|数据|总览)|经营汇总|全店汇总|汇总.*经营/u', $prompt)) {
            $rows = [];
            foreach (['hsx_erp.stock.summary', 'hsx_erp.payable.summary', 'hsx_erp.receivable.summary', 'hsx_erp.sales.summary'] as $summaryTool) {
                if (!in_array($summaryTool, $available, true)) continue;
                $arguments = (new AiDefaultToolArgumentsRequested())->handle(['tool_key' => $summaryTool, 'prompt' => $prompt]);
                $rows[] = $this->result($summaryTool, (array)($arguments['arguments'] ?? []), 0.92, true);
            }
            return $rows;
        }
        $detail = preg_match('/哪些|谁|明细|列表|逐笔|哪几|具体/u', $prompt) === 1;
        $toolKey = '';
        if (preg_match('/应付|待付款|没付款|未付款|打款|供应商/u', $prompt)) {
            $toolKey = 'hsx_erp.payable.' . ($detail ? 'search' : 'summary');
        } elseif (preg_match('/应收|待收款|没收回|未收款|客户.*(?:欠|没结)|谁.*没结/u', $prompt)) {
            $toolKey = 'hsx_erp.receivable.' . ($detail ? 'search' : 'summary');
        } elseif (preg_match('/库存|在库|货盘|待拍照|待定价|待上架|资料完善|仓库/u', $prompt)) {
            $toolKey = 'hsx_erp.stock.summary';
        } elseif (preg_match('/销售|卖了|售出|销售额|毛利|成交/u', $prompt)) {
            $toolKey = 'hsx_erp.sales.' . ($detail ? 'search' : 'summary');
        }
        if ($toolKey === '' || !in_array($toolKey, $available, true)) return [];
        $arguments = (new AiDefaultToolArgumentsRequested())->handle(['tool_key' => $toolKey, 'prompt' => $prompt]);
        return $this->result($toolKey, (array)($arguments['arguments'] ?? []), 0.96);
    }

    private function result(string $toolKey, array $arguments, float $confidence, bool $aggregate = false): array
    {
        return [
            'handled' => true,
            'tool_key' => $toolKey,
            'arguments' => $arguments,
            'confidence' => $confidence,
            'aggregate' => $aggregate,
            'source_plugin' => 'hsx_erp',
        ];
    }
}
