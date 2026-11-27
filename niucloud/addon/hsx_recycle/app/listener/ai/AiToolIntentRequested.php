<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\ai;

/** 回收插件识别自身高频经营问题；无法确定时返回空，让模型继续编排。 */
final class AiToolIntentRequested
{
    public function handle(array $event): array
    {
        $prompt = trim((string)($event['prompt'] ?? ''));
        $available = array_values(array_map('strval', (array)($event['available_tool_keys'] ?? [])));
        if ($prompt === '' || !array_intersect($available, ['hsx_recycle.workflow.summary', 'hsx_recycle.order.search'])) return [];

        if ((string)($event['agent_key'] ?? '') === 'business.owner'
            && in_array('hsx_recycle.workflow.summary', $available, true)
            && preg_match('/经营(?:情况|数据|总览)|经营汇总|全店汇总|汇总.*经营/u', $prompt)) {
            $arguments = (new AiDefaultToolArgumentsRequested())->handle([
                'tool_key' => 'hsx_recycle.workflow.summary', 'prompt' => $prompt,
            ]);
            return $this->result('hsx_recycle.workflow.summary', (array)($arguments['arguments'] ?? []), 0.92, true);
        }

        $toolKey = '';
        $confidence = 0.0;
        if (preg_match('/待处理工作|待办|我的任务|工作量|流程.*(?:堵|卡)|(?:堵|卡).*环节|还有多少.*(?:签收|质检|定价|打款)/u', $prompt)) {
            $toolKey = 'hsx_recycle.workflow.summary';
            $confidence = 0.98;
        } elseif (preg_match('/订单|设备|明细|列表|哪(?:些|几)|查询.*(?:待签收|待质检|待确认|待打款)|IMEI|SN|串号|快递/u', $prompt)) {
            $toolKey = 'hsx_recycle.order.search';
            $confidence = 0.96;
        } elseif (preg_match('/回收.*(?:情况|进度|流程)|(?:签收|质检|定价|打款).*(?:多少|情况)/u', $prompt)) {
            $toolKey = 'hsx_recycle.workflow.summary';
            $confidence = 0.9;
        } elseif (preg_match('/刚才|这些|其中|详细|展开/u', $prompt)) {
            $previous = (string)($event['previous_tool_key'] ?? '');
            if ($previous === 'hsx_recycle.workflow.summary') {
                $toolKey = 'hsx_recycle.order.search';
                $confidence = 0.86;
            }
        }
        if ($toolKey === '' || !in_array($toolKey, $available, true)) return [];
        $arguments = (new AiDefaultToolArgumentsRequested())->handle(['tool_key' => $toolKey, 'prompt' => $prompt]);
        $resolvedArguments = (array)($arguments['arguments'] ?? []);
        if ($toolKey === 'hsx_recycle.order.search' && preg_match('/刚才|这些|其中|详细|展开/u', $prompt)) {
            $resolvedArguments = array_merge(
                $this->workflowDetailArguments((array)($event['previous_tool_result'] ?? [])),
                $resolvedArguments
            );
        }
        return $this->result($toolKey, $resolvedArguments, $confidence);
    }

    private function result(string $toolKey, array $arguments, float $confidence, bool $aggregate = false): array
    {
        return [
            'handled' => true,
            'tool_key' => $toolKey,
            'arguments' => $arguments,
            'confidence' => $confidence,
            'aggregate' => $aggregate,
            'source_plugin' => 'hsx_recycle',
        ];
    }

    private function workflowDetailArguments(array $snapshot): array
    {
        $pending = (array)($snapshot['data']['pending'] ?? []);
        if ($pending === []) return [];
        $statusMap = [
            'sign' => 'pending_sign',
            'check' => 'checking',
            'price' => 'checked',
            'confirm' => 'pending_confirm',
            'pay' => 'pending_payment',
        ];
        $bestStage = '';
        $bestCount = 0;
        foreach ($statusMap as $stage => $status) {
            $count = (int)($pending[$stage] ?? 0);
            if ($count > $bestCount) {
                $bestStage = $stage;
                $bestCount = $count;
            }
        }
        return $bestStage !== '' ? ['status' => $statusMap[$bestStage], 'limit' => 10] : [];
    }
}
