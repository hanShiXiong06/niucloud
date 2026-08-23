<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\ai;

use addon\hsx_erp\app\service\core\ai\AiErpReadService;

final class AiToolExecuteRequested
{
    public function handle(array $event): array
    {
        $toolKey = (string)($event['tool_key'] ?? '');
        $allowed = [
            'hsx_erp.stock.summary', 'hsx_erp.payable.summary', 'hsx_erp.payable.search',
            'hsx_erp.receivable.summary', 'hsx_erp.receivable.search',
            'hsx_erp.sales.summary', 'hsx_erp.sales.search',
        ];
        if (!in_array($toolKey, $allowed, true)) return [];
        $service = new AiErpReadService();
        $siteId = (int)($event['site_id'] ?? 0);
        $arguments = (array)($event['arguments'] ?? []);
        $actor = (array)($event['actor'] ?? []);
        if ($toolKey === 'hsx_erp.stock.summary') {
            $data = $service->stockSummary($siteId, $arguments, $actor);
        } elseif (str_starts_with($toolKey, 'hsx_erp.sales.')) {
            $data = str_ends_with($toolKey, '.summary')
                ? $service->salesSummary($siteId, $arguments, $actor)
                : $service->searchSales($siteId, $arguments, $actor);
        } else {
            $side = str_contains($toolKey, '.receivable.') ? 'receivable' : 'payable';
            $data = str_ends_with($toolKey, '.summary')
                ? $service->financeSummary($siteId, $side, $arguments, $actor)
                : $service->searchFinance($siteId, $side, $arguments, $actor);
        }
        return ['tool_key' => $toolKey, 'source_plugin' => 'hsx_erp', 'handled' => true, 'data' => $data];
    }
}
