<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\job;

use addon\hsx_erp\app\service\admin\ErpOpeningService;
use core\base\BaseJob;
use think\facade\Log;

class ErpOpeningImport extends BaseJob
{
    public function doJob(int $batchId, int $siteId, string $action = 'parse'): bool
    {
        try {
            (new ErpOpeningService())->run($batchId, $siteId, $action);
            return true;
        } catch (\Throwable $e) {
            Log::error('ERP期初建账后台任务失败', [
                'batch_id' => $batchId,
                'site_id' => $siteId,
                'action' => $action,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
