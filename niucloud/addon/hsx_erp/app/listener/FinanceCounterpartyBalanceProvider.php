<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\FinanceCounterpartyBalanceService;
use think\facade\Log;

/**
 * 查询往来账(谁欠谁多少) — 给回收"打款即折账"用。
 * 事件通道: GetFinanceCounterpartyBalance
 * payload: ['site_id'=>int, 'counterparty_id'=>int]
 * 返回: ['payable','receivable','offsetable','can_offset','net','net_direction']
 * 只读、故障隔离: 出错返回空账(全 0), 不打断回收流程。
 */
class FinanceCounterpartyBalanceProvider
{
    public function handle($event)
    {
        $payload = is_array($event) ? $event : (array)$event;
        $cpId = (int)($payload['counterparty_id'] ?? 0);
        $siteId = (int)($payload['site_id'] ?? 0);
        if ($cpId <= 0) {
            return null;
        }
        try {
            return (new FinanceCounterpartyBalanceService())->getCounterpartyBalance($cpId, $siteId > 0 ? $siteId : null);
        } catch (\Throwable $e) {
            Log::warning('[erp_finance] 查询往来账失败: ' . $e->getMessage());
            return [
                'counterparty_id' => $cpId, 'payable' => 0.0, 'receivable' => 0.0,
                'offsetable' => 0.0, 'can_offset' => false, 'net' => 0.0, 'net_direction' => 'none',
            ];
        }
    }
}
