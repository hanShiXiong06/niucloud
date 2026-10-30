<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\notice;
use addon\hsx_lead\app\service\{NoticeConfigService,NoticeService};

final class TaskNoticeData
{
    public function handle(array $params): ?array
    {
        $phase=array_search((string)($params['key']??''),NoticeConfigService::PHASES,true);
        if ($phase===false) return null;
        $data=$params['data']??[];
        return NoticeService::templateData((int)$params['site_id'],(int)($data['task_id']??0),$phase,(int)($data['notice_audit_id']??0));
    }
}

