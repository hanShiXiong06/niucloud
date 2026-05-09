<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\job;

use addon\recycle_daheng_quote\app\service\admin\quotation_v2\SyncService;
use core\base\BaseJob;
use think\facade\Log;

class DahengQuoteAutoSync extends BaseJob
{
    public function doJob(array $params = []): void
    {
        try {
            $siteId = !empty($params['site_id']) ? (int)$params['site_id'] : null;
            $result = (new SyncService())->syncDueDatasets($siteId);
            Log::info('大亨速收报价自动同步完成', $result);
        } catch (\Throwable $e) {
            Log::error('大亨速收报价自动同步失败：' . $e->getMessage());
        }
    }
}
