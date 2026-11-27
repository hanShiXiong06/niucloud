<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;
use addon\hsx_lead\app\service\cooperation\PaymentService;
final class CooperationPayCreate
{
    public function handle(array $event)
    {
        if (($event['trade_type'] ?? '') !== PaymentService::TRADE_TYPE) return null;
        return PaymentService::paymentInfo((int) $event['site_id'], (int) $event['trade_id']);
    }
}
