<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\job;

use addon\hsx_member_card\app\service\admin\MemberCardMigrationService;
use core\base\BaseJob;
use think\facade\Log;

final class MemberCardMigrationImport extends BaseJob
{
    public function doJob(int $taskId, int $siteId): bool
    {
        try {
            (new MemberCardMigrationService())->run($taskId, $siteId);
            return true;
        } catch (\Throwable $e) {
            Log::error('会员卡期初同步后台任务失败', [
                'task_id' => $taskId,
                'site_id' => $siteId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
