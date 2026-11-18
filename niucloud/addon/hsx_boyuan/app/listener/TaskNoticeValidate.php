<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;

use addon\hsx_boyuan\app\service\NoticeService;
use think\facade\Db;

/** Recheck queued reminders immediately before delivery; do not touch the source task. */
final class TaskNoticeValidate
{
    public function handle(array $event = []): array
    {
        if (($event['source_plugin'] ?? '') !== 'hsx_boyuan' || ($event['source_type'] ?? '') !== 'sync_task') return [];
        $site = (int)($event['site_id'] ?? 0);
        $id = (int)($event['source_id'] ?? 0);
        $status = $site > 0 && $id > 0
            ? Db::name('hsx_boyuan_task')->where('site_id', $site)->where('id', $id)->value('status')
            : null;
        $valid = in_array($status, NoticeService::REMIND_STATUSES, true);
        return ['valid' => $valid, 'reason' => $valid ? '' : '该博远任务当前无需处理，不再发送待办提醒'];
    }
}
