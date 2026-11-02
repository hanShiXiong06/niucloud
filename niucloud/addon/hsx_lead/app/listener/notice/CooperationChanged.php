<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\notice;
use addon\hsx_lead\app\service\cooperation\CooperationNoticeService;
final class CooperationChanged
{
    public function handle(array $event): void { (new CooperationNoticeService())->changed($event); }
}
