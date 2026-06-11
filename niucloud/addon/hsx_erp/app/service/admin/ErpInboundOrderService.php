<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\job\PublishOutboxEvent;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpDeviceIdentity;
use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpStockOrder;
use addon\hsx_erp\app\model\ErpStockOrderItem;
use addon\hsx_erp\app\support\ErpDomainEvent;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpInboundOrderService extends BaseAdminService
{
    public function getPage(array $where = []): array
    {
        $query = ErpStockOrder::where([
            ['site_id', '=', $this->site_id],
            ['order_type', '=', 'purchase_in'],
        ])->order('id desc');
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('order_no|operator_name|remark', '%' . $keyword . '%');
        }
        if (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }

        $result = $this->pageQuery($query);
        $orderIds = array_map('intval', array_column($result['data'] ?? [], 'id'));
        if (empty($orderIds)) {
            return $result;
        }

        $counts = ErpStockOrderItem::where([['site_id', '=', $this->site_id]])
            ->whereIn('order_id', $orderIds)
            ->field('order_id,status,count(*) count')
            ->group('order_id,status')
            ->select()
            ->toArray();
        $countMap = [];
        foreach ($counts as $row) {
            $countMap[(int)$row['order_id']][(string)$row['status']] = (int)$row['count'];
        }
        foreach ($result['data'] as &$order) {
            $map = $countMap[(int)$order['id']] ?? [];
            $order['pending_count'] = (int)($map[ErpDict::STOCK_ITEM_PENDING] ?? 0);
            $order['confirmed_count'] = (int)($map[ErpDict::STOCK_ITEM_CONFIRMED] ?? 0);
            $order['rejected_count'] = (int)($map[ErpDict::STOCK_ITEM_REJECTED] ?? 0);
        }
        unset($order);
        $this->appendCounterparties($result['data']);
        return $result;
    }

    public function getInfo(int $orderId): array
    {
        $order = $this->findOrder($orderId);
        $items = ErpStockOrderItem::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])->order('id asc')->select()->toArray();
        $assetIds = array_map('intval', array_column($items, 'asset_id'));
        $assetMap = [];
        if (!empty($assetIds)) {
            $assets = ErpAsset::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', $assetIds)->select()->toArray();
            foreach ($assets as $asset) {
                $assetMap[(int)$asset['id']] = $asset;
            }
        }
        foreach ($items as &$item) {
            $item['asset'] = $assetMap[(int)$item['asset_id']] ?? null;
        }
        unset($item);

        return [
            'order' => $order->toArray(),
            'counterparty' => (int)$order->counterparty_id > 0
                ? ErpCounterparty::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$order->counterparty_id],
                ])->findOrEmpty()->toArray()
                : [],
            'items' => $items,
            'summary' => $this->buildSummary($items),
        ];
    }

    public function rejectItems(int $orderId, array $itemIds, string $reason): array
    {
        $itemIds = $this->normalizeIds($itemIds);
        $reason = trim($reason);
        if (empty($itemIds)) {
            throw new CommonException('请选择需要驳回的设备');
        }
        if ($reason === '') {
            throw new CommonException('请填写驳回原因');
        }

        $now = time();
        $outboxIds = [];
        Db::startTrans();
        try {
            $order = $this->findOrder($orderId, true);
            $items = ErpStockOrderItem::where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $orderId],
                ['status', '=', ErpDict::STOCK_ITEM_PENDING],
            ])->whereIn('id', $itemIds)->lock(true)->select();
            if ($items->count() !== count($itemIds)) {
                throw new CommonException('部分设备已被处理，请刷新后重试');
            }

            foreach ($items as $item) {
                $asset = $this->findAsset((int)$item->asset_id, true);
                if ((string)$asset->inventory_status !== ErpDict::INVENTORY_PENDING_IN) {
                    throw new CommonException('设备当前状态不允许驳回：' . ($asset->imei ?: $asset->asset_no));
                }
                $item->save([
                    'status' => ErpDict::STOCK_ITEM_REJECTED,
                    'reject_reason' => $reason,
                    'rejected_by' => $this->uid,
                    'rejected_name' => $this->username ?: '',
                    'rejected_at' => $now,
                    'update_at' => $now,
                ]);
                $asset->save([
                    'inventory_status' => ErpDict::INVENTORY_INBOUND_REJECTED,
                    'version' => (int)$asset->version + 1,
                    'update_at' => $now,
                ]);
                ErpAssetCycle::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$asset->cycle_id],
                ])->update([
                    'status' => ErpDict::INVENTORY_INBOUND_REJECTED,
                    'update_at' => $now,
                ]);
                $this->writeOperation($asset, $orderId, 'erp.asset.inbound_rejected.v1', 'reject_inbound', [
                    'reason' => $reason,
                    'stock_order_item_id' => (int)$item->id,
                ]);
                $outboxIds[] = $this->writeOutbox($asset, 'erp.asset.inbound_rejected.v1', [
                    'stock_order_id' => $orderId,
                    'stock_order_item_id' => (int)$item->id,
                    'reason' => $reason,
                ], $now);
            }
            $this->refreshOrderStatus($order);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
        $this->publishOutbox($outboxIds);
        return ['rejected_count' => count($itemIds)];
    }

    public function confirmItems(int $orderId, array $itemIds, array $data = []): array
    {
        $itemIds = $this->normalizeIds($itemIds);
        if (empty($itemIds)) {
            throw new CommonException('请选择需要确认入库的设备');
        }
        $this->findOrder($orderId);
        $items = ErpStockOrderItem::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
            ['status', '=', ErpDict::STOCK_ITEM_PENDING],
        ])->whereIn('id', $itemIds)->select();
        if ($items->count() !== count($itemIds)) {
            throw new CommonException('部分设备已被处理，请刷新后重试');
        }
        return (new ErpAssetService())->confirmAssetsInbound(
            array_map('intval', $items->column('asset_id')),
            $data
        );
    }

    public function resubmitItem(int $orderId, int $itemId, array $data = []): array
    {
        $now = time();
        Db::startTrans();
        try {
            $order = $this->findOrder($orderId, true);
            $item = ErpStockOrderItem::where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $orderId],
                ['id', '=', $itemId],
            ])->lock(true)->findOrEmpty();
            if ($item->isEmpty() || (string)$item->status !== ErpDict::STOCK_ITEM_REJECTED) {
                throw new CommonException('该设备不是可重新提交的驳回状态');
            }
            $asset = $this->findAsset((int)$item->asset_id, true);
            $assetData = [
                'inventory_status' => ErpDict::INVENTORY_PENDING_IN,
                'version' => (int)$asset->version + 1,
                'update_at' => $now,
            ];
            foreach (['imei', 'imei2', 'sn', 'model', 'capacity', 'color'] as $field) {
                if (array_key_exists($field, $data)) {
                    $assetData[$field] = trim((string)$data[$field]);
                }
            }
            $imei = (string)($assetData['imei'] ?? $asset->imei);
            $sn = (string)($assetData['sn'] ?? $asset->sn);
            if ($imei === '' && $sn === '') {
                throw new CommonException('IMEI 和 SN 至少填写一个');
            }
            $identity = $this->resolveCorrectedIdentity($asset, $assetData, $now);
            $assetData['identity_id'] = (int)$identity->id;
            $itemData = [
                'status' => ErpDict::STOCK_ITEM_PENDING,
                'reject_reason' => '',
                'rejected_by' => 0,
                'rejected_name' => '',
                'rejected_at' => 0,
                'retry_count' => (int)$item->retry_count + 1,
                'update_at' => $now,
            ];
            if (array_key_exists('purchase_cost', $data)) {
                $cost = round((float)$data['purchase_cost'], 2);
                $assetData['purchase_cost'] = $cost;
                $assetData['current_cost'] = '0.00';
                $itemData['amount'] = $cost;
            }
            $asset->save($assetData);
            $item->save($itemData);
            ErpAssetCycle::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$asset->cycle_id],
            ])->update([
                'identity_id' => (int)$identity->id,
                'status' => ErpDict::INVENTORY_PENDING_IN,
                'update_at' => $now,
            ]);
            $this->writeOperation($asset, $orderId, 'erp.asset.inbound_resubmitted.v1', 'resubmit_inbound', [
                'stock_order_item_id' => $itemId,
                'retry_count' => (int)$item->retry_count,
            ]);
            $outboxId = $this->writeOutbox($asset, 'erp.asset.inbound_resubmitted.v1', [
                'stock_order_id' => $orderId,
                'stock_order_item_id' => $itemId,
            ], $now);
            $this->refreshOrderStatus($order);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
        $this->publishOutbox([$outboxId]);
        return $this->getInfo($orderId);
    }

    private function resolveCorrectedIdentity(ErpAsset $asset, array $assetData, int $now): ErpDeviceIdentity
    {
        $imei = trim((string)($assetData['imei'] ?? $asset->imei));
        $imei2 = trim((string)($assetData['imei2'] ?? $asset->imei2));
        $sn = trim((string)($assetData['sn'] ?? $asset->sn));
        $model = trim((string)($assetData['model'] ?? $asset->model));
        $identityKey = $imei !== '' ? 'imei:' . $imei : 'sn:' . $sn;
        $identity = ErpDeviceIdentity::where([
            ['site_id', '=', $this->site_id],
            ['identity_key', '=', $identityKey],
        ])->lock(true)->findOrEmpty();

        if (!$identity->isEmpty()) {
            $identity->save([
                'imei' => $imei,
                'imei2' => $imei2,
                'sn' => $sn,
                'model' => $model,
                'update_at' => $now,
            ]);
            return $identity;
        }

        $currentIdentity = ErpDeviceIdentity::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->identity_id],
        ])->lock(true)->findOrEmpty();
        $usedByOtherCycles = ErpAssetCycle::where([
            ['site_id', '=', $this->site_id],
            ['identity_id', '=', (int)$asset->identity_id],
            ['id', '<>', (int)$asset->cycle_id],
        ])->count();

        $identityData = [
            'identity_key' => $identityKey,
            'imei' => $imei,
            'imei2' => $imei2,
            'sn' => $sn,
            'model' => $model,
            'category_id' => (int)$asset->category_id,
            'update_at' => $now,
        ];
        if (!$currentIdentity->isEmpty() && $usedByOtherCycles === 0) {
            $currentIdentity->save($identityData);
            return $currentIdentity;
        }

        return ErpDeviceIdentity::create(array_merge($identityData, [
            'site_id' => $this->site_id,
            'identity_no' => $this->makeIdentityNo(),
            'create_at' => $now,
        ]));
    }

    private function refreshOrderStatus(ErpStockOrder $order): void
    {
        $counts = ErpStockOrderItem::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', (int)$order->id],
        ])->field('status,count(*) count')->group('status')->select()->toArray();
        $map = [];
        foreach ($counts as $row) {
            $map[(string)$row['status']] = (int)$row['count'];
        }
        $pending = (int)($map[ErpDict::STOCK_ITEM_PENDING] ?? 0);
        $confirmed = (int)($map[ErpDict::STOCK_ITEM_CONFIRMED] ?? 0);
        $rejected = (int)($map[ErpDict::STOCK_ITEM_REJECTED] ?? 0);
        $status = ErpDict::STOCK_ORDER_DRAFT;
        if ($pending === 0 && $rejected === 0 && $confirmed > 0) {
            $status = ErpDict::STOCK_ORDER_CONFIRMED;
        } elseif ($confirmed > 0) {
            $status = ErpDict::STOCK_ORDER_PARTIAL_CONFIRMED;
        } elseif ($pending === 0 && $rejected > 0) {
            $status = ErpDict::STOCK_ORDER_REJECTED;
        }
        $order->save(['status' => $status, 'update_at' => time()]);
    }

    private function findOrder(int $id, bool $lock = false): ErpStockOrder
    {
        $query = ErpStockOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
            ['order_type', '=', 'purchase_in'],
        ]);
        if ($lock) {
            $query->lock(true);
        }
        $order = $query->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('入库单不存在');
        }
        return $order;
    }

    private function findAsset(int $id, bool $lock = false): ErpAsset
    {
        $query = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ]);
        if ($lock) {
            $query->lock(true);
        }
        $asset = $query->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('入库设备不存在');
        }
        return $asset;
    }

    private function buildSummary(array $items): array
    {
        $summary = ['total' => count($items), 'pending' => 0, 'confirmed' => 0, 'rejected' => 0];
        foreach ($items as $item) {
            $status = (string)$item['status'];
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }
        return $summary;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function writeOperation(ErpAsset $asset, int $orderId, string $eventName, string $action, array $payload): void
    {
        ErpOperationEvent::create([
            'site_id' => $this->site_id,
            'event_id' => $this->makeEventId($action, (int)$asset->id),
            'event_name' => $eventName,
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'document_type' => 'stock_in',
            'document_id' => $orderId,
            'stage' => 'inbound',
            'action' => $action,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'payload' => $payload,
            'occurred_at' => time(),
        ]);
    }

    private function writeOutbox(ErpAsset $asset, string $eventName, array $payload, int $now): int
    {
        $eventId = $this->makeEventId($eventName, (int)$asset->id);
        $domainEvent = ErpDomainEvent::create(
            $this->site_id,
            $eventName,
            $eventId,
            'asset',
            (int)$asset->id,
            ['type' => 'staff', 'id' => $this->uid, 'name' => $this->username ?: ''],
            ['plugin' => 'hsx_erp', 'type' => 'stock_in', 'id' => (int)($payload['stock_order_id'] ?? 0)],
            array_merge([
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'source_device_id' => (int)$asset->source_device_id,
            ], $payload),
            $now
        );
        $event = ErpDomainEvent::writeOutbox($domainEvent, $now);
        return (int)$event->id;
    }

    private function publishOutbox(array $ids): void
    {
        foreach ($ids as $id) {
            PublishOutboxEvent::dispatch(['outbox_id' => (int)$id]);
        }
    }

    private function makeEventId(string $prefix, int $id): string
    {
        return str_replace('.', '-', $prefix) . '-' . $id . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
    }

    private function makeIdentityNo(): string
    {
        return 'DI' . date('YmdHis') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT)
            . random_int(100000, 999999);
    }

    private function appendCounterparties(array &$rows): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn(array $row) => (int)($row['counterparty_id'] ?? 0),
            $rows
        ))));
        if (empty($ids)) {
            return;
        }
        $map = [];
        foreach (ErpCounterparty::where([['site_id', '=', $this->site_id]])
                     ->whereIn('id', $ids)->select()->toArray() as $counterparty) {
            $map[(int)$counterparty['id']] = $counterparty;
        }
        foreach ($rows as &$row) {
            $row['counterparty'] = $map[(int)($row['counterparty_id'] ?? 0)] ?? null;
        }
        unset($row);
    }
}
