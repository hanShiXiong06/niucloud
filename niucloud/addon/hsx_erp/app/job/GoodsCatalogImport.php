<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\job;

use addon\hsx_erp\app\service\admin\ErpGoodsCatalogImportTaskService;
use core\base\BaseJob;
use think\facade\Log;

/** ERP 商品目录 Excel 异步导入 */
class GoodsCatalogImport extends BaseJob
{
    public function doJob(int $taskId, int $siteId): bool
    {
        try {
            (new ErpGoodsCatalogImportTaskService())->runTask($taskId, $siteId);
            return true;
        } catch (\Throwable $e) {
            Log::error('ERP商品目录导入任务失败', [
                'task_id' => $taskId,
                'site_id' => $siteId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
