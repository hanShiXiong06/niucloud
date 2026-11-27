<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\PaymentOrderLifecycle;
use addon\hsx_lead\app\service\cooperation\ManualPaymentService;
use core\base\BaseJob;
final class CloseUnpaidOrders extends BaseJob
{
    /** A completed sweep is not proof that every original payment could be closed. */
    public function doJob($data = []): string
    {
        $manual = ManualPaymentService::tick(10);
        $result = PaymentOrderLifecycle::tick(5, 50);
        // CoreScheduleService persists string results verbatim; returning true
        // would hide blocked/uncertain orders behind a generic success message.
        return json_encode(['examined' => (int) $result['examined'], 'closed' => (int) $result['closed'],
            'reconciled' => (int) $result['reconciled'], 'blocked' => (int) $result['blocked'], 'manual_receipts' => $manual,
            'message' => 'closed为已核实关闭；reconciled为已收款且补齐业务；blocked为仍待核对，未强行取消或重复收费'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
