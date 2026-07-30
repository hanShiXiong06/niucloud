<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

use addon\hsx_performance\app\model\PerformanceAnomaly;

final class PerformanceAnomalyService
{
    public function record(int $siteId, string $eventId, string $type, string $message, array $payload = [], string $severity = 'error'): void
    {
        try {
            $where = [
                ['site_id', '=', max(0, $siteId)],
                ['event_id', '=', mb_substr($eventId, 0, 100)],
                ['anomaly_type', '=', mb_substr($type, 0, 50)],
                ['status', '=', 'open'],
            ];
            $existing = PerformanceAnomaly::where($where)->findOrEmpty();
            $now = time();
            if (!$existing->isEmpty()) {
                $existing->save([
                    'message' => mb_substr($message, 0, 500),
                    'payload_json' => $payload,
                    'severity' => mb_substr($severity, 0, 20),
                    'occurrence_count' => (int)$existing->occurrence_count + 1,
                    'update_at' => $now,
                ]);
                return;
            }
            PerformanceAnomaly::create([
                'site_id' => max(0, $siteId),
                'event_id' => mb_substr($eventId, 0, 100),
                'anomaly_type' => mb_substr($type, 0, 50),
                'severity' => mb_substr($severity, 0, 20),
                'message' => mb_substr($message, 0, 500),
                'payload_json' => $payload,
                'status' => 'open',
                'occurrence_count' => 1,
                'resolved_at' => 0,
                'create_at' => $now,
                'update_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // 数据库尚未升级时不能让异常记录本身掩盖原始业务错误。
        }
    }
}
