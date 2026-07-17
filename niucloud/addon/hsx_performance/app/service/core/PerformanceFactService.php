<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

use addon\hsx_performance\app\model\PerformanceFact;

final class PerformanceFactService
{
    public function consume(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $eventId = mb_substr(trim((string)($event['event_id'] ?? '')), 0, 100);
        $employeeUid = (int)($event['employee_uid'] ?? $event['actor']['uid'] ?? 0);
        $actionKey = mb_substr(trim((string)($event['action_key'] ?? '')), 0, 80);
        if ($siteId <= 0 || $eventId === '' || $employeeUid <= 0 || $actionKey === '') {
            return ['consumer' => 'hsx_performance', 'status' => 'skipped', 'reason' => 'invalid_fact'];
        }
        $existing = PerformanceFact::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
        if (!$existing->isEmpty()) return ['consumer' => 'hsx_performance', 'status' => 'duplicate', 'fact_id' => (int)$existing->id];
        $fact = PerformanceFact::create([
            'site_id' => $siteId,
            'event_id' => $eventId,
            'source_plugin' => mb_substr(trim((string)($event['source_plugin'] ?? '')), 0, 50),
            'business_chain' => mb_substr(trim((string)($event['business_chain'] ?? '')), 0, 40),
            'action_key' => $actionKey,
            'employee_uid' => $employeeUid,
            'employee_name' => mb_substr(trim((string)($event['employee_name'] ?? $event['actor']['name'] ?? '')), 0, 60),
            'role_key' => mb_substr(trim((string)($event['role_key'] ?? '')), 0, 50),
            'business_type' => mb_substr(trim((string)($event['business_type'] ?? '')), 0, 50),
            'business_id' => mb_substr(trim((string)($event['business_id'] ?? '')), 0, 80),
            'business_no' => mb_substr(trim((string)($event['business_no'] ?? '')), 0, 80),
            'asset_id' => max(0, (int)($event['asset_id'] ?? 0)),
            'imei' => mb_substr(trim((string)($event['imei'] ?? '')), 0, 64),
            'quantity' => round((float)($event['quantity'] ?? 1), 2),
            'amount' => round((float)($event['amount'] ?? 0), 2),
            'profit' => round((float)($event['profit'] ?? 0), 2),
            'payload_json' => $event,
            'occurred_at' => max(1, (int)($event['occurred_at'] ?? time())),
            'create_at' => time(),
        ]);
        return ['consumer' => 'hsx_performance', 'status' => 'processed', 'fact_id' => (int)$fact->id];
    }
}
