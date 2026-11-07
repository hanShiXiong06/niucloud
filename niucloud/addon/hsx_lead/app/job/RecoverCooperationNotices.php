<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\CooperationNoticeOutbox;
use core\base\BaseJob;
final class RecoverCooperationNotices extends BaseJob
{
    public function doJob($data=[]): bool { CooperationNoticeOutbox::recover(5,20);return true; }
}
