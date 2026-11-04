<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;
use addon\hsx_lead\app\service\cooperation\PromotionService;
final class PromotionRefundSuccess
{
    public function handle(array $data): bool { return PromotionService::refund($data); }
}
