<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\listener;

use addon\hsx_ai\app\service\core\AiConfigService;
use addon\hsx_ai\app\service\core\AiIntegrationService;

final class AiCapabilityRequested
{
    public function handle(array $request): array
    {
        $siteId = (int)($request['site_id'] ?? 0);
        if ($siteId <= 0) return ['consumer' => 'hsx_ai', 'available' => false, 'scenes' => []];
        $config = (new AiConfigService())->get($siteId);
        $integrationKey = trim((string)($request['integration_key'] ?? $request['source_plugin'] ?? ''));
        $available = !empty($config['enabled']);
        if ($available && $integrationKey !== '') {
            $available = (new AiIntegrationService())->isEnabled($siteId, $integrationKey);
        }
        return [
            'consumer' => 'hsx_ai',
            'available' => $available,
            'integration_key' => $integrationKey,
            'scenes' => array_values(array_map(static fn(array $scene): array => [
                'key' => (string)$scene['key'],
                'name' => (string)$scene['name'],
                'enabled' => (int)$scene['enabled'],
                'response_mode' => (string)$scene['response_mode'],
            ], $config['scenes'])),
        ];
    }
}
