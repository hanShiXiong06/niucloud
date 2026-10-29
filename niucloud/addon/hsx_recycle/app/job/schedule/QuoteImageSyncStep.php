<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job\schedule;

use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncService;
use core\base\BaseJob;

class QuoteImageSyncStep extends BaseJob
{
    public function doJob(int $site_id, string $run_id): void { (new QuoteImageSyncService())->step($site_id, $run_id); }
}
