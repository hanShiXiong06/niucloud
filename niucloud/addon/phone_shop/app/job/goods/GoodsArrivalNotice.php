<?php
declare(strict_types=1);

namespace addon\phone_shop\app\job\goods;

use addon\phone_shop\app\service\core\goods\CoreGoodsArrivalService;
use core\base\BaseJob;

class GoodsArrivalNotice extends BaseJob
{
    public function doJob(int $siteId, int $taskId): void
    {
        (new CoreGoodsArrivalService())->run($siteId, $taskId);
    }
}
