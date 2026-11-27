<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;
use addon\hsx_lead\app\service\cooperation\PaymentRefundService;
final class CooperationOrderRefundSuccess
{
    public function handle(array $event): bool{return PaymentRefundService::confirmed($event);}
}
