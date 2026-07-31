<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\listener;

use addon\hsx_ai\app\service\core\AiGatewayService;
use addon\hsx_ai\app\service\core\AiIntegrationService;

final class AiExecuteRequested
{
    public function handle(array $event): array
    {
        try {
            $sourcePlugin = trim((string)($event['source']['plugin'] ?? ''));
            if ($sourcePlugin !== '' && $sourcePlugin !== 'hsx_ai') {
                (new AiIntegrationService())->assertEnabled((int)($event['site_id'] ?? 0), $sourcePlugin);
            }
            $result = (new AiGatewayService())->execute((int)($event['site_id'] ?? 0), $event);
            return [
                'consumer' => 'hsx_ai',
                'status' => 'processed',
                'result' => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'consumer' => 'hsx_ai',
                'status' => 'failed',
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }
}
