<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\listener\audit;

use addon\hsx_recycle\app\service\core\audit\OperationAuditService;
use app\service\core\site\CoreSiteService;
use think\facade\Log;

final class BusinessOperationRecorded
{
    public function handle(array $event): void
    {
        $site=(int)($event['site_id']??0);
        try {
            if($site<=0 || !in_array('hsx_recycle',(new CoreSiteService())->getAddonKeysBySiteId($site),true)) return;
            (new OperationAuditService())->record($event);
        } catch(\Throwable $e) {
            // The accounting document is authoritative. Do not ask the user to pay or resubmit on log failure.
            Log::warning('回收统一操作记录未写入；请检查表结构及日志服务。site='.$site.' type='.get_class($e));
        }
    }
}
