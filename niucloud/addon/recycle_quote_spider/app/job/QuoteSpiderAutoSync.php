<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\job;

use addon\recycle_quote_spider\app\service\core\QuoteSyncService;
use core\base\BaseJob;
use think\facade\Log;

class QuoteSpiderAutoSync extends BaseJob
{
    public function doJob(array $params = []): void
    {
        try {
            $siteId = !empty($params['site_id']) ? (int)$params['site_id'] : null;
            $result = (new QuoteSyncService())->syncDueSources($siteId);
            Log::info('回收报价爬虫自动同步完成', $result);
        } catch (\Throwable $e) {
            Log::error('回收报价爬虫自动同步失败：' . $e->getMessage());
        }
    }
}
