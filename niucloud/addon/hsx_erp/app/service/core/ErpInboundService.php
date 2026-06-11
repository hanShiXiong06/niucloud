<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpDeviceIdentity;
use addon\hsx_erp\app\model\ErpInboxEvent;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpStockOrder;
use addon\hsx_erp\app\model\ErpStockOrderItem;
use addon\hsx_erp\app\model\ErpSyncBatch;
use addon\hsx_erp\app\model\ErpSyncTarget;
use addon\hsx_erp\app\support\ErpMoney;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 标准设备入库事件接收服务。
 */
class ErpInboundService
{
    private const CONSUMER = 'hsx_erp.inbound';

    public function receive(array $event): array
    {
        $this->validateEvent($event);

        $siteId = (int)$event['site_id'];
        $eventId = (string)$event['event_id'];
        $existingInbox = ErpInboxEvent::where([
            ['site_id', '=', $siteId],
            ['event_id', '=', $eventId],
            ['consumer', '=', self::CONSUMER],
        ])->findOrEmpty();
        if (!$existingInbox->isEmpty()) {
            return $this->existingResult($siteId, $eventId);
        }

        $now = time();
        Db::startTrans();
        try {
            $inbox = ErpInboxEvent::create([
                'site_id' => $siteId,
                'event_id' => $eventId,
                'event_name' => (string)$event['event_name'],
                'consumer' => self::CONSUMER,
                'status' => ErpDict::SYNC_PROCESSING,
                'payload' => $event,
                'create_at' => $now,
                'update_at' => $now,
            ]);

            $batch = ErpSyncBatch::create([
                'site_id' => $siteId,
                'batch_no' => $this->makeNo('IB', $siteId),
                'event_id' => $eventId,
                'source_plugin' => (string)($event['source']['plugin'] ?? ''),
                'source_type' => (string)($event['source']['type'] ?? ''),
                'device_count' => count($event['devices']),
                'status' => ErpDict::SYNC_PROCESSING,
                'operator_id' => (int)($event['operator']['id'] ?? 0),
                'operator_name' => (string)($event['operator']['name'] ?? ''),
                'payload' => $event,
                'create_at' => $now,
                'update_at' => $now,
            ]);

            $target = ErpSyncTarget::create([
                'site_id' => $siteId,
                'batch_id' => (int)$batch->id,
                'target' => ErpDict::TARGET_SELF_ERP,
                'status' => ErpDict::SYNC_PROCESSING,
                'attempts' => 1,
                'last_attempt_at' => $now,
                'create_at' => $now,
                'update_at' => $now,
            ]);

            $stockOrder = ErpStockOrder::create([
                'site_id' => $siteId,
                'order_no' => $this->makeNo('RK', $siteId),
                'order_type' => 'purchase_in',
                'status' => ErpDict::STOCK_ORDER_DRAFT,
                'source_plugin' => (string)($event['source']['plugin'] ?? ''),
                'source_type' => (string)($event['source']['type'] ?? ''),
                'source_id' => (int)($event['source']['id'] ?? 0),
                'device_count' => 0,
                'total_cost' => 0,
                'operator_id' => (int)($event['operator']['id'] ?? 0),
                'operator_name' => (string)($event['operator']['name'] ?? ''),
                'remark' => $this->makeStockOrderRemark($event),
                'create_at' => $now,
                'update_at' => $now,
            ]);

            $created = [];
            $existing = [];
            $totalCost = '0.00';
            $counterpartyIds = [];
            foreach ($event['devices'] as $device) {
                $result = $this->createPendingAsset($siteId, $stockOrder, $event, (array)$device, $now);
                $totalCost = ErpMoney::add($totalCost, $result['cost']);
                if ((int)$result['counterparty_id'] > 0) {
                    $counterpartyIds[(int)$result['counterparty_id']] = (int)$result['counterparty_id'];
                }
                if ($result['created']) {
                    $created[] = $result['asset'];
                } else {
                    $existing[] = $result['asset'];
                }
            }

            $stockOrder->save([
                'device_count' => count($created),
                'counterparty_id' => count($counterpartyIds) === 1 ? (int)reset($counterpartyIds) : 0,
                'source_member_id' => $this->singleSourceMemberId($event['devices']),
                'total_cost' => $totalCost,
                'update_at' => $now,
            ]);
            $stockOrderId = (int)$stockOrder->id;
            $stockOrderNo = (string)$stockOrder->order_no;
            if (empty($created)) {
                $stockOrder->delete();
                $stockOrderId = 0;
                $stockOrderNo = '';
            }

            $response = [
                'batch_id' => (int)$batch->id,
                'batch_no' => (string)$batch->batch_no,
                'stock_order_id' => $stockOrderId,
                'stock_order_no' => $stockOrderNo,
                'created_count' => count($created),
                'existing_count' => count($existing),
                'created_assets' => $created,
                'existing_assets' => $existing,
            ];

            $target->save([
                'status' => ErpDict::SYNC_COMPLETED,
                'response_data' => $response,
                'completed_at' => $now,
                'update_at' => $now,
            ]);
            $batch->save([
                'status' => ErpDict::SYNC_COMPLETED,
                'update_at' => $now,
            ]);
            $inbox->save([
                'status' => ErpDict::SYNC_COMPLETED,
                'processed_at' => $now,
                'update_at' => $now,
            ]);

            Db::commit();
            return array_merge(['target' => ErpDict::TARGET_SELF_ERP], $response);
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException('ERP接收入库设备失败：' . $e->getMessage());
        }
    }

    private function createPendingAsset(
        int $siteId,
        ErpStockOrder $stockOrder,
        array $event,
        array $device,
        int $now
    ): array {
        $sourcePlugin = (string)($event['source']['plugin'] ?? '');
        $sourceType = (string)($event['source']['type'] ?? '');
        $sourceDeviceId = (int)($device['source_device_id'] ?? 0);

        $cycle = ErpAssetCycle::where([
            ['site_id', '=', $siteId],
            ['source_plugin', '=', $sourcePlugin],
            ['source_type', '=', $sourceType],
            ['source_device_id', '=', $sourceDeviceId],
        ])->findOrEmpty();
        if (!$cycle->isEmpty()) {
            $asset = ErpAsset::where([
                ['site_id', '=', $siteId],
                ['cycle_id', '=', (int)$cycle->id],
            ])->findOrEmpty();
            return [
                'created' => false,
                'cost' => 0,
                'counterparty_id' => (int)$cycle->counterparty_id,
                'asset' => $asset->isEmpty() ? [] : $asset->toArray(),
            ];
        }

        $identity = $this->resolveIdentity($siteId, $device, $now);
        $counterpartyId = $this->resolveCounterparty($siteId, $device, $now);
        $ownershipType = (string)($device['ownership_type'] ?? ErpDict::OWNERSHIP_OWNED);
        $purchaseCost = ErpMoney::normalize($device['purchase_cost'] ?? 0);
        $sourceMemberId = (int)($device['member_id'] ?? 0);
        $device['counterparty_id'] = $counterpartyId;
        $device['refurbishment'] = $this->normalizeRefurbishmentPlan($device);

        $cycle = ErpAssetCycle::create([
            'site_id' => $siteId,
            'cycle_no' => $this->makeNo('CY', $siteId),
            'identity_id' => (int)$identity->id,
            'source_plugin' => $sourcePlugin,
            'source_type' => $sourceType,
            'source_id' => (int)($device['source_id'] ?? ($event['source']['id'] ?? 0)),
            'source_device_id' => $sourceDeviceId,
            'counterparty_id' => $counterpartyId,
            'source_member_id' => $sourceMemberId,
            'ownership_type' => $ownershipType,
            'status' => ErpDict::INVENTORY_PENDING_IN,
            'acquired_at' => (int)($device['acquired_at'] ?? $event['occurred_at'] ?? $now),
            'create_at' => $now,
            'update_at' => $now,
        ]);

        $asset = ErpAsset::create([
            'site_id' => $siteId,
            'asset_no' => $this->makeNo('AS', $siteId),
            'identity_id' => (int)$identity->id,
            'cycle_id' => (int)$cycle->id,
            'source_device_id' => $sourceDeviceId,
            'counterparty_id' => $counterpartyId,
            'source_member_id' => $sourceMemberId,
            'imei' => (string)($device['imei'] ?? ''),
            'imei2' => (string)($device['imei2'] ?? ''),
            'sn' => (string)($device['sn'] ?? ''),
            'model' => (string)($device['model'] ?? ''),
            'category_id' => (int)($device['category_id'] ?? 0),
            'capacity' => (string)($device['capacity'] ?? ''),
            'color' => (string)($device['color'] ?? ''),
            'ownership_type' => $ownershipType,
            'inventory_status' => ErpDict::INVENTORY_PENDING_IN,
            'purchase_cost' => $purchaseCost,
            'current_cost' => '0.00',
            'current_sale_price' => round((float)($device['suggested_sale_price'] ?? 0), 2),
            'check_snapshot' => (array)($device['check_snapshot'] ?? []),
            'source_snapshot' => $device,
            'create_at' => $now,
            'update_at' => $now,
        ]);

        ErpStockOrderItem::create([
            'site_id' => $siteId,
            'order_id' => (int)$stockOrder->id,
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$cycle->id,
            'source_device_id' => $sourceDeviceId,
            'amount' => $purchaseCost,
            'status' => 'pending',
            'create_at' => $now,
            'update_at' => $now,
        ]);

        ErpOperationEvent::create([
            'site_id' => $siteId,
            'event_id' => $this->makeEventId('asset-created', (int)$asset->id),
            'event_name' => 'erp.asset.created',
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$cycle->id,
            'document_type' => 'stock_in',
            'document_id' => (int)$stockOrder->id,
            'stage' => 'inbound',
            'action' => 'create_pending_asset',
            'operator_id' => (int)($event['operator']['id'] ?? 0),
            'operator_name' => (string)($event['operator']['name'] ?? ''),
            'payload' => [
                'source_plugin' => $sourcePlugin,
                'source_device_id' => $sourceDeviceId,
            ],
            'occurred_at' => $now,
        ]);

        return [
            'created' => true,
            'cost' => $purchaseCost,
            'counterparty_id' => $counterpartyId,
            'asset' => $asset->toArray(),
        ];
    }

    private function normalizeRefurbishmentPlan(array $device): array
    {
        $plan = (array)($device['refurbishment'] ?? []);
        $required = array_key_exists('required', $plan)
            ? (bool)$plan['required']
            : (bool)($device['refurbishment_required'] ?? false);
        $suggestedItems = $plan['suggested_items'] ?? ($device['refurbishment_items'] ?? []);
        if (!is_array($suggestedItems)) {
            $suggestedItems = [];
        }

        return [
            'required' => $required,
            'decision_source' => (string)($plan['decision_source'] ?? ($required ? 'source' : 'default')),
            'reason' => trim((string)($plan['reason'] ?? ($device['refurbishment_reason'] ?? ''))),
            'suggested_items' => array_values($suggestedItems),
            'estimated_cost' => ErpMoney::normalize($plan['estimated_cost'] ?? ($device['refurbishment_estimated_cost'] ?? 0)),
            'decided_by' => (array)($plan['decided_by'] ?? []),
            'assignee' => (array)($plan['assignee'] ?? []),
            'decided_at' => (int)($plan['decided_at'] ?? 0),
        ];
    }

    private function resolveCounterparty(int $siteId, array $device, int $now): int
    {
        $counterparty = (array)($device['counterparty'] ?? []);
        if (empty($counterparty) && (int)($device['counterparty_id'] ?? 0) > 0) {
            $counterparty = ['id' => (int)$device['counterparty_id']];
        }
        if (empty($counterparty)) {
            return 0;
        }
        return (int)(new ErpCounterpartyService())->resolve($siteId, $counterparty, $now)->id;
    }

    private function singleSourceMemberId(array $devices): int
    {
        $memberIds = [];
        foreach ($devices as $device) {
            $memberId = (int)($device['member_id'] ?? 0);
            if ($memberId > 0) {
                $memberIds[$memberId] = $memberId;
            }
        }
        return count($memberIds) === 1 ? (int)reset($memberIds) : 0;
    }

    private function resolveIdentity(int $siteId, array $device, int $now): ErpDeviceIdentity
    {
        $imei = trim((string)($device['imei'] ?? ''));
        $sn = trim((string)($device['sn'] ?? ''));
        $identityKey = $imei !== ''
            ? 'imei:' . $imei
            : ($sn !== '' ? 'sn:' . $sn : 'source:' . (int)($device['source_device_id'] ?? 0));
        $identity = ErpDeviceIdentity::where([
            ['site_id', '=', $siteId],
            ['identity_key', '=', $identityKey],
        ])->findOrEmpty();

        if (!$identity->isEmpty()) {
            return $identity;
        }

        return ErpDeviceIdentity::create([
            'site_id' => $siteId,
            'identity_no' => $this->makeNo('DI', $siteId),
            'identity_key' => $identityKey,
            'imei' => $imei,
            'imei2' => trim((string)($device['imei2'] ?? '')),
            'sn' => $sn,
            'model' => (string)($device['model'] ?? ''),
            'category_id' => (int)($device['category_id'] ?? 0),
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function validateEvent(array $event): void
    {
        if (empty($event['event_id']) || empty($event['site_id']) || empty($event['event_name'])) {
            throw new CommonException('设备入库事件缺少必要字段');
        }
        if ((int)($event['event_version'] ?? 0) !== 1) {
            throw new CommonException('设备入库事件版本不受支持');
        }
        if (empty($event['devices']) || !is_array($event['devices'])) {
            throw new CommonException('请选择需要同步的设备');
        }
        foreach ($event['devices'] as $device) {
            if ((int)($device['source_device_id'] ?? 0) <= 0) {
                throw new CommonException('来源设备ID不能为空');
            }
        }
    }

    private function makeStockOrderRemark(array $event): string
    {
        $remark = trim((string)($event['remark'] ?? ''));
        if ($remark !== '') {
            return $remark;
        }
        $sourcePlugin = (string)($event['source']['plugin'] ?? '');
        $sourceType = (string)($event['source']['type'] ?? '');
        if ($sourcePlugin === 'hsx_recycle') {
            return '回收设备同步生成的待入库单';
        }
        if ($sourcePlugin === 'hsx_erp' && $sourceType === 'manual_inbound') {
            return 'ERP 手工建档生成的待入库单';
        }
        return '外部业务同步生成的待入库单';
    }

    private function existingResult(int $siteId, string $eventId): array
    {
        $batch = ErpSyncBatch::where([
            ['site_id', '=', $siteId],
            ['event_id', '=', $eventId],
        ])->findOrEmpty();
        if ($batch->isEmpty()) {
            return [
                'target' => ErpDict::TARGET_SELF_ERP,
                'duplicate' => true,
            ];
        }
        $target = ErpSyncTarget::where([
            ['site_id', '=', $siteId],
            ['batch_id', '=', (int)$batch->id],
            ['target', '=', ErpDict::TARGET_SELF_ERP],
        ])->findOrEmpty();
        return array_merge([
            'target' => ErpDict::TARGET_SELF_ERP,
            'duplicate' => true,
            'batch_id' => (int)$batch->id,
            'batch_no' => (string)$batch->batch_no,
        ], $target->isEmpty() ? [] : (array)$target->response_data);
    }

    private function makeNo(string $prefix, int $siteId): string
    {
        return $prefix . date('YmdHis') . str_pad((string)$siteId, 3, '0', STR_PAD_LEFT) . random_int(100000, 999999);
    }

    private function makeEventId(string $prefix, int $id): string
    {
        return $prefix . '-' . $id . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
    }
}
