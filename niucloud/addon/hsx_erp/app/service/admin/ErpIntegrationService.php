<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpInboxEvent;
use addon\hsx_erp\app\model\ErpOutboxEvent;
use addon\hsx_erp\app\support\ErpDomainEvent;
use core\base\BaseAdminService;

/**
 * ERP 对外集成边界。
 *
 * 未来 AI、中台、商城、回收等插件都从这里进入或订阅 ERP 事实。
 * 当前第一阶段只落事件记录，不把旧插件实现耦合进 ERP 核心。
 */
class ErpIntegrationService extends BaseAdminService
{
    public function recordInbox(array $payload): int
    {
        $now = time();
        $row = ErpInboxEvent::create([
            'site_id' => $this->site_id,
            'event_id' => (string)($payload['event_id'] ?? ''),
            'source_plugin' => (string)($payload['source_plugin'] ?? ''),
            'event_name' => (string)($payload['event_name'] ?? ''),
            'payload_json' => json_encode($payload['payload'] ?? [], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'occurred_at' => (int)($payload['occurred_at'] ?? $now),
            'create_at' => $now,
            'update_at' => $now,
        ]);
        return (int)$row->id;
    }

    public function publishOutbox(string $eventName, array $payload): int
    {
        $now = time();
        $row = ErpOutboxEvent::create([
            'site_id' => $this->site_id,
            'event_id' => ErpLedgerService::makeNo('EV'),
            'event_name' => $eventName,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'occurred_at' => $now,
            'create_at' => $now,
            'update_at' => $now,
        ]);
        return (int)$row->id;
    }

    /**
     * 在当前业务事务内写入领域事件发件箱。
     *
     * 调用方可在事务提交后执行 dispatchDomainEvent()；即使进程在提交后中断，
     * pending 记录仍然存在，不会出现“业务成功但事件事实丢失”。
     *
     * @return array{id:int,event_id:string}
     */
    public function enqueueDomainEvent(
        string $eventName,
        string $aggregateType,
        int $aggregateId,
        array $payload,
        array $source = [],
        array $requiredConsumers = []
    ): array
    {
        $now = time();
        $eventId = ErpLedgerService::makeNo('EV');
        $event = ErpDomainEvent::create(
            $this->site_id,
            $eventName,
            $eventId,
            $aggregateType,
            $aggregateId,
            ['type' => 'admin', 'id' => (int)$this->uid, 'name' => (string)$this->username],
            array_merge(['plugin' => 'hsx_erp', 'type' => $aggregateType, 'id' => $aggregateId], $source),
            $payload,
            $now
        );
        ErpDomainEvent::validate($event);
        $event['_delivery'] = [
            'required_consumers' => array_values(array_unique(array_filter(array_map('strval', $requiredConsumers)))),
            'attempts' => 0,
            'last_error' => '',
            'results' => [],
        ];
        $row = ErpOutboxEvent::create([
            'site_id' => $this->site_id, 'event_id' => $eventId, 'event_name' => $eventName,
            'payload_json' => json_encode($event, JSON_UNESCAPED_UNICODE), 'status' => 'pending',
            'occurred_at' => $now, 'create_at' => $now, 'update_at' => $now,
        ]);
        return ['id' => (int)$row->id, 'event_id' => $eventId];
    }

    /** 派发既有发件箱事件；重试始终复用原 event_id 与事实快照。 */
    public function dispatchDomainEvent(int $outboxId, int $siteId = 0): array
    {
        $siteId = $siteId > 0 ? $siteId : (int)$this->site_id;
        $row = ErpOutboxEvent::where([
            ['site_id', '=', $siteId],
            ['id', '=', $outboxId],
        ])->findOrEmpty();
        if ($row->isEmpty()) {
            return ['ok' => false, 'message' => '领域事件不存在'];
        }
        $stored = json_decode((string)$row->payload_json, true);
        if (!is_array($stored)) {
            $row->save(['status' => 'failed', 'update_at' => time()]);
            return ['ok' => false, 'event_id' => (string)$row->event_id, 'message' => '领域事件内容损坏'];
        }
        $delivery = (array)($stored['_delivery'] ?? []);
        $delivery['attempts'] = (int)($delivery['attempts'] ?? 0) + 1;
        $event = $stored;
        unset($event['_delivery']);
        $stored['_delivery'] = $delivery;
        $row->save([
            'status' => 'processing',
            'payload_json' => json_encode($stored, JSON_UNESCAPED_UNICODE),
            'update_at' => time(),
        ]);
        try {
            ErpDomainEvent::validate($event);
            $results = (array)event('ErpDomainEvent', $event);
            $processedConsumers = [];
            foreach ($results as $result) {
                if (is_array($result) && !empty($result['error'])) {
                    throw new \RuntimeException((string)($result['message'] ?? '插件监听器处理失败'));
                }
                if (!is_array($result)) continue;
                $consumer = trim((string)($result['consumer'] ?? ''));
                $status = (string)($result['status'] ?? '');
                if ($consumer !== '' && (in_array($status, ['processed', 'duplicate'], true) || ($status === '' && empty($result['skipped'])))) {
                    $processedConsumers[$consumer] = true;
                }
            }
            $required = array_values(array_unique(array_filter(array_map('strval', (array)($delivery['required_consumers'] ?? [])))));
            $missing = array_values(array_filter($required, static fn(string $consumer): bool => !isset($processedConsumers[$consumer])));
            if ($missing !== []) {
                throw new \RuntimeException('必需插件未接收事件：' . implode('、', $missing));
            }
            $delivery['last_error'] = '';
            $delivery['results'] = $results;
            $stored = $event;
            $stored['_delivery'] = $delivery;
            $row->save([
                'status' => 'done',
                'payload_json' => json_encode($stored, JSON_UNESCAPED_UNICODE),
                'update_at' => time(),
            ]);
            return [
                'ok' => true,
                'delivered' => $required === [] || $missing === [],
                'event_id' => (string)$row->event_id,
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            $delivery['last_error'] = $e->getMessage();
            $delivery['results'] = $results ?? [];
            $stored = $event;
            $stored['_delivery'] = $delivery;
            $row->save([
                'status' => 'failed',
                'payload_json' => json_encode($stored, JSON_UNESCAPED_UNICODE),
                'update_at' => time(),
            ]);
            return ['ok' => false, 'event_id' => (string)$row->event_id, 'message' => $e->getMessage()];
        }
    }

    /** 写入发件箱并立即派发，适合无需和其它业务写入共享事务的显式同步操作。 */
    public function publishDomainEvent(
        string $eventName,
        string $aggregateType,
        int $aggregateId,
        array $payload,
        array $source = [],
        array $requiredConsumers = []
    ): array {
        $queued = $this->enqueueDomainEvent($eventName, $aggregateType, $aggregateId, $payload, $source, $requiredConsumers);
        return $this->dispatchDomainEvent((int)$queued['id']);
    }

    /**
     * 计划任务补偿 pending/failed 事件，使用原事件ID重放并由消费端幂等兜底。
     * 连续失败十次后保留 failed 供人工排障，避免无上限重试污染日志。
     */
    public function retryPendingDomainEvents(int $siteId = 0, int $limit = 100): array
    {
        $query = ErpOutboxEvent::whereIn('status', ['pending', 'failed'])
            ->where('update_at', '<=', time() - 60);
        if ($siteId > 0) $query->where('site_id', '=', $siteId);
        $rows = $query->order('id asc')->limit(max(1, min(500, $limit)))->select()->toArray();
        $result = ['scanned' => count($rows), 'done' => 0, 'failed' => 0, 'dead' => 0];
        foreach ($rows as $row) {
            $stored = json_decode((string)($row['payload_json'] ?? ''), true);
            $attempts = (int)($stored['_delivery']['attempts'] ?? 0);
            if ($attempts >= 10) {
                $result['dead']++;
                continue;
            }
            $dispatch = $this->dispatchDomainEvent((int)$row['id'], (int)$row['site_id']);
            $result[!empty($dispatch['ok']) ? 'done' : 'failed']++;
        }
        return $result;
    }
}
