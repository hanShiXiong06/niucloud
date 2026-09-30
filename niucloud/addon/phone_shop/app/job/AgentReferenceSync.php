<?php
declare(strict_types=1);

namespace addon\phone_shop\app\job;

use addon\phone_shop\app\service\core\agent\AgentReferenceSyncService;
use core\base\BaseJob;

class AgentReferenceSync extends BaseJob
{
    public function doJob(int $masterSiteId, int $agentSiteId, string $token): void
    {
        $service = new AgentReferenceSyncService();
        $info = $service->info($masterSiteId, $agentSiteId);
        if ($info['token'] !== $token) return;
        while (empty($info['result']['done'])) {
            $info = $service->step($masterSiteId, $agentSiteId, $token, (int)($info['result']['revision'] ?? 0));
        }
    }
}
