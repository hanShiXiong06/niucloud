<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\job\PublishOutboxEvent;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpRefurbishItem;
use addon\hsx_erp\app\model\ErpRefurbishOrder;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\support\ErpDomainEvent;
use addon\hsx_erp\app\support\ErpMoney;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpRefurbishmentService extends BaseAdminService
{
    private const ITEM_TYPES = ['part', 'labor', 'external', 'logistics', 'inspection', 'other'];

    public function getPage(array $where = []): array
    {
        $query = ErpRefurbishOrder::where([['site_id', '=', $this->site_id]]);
        if (($where['status'] ?? '') !== '') {
            $query->where('status', '=', (string)$where['status']);
        }
        if (!empty($where['assigned_uid'])) {
            $query->where('assigned_uid', '=', (int)$where['assigned_uid']);
        }
        if (!empty($where['start_time'])) {
            $query->where('create_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('create_at', '<=', (int)$where['end_time']);
        }
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $assetIds = ErpAsset::where([['site_id', '=', $this->site_id]])
                ->whereLike('asset_no|imei|sn|model', '%' . $keyword . '%')->column('id');
            $query->where(function ($query) use ($keyword, $assetIds) {
                $query->whereLike('order_no|assigned_name', '%' . $keyword . '%');
                if (!empty($assetIds)) {
                    $query->whereOrIn('asset_id', $assetIds);
                }
            });
        }

        $sortMap = ['id' => 'id', 'order_no' => 'order_no', 'create_at' => 'create_at'];
        $sf = $sortMap[(string)($where['sort_field'] ?? '')] ?? 'id';
        $so = strtolower((string)($where['sort_order'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $query->order($sf, $so);

        $page = $this->pageQuery($query);
        $this->appendRelations($page['data']);
        return $page;
    }

    public function getInfo(int $id): array
    {
        $order = $this->findOrder($id);
        $asset = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$order->asset_id],
        ])->findOrEmpty();
        return [
            'order' => $order->toArray(),
            'asset' => $asset->isEmpty() ? [] : $asset->toArray(),
            'items' => ErpRefurbishItem::where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $id],
            ])->order('id asc')->select()->toArray(),
        ];
    }

    public function userOptions(string $keyword = ''): array
    {
        $uids = SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
        ])->column('uid');
        if (empty($uids)) {
            return [];
        }
        $query = SysUser::where([['status', '=', 1]])->whereIn('uid', $uids);
        if ($keyword !== '') {
            $query->whereLike('username|real_name|mobile', '%' . trim($keyword) . '%');
        }
        $users = $query->field('uid,username,real_name,mobile')->order('uid desc')->limit(100)->select()->toArray();
        usort($users, fn(array $left, array $right): int =>
            ((int)$right['uid'] === $this->uid ? 1 : 0) <=> ((int)$left['uid'] === $this->uid ? 1 : 0)
        );
        return $users;
    }

    public function create(array $data): array
    {
        $assetId = (int)($data['asset_id'] ?? 0);
        $items = $this->normalizeItems((array)($data['items'] ?? []), false);
        if (empty($items)) {
            throw new CommonException('请至少选择一个整备项目');
        }
        $assignedUid = (int)($data['assigned_uid'] ?? $this->uid);
        $assigned = $this->findUser($assignedUid);
        $now = time();

        Db::startTrans();
        try {
            $asset = $this->lockAsset($assetId);
            if ((string)$asset->inventory_status !== ErpDict::INVENTORY_IN_STOCK) {
                throw new CommonException('只有在库设备可以发起整备');
            }
            $activeCount = ErpRefurbishOrder::where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
                ['status', '=', ErpDict::REFURBISH_PROCESSING],
            ])->count();
            if ($activeCount > 0) {
                throw new CommonException('该设备已有进行中的整备工单');
            }

            $order = ErpRefurbishOrder::create([
                'site_id' => $this->site_id,
                'order_no' => $this->makeNo('ZB'),
                'asset_id' => $assetId,
                'cycle_id' => (int)$asset->cycle_id,
                'status' => ErpDict::REFURBISH_PROCESSING,
                'assigned_uid' => $assignedUid,
                'assigned_name' => $this->userName($assigned),
                'planned_finish_at' => (int)($data['planned_finish_at'] ?? 0),
                'started_at' => $now,
                'remark' => trim((string)($data['remark'] ?? '')),
                'create_at' => $now,
                'update_at' => $now,
            ]);
            foreach ($items as $item) {
                ErpRefurbishItem::create(array_merge($item, [
                    'site_id' => $this->site_id,
                    'order_id' => (int)$order->id,
                    'amount' => '0.00',
                    'create_at' => $now,
                    'update_at' => $now,
                ]));
            }

            $this->changeAssetStatus($asset, ErpDict::INVENTORY_REFURBISHING, 'refurbishment_start', (int)$order->id, $now);
            $outboxId = $this->writeEvent($asset, 'erp.refurbishment.created.v1', [
                'refurbish_order_id' => (int)$order->id,
                'refurbish_order_no' => (string)$order->order_no,
                'assigned_uid' => $assignedUid,
                'item_count' => count($items),
            ], $now);
            Db::commit();
            $this->publish([$outboxId]);
            return $this->getInfo((int)$order->id);
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function complete(int $id, array $data): array
    {
        $items = $this->normalizeItems((array)($data['items'] ?? []), true);
        if (empty($items)) {
            throw new CommonException('请填写实际整备项目');
        }
        $now = time();
        $outboxIds = [];

        Db::startTrans();
        try {
            $order = ErpRefurbishOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) {
                throw new CommonException('整备工单不存在');
            }
            if ((string)$order->status !== ErpDict::REFURBISH_PROCESSING) {
                throw new CommonException('只有进行中的整备工单可以完工');
            }
            $asset = $this->lockAsset((int)$order->asset_id);
            if ((string)$asset->inventory_status !== ErpDict::INVENTORY_REFURBISHING) {
                throw new CommonException('设备当前不在整备中');
            }

            ErpRefurbishItem::where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $id],
            ])->delete();

            $beforeCost = ErpMoney::normalize($asset->current_cost);
            $currentCost = $beforeCost;
            $totalCost = '0.00';
            foreach ($items as $item) {
                $amount = ErpMoney::normalize($item['amount']);
                ErpRefurbishItem::create(array_merge($item, [
                    'site_id' => $this->site_id,
                    'order_id' => $id,
                    'amount' => $amount,
                    'create_at' => $now,
                    'update_at' => $now,
                ]));
                if (ErpMoney::compare($amount, '0.00') <= 0) {
                    continue;
                }
                $afterCost = ErpMoney::add($currentCost, $amount);
                ErpCostLedger::create([
                    'site_id' => $this->site_id,
                    'ledger_no' => $this->makeNo('CL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'cost_type' => $this->costType((string)$item['item_type']),
                    'amount_delta' => $amount,
                    'before_cost' => $currentCost,
                    'after_cost' => $afterCost,
                    'source_plugin' => 'hsx_erp',
                    'source_type' => 'refurbishment',
                    'source_id' => $id,
                    'counterparty_id' => 0,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'occurred_at' => $now,
                    'remark' => (string)$item['item_name'] . ((string)$item['remark'] !== '' ? '：' . $item['remark'] : ''),
                ]);
                $currentCost = $afterCost;
                $totalCost = ErpMoney::add($totalCost, $amount);
            }

            $asset->save([
                'current_cost' => $currentCost,
                'inventory_status' => ErpDict::INVENTORY_PENDING_PRICING,
                'version' => (int)$asset->version + 1,
                'update_at' => $now,
            ]);
            ErpAssetCycle::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$asset->cycle_id],
            ])->update(['status' => ErpDict::INVENTORY_PENDING_PRICING, 'update_at' => $now]);
            $order->save([
                'status' => ErpDict::REFURBISH_COMPLETED,
                'completed_at' => $now,
                'accepted_by' => $this->uid,
                'accepted_name' => $this->username ?: '',
                'total_cost' => $totalCost,
                'completion_remark' => trim((string)($data['completion_remark'] ?? '')),
                'update_at' => $now,
            ]);
            $this->writeStatusLedger($asset, ErpDict::INVENTORY_REFURBISHING, ErpDict::INVENTORY_PENDING_PRICING, 'refurbishment_complete', $id, $now);
            $this->writeOperation($asset, 'erp.refurbishment.completed.v1', 'complete_refurbishment', $id, [
                'before_cost' => $beforeCost,
                'added_cost' => $totalCost,
                'after_cost' => $currentCost,
            ], $now);
            $outboxIds[] = $this->writeEvent($asset, 'erp.refurbishment.completed.v1', [
                'refurbish_order_id' => $id,
                'added_cost' => $totalCost,
                'current_cost' => $currentCost,
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ], $now);
            $outboxIds = array_merge($outboxIds, $this->writeReadyForPhotoEvents($asset, $id, $now));
            if (ErpMoney::compare($totalCost, '0.00') > 0) {
                $outboxIds[] = $this->writeEvent($asset, 'erp.asset.cost_changed.v1', [
                    'document_type' => 'refurbishment',
                    'document_id' => $id,
                    'before_cost' => $beforeCost,
                    'amount_delta' => $totalCost,
                    'after_cost' => $currentCost,
                ], $now);
            }
            Db::commit();
            $this->publish($outboxIds);
            return $this->getInfo($id);
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function cancel(int $id, string $remark = ''): array
    {
        $now = time();
        $outboxId = 0;
        Db::startTrans();
        try {
            $order = ErpRefurbishOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty() || (string)$order->status !== ErpDict::REFURBISH_PROCESSING) {
                throw new CommonException('只有进行中的整备工单可以取消');
            }
            $asset = $this->lockAsset((int)$order->asset_id);
            $order->save([
                'status' => ErpDict::REFURBISH_CANCELLED,
                'completion_remark' => trim($remark),
                'update_at' => $now,
            ]);
            $this->changeAssetStatus($asset, ErpDict::INVENTORY_IN_STOCK, 'refurbishment_cancel', $id, $now);
            $outboxId = $this->writeEvent($asset, 'erp.refurbishment.cancelled.v1', [
                'refurbish_order_id' => $id,
                'reason' => trim($remark),
                'next_status' => ErpDict::INVENTORY_IN_STOCK,
            ], $now);
            Db::commit();
            $this->publish([$outboxId]);
            return $this->getInfo($id);
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function skip(int $assetId, string $remark = ''): array
    {
        $now = time();
        $outboxId = 0;
        Db::startTrans();
        try {
            $asset = $this->lockAsset($assetId);
            if ((string)$asset->inventory_status !== ErpDict::INVENTORY_IN_STOCK) {
                throw new CommonException('只有在库设备可以跳过整备');
            }
            $this->changeAssetStatus($asset, ErpDict::INVENTORY_PENDING_PRICING, 'skip_refurbishment', 0, $now, [
                'remark' => trim($remark),
            ]);
            $outboxId = $this->writeEvent($asset, 'erp.refurbishment.skipped.v1', [
                'reason' => trim($remark),
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ], $now);
            Db::commit();
            $this->publish([$outboxId]);
            $updated = ErpAsset::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $assetId],
            ])->findOrEmpty();
            return ['asset' => $updated->isEmpty() ? [] : $updated->toArray()];
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    private function appendRelations(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        $assetIds = array_values(array_unique(array_map('intval', array_column($rows, 'asset_id'))));
        $orderIds = array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
        $assets = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $assetIds)->select()->toArray();
        $assetMap = array_column($assets, null, 'id');
        $items = ErpRefurbishItem::where([['site_id', '=', $this->site_id]])
            ->whereIn('order_id', $orderIds)->select()->toArray();
        $itemMap = [];
        foreach ($items as $item) {
            $itemMap[(int)$item['order_id']][] = $item;
        }
        foreach ($rows as &$row) {
            $row['asset'] = $assetMap[(int)$row['asset_id']] ?? [];
            $row['items'] = $itemMap[(int)$row['id']] ?? [];
        }
        unset($row);
    }

    private function normalizeItems(array $items, bool $withAmount): array
    {
        $result = [];
        foreach ($items as $item) {
            $name = trim((string)($item['item_name'] ?? ''));
            $type = (string)($item['item_type'] ?? 'other');
            if ($name === '') {
                continue;
            }
            if (!in_array($type, self::ITEM_TYPES, true)) {
                throw new CommonException('整备项目类型不正确');
            }
            $result[] = [
                'item_type' => $type,
                'item_name' => $name,
                'amount' => $withAmount ? ErpMoney::normalize($item['amount'] ?? 0) : '0.00',
                'remark' => trim((string)($item['remark'] ?? '')),
            ];
        }
        return $result;
    }

    private function findOrder(int $id): ErpRefurbishOrder
    {
        $order = ErpRefurbishOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('整备工单不存在');
        }
        return $order;
    }

    private function lockAsset(int $assetId): ErpAsset
    {
        $asset = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $assetId],
        ])->lock(true)->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('ERP资产不存在');
        }
        return $asset;
    }

    private function findUser(int $uid): array
    {
        $allowed = SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $uid],
            ['status', '=', 1],
        ])->count();
        $user = SysUser::where([['uid', '=', $uid], ['status', '=', 1]])
            ->field('uid,username,real_name,mobile')->findOrEmpty();
        if (!$allowed || $user->isEmpty()) {
            throw new CommonException('整备负责人不存在或不属于当前站点');
        }
        return $user->toArray();
    }

    private function userName(array $user): string
    {
        return (string)($user['real_name'] ?: $user['username'] ?: ('员工#' . $user['uid']));
    }

    private function changeAssetStatus(
        ErpAsset $asset,
        string $afterStatus,
        string $action,
        int $documentId,
        int $now,
        array $payload = []
    ): void {
        $beforeStatus = (string)$asset->inventory_status;
        $asset->save([
            'inventory_status' => $afterStatus,
            'version' => (int)$asset->version + 1,
            'update_at' => $now,
        ]);
        ErpAssetCycle::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->cycle_id],
        ])->update(['status' => $afterStatus, 'update_at' => $now]);
        $this->writeStatusLedger($asset, $beforeStatus, $afterStatus, $action, $documentId, $now, $payload);
        $this->writeOperation($asset, 'erp.asset.status_changed.v1', $action, $documentId, array_merge([
            'before_status' => $beforeStatus,
            'after_status' => $afterStatus,
        ], $payload), $now);
    }

    private function writeStatusLedger(
        ErpAsset $asset,
        string $beforeStatus,
        string $afterStatus,
        string $action,
        int $documentId,
        int $now,
        array $payload = []
    ): void {
        ErpStockLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => $this->makeNo('SL'),
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'stock_order_id' => 0,
            'action' => $action,
            'before_status' => $beforeStatus,
            'after_status' => $afterStatus,
            'warehouse_id' => (int)$asset->warehouse_id,
            'location_id' => (int)$asset->location_id,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'occurred_at' => $now,
            'payload' => array_merge(['refurbish_order_id' => $documentId], $payload),
        ]);
    }

    private function writeOperation(
        ErpAsset $asset,
        string $eventName,
        string $action,
        int $documentId,
        array $payload,
        int $now
    ): void {
        ErpOperationEvent::create([
            'site_id' => $this->site_id,
            'event_id' => $this->makeEventId($action, (int)$asset->id),
            'event_name' => $eventName,
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'document_type' => 'refurbishment',
            'document_id' => $documentId,
            'stage' => 'refurbishment',
            'action' => $action,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'payload' => $payload,
            'occurred_at' => $now,
        ]);
    }

    private function writeEvent(ErpAsset $asset, string $eventName, array $payload, int $now): int
    {
        $event = ErpDomainEvent::create(
            $this->site_id,
            $eventName,
            $this->makeEventId(str_replace('.', '-', $eventName), (int)$asset->id),
            'asset',
            (int)$asset->id,
            ['type' => 'staff', 'id' => $this->uid, 'name' => $this->username ?: ''],
            ['plugin' => 'hsx_erp', 'type' => 'refurbishment', 'id' => (int)($payload['refurbish_order_id'] ?? 0)],
            array_merge([
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'source_device_id' => (int)$asset->source_device_id,
            ], $payload),
            $now
        );
        return (int)ErpDomainEvent::writeOutbox($event, $now)->id;
    }

    private function writeReadyForPhotoEvents(ErpAsset $asset, int $documentId, int $now): array
    {
        $sourceSnapshot = (array)$asset->source_snapshot;
        if ((string)($sourceSnapshot['sale_destination'] ?? '') !== ErpDict::SALE_DESTINATION_MALL) {
            return [];
        }
        return [
            $this->writeEvent($asset, 'erp.asset.ready_for_photo.v1', [
                'refurbish_order_id' => $documentId,
                'source_device_id' => (int)$asset->source_device_id,
                'sale_destination' => ErpDict::SALE_DESTINATION_MALL,
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ], $now),
        ];
    }

    private function publish(array $outboxIds): void
    {
        foreach ($outboxIds as $outboxId) {
            PublishOutboxEvent::dispatch(['outbox_id' => (int)$outboxId]);
        }
    }

    private function costType(string $itemType): string
    {
        return match ($itemType) {
            'part' => 'part',
            'labor', 'external' => 'labor',
            'logistics' => 'logistics',
            'inspection' => 'inspection',
            default => 'adjustment',
        };
    }

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT)
            . random_int(100000, 999999);
    }

    private function makeEventId(string $prefix, int $id): string
    {
        return $prefix . '-' . $id . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
    }
}
