<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\PromotionService;
use core\base\BaseJob;
final class SettlePromotion extends BaseJob
{
    public function doJob($data=[]): bool { PromotionService::tick(5);return true; }
}
