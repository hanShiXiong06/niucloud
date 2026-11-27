<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job;

use addon\hsx_recycle\app\service\admin\express\ExpressProductImportTaskService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 快递产品 Excel 异步导入。
 */
class ExpressProductImport extends BaseJob
{
    public function doJob(string $taskId, int $siteId): bool
    {
        try {
            (new ExpressProductImportTaskService())->runTask($taskId, $siteId);
            return true;
        } catch (\Throwable $e) {
            Log::error('快递产品导入任务失败', [
                'task_id' => $taskId,
                'site_id' => $siteId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
