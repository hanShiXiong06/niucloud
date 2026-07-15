<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpInboxEvent;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\service\admin\ErpConfigService;
use addon\hsx_erp\app\service\admin\ErpConsignmentInboundService;
use addon\hsx_erp\app\service\admin\ErpPurchaseService;
use addon\hsx_erp\app\support\ErpIdempotency;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 消费回收插件的标准设备入库请求，并统一落为 ERP 采购、库存和设备级应付事实。
 *
 * 事件在后台请求内同步派发。BaseAdminService 没有安全的站点切换能力，因此本监听器
 * 只允许消费与当前请求站点一致的事件，绝不通过 request()->siteId($id) 篡改上下文。
 */
class ErpDeviceInboundRequested
{
    public function handle($event): ?array
    {
        if (!is_array($event)) {
            throw new CommonException('ERP入库事件格式不正确');
        }

        $targets = array_values(array_unique(array_filter(array_map('strval', (array)($event['targets'] ?? [])))));
        if (!in_array('self_erp', $targets, true)) {
            return null;
        }

        $source = (array)($event['source'] ?? []);
        $sourcePlugin = trim((string)($source['plugin'] ?? $event['source_plugin'] ?? ''));
        if ($sourcePlugin === '') {
            throw new CommonException('ERP入库事件缺少来源插件');
        }
        $eventName = trim((string)($event['event_name'] ?? ''));
        $legacyRecycleEvent = $eventName === 'recycle.device.inbound_requested';
        if (!$legacyRecycleEvent && $eventName !== 'erp.device.inbound_requested') {
            throw new CommonException('ERP不支持该设备入库事件');
        }
        if ((int)($event['event_version'] ?? 0) !== 1) {
            throw new CommonException('ERP不支持该设备入库事件版本');
        }
        $sourceType = trim((string)($source['type'] ?? $event['source_type'] ?? ''));
        if ($legacyRecycleEvent && $sourcePlugin === 'hsx_recycle') {
            $sourceType = 'hsx_recycle.recycle_purchase';
        } elseif ($sourceType === '') {
            throw new CommonException('ERP入库事件缺少业务来源类型');
        } elseif (!str_starts_with($sourceType, $sourcePlugin . '.') && !str_starts_with($sourceType, $sourcePlugin . '_')) {
            $sourceType = $sourcePlugin . '.' . $sourceType;
        }
        $sourceOption = $this->resolveBusinessSource($sourceType);
        if (!$legacyRecycleEvent && (!$sourceOption || (string)($sourceOption['direction'] ?? '') !== 'expense' || (string)($sourceOption['scene'] ?? '') !== 'purchase')) {
            throw new CommonException('该插件未为当前站点注册可用的采购来源');
        }
        $event['_erp_source'] = [
            'plugin' => $sourcePlugin,
            'plugin_name' => trim((string)($source['plugin_name'] ?? $source['name'] ?? $sourceOption['plugin_name'] ?? '')) ?: $sourcePlugin,
            'type' => $sourceType,
            'name' => trim((string)($source['source_name'] ?? $sourceOption['name'] ?? '')) ?: '外部采购',
        ];

        $eventSiteId = (int)($event['site_id'] ?? 0);
        $currentSiteId = $this->currentSiteId();
        if ($eventSiteId <= 0 || $currentSiteId <= 0 || $eventSiteId !== $currentSiteId) {
            throw new CommonException('入库事件站点与当前请求站点不一致，已拒绝处理');
        }

        $eventId = ErpIdempotency::normalize($event['event_id'] ?? '');
        if ($eventId === '') {
            throw new CommonException('ERP入库事件缺少event_id');
        }

        $devices = array_values(array_filter((array)($event['devices'] ?? []), 'is_array'));
        if ($devices === []) {
            throw new CommonException('ERP入库事件没有设备');
        }

        $requestPayload = $this->canonicalRequest($event);
        $existing = $this->findInbox($currentSiteId, $eventId);
        if ($existing !== null) {
            $this->assertSameInboxRequest($existing, $requestPayload);
            if ($this->isProcessedInbox($existing)) return $this->duplicateResult($existing);
        }

        try {
            return $this->withinTransaction(function () use ($event, $devices, $eventId, $currentSiteId, $requestPayload): array {
                $locked = $this->findInbox($currentSiteId, $eventId, true);
                if ($locked !== null) {
                    $this->assertSameInboxRequest($locked, $requestPayload);
                    if ($this->isProcessedInbox($locked)) return $this->duplicateResult($locked);
                    $inboxId = (int)$locked['id'];
                    $this->markInboxProcessing($inboxId, $requestPayload);
                } else {
                    $inboxId = $this->createInbox($currentSiteId, $eventId, $event, $requestPayload);
                }

                // 顶层事务包住全部来源分组；任一采购失败时，所有采购、库存、应付和
                // inbox 状态一起回滚，杜绝“一次推送只成功一半”。
                $result = $this->processDevices($event, $devices, $eventId, $currentSiteId);
                $this->completeInbox($inboxId, $requestPayload, $result);
                return $result;
            });
        } catch (\Throwable $e) {
            $processed = $this->findInbox($currentSiteId, $eventId);
            if ($processed !== null) {
                $this->assertSameInboxRequest($processed, $requestPayload);
                if ($this->isProcessedInbox($processed)) return $this->duplicateResult($processed);
            }
            $this->recordFailedInbox($currentSiteId, $eventId, $event, $requestPayload, $e->getMessage());
            throw $e;
        }
    }

    protected function processDevices(array $event, array $devices, string $eventId, int $currentSiteId): array
    {
        $ownedDevices = [];
        $consignedDevices = [];
        foreach ($devices as $device) {
            if ((string)($device['ownership_type'] ?? 'owned') === 'consign') {
                $consignedDevices[] = $device;
                continue;
            }
            $ownedDevices[] = $device;
        }

        $groups = $this->groupDevices($ownedDevices);
        $orderIds = [];
        $assetIds = [];
        $createdCount = 0;
        $existingCount = 0;
        foreach ($groups as $groupKey => $group) {
            $requestId = ErpIdempotency::child($eventId, 'purchase-' . substr(hash('sha256', (string)$groupKey), 0, 12));
            $existingOrderId = $this->existingOrderId($currentSiteId, $requestId);
            $purchaseData = $this->buildPurchaseData($event, $group, $requestId);
            $orderId = $existingOrderId > 0 ? $existingOrderId : $this->createPurchase($purchaseData);
            $orderIds[] = $orderId;
            $deviceCount = count($group['devices']);
            if ($existingOrderId > 0) $existingCount += $deviceCount;
            else $createdCount += $deviceCount;
        }

        $source = (array)($event['_erp_source'] ?? []);
        $sourcePlugin = trim((string)($source['plugin'] ?? ''));
        $sourcePluginName = trim((string)($source['plugin_name'] ?? '')) ?: $sourcePlugin;
        foreach ($consignedDevices as $device) {
            $warehouseId = (int)($device['target_warehouse_id'] ?? 0);
            $locationId = (int)($device['target_location_id'] ?? 0);
            $item = $this->mapDeviceItem($device, $warehouseId, $locationId, 0, $sourcePlugin, $sourcePluginName);
            $registered = $this->registerConsignment($event, $device, $item);
            $assetIds[] = (int)$registered['asset_id'];
            if (!empty($registered['created'])) $createdCount++;
            else $existingCount++;
        }

        return [
            'consumer' => 'hsx_erp',
            'target' => 'self_erp',
            'status' => $createdCount > 0 ? 'processed' : 'duplicate',
            'order_ids' => array_values(array_unique(array_map('intval', $orderIds))),
            'consignment_asset_ids' => array_values(array_unique(array_filter(array_map('intval', $assetIds)))),
            'created_count' => $createdCount,
            'existing_count' => $existingCount,
            'skipped_count' => 0,
            'skipped' => [],
        ];
    }

    /**
     * 独立封装代卖登记入口，避免没有代卖设备时初始化带请求上下文的后台服务，
     * 同时允许契约测试替换持久化实现。
     */
    protected function registerConsignment(array $event, array $device, array $item): array
    {
        return (new ErpConsignmentInboundService())->register($event, $device, $item);
    }

    /** @return array<string,array{source_id:string,source_order_no:string,counterparty:array,devices:array}> */
    protected function groupDevices(array $devices): array
    {
        $groups = [];
        foreach ($devices as $device) {
            $sourceId = trim((string)($device['source_id'] ?? ''));
            $sourceOrderNo = trim((string)($device['source_order_no'] ?? ''));
            $counterparty = (array)($device['counterparty'] ?? []);
            $partyKey = implode(':', [
                (string)($counterparty['source_plugin'] ?? ''),
                (string)($counterparty['source_type'] ?? ''),
                (string)($counterparty['source_id'] ?? ''),
                (string)($counterparty['mobile'] ?? ''),
                (string)($counterparty['name'] ?? ''),
            ]);
            $orderKey = $sourceId !== '' && $sourceId !== '0'
                ? 'id:' . $sourceId
                : ($sourceOrderNo !== '' ? 'no:' . $sourceOrderNo : 'device:' . trim((string)($device['source_device_id'] ?? '')));
            $key = hash('sha256', $orderKey . '|' . $partyKey);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'source_id' => $sourceId,
                    'source_order_no' => $sourceOrderNo,
                    'counterparty' => $counterparty,
                    'devices' => [],
                ];
            }
            $groups[$key]['devices'][] = $device;
        }
        return $groups;
    }

    protected function buildPurchaseData(array $event, array $group, string $requestId): array
    {
        $source = (array)($event['_erp_source'] ?? []);
        $sourcePlugin = trim((string)($source['plugin'] ?? ''));
        $sourcePluginName = trim((string)($source['plugin_name'] ?? '')) ?: $sourcePlugin;
        $sourceType = trim((string)($source['type'] ?? ''));
        $sourceName = trim((string)($source['name'] ?? '')) ?: '外部采购';
        $channel = (array)($event['channel'] ?? []);
        $channelCode = trim((string)($channel['code'] ?? $channel['key'] ?? $event['channel_code'] ?? ''));
        $channelName = trim((string)($channel['name'] ?? $channel['label'] ?? $event['channel_name'] ?? ''));
        if ($channelName === '' && $sourcePlugin === 'hsx_recycle') $channelName = '手机回收';
        if ($channelCode === '' && $sourcePlugin === 'hsx_recycle') $channelCode = 'hsx_recycle';
        $counterparty = (array)($group['counterparty'] ?? []);
        $partyName = trim((string)($counterparty['name'] ?? $counterparty['contact_name'] ?? ''));
        if ($partyName === '') {
            $partyName = trim((string)($counterparty['mobile'] ?? ''));
        }
        if ($partyName === '') {
            $partyName = '来源客户#' . ((string)($counterparty['source_id'] ?? '') ?: (string)($group['source_id'] ?? '未知'));
        }

        $items = [];
        $sourcePaidAmount = 0.0;
        $acquiredAt = [];
        foreach ((array)$group['devices'] as $index => $device) {
            $warehouseId = (int)($device['target_warehouse_id'] ?? 0);
            $locationId = (int)($device['target_location_id'] ?? 0);
            $deviceLabel = trim((string)($device['imei'] ?? $device['sn'] ?? $device['model'] ?? '')) ?: ('第' . ($index + 1) . '台设备');
            if ($warehouseId <= 0 || $locationId <= 0) {
                throw new CommonException($deviceLabel . '缺少目标仓库或库位，不能入库');
            }
            $cost = round((float)($device['purchase_cost'] ?? 0), 2);
            if ($cost <= 0) {
                throw new CommonException($deviceLabel . '缺少有效回收成本，不能生成采购入库');
            }
            $sourcePaidAmount = round($sourcePaidAmount + max(0, (float)($device['paid_amount'] ?? 0)), 2);
            $time = (int)($device['acquired_at'] ?? 0);
            if ($time > 0) {
                $acquiredAt[] = $time;
            }
            $items[] = $this->mapDeviceItem($device, $warehouseId, $locationId, $cost, $sourcePlugin, $sourcePluginName);
        }

        $sourceOrderNo = trim((string)($group['source_order_no'] ?? ''));
        $sourceId = trim((string)($group['source_id'] ?? ''));
        if ($sourceOrderNo === '') {
            throw new CommonException('设备入库事件缺少来源订单号，已拒绝创建采购单');
        }
        $eventId = trim((string)($event['event_id'] ?? ''));
        $operator = (array)($event['operator'] ?? []);
        $remark = $sourceName . '入库';
        if ($sourceOrderNo !== '') {
            $remark .= '；原业务单 ' . $sourceOrderNo;
        }
        if ($sourcePaidAmount > 0) {
            $remark .= '；来源系统记录已付 ¥' . number_format($sourcePaidAmount, 2, '.', '') . '，ERP未收到付款账户，本次仅生成应付，须由财务核对结算';
        }

        return [
            'party_id' => 0,
            'party_name' => $partyName,
            'member_id' => (int)($counterparty['member_id'] ?? (($counterparty['source_type'] ?? '') === 'member' ? ($counterparty['source_id'] ?? 0) : 0)),
            'contact_name' => trim((string)($counterparty['contact_name'] ?? $partyName)),
            'contact_mobile' => trim((string)($counterparty['mobile'] ?? '')),
            'm_no' => trim((string)($counterparty['m_no'] ?? $counterparty['mobile'] ?? '')),
            'purchase_channel' => $channelName,
            'purchase_channel_key' => $channelCode,
            'purchaser_uid' => (int)($operator['id'] ?? 0),
            'settle_method' => '挂账',
            // 标准事件没有 ERP 付款账户，不能伪造已付款资金事实。
            'paid_amount' => 0,
            'purchase_at' => $acquiredAt === [] ? (int)($event['occurred_at'] ?? time()) : min($acquiredAt),
            'remark' => $remark,
            'source_plugin' => $sourcePlugin,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'origin_plugin' => $sourcePlugin,
            'origin_plugin_name' => $sourcePluginName,
            'origin_type' => $sourceType,
            'origin_name' => $sourceName,
            'origin_id' => $sourceId,
            'origin_no' => $sourceOrderNo,
            'source_order_no' => $sourceOrderNo,
            'origin_event_id' => $eventId,
            'event_id' => $eventId,
            'request_id' => $requestId,
            'items' => $items,
        ];
    }

    protected function mapDeviceItem(array $device, int $warehouseId, int $locationId, float $cost, string $sourcePlugin, string $sourcePluginName): array
    {
        $check = (array)($device['check_snapshot'] ?? []);
        $refurbishment = (array)($device['refurbishment'] ?? []);
        $refurbishmentReason = mb_substr(trim((string)($refurbishment['reason'] ?? '')), 0, 500);
        $refurbishmentItems = array_values(array_filter((array)($refurbishment['suggested_items'] ?? []), 'is_array'));
        $sourceDeviceId = mb_substr(trim((string)($device['source_device_id'] ?? '')), 0, 80);
        $sourceDeviceValue = ctype_digit($sourceDeviceId) ? (int)$sourceDeviceId : $sourceDeviceId;
        $capacity = trim((string)($device['capacity'] ?? ''));
        $color = trim((string)($device['color'] ?? ''));
        $spec = implode(' ', array_values(array_filter([$capacity, $color])));
        $qualityParts = array_values(array_filter([
            $this->snapshotText($check['check_result'] ?? ''),
            trim((string)($check['check_remark'] ?? '')),
        ]));
        $images = '';
        foreach (['check_images_buyer', 'check_images_seller', 'check_images'] as $imageField) {
            $candidate = $check[$imageField] ?? '';
            if ((is_array($candidate) && $candidate !== []) || (!is_array($candidate) && trim((string)$candidate) !== '')) {
                $images = $candidate;
                break;
            }
        }
        if (is_array($images)) {
            $images = json_encode(array_values($images), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }
        return [
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'imei' => trim((string)($device['imei'] ?? '')) ?: trim((string)($device['imei2'] ?? '')),
            'sn' => trim((string)($device['sn'] ?? '')),
            'model' => trim((string)($device['model'] ?? '')),
            'spec' => $spec,
            'spec_json' => [
                'capacity' => $capacity,
                'capacity_value' => $device['capacity_value'] ?? $capacity,
                'color' => $color,
                'color_value' => $device['color_value'] ?? $color,
                'imei2' => trim((string)($device['imei2'] ?? '')),
                'source_plugin' => $sourcePlugin,
                'source_device_id' => $sourceDeviceValue,
                'source_order_no' => trim((string)($device['source_order_no'] ?? '')),
                'settlement_status' => trim((string)($device['settlement_status'] ?? '')),
                'source_paid_amount' => round((float)($device['paid_amount'] ?? 0), 2),
                'payee_methods' => array_values(array_filter((array)($device['payment_methods'] ?? []), 'is_array')),
                'refurbishment_suggestion' => [
                    'required' => (bool)($refurbishment['required'] ?? false),
                    'reason' => $refurbishmentReason,
                    'items' => $refurbishmentItems,
                    'estimated_cost' => round((float)($refurbishment['estimated_cost'] ?? 0), 2),
                    'decision_source' => trim((string)($refurbishment['decision_source'] ?? $sourcePlugin)),
                ],
            ],
            'color' => $color,
            'catalog_product_id' => (int)($device['catalog_product_id'] ?? 0),
            'estimate_sale_price' => round((float)($device['suggested_sale_price'] ?? 0), 2),
            'retail_price' => round((float)($device['suggested_sale_price'] ?? 0), 2),
            'image_urls' => trim((string)$images),
            'quality_remark' => mb_substr(implode('；', $qualityParts), 0, 500),
            'purchase_cost' => $cost,
            'refurbish_required' => (bool)($refurbishment['required'] ?? false),
            'refurbish_reason' => $refurbishmentReason,
            'remark' => $sourceDeviceId !== '' ? '来源设备#' . $sourceDeviceId : '外部来源设备',
            'remark_internal' => $sourceDeviceId !== ''
                ? '来源插件 ' . ($sourcePluginName ?: $sourcePlugin) . '；来源设备ID ' . $sourceDeviceId
                : '来源插件 ' . ($sourcePluginName ?: $sourcePlugin),
        ];
    }

    protected function currentSiteId(): int
    {
        return (int)request()->siteId();
    }

    protected function existingOrderId(int $siteId, string $requestId): int
    {
        $order = ErpPurchaseOrder::where([
            ['site_id', '=', $siteId],
            ['request_id', '=', $requestId],
        ])->findOrEmpty();
        return $order->isEmpty() ? 0 : (int)$order->id;
    }

    protected function createPurchase(array $data): int
    {
        return (new ErpPurchaseService())->create($data);
    }

    protected function resolveBusinessSource(string $sourceType): ?array
    {
        return (new ErpConfigService())->findBusinessSource($sourceType);
    }

    protected function findInbox(int $siteId, string $eventId, bool $lock = false): ?array
    {
        $query = ErpInboxEvent::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]]);
        if ($lock) $query->lock(true);
        $row = $query->findOrEmpty();
        return $row->isEmpty() ? null : $row->toArray();
    }

    protected function createInbox(int $siteId, string $eventId, array $event, array $requestPayload): int
    {
        $now = time();
        $source = (array)($event['_erp_source'] ?? []);
        $row = ErpInboxEvent::create([
            'site_id' => $siteId,
            'event_id' => $eventId,
            'source_plugin' => (string)($source['plugin'] ?? ''),
            'event_name' => (string)($event['event_name'] ?? 'erp.device.inbound_requested'),
            'payload_json' => json_encode(['request' => $requestPayload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'processing',
            'occurred_at' => (int)($event['occurred_at'] ?? $now),
            'create_at' => $now,
            'update_at' => $now,
        ]);
        return (int)$row->id;
    }

    protected function markInboxProcessing(int $inboxId, array $requestPayload): void
    {
        ErpInboxEvent::where('id', '=', $inboxId)->update([
            'payload_json' => json_encode(['request' => $requestPayload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'processing',
            'update_at' => time(),
        ]);
    }

    protected function completeInbox(int $inboxId, array $requestPayload, array $result): void
    {
        ErpInboxEvent::where('id', '=', $inboxId)->update([
            'payload_json' => json_encode(['request' => $requestPayload, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'processed',
            'update_at' => time(),
        ]);
    }

    protected function recordFailedInbox(int $siteId, string $eventId, array $event, array $requestPayload, string $message): void
    {
        $existing = $this->findInbox($siteId, $eventId);
        if ($existing !== null && $this->isProcessedInbox($existing)) return;
        $payload = json_encode([
            'request' => $requestPayload,
            'error' => mb_substr(trim($message), 0, 500),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $values = ['payload_json' => $payload, 'status' => 'failed', 'update_at' => time()];
        try {
            if ($existing !== null) {
                ErpInboxEvent::where([['site_id', '=', $siteId], ['id', '=', (int)$existing['id']]])->update($values);
                return;
            }
            $source = (array)($event['_erp_source'] ?? []);
            $now = time();
            ErpInboxEvent::create(array_merge($values, [
                'site_id' => $siteId,
                'event_id' => $eventId,
                'source_plugin' => (string)($source['plugin'] ?? ''),
                'event_name' => (string)($event['event_name'] ?? 'erp.device.inbound_requested'),
                'occurred_at' => (int)($event['occurred_at'] ?? $now),
                'create_at' => $now,
            ]));
        } catch (\Throwable) {
            // 并发消费者可能已写入成功结果；失败记录不能覆盖 processed 事实。
        }
    }

    protected function withinTransaction(callable $callback): array
    {
        return Db::transaction($callback);
    }

    protected function duplicateResult(array $inbox): array
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $result = is_array($stored) && is_array($stored['result'] ?? null) ? $stored['result'] : [];
        $existingCount = (int)($result['created_count'] ?? 0) + (int)($result['existing_count'] ?? 0);
        return array_merge($result, [
            'consumer' => 'hsx_erp',
            'target' => 'self_erp',
            'status' => 'duplicate',
            'created_count' => 0,
            'existing_count' => $existingCount,
        ]);
    }

    protected function assertSameInboxRequest(array $inbox, array $requestPayload): void
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $request = is_array($stored) && is_array($stored['request'] ?? null) ? $stored['request'] : null;
        if ($request === null) return;
        $storedHash = hash('sha256', json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $currentHash = hash('sha256', json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        if (!hash_equals($storedHash, $currentHash)) {
            throw new CommonException('event_id已被不同设备入库事实占用，请修正插件幂等键');
        }
    }

    protected function isProcessedInbox(array $inbox): bool
    {
        return in_array((string)($inbox['status'] ?? ''), ['processed', 'done'], true);
    }

    /** 固化用于幂等碰撞校验的规范请求，排除 ERP 内部临时元数据。 */
    protected function canonicalRequest(array $event): array
    {
        unset($event['_delivery']);
        return $this->canonicalize($event);
    }

    private function canonicalize(array $value): array
    {
        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
        foreach ($value as $key => $item) {
            if (is_array($item)) $value[$key] = $this->canonicalize($item);
        }
        if (!$isList) ksort($value);
        return $value;
    }

    private function snapshotText($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }
        return trim((string)$value);
    }
}
