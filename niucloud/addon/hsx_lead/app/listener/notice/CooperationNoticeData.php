<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\notice;

use addon\hsx_lead\app\service\NoticeConfigService;
use addon\hsx_lead\app\service\cooperation\CooperationNoticeService;

/** Framework NoticeData payloads for merchant collaboration notifications. */
final class CooperationNoticeData
{
    public function handle(array $params): ?array
    {
        if (!in_array((string)($params['key']??''),NoticeConfigService::PHASES,true)) return null;
        $data=$params['data']??[];
        if (!is_array($data)) return null;
        $channel=(string)($data['channel']??$params['channel']??'weapp');
        $site=(int)($params['site_id']??0);
        $collaborationId=(int)($data['collaboration_id']??0);
        if ($site<=0 || $collaborationId<=0 || !in_array($channel,['weapp','wechat','sms'],true)) return null;
        return CooperationNoticeService::templateData($site,$collaborationId,(int)($data['notice_audit_id']??0),$channel);
    }
}
