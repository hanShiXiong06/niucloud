<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job;

use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelImportTaskService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 设备分类 Excel 异步导入
 */
class DeviceModelImport extends BaseJob
{
    public function doJob(int $taskId, int $siteId): bool
    {
        try {
            (new RecycleDeviceModelImportTaskService())->runTask($taskId, $siteId);
            return true;
        } catch (\Throwable $e) {
            Log::error('设备分类导入任务失败', [
                'task_id' => $taskId,
                'site_id' => $siteId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
