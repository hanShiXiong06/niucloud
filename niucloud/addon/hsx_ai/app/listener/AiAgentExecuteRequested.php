<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\listener;

use addon\hsx_ai\app\service\core\AiAgentService;
use addon\hsx_ai\app\service\core\AiIntegrationService;

/**
 * 业务插件的非流式智能体入口。调用方必须传入由服务端构建的 actor。
 */
final class AiAgentExecuteRequested
{
    public function handle(array $event): array
    {
        try {
            $siteId = (int)($event['site_id'] ?? 0);
            $sourcePlugin = trim((string)($event['source']['plugin'] ?? ''));
            if ($sourcePlugin !== '' && $sourcePlugin !== 'hsx_ai') {
                (new AiIntegrationService())->assertEnabled($siteId, $sourcePlugin);
            }
            $result = (new AiAgentService())->execute($siteId, $event, [
                'scene' => (string)($event['scene_key'] ?? 'general'),
                'actor' => (array)($event['actor'] ?? []),
                'conversation_id' => (int)($event['conversation_id'] ?? 0),
                'message_id' => (int)($event['message_id'] ?? 0),
                'approved_tool_calls' => (array)($event['approved_tool_calls'] ?? []),
            ]);
            return ['consumer' => 'hsx_ai', 'status' => 'processed', 'result' => $result];
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
