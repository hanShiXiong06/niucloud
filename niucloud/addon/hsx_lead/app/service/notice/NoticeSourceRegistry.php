<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;
use addon\hsx_lead\app\service\NoticePreflightException;
final class NoticeSourceRegistry
{
    private static function adapter(array $task): string
    {
        return match($task['source_type']??''){'member_account_log'=>AccountNoticeSource::class,'collaboration_assignment'=>CooperationResponseNotices::class,default=>throw new NoticePreflightException('未知通知来源，未发送')};
    }
    public static function verify(array $task): array { $adapter=self::adapter($task);return$adapter::verify($task); }
    public static function variables(array $task,array $source): array { $adapter=self::adapter($task);return$adapter::variables($task,$source); }
}
