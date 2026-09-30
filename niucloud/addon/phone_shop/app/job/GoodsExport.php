<?php
declare(strict_types=1);

namespace addon\phone_shop\app\job;

use addon\phone_shop\app\service\admin\goods\GoodsTransferService;
use core\base\BaseJob;
use think\facade\Log;

class GoodsExport extends BaseJob
{
    public function doJob(int $taskId, int $siteId): bool
    {
        try {
            (new GoodsTransferService())->runExport($taskId, $siteId);
            return true;
        } catch (\Throwable $e) {
            Log::error('商城商品导出任务失败', ['task_id' => $taskId, 'site_id' => $siteId, 'message' => $e->getMessage()]);
            return false;
        }
    }
}
