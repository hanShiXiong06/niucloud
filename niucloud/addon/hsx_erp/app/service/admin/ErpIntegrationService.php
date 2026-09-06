<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\listener\ErpDeviceInboundRequested;
use addon\hsx_erp\app\listener\ErpSaleCreatedRequested;
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
    private const DELIVERY_LEASE_SECONDS = 300;
    private const MAX_AUTOMATIC_ATTEMPTS = 10;

    public static function forSite(int $siteId, int $operatorUid = 0, string $operatorName = '系统补偿'): self
    {
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $operatorUid;
        $service->username = $operatorName;
        return $service;
    }

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
        $snapshot = $row->toArray();
        $stored = json_decode((string)$row->payload_json, true);
        if ((string)$row->status === 'done') {
            return ['ok' => true, 'duplicate' => true, 'event_id' => (string)$row->event_id, 'results' => $stored['_delivery']['results'] ?? []];
        }
        $now = time();
        if ((string)$row->status === 'processing' && (int)$row->update_at > $now - self::DELIVERY_LEASE_SECONDS) {
            return ['ok' => false, 'busy' => true, 'event_id' => (string)$row->event_id, 'message' => '领域事件正在投递，请稍后查看结果'];
        }
        if (!is_array($stored)) {
            $this->updateOutboxIfCurrent($snapshot, ['status' => 'failed', 'update_at' => $now]);
            return ['ok' => false, 'event_id' => (string)$row->event_id, 'message' => '领域事件内容损坏'];
        }
        $delivery = (array)($stored['_delivery'] ?? []);
        $delivery['attempts'] = (int)($delivery['attempts'] ?? 0) + 1;
        // 原子领取和租约都只使用既有字段；超时重放仍复用原 event_id，由消费者幂等兜底。
        $delivery['lease_token'] = bin2hex(random_bytes(16));
        $event = $stored;
        unset($event['_delivery']);
        $stored['_delivery'] = $delivery;
        $claim = [
            'status' => 'processing',
            'payload_json' => json_encode($stored, JSON_UNESCAPED_UNICODE),
            'update_at' => $now,
        ];
        if (!$this->updateOutboxIfCurrent($snapshot, $claim)) {
            return ['ok' => false, 'busy' => true, 'event_id' => (string)$row->event_id, 'message' => '领域事件已由其他任务领取'];
        }
        $claimedSnapshot = array_merge($snapshot, $claim);
        try {
            ErpDomainEvent::validate($event);
            $results = (array)event('ErpDomainEvent', $event);
            $processedConsumers = [];
            foreach ($results as $result) {
                if (is_array($result) && (!empty($result['error']) || (string)($result['status'] ?? '') === 'failed')) {
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
            unset($delivery['lease_token']);
            $stored = $event;
            $stored['_delivery'] = $delivery;
            if (!$this->updateOutboxIfCurrent($claimedSnapshot, [
                'status' => 'done',
                'payload_json' => json_encode($stored, JSON_UNESCAPED_UNICODE),
                'update_at' => time(),
            ])) {
                return ['ok' => false, 'stale' => true, 'event_id' => (string)$row->event_id, 'message' => '投递租约已被接管，未覆盖新任务结果'];
            }
            return [
                'ok' => true,
                'delivered' => $required === [] || $missing === [],
                'event_id' => (string)$row->event_id,
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            $delivery['last_error'] = $e->getMessage();
            $delivery['results'] = $results ?? [];
            unset($delivery['lease_token']);
            $stored = $event;
            $stored['_delivery'] = $delivery;
            if (!$this->updateOutboxIfCurrent($claimedSnapshot, [
                'status' => 'failed',
                'payload_json' => json_encode($stored, JSON_UNESCAPED_UNICODE),
                'update_at' => time(),
            ])) {
                return ['ok' => false, 'stale' => true, 'event_id' => (string)$row->event_id, 'message' => '投递租约已被接管，未覆盖新任务结果'];
            }
            return ['ok' => false, 'event_id' => (string)$row->event_id, 'message' => $e->getMessage()];
        }
    }

    /** 比较领取前的完整快照；同秒并发也不能重复领取或覆盖其他租约的结果。 */
    private function updateOutboxIfCurrent(array $snapshot, array $values): bool
    {
        return (int)ErpOutboxEvent::where([
            ['site_id', '=', (int)$snapshot['site_id']],
            ['id', '=', (int)$snapshot['id']],
            ['status', '=', (string)$snapshot['status']],
            ['update_at', '=', (int)$snapshot['update_at']],
            ['payload_json', '=', $snapshot['payload_json']],
        ])->update($values) === 1;
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
     * 计划任务补偿 pending/failed 和租约超时的 processing，使用原事件ID幂等重放。
     * 连续失败十次后保留 failed 供人工排障，避免无上限重试污染日志。
     */
    public function retryPendingDomainEvents(int $siteId = 0, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $result = ['scanned' => 0, 'done' => 0, 'failed' => 0, 'dead' => 0];
        $cursor = 0;
        $handled = 0;
        $now = time();
        while ($handled < $limit) {
            $query = ErpOutboxEvent::whereIn('status', ['pending', 'failed', 'processing'])
                ->where('update_at', '<=', $now - 60)->where('id', '>', $cursor);
            if ($siteId > 0) $query->where('site_id', '=', $siteId);
            $rows = $query->order('id asc')->limit($limit)->select()->toArray();
            if ($rows === []) break;
            foreach ($rows as $row) {
                $cursor = (int)$row['id'];
                $result['scanned']++;
                if ((string)$row['status'] === 'processing' && (int)$row['update_at'] > $now - self::DELIVERY_LEASE_SECONDS) continue;
                $stored = json_decode((string)($row['payload_json'] ?? ''), true);
                $attempts = (int)($stored['_delivery']['attempts'] ?? 0);
                if (!is_array($stored) || $attempts >= self::MAX_AUTOMATIC_ATTEMPTS) {
                    // 保留失败事实供人工处理，但用游标越过，不占用后续可重试事件的额度。
                    if ((string)$row['status'] !== 'failed') {
                        $values = ['status' => 'failed', 'update_at' => $now];
                        if (is_array($stored)) {
                            $stored['_delivery']['last_error'] = '投递未完成且已达到自动重试上限，请人工检查后重试';
                            unset($stored['_delivery']['lease_token']);
                            $values['payload_json'] = json_encode($stored, JSON_UNESCAPED_UNICODE);
                        }
                        $this->updateOutboxIfCurrent($row, $values);
                    }
                    $result['dead']++;
                    continue;
                }
                $dispatch = $this->dispatchDomainEvent((int)$row['id'], (int)$row['site_id']);
                if (!empty($dispatch['busy']) || !empty($dispatch['stale'])) continue;
                $result[!empty($dispatch['ok']) ? 'done' : 'failed']++;
                if (++$handled >= $limit) break;
            }
        }
        return $result;
    }

    /**
     * 补偿“外部系统已完成、ERP消费失败”的高价值入站事实。
     *
     * 回收入库、商城付款和退款都属于已发生的外部事实：来源成功后即使
     * ERP 短暂不可用，也必须继续重试入库、入账、冲账和库存恢复。消费端使用
     * 原 event_id 幂等，重放不会重复生成单据或流水。连续失败十次后保留
     * failed 交由人工排障，避免无限重试。
     */
    public function retryFailedExternalRequests(int $siteId = 0, int $limit = 100): array
    {
        $handlers = [
            'recycle.device.inbound_requested' => static fn(int $siteId, array $request): array => ErpDeviceInboundRequested::forSite($siteId)->handle($request) ?? [],
            'erp.device.inbound_requested' => static fn(int $siteId, array $request): array => ErpDeviceInboundRequested::forSite($siteId)->handle($request) ?? [],
            'erp.sale.created_requested' => static fn(int $siteId, array $request): array => ErpSaleCreatedRequested::forSite($siteId)->handle($request) ?? [],
            ErpExternalSaleRecordedService::EVENT_NAME => static fn(int $siteId, array $request): array => ErpExternalSaleRecordedService::forSite($siteId)->consume($request),
            ErpExternalSaleRefundedService::EVENT_NAME => static fn(int $siteId, array $request): array => ErpExternalSaleRefundedService::forSite($siteId)->consume($request),
        ];
        $limit = max(1, min(500, $limit));
        $result = ['scanned' => 0, 'done' => 0, 'failed' => 0, 'dead' => 0];
        $cursor = 0;
        $handled = 0;
        $cutoff = time() - 60;
        while ($handled < $limit) {
            $query = ErpInboxEvent::where([
                ['status', '=', 'failed'],
                ['update_at', '<=', $cutoff],
                ['id', '>', $cursor],
            ])->whereIn('event_name', array_keys($handlers));
            if ($siteId > 0) $query->where('site_id', '=', $siteId);
            $rows = $query->order('id asc')->limit($limit)->select()->toArray();
            if ($rows === []) break;
            foreach ($rows as $row) {
                $cursor = (int)$row['id'];
                $result['scanned']++;
                $stored = json_decode((string)($row['payload_json'] ?? ''), true);
                $request = is_array($stored) && is_array($stored['request'] ?? null) ? $stored['request'] : [];
                $attempts = (int)($stored['_retry']['attempts'] ?? 0);
                if ($attempts >= self::MAX_AUTOMATIC_ATTEMPTS || $request === []) {
                    $result['dead']++;
                    continue;
                }
                try {
                    $handlers[(string)$row['event_name']]((int)$row['site_id'], $request);
                    $result['done']++;
                } catch (\Throwable) {
                    // consume() 已刷新失败次数和错误原因；调度摘要只统计结果。
                    $result['failed']++;
                }
                if (++$handled >= $limit) break;
            }
        }
        return $result;
    }
}
