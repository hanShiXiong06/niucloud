<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job\schedule;

use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncService;
use core\base\BaseJob;

class QuoteImageSyncTick extends BaseJob
{
    public function doJob(array $params = []): string
    {
        // 框架把返回值直接写入计划任务日志的 TEXT 字段，不能返回关联数组。
        return json_encode((new QuoteImageSyncService())->tick(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
