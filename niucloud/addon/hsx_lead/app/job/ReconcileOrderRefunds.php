<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\PaymentRefundService;
use core\base\BaseJob;

/** Query persisted original refunds only. Never initiates or retries a transfer. */
final class ReconcileOrderRefunds extends BaseJob
{
    public function doJob($data=[]): bool { PaymentRefundService::tick(20);return true; }
}
