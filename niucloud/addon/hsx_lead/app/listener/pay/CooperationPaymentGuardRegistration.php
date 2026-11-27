<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;
use addon\hsx_lead\app\middleware\CooperationPaymentGuard;
final class CooperationPaymentGuardRegistration
{
    public function handle($event = null): void
    {
        app()->middleware->controller(CooperationPaymentGuard::class);
    }
}
