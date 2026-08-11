<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\job\schedule;

use addon\hsx_marketing\app\service\core\MarketingTickService;
use core\base\BaseJob;

final class MarketingTick extends BaseJob
{
    public function doJob(array $params = []): void
    {
        (new MarketingTickService())->tick();
    }
}
