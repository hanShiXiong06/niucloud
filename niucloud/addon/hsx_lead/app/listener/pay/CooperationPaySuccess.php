<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;
use addon\hsx_lead\app\service\cooperation\PaymentService;
final class CooperationPaySuccess
{
    public function handle(array $event)
    {
        if (($event['trade_type']??'')!==PaymentService::TRADE_TYPE) return true;
        return PaymentService::confirm($event);
    }
}
