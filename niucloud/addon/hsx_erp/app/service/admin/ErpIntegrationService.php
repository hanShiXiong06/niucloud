<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpInboxEvent;
use addon\hsx_erp\app\model\ErpOutboxEvent;
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
}
