<?php
declare(strict_types=1);

namespace addon\phone_shop\app\job;

use addon\phone_shop\app\service\core\agent\AgentReferenceSyncService;
use core\base\BaseJob;

class AgentReferenceSyncTick extends BaseJob
{
    public function doJob(): void
    {
        (new AgentReferenceSyncService())->tick();
    }
}
