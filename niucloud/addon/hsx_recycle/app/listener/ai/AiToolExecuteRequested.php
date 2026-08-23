<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\ai;

use addon\hsx_recycle\app\service\core\ai\AiRecycleReadService;

final class AiToolExecuteRequested
{
    public function handle(array $event): array
    {
        $toolKey = (string)($event['tool_key'] ?? '');
        if (!in_array($toolKey, ['hsx_recycle.workflow.summary', 'hsx_recycle.order.search'], true)) return [];
        $service = new AiRecycleReadService();
        $arguments = (array)($event['arguments'] ?? []);
        $data = $toolKey === 'hsx_recycle.workflow.summary'
            ? $service->workflowSummary((int)$event['site_id'], $arguments, (array)($event['actor'] ?? []))
            : $service->searchOrders((int)$event['site_id'], $arguments);
        return ['tool_key' => $toolKey, 'source_plugin' => 'hsx_recycle', 'handled' => true, 'data' => $data];
    }
}
