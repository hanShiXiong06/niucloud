<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2;

use addon\recycle_daheng_quote\app\service\admin\quotation_v2\SyncService;
use core\base\BaseAdminController;

/**
 * 报价 2.0 同步
 */
class Sync extends BaseAdminController
{
    public function preview(int $datasetId)
    {
        return success((new SyncService())->preview($datasetId));
    }

    public function import(int $logId)
    {
        return success((new SyncService())->importFromPreview($logId));
    }

    public function syncNow(int $datasetId)
    {
        return success((new SyncService())->syncNow($datasetId));
    }
}
