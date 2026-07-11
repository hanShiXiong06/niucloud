<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

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
        if ((int)$event['event_version'] !== 1) {
            throw new \InvalidArgumentException('ERP领域事件版本尚未注册：v' . (int)$event['event_version']);
        }
        if (trim((string)$event['event_id']) === '') {
            throw new \InvalidArgumentException('ERP领域事件ID不能为空');
        }
        if ((int)$event['site_id'] <= 0 || (int)$event['aggregate_id'] <= 0 || (int)$event['occurred_at'] <= 0) {
            throw new \InvalidArgumentException('ERP领域事件站点、聚合根和发生时间必须有效');
        }
        if (!is_array($event['source']) || trim((string)($event['source']['plugin'] ?? '')) === '') {
            throw new \InvalidArgumentException('ERP领域事件必须声明来源插件');
        }
        if (!is_array($event['payload'])) {
            throw new \InvalidArgumentException('ERP领域事件payload必须为对象');
        }
        if (str_starts_with((string)$event['event_name'], 'erp.asset.') && (int)($event['payload']['asset_id'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('ERP设备领域事件必须携带asset_id');
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
