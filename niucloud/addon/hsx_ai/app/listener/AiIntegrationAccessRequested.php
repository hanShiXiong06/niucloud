<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\listener;

use addon\hsx_ai\app\service\core\AiIntegrationService;

final class AiIntegrationAccessRequested
{
    public function handle(array $event): array
    {
        $key = trim((string)($event['integration_key'] ?? ''));
        return [
            'consumer' => 'hsx_ai',
            'integration_key' => $key,
            'allowed' => $key !== '' && (new AiIntegrationService())->isEnabled((int)($event['site_id'] ?? 0), $key),
        ];
    }
}
