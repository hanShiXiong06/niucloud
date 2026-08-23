<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\ai;

/** 回收插件自行把快捷问题转换为业务查询参数，AI 中台不感知订单状态含义。 */
final class AiDefaultToolArgumentsRequested
{
    public function handle(array $event): array
    {
        $toolKey = (string)($event['tool_key'] ?? '');
        if (!in_array($toolKey, ['hsx_recycle.workflow.summary', 'hsx_recycle.order.search'], true)) return [];
        $prompt = trim((string)($event['prompt'] ?? ''));
        if ($toolKey === 'hsx_recycle.workflow.summary') {
            return $this->handled($toolKey, [
                'period' => $this->period($prompt),
                'mine' => preg_match('/我|我的|本人/u', $prompt) === 1
                    && preg_match('/全店|全部|全员|店里|门店/u', $prompt) !== 1,
            ]);
        }

        $arguments = ['limit' => 20];
        $statusMap = [
            '/待签收/u' => 'pending_sign',
            '/待质检|质检中/u' => 'checking',
            '/已质检/u' => 'checked',
            '/待确认/u' => 'pending_confirm',
            '/待打款|待付款/u' => 'pending_payment',
            '/已完成/u' => 'completed',
            '/已关闭/u' => 'closed',
            '/已取消/u' => 'cancelled',
        ];
        foreach ($statusMap as $pattern => $status) {
            if (preg_match($pattern, $prompt) === 1) {
                $arguments['status'] = $status;
                break;
            }
        }
        if (preg_match('/今天|今日/u', $prompt) === 1) {
            $arguments['start_date'] = date('Y-m-d');
            $arguments['end_date'] = date('Y-m-d');
        } elseif (preg_match('/昨天|昨日/u', $prompt) === 1) {
            $arguments['start_date'] = date('Y-m-d', strtotime('-1 day'));
            $arguments['end_date'] = $arguments['start_date'];
        }
        return $this->handled($toolKey, $arguments);
    }

    private function period(string $prompt): string
    {
        if (preg_match('/本月|这个月|月度/u', $prompt) === 1) return 'month';
        if (preg_match('/本周|这周|一周|周度/u', $prompt) === 1) return 'week';
        return 'today';
    }

    private function handled(string $toolKey, array $arguments): array
    {
        return [
            'handled' => true,
            'tool_key' => $toolKey,
            'source_plugin' => 'hsx_recycle',
            'arguments' => $arguments,
        ];
    }
}
