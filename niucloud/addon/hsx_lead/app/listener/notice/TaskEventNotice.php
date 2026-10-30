<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\notice;
use addon\hsx_lead\app\service\NoticeService;

final class TaskEventNotice
{
    public function handle(array $event): ?array
    {
        $action=(string)($event['action']??'');
        $phase=$action==='assigned' || $action==='assign'?'assigned':'changed';
        // Do not notify merchants about their own accept, followup or sale-submission clicks.
        if ($phase==='changed' && !in_array($action,['verify','cancel','close','rejected_by_manager'],true)) return null;
        $site=(int)($event['site_id']??0); $task=(int)($event['task_id']??0);
        if ($site<=0 || $task<=0) return null;
        $eventKey=(string)($event['event_key']??'');
        if ($eventKey==='') throw new \RuntimeException('A committed task notification needs a stable event_key');
        return (new NoticeService())->taskNotice($site,$task,$phase,$eventKey);
    }
}

