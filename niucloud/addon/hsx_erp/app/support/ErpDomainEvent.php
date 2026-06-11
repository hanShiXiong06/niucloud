<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use addon\hsx_erp\app\model\ErpOutboxEvent;

final class ErpDomainEvent
{
    public static function create(
        int $siteId,
        string $eventName,
        string $eventId,
        string $aggregateType,
        int $aggregateId,
        array $operator,
        array $source,
        array $payload,
        int $occurredAt
    ): array {
        return [
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_version' => self::version($eventName),
            'site_id' => $siteId,
            'occurred_at' => $occurredAt,
            'operator' => [
                'type' => (string)($operator['type'] ?? 'system'),
                'id' => (int)($operator['id'] ?? 0),
                'name' => (string)($operator['name'] ?? ''),
            ],
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'source' => [
                'plugin' => (string)($source['plugin'] ?? 'hsx_erp'),
                'type' => (string)($source['type'] ?? ''),
                'id' => (int)($source['id'] ?? 0),
            ],
            'payload' => $payload,
        ];
    }

    public static function writeOutbox(array $event, int $now): ErpOutboxEvent
    {
        return ErpOutboxEvent::create([
            'site_id' => (int)$event['site_id'],
            'event_id' => (string)$event['event_id'],
            'event_name' => (string)$event['event_name'],
            'aggregate_type' => (string)$event['aggregate_type'],
            'aggregate_id' => (int)$event['aggregate_id'],
            'status' => 'pending',
            'payload' => $event,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    public static function validate(array $event): void
    {
        foreach ([
            'event_id', 'event_name', 'event_version', 'site_id', 'occurred_at',
            'operator', 'aggregate_type', 'aggregate_id', 'source', 'payload',
        ] as $field) {
            if (!array_key_exists($field, $event)) {
                throw new \InvalidArgumentException('ERP领域事件缺少字段：' . $field);
            }
        }
        if ((int)$event['event_version'] !== self::version((string)$event['event_name'])) {
            throw new \InvalidArgumentException('ERP领域事件版本不受支持');
        }
    }

    private static function version(string $eventName): int
    {
        if (!preg_match('/\.v(\d+)$/', $eventName, $matches)) {
            throw new \InvalidArgumentException('ERP领域事件名称必须包含版本号');
        }
        return (int)$matches[1];
    }
}
