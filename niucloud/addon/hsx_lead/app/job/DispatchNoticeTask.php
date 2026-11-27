<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\notice\NoticeTaskRuntime;
use core\base\BaseJob;
final class DispatchNoticeTask extends BaseJob
{
    public function doJob(array $data): array
    {
        return (new NoticeTaskRuntime())->process((int) ($data['site_id'] ?? 0), (int) ($data['id'] ?? 0));
    }
}
