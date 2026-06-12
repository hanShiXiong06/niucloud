<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\listener;

use addon\hsx_finance\app\service\core\CoreFinanceLedgerService;
use think\facade\Log;

/**
 * 监听"应收产生"事件(销售成交 → 财务)。
 * 通道(ThinkPHP事件名): FinanceReceivableCreated
 * 契约: finance.receivable.created.v1
 */
class ReceivableCreatedListener
{
    public function handle($event)
    {
        try {
            $payload = is_array($event) ? $event : (array)$event;
            return (new CoreFinanceLedgerService())->recordReceivable($payload);
        } catch (\Throwable $e) {
            Log::error('[hsx_finance] 记应收失败: ' . $e->getMessage());
            return 0;
        }
    }
}
