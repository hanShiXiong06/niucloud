<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\CooperationNoticeOutbox;
use core\base\BaseJob;
final class DispatchCooperationNotice extends BaseJob
{
    public function doJob(array $data): bool { return CooperationNoticeOutbox::process((int)$data['site_id'],(int)$data['id']); }
}
