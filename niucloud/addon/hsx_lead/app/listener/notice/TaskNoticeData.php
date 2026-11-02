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
        $channel=(string)($data['channel']??$params['channel']??'weapp');
        if(!in_array($channel,['weapp','wechat','sms'],true))return null;
        if ((int)($data['collaboration_id']??0)>0) return \addon\hsx_lead\app\service\cooperation\CooperationNoticeService::templateData((int)$params['site_id'],(int)$data['collaboration_id'],(int)($data['notice_audit_id']??0),$channel);
        return NoticeService::templateData((int)$params['site_id'],(int)($data['task_id']??0),$phase,(int)($data['notice_audit_id']??0),$channel,(int)($data['attempt']??0));
    }
}

