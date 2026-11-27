<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\ai;

/** ERP 插件自行把自然语言中的时间、范围和明细口径转换为工具参数。 */
final class AiDefaultToolArgumentsRequested
{
    public function handle(array $event): array
    {
        $toolKey = (string)($event['tool_key'] ?? '');
        if (!str_starts_with($toolKey, 'hsx_erp.')) return [];
        $prompt = trim((string)($event['prompt'] ?? ''));
        $mine = preg_match('/我|我的|本人/u', $prompt) === 1
            && preg_match('/全店|全部|全员|门店/u', $prompt) !== 1;
        $period = preg_match('/本月|这个月|月度/u', $prompt) ? 'month'
            : (preg_match('/本周|这周|一周|周度/u', $prompt) ? 'week' : 'today');

        if ($toolKey === 'hsx_erp.stock.summary') return $this->handled($toolKey, []);
        if (str_ends_with($toolKey, '.summary')) {
            return $this->handled($toolKey, str_contains($toolKey, '.sales.')
                ? ['period' => $period, 'mine' => $mine]
                : ['mine' => $mine]);
        }

        $arguments = ['mine' => $mine, 'limit' => 20];
        if (str_contains($toolKey, '.payable.') || str_contains($toolKey, '.receivable.')) $arguments['status'] = 'open';
        if (preg_match('/今天|今日/u', $prompt)) {
            $arguments['start_date'] = date('Y-m-d');
            $arguments['end_date'] = date('Y-m-d');
        } elseif (preg_match('/昨天|昨日/u', $prompt)) {
            $arguments['start_date'] = date('Y-m-d', strtotime('-1 day'));
            $arguments['end_date'] = $arguments['start_date'];
        }
        return $this->handled($toolKey, $arguments);
    }

    private function handled(string $toolKey, array $arguments): array
    {
        return ['handled' => true, 'tool_key' => $toolKey, 'source_plugin' => 'hsx_erp', 'arguments' => $arguments];
    }
}
