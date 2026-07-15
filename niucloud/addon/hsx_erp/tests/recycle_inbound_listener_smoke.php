<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\listener\ErpDeviceInboundRequested;
use core\exception\CommonException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

class FakeRecycleInboundListener extends ErpDeviceInboundRequested
{
    public int $siteId = 100005;
    public array $created = [];
    public array $registeredConsignments = [];
    public array $existing = [];
    public array $inboxes = [];
    private int $nextId = 900;
    private int $nextInboxId = 10;

    protected function currentSiteId(): int
    {
        return $this->siteId;
    }

    protected function existingOrderId(int $siteId, string $requestId): int
    {
        return (int)($this->existing[$requestId] ?? 0);
    }

    protected function createPurchase(array $data): int
    {
        $this->created[] = $data;
        return ++$this->nextId;
    }

    protected function registerConsignment(array $event, array $device, array $item): array
    {
        $this->registeredConsignments[] = compact('event', 'device', 'item');
        return ['asset_id' => ++$this->nextId, 'created' => true];
    }

    protected function resolveBusinessSource(string $sourceType): ?array
    {
        return [
            'key' => $sourceType,
            'name' => str_contains($sourceType, 'recycle') ? '回收插件采购' : '合作方采购',
            'direction' => 'expense',
            'scene' => 'purchase',
            'source_plugin' => strstr($sourceType, '.', true) ?: 'hsx_recycle',
            'enabled' => 1,
        ];
    }

    protected function findInbox(int $siteId, string $eventId, bool $lock = false): ?array
    {
        return $this->inboxes[$siteId . ':' . $eventId] ?? null;
    }

    protected function createInbox(int $siteId, string $eventId, array $event, array $requestPayload): int
    {
        $id = ++$this->nextInboxId;
        $this->inboxes[$siteId . ':' . $eventId] = [
            'id' => $id,
            'status' => 'processing',
            'payload_json' => json_encode(['request' => $requestPayload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        return $id;
    }

    protected function markInboxProcessing(int $inboxId, array $requestPayload): void
    {
        foreach ($this->inboxes as &$inbox) {
            if ((int)$inbox['id'] !== $inboxId) continue;
            $inbox['status'] = 'processing';
            $inbox['payload_json'] = json_encode(['request' => $requestPayload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        unset($inbox);
    }

    protected function completeInbox(int $inboxId, array $requestPayload, array $result): void
    {
        foreach ($this->inboxes as &$inbox) {
            if ((int)$inbox['id'] !== $inboxId) continue;
            $inbox['status'] = 'processed';
            $inbox['payload_json'] = json_encode(['request' => $requestPayload, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        unset($inbox);
    }

    protected function recordFailedInbox(int $siteId, string $eventId, array $event, array $requestPayload, string $message): void
    {
        $key = $siteId . ':' . $eventId;
        $this->inboxes[$key] = [
            'id' => (int)($this->inboxes[$key]['id'] ?? ++$this->nextInboxId),
            'status' => 'failed',
            'payload_json' => json_encode(['request' => $requestPayload, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    protected function withinTransaction(callable $callback): array
    {
        return $callback();
    }
}

$counterpartyA = [
    'source_plugin' => 'niucloud', 'source_type' => 'member', 'source_id' => 88,
    'member_id' => 88, 'name' => '回收客户甲', 'mobile' => '13800000001',
];
$counterpartyB = [
    'source_plugin' => 'niucloud', 'source_type' => 'member', 'source_id' => 99,
    'name' => '回收客户乙', 'mobile' => '13800000002',
];
$device = static function (int $id, int $orderId, string $orderNo, array $counterparty, int $warehouseId, int $locationId): array {
    return [
        'source_id' => $orderId,
        'source_device_id' => $id,
        'source_order_no' => $orderNo,
        'imei' => 'IMEI-' . $id,
        'sn' => '',
        'model' => '测试手机' . $id,
        'capacity' => '256G',
        'color' => '黑色',
        'ownership_type' => 'owned',
        'purchase_cost' => 1000,
        'paid_amount' => 1000,
        'settlement_status' => 'paid',
        'counterparty' => $counterparty,
        'target_warehouse_id' => $warehouseId,
        'target_location_id' => $locationId,
        'suggested_sale_price' => 1200,
        'acquired_at' => 1783700000 + $id,
        'check_snapshot' => ['check_result' => '检测正常', 'check_remark' => '屏幕轻微划痕'],
    ];
};

$event = [
    'event_id' => 'recycle-inbound-100005-test-001',
    'event_name' => 'recycle.device.inbound_requested',
    'event_version' => 1,
    'site_id' => 100005,
    'occurred_at' => 1783709999,
    'targets' => ['self_erp'],
    'operator' => ['type' => 'staff', 'id' => 7, 'name' => '采购员'],
    'source' => ['plugin' => 'hsx_recycle', 'type' => 'recycle_device', 'id' => 0],
    'devices' => [
        $device(11, 101, 'RC20260711001', $counterpartyA, 1, 11),
        $device(12, 101, 'RC20260711001', $counterpartyA, 2, 22),
        $device(13, 102, 'RC20260711002', $counterpartyB, 1, 11),
    ],
];

$listener = new FakeRecycleInboundListener();
$result = $listener->handle($event);
$assert(($result['consumer'] ?? '') === 'hsx_erp', '返回值必须声明hsx_erp消费者');
$assert(($result['target'] ?? '') === 'self_erp', '返回值必须声明self_erp目标');
$assert(($result['status'] ?? '') === 'processed', '首次消费状态必须为processed');
$assert(count($result['order_ids'] ?? []) === 2, '三个设备必须按来源订单和往来主体拆成两张采购单');
$assert(($result['created_count'] ?? 0) === 3, '首次消费必须报告三台新增设备');
$assert(count($listener->created) === 2, '监听器必须分组调用两次采购服务');

$first = $listener->created[0];
$assert(($first['origin_plugin'] ?? '') === 'hsx_recycle', '采购单必须保存回收来源插件');
$assert(($first['origin_type'] ?? '') === 'hsx_recycle.recycle_purchase', '采购单必须保存回收采购来源类型');
$assert(($first['origin_no'] ?? '') === 'RC20260711001' && ($first['source_order_no'] ?? '') === 'RC20260711001', '采购单必须保存原回收单号');
$assert(($first['origin_event_id'] ?? '') === $event['event_id'], '采购单必须保存原始事件ID');
$assert(($first['party_name'] ?? '') === '回收客户甲', '采购单必须按来源往来主体建单');
$assert(($first['member_id'] ?? 0) === 88, '回收会员ID必须传给ERP并绑定既有会员主体');
$assert(($first['contact_mobile'] ?? '') === '13800000001', '回收会员手机号必须写入ERP主体联系方式');
$assert(($first['paid_amount'] ?? -1) === 0, '缺少ERP账户时不得伪造已付款事实');
$assert(count($first['items'] ?? []) === 2, '同来源订单和往来主体的设备必须合并建单');
$assert(($first['items'][0]['warehouse_id'] ?? 0) === 1 && ($first['items'][0]['location_id'] ?? 0) === 11, '第一台设备必须保留自己的仓库库位');
$assert(($first['items'][1]['warehouse_id'] ?? 0) === 2 && ($first['items'][1]['location_id'] ?? 0) === 22, '第二台设备必须保留自己的仓库库位');
$assert(($first['items'][0]['spec_json']['source_device_id'] ?? 0) === 11, '设备快照必须保留原回收设备行ID');
$assert(strlen((string)$first['request_id']) <= 80 && str_starts_with((string)$first['request_id'], $event['event_id']), '分组request_id必须由事件ID派生且符合长度约束');
$assert($listener->created[0]['request_id'] !== $listener->created[1]['request_id'], '不同分组必须使用不同request_id');
$assert(($listener->inboxes['100005:' . $event['event_id']]['status'] ?? '') === 'processed', '完整入库事件必须写入processed收件箱');

$createdBeforeReplay = count($listener->created);
$inboxDuplicate = $listener->handle($event);
$assert(($inboxDuplicate['status'] ?? '') === 'duplicate', '相同event_id必须由顶层inbox返回duplicate');
$assert(count($listener->created) === $createdBeforeReplay, 'inbox重复事件不得再次进入任何采购分组');

$collision = $event;
$collision['devices'][] = $device(14, 103, 'RC20260711003', $counterpartyA, 1, 11);
$collisionRejected = false;
try {
    $listener->handle($collision);
} catch (CommonException $e) {
    $collisionRejected = str_contains($e->getMessage(), 'event_id');
}
$assert($collisionRejected, '同event_id增加新分组必须拒绝，不能追加采购单');

$duplicate = new FakeRecycleInboundListener();
$duplicate->existing = array_combine(
    array_column($listener->created, 'request_id'),
    $result['order_ids']
);
$duplicateResult = $duplicate->handle($event);
$assert(($duplicateResult['status'] ?? '') === 'duplicate', '相同事件重放必须返回duplicate');
$assert(($duplicateResult['existing_count'] ?? 0) === 3, '相同事件重放必须报告三台已存在设备');
$assert($duplicate->created === [], '相同事件重放不得再次调用采购创建');

$otherTarget = $event;
$otherTarget['targets'] = ['third_party_erp'];
$assert((new FakeRecycleInboundListener())->handle($otherTarget) === null, '未指定self_erp时监听器不得认领事件');

$siteMismatch = new FakeRecycleInboundListener();
$siteMismatch->siteId = 100006;
$rejected = false;
try {
    $siteMismatch->handle($event);
} catch (CommonException $e) {
    $rejected = str_contains($e->getMessage(), '站点');
}
$assert($rejected, '事件站点与当前请求站点不一致时必须拒绝处理');

$genericEvent = $event;
$genericEvent['event_id'] = 'partner-purchase-inbound-001';
$genericEvent['event_name'] = 'erp.device.inbound_requested';
$genericEvent['source'] = ['plugin' => 'partner_purchase', 'plugin_name' => '合作采购', 'type' => 'partner_purchase.device_purchase', 'source_name' => '合作方采购'];
$genericEvent['channel'] = ['code' => 'partner_api', 'name' => '合作方接口'];
$genericListener = new FakeRecycleInboundListener();
$genericResult = $genericListener->handle($genericEvent);
$assert(($genericResult['status'] ?? '') === 'processed', '注册采购来源的其它插件必须可复用标准入库Hook');
$assert(($genericListener->created[0]['origin_plugin'] ?? '') === 'partner_purchase', '标准入库Hook不得把来源硬编码为回收插件');
$assert(($genericListener->created[0]['origin_type'] ?? '') === 'partner_purchase.device_purchase', '标准入库Hook必须保留插件注册来源类型');
$assert(($genericListener->created[0]['purchase_channel'] ?? '') === '合作方接口', '标准入库Hook必须保留独立业务渠道');

$consignmentEvent = $event;
$consignmentEvent['event_id'] = 'recycle-consignment-inbound-001';
$consignmentDevice = $device(21, 201, 'RC20260711021', $counterpartyA, 3, 33);
$consignmentDevice['ownership_type'] = 'consign';
$consignmentDevice['purchase_cost'] = 0;
$consignmentDevice['paid_amount'] = 0;
$consignmentEvent['devices'] = [$consignmentDevice];
$consignmentListener = new FakeRecycleInboundListener();
$consignmentResult = $consignmentListener->handle($consignmentEvent);
$assert(($consignmentResult['status'] ?? '') === 'processed', '代卖设备首次登记必须返回processed');
$assert(($consignmentResult['order_ids'] ?? []) === [], '代卖登记不得生成ERP采购单');
$assert(count($consignmentResult['consignment_asset_ids'] ?? []) === 1, '代卖登记必须返回客户物权资产ID');
$assert($consignmentListener->created === [], '代卖登记不得调用采购创建服务');
$assert(count($consignmentListener->registeredConsignments) === 1, '代卖设备必须进入独立登记服务');

$eventConfig = require dirname(__DIR__) . '/app/event.php';
$listeners = (array)($eventConfig['listen']['ErpDeviceInboundRequested'] ?? []);
$assert(in_array('addon\hsx_erp\app\listener\ErpDeviceInboundRequested', $listeners, true), 'app/event.php必须注册回收入库监听器');

$listenerSource = (string)file_get_contents(dirname(__DIR__) . '/app/listener/ErpDeviceInboundRequested.php');
$assert(str_contains($listenerSource, 'withinTransaction') && str_contains($listenerSource, 'completeInbox'), '整次设备入库必须以顶层事务和Inbox保证原子幂等');
$assert(!str_contains($listenerSource, "!== 'hsx_recycle'"), 'ERP入库核心不得只认某一个具体插件');

$recycleSyncSource = (string)file_get_contents(dirname(__DIR__, 2) . '/hsx_recycle/app/service/admin/order/RecycleDeviceErpSyncService.php');
$assert(str_contains($recycleSyncSource, 'memberSnapshot') && str_contains($recycleSyncSource, "['nickname']"), '回收插件必须按member_id读取nickname/mobile生成ERP主体快照');
$purchaseSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpPurchaseService.php');
$assert(str_contains($purchaseSource, 'ErpPartyMember') && str_contains($purchaseSource, 'bindPartyMember'), 'ERP采购入库必须把来源会员稳定绑定到往来主体');

echo "[PASS] ERP recycle inbound listener smoke test\n";
