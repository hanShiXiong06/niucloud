<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\job;

use addon\recycle_quote_spider\app\service\core\QuoteSyncService;
use core\base\BaseJob;
use think\facade\Log;

class QuoteSpiderManualSync extends BaseJob
{
    public function doJob(int $source_id, int $log_id): void
    {
        try {
            (new QuoteSyncService())->sync($source_id, 'manual', $log_id);
        } catch (\Throwable $e) {
            Log::error('回收报价爬虫手动同步失败：' . $e->getMessage());
        }
    }
}
