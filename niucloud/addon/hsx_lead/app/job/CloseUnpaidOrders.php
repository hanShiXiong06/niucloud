<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\PaymentOrderLifecycle;
use core\base\BaseJob;
final class CloseUnpaidOrders extends BaseJob
{
    public function doJob($data=[]): bool { PaymentOrderLifecycle::tick(5,50);return true; }
}
