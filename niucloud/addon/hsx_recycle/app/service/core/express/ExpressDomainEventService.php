<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use think\facade\Log;

/** 快递领域事件统一出口，供 ERP、通知、财务或未来独立快递插件订阅。 */
class ExpressDomainEventService
{
    public function dispatch(string $eventName, array $payload): void
    {
        $siteId = (int)($payload['site_id'] ?? 0);
        $businessKey = (string)($payload['third_order_no'] ?? $payload['order_no'] ?? $payload['delivery_id'] ?? '');
        $occurredAt = time();

        $event = [
            'event_id' => hash('sha256', $eventName . '|' . $siteId . '|' . $businessKey . '|' . json_encode($payload)),
            'event_name' => $eventName,
            'schema_version' => '1.0',
            'site_id' => $siteId,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
        ];

        try {
            event('HsxExpressDomainEvent', $event);
        } catch (\Throwable $e) {
            // 领域通知不能反向破坏已完成的第三方下单；后续可接统一 Outbox 做补偿。
            Log::error('快递领域事件派发失败：' . $e->getMessage(), [
                'event_id' => $event['event_id'],
                'event_name' => $eventName,
                'site_id' => $siteId,
            ]);
        }
    }
}
