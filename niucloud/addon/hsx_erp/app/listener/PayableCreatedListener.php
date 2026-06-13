<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\core\CoreFinanceLedgerService;
use think\facade\Log;

/**
 * 监听"应付产生"事件(回收确认/定价 → 财务)。
 * 通道(ThinkPHP事件名): FinancePayableCreated
 * 契约: finance.payable.created.v1 (payload 见《应付与结算契约》)
 * 故障隔离: 落库异常只记日志, 不回抛打断业务流。
 */
class PayableCreatedListener
{
    public function handle($event)
    {
        try {
            $payload = is_array($event) ? $event : (array)$event;
            return (new CoreFinanceLedgerService())->recordPayable($payload);
        } catch (\Throwable $e) {
            Log::error('[erp_finance] 记应付失败: ' . $e->getMessage());
            return 0;
        }
    }
}
