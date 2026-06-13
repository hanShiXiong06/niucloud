<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\job\PublishOutboxEvent;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\ErpLocationAssign;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpRefurbishItem;
use addon\hsx_erp\app\model\ErpRefurbishOrder;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\model\ErpStockOrder;
use addon\hsx_erp\app\model\ErpStockOrderItem;
use addon\hsx_erp\app\support\ErpDomainEvent;
use addon\hsx_erp\app\support\ErpMoney;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpAssetService extends BaseAdminService
{
    /**
     * 是否可查看全部（管理员 is_admin 组看全部，其余员工只看自己负责库位）。
     */
    protected function canViewAll(): bool
    {
        return SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['is_admin', '=', 1],
        ])->count() > 0;
    }

    /**
     * 当前用户的库位过滤范围。
     * null = 不限制（管理员）；[-1] = 无任何负责库位（看不到任何设备）；否则为负责的库位ID集合。
     */
    protected function scopedLocationIds(): ?array
    {
        if ($this->canViewAll()) {
            return null;
        }
        $ids = ErpLocationAssign::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
        ])->column('location_id');
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return empty($ids) ? [-1] : $ids;
    }

    public function getPage(array $where = []): array
    {
        $query = ErpAsset::where([['site_id', '=', $this->site_id]])->order('id desc');
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('asset_no|imei|imei2|sn|model', '%' . $keyword . '%')
                    ->whereOr('source_device_id', '=', is_numeric($keyword) ? (int)$keyword : 0);
            });
        }
        if (!empty($where['inventory_status'])) {
            $query->where('inventory_status', '=', (string)$where['inventory_status']);
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('location_id', '=', (int)$where['location_id']);
        }
        // 员工只看自己负责库位的设备；管理员看全部
        $scope = $this->scopedLocationIds();
        if ($scope !== null) {
            $query->whereIn('location_id', $scope);
        }
        $result = $this->pageQuery($query);
        $this->appendCounterparties($result['data']);
        return $result;
    }

    public function getInfo(int $id): array
    {
        $asset = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('ERP资产不存在');
        }

        $counterparty = (int)$asset->counterparty_id > 0
            ? ErpCounterparty::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$asset->counterparty_id],
            ])->findOrEmpty()->toArray()
            : [];
        return [
            'asset' => $asset->toArray(),
            'counterparty' => $counterparty,
            'stock_ledger' => ErpStockLedger::where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $id],
            ])->order('id desc')->select()->toArray(),
            'cost_ledger' => ErpCostLedger::where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $id],
            ])->order('id desc')->select()->toArray(),
            'timeline' => ErpOperationEvent::where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $id],
            ])->order('occurred_at desc,id desc')->select()->toArray(),
        ];
    }

    public function confirmInbound(int $stockOrderId, array $data = []): array
    {
        $order = ErpStockOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $stockOrderId],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('入库单不存在');
        }

        $assetIds = ErpStockOrderItem::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $stockOrderId],
            ['status', '=', 'pending'],
        ])->column('asset_id');
        if (empty($assetIds)) {
            if ((string)$order->status === ErpDict::STOCK_ORDER_CONFIRMED) {
                return [
                    'confirmed_count' => 0,
                    'existing_count' => (int)$order->device_count,
                    'order' => $order->toArray(),
                ];
            }
            throw new CommonException('入库单没有可确认设备');
        }

        $result = $this->confirmAssetsInbound($assetIds, $data);
        $result['order'] = ErpStockOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $stockOrderId],
        ])->findOrEmpty()->toArray();
        return $result;
    }

    public function confirmInboundByAsset(int $assetId, array $data = []): array
    {
        return $this->confirmAssetsInbound([$assetId], $data);
    }

    public function confirmAssetsInbound(array $assetIds, array $data = []): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));
        if (empty($assetIds)) {
            throw new CommonException('请选择需要确认入库的设备');
        }

        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $locationId = (int)($data['location_id'] ?? 0);
        (new ErpWarehouseService())->validateInboundLocation($warehouseId, $locationId);
        $remark = trim((string)($data['remark'] ?? ''));
        $now = time();
        $outboxIds = [];
        $confirmedAssetIds = [];
        $existingAssetIds = [];
        $orderIds = [];

        Db::startTrans();
        try {
            $items = ErpStockOrderItem::where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 'pending'],
            ])->whereIn('asset_id', $assetIds)->lock(true)->select();
            $pendingItemMap = [];
            foreach ($items as $item) {
                $pendingItemMap[(int)$item->asset_id] = $item;
            }

            $missingAssetIds = array_values(array_diff($assetIds, array_keys($pendingItemMap)));
            if (!empty($missingAssetIds)) {
                $existingAssetIds = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['inventory_status', '=', ErpDict::INVENTORY_IN_STOCK],
                ])->whereIn('id', $missingAssetIds)->column('id');
                $invalidAssetIds = array_diff($missingAssetIds, array_map('intval', $existingAssetIds));
                if (!empty($invalidAssetIds)) {
                    throw new CommonException('部分设备不存在待入库明细，请刷新列表后重试');
                }
            }

            foreach ($pendingItemMap as $item) {
                $stockOrderId = (int)$item->order_id;
                $order = ErpStockOrder::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $stockOrderId],
                ])->lock(true)->findOrEmpty();
                if ($order->isEmpty()) {
                    throw new CommonException('设备所属入库单不存在');
                }
                $orderIds[$stockOrderId] = $stockOrderId;

                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$item->asset_id],
                ])->lock(true)->findOrEmpty();
                if ($asset->isEmpty()) {
                    throw new CommonException('入库设备不存在');
                }
                if ((string)$asset->inventory_status !== ErpDict::INVENTORY_PENDING_IN) {
                    throw new CommonException('设备状态不允许入库：' . ($asset->imei ?: $asset->asset_no));
                }

                $beforeStatus = (string)$asset->inventory_status;
                $purchaseCost = ErpMoney::normalize($asset->purchase_cost);
                $asset->save([
                    'inventory_status' => ErpDict::INVENTORY_IN_STOCK,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'current_cost' => $purchaseCost,
                    'stock_in_at' => $now,
                    'version' => (int)$asset->version + 1,
                    'update_at' => $now,
                ]);
                ErpAssetCycle::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$asset->cycle_id],
                ])->update([
                    'status' => ErpDict::INVENTORY_IN_STOCK,
                    'update_at' => $now,
                ]);
                $item->save(['status' => 'confirmed', 'update_at' => $now]);
                $confirmedAssetIds[] = (int)$asset->id;

                ErpStockLedger::create([
                    'site_id' => $this->site_id,
                    'ledger_no' => $this->makeNo('SL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'stock_order_id' => $stockOrderId,
                    'action' => 'stock_in',
                    'before_status' => $beforeStatus,
                    'after_status' => ErpDict::INVENTORY_IN_STOCK,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'occurred_at' => $now,
                    'payload' => ['remark' => $remark],
                ]);
                ErpCostLedger::create([
                    'site_id' => $this->site_id,
                    'ledger_no' => $this->makeNo('CL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'cost_type' => 'purchase',
                    'amount_delta' => $purchaseCost,
                    'before_cost' => '0.00',
                    'after_cost' => $purchaseCost,
                    'source_plugin' => (string)$order->source_plugin,
                    'source_type' => (string)$order->source_type,
                    'source_id' => (int)$item->source_device_id,
                    'counterparty_id' => (int)$asset->counterparty_id,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'occurred_at' => $now,
                    'remark' => '确认入库生成初始采购成本',
                ]);
                $this->writeOperation(
                    (int)$asset->id,
                    (int)$asset->cycle_id,
                    'erp.asset.stocked.v1',
                    'confirm_stock_in',
                    $stockOrderId,
                    ['warehouse_id' => $warehouseId, 'location_id' => $locationId]
                );
                $sourceSnapshot = (array)$asset->source_snapshot;
                $payableAmount = ErpMoney::normalize($sourceSnapshot['payable_amount'] ?? $purchaseCost);
                $paidAmount = ErpMoney::normalize($sourceSnapshot['paid_amount'] ?? 0);
                $settlementStatus = array_key_exists('settlement_status', $sourceSnapshot)
                    ? (string)$sourceSnapshot['settlement_status']
                    : (ErpMoney::compare($payableAmount, '0.00') === 0 ? 'not_applicable' : 'unknown');
                $eventId = $this->makeEventId('stocked', (int)$asset->id);
                $domainEvent = ErpDomainEvent::create(
                    $this->site_id,
                    'erp.asset.stocked.v1',
                    $eventId,
                    'asset',
                    (int)$asset->id,
                    ['type' => 'staff', 'id' => $this->uid, 'name' => $this->username ?: ''],
                    [
                        'plugin' => (string)$order->source_plugin,
                        'type' => (string)$order->source_type,
                        'id' => (int)$item->source_device_id,
                    ],
                    [
                        'asset_id' => (int)$asset->id,
                        'cycle_id' => (int)$asset->cycle_id,
                        'stock_order_id' => $stockOrderId,
                        'source_device_id' => (int)$asset->source_device_id,
                        'counterparty_id' => (int)$asset->counterparty_id,
                        'source_member_id' => (int)$asset->source_member_id,
                        'ownership_type' => (string)$asset->ownership_type,
                        'payable_amount' => $payableAmount,
                        'paid_amount' => $paidAmount,
                        'settlement_status' => $settlementStatus,
                    ],
                    $now
                );
                $outbox = ErpDomainEvent::writeOutbox($domainEvent, $now);
                $outboxIds[] = (int)$outbox->id;
                $outboxIds = array_merge(
                    $outboxIds,
                    $this->applyPostInboundRefurbishmentDecision($asset, $stockOrderId, $now)
                );
            }

            foreach ($orderIds as $orderId) {
                $order = ErpStockOrder::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $orderId],
                ])->lock(true)->findOrEmpty();
                $pendingCount = ErpStockOrderItem::where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', $orderId],
                    ['status', '=', 'pending'],
                ])->count();
                $confirmedCount = ErpStockOrderItem::where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', $orderId],
                    ['status', '=', ErpDict::STOCK_ITEM_CONFIRMED],
                ])->count();
                $rejectedCount = ErpStockOrderItem::where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', $orderId],
                    ['status', '=', ErpDict::STOCK_ITEM_REJECTED],
                ])->count();
                $orderStatus = ErpDict::STOCK_ORDER_DRAFT;
                if ($pendingCount === 0 && $rejectedCount === 0) {
                    $orderStatus = ErpDict::STOCK_ORDER_CONFIRMED;
                } elseif ($confirmedCount > 0) {
                    $orderStatus = ErpDict::STOCK_ORDER_PARTIAL_CONFIRMED;
                } elseif ($pendingCount === 0 && $rejectedCount > 0) {
                    $orderStatus = ErpDict::STOCK_ORDER_REJECTED;
                }
                $orderData = [
                    'status' => $orderStatus,
                    'warehouse_id' => $warehouseId,
                    'remark' => $remark ?: (string)$order->remark,
                    'update_at' => $now,
                ];
                if ($orderStatus === ErpDict::STOCK_ORDER_CONFIRMED) {
                    $orderData['confirmed_by'] = $this->uid;
                    $orderData['confirmed_at'] = $now;
                }
                $order->save($orderData);
            }

            Db::commit();
            foreach ($outboxIds as $outboxId) {
                PublishOutboxEvent::dispatch(['outbox_id' => $outboxId]);
            }
            return [
                'confirmed_count' => count($confirmedAssetIds),
                'existing_count' => count($existingAssetIds),
                'asset_ids' => $confirmedAssetIds,
                'order_ids' => array_values($orderIds),
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    private function writeOperation(
        int $assetId,
        int $cycleId,
        string $eventName,
        string $action,
        int $documentId,
        array $payload
    ): void {
        ErpOperationEvent::create([
            'site_id' => $this->site_id,
            'event_id' => $this->makeEventId($action, $assetId),
            'event_name' => $eventName,
            'asset_id' => $assetId,
            'cycle_id' => $cycleId,
            'document_type' => 'stock_in',
            'document_id' => $documentId,
            'stage' => 'inbound',
            'action' => $action,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'payload' => $payload,
            'occurred_at' => time(),
        ]);
    }

    private function applyPostInboundRefurbishmentDecision(ErpAsset $asset, int $stockOrderId, int $now): array
    {
        $sourceSnapshot = (array)$asset->source_snapshot;
        $plan = $this->normalizeRefurbishmentPlan((array)($sourceSnapshot['refurbishment'] ?? []));
        if ($plan['required']) {
            $refurbishOrder = $this->createRefurbishmentOrderFromDecision($asset, $plan, $stockOrderId, $now);
            $this->writeOperation(
                (int)$asset->id,
                (int)$asset->cycle_id,
                'erp.refurbishment.required.v1',
                'refurbishment_required',
                $stockOrderId,
                [
                    'stock_order_id' => $stockOrderId,
                    'required' => true,
                    'decision_source' => $plan['decision_source'],
                    'reason' => $plan['reason'],
                    'suggested_items' => $plan['suggested_items'],
                    'estimated_cost' => $plan['estimated_cost'],
                    'assignee' => $plan['assignee'],
                    'refurbish_order_id' => (int)$refurbishOrder->id,
                    'refurbish_order_no' => (string)$refurbishOrder->order_no,
                    'next_status' => ErpDict::INVENTORY_REFURBISHING,
                ]
            );
            return [
                $this->writeDecisionEvent($asset, 'erp.refurbishment.required.v1', $stockOrderId, [
                    'required' => true,
                    'decision_source' => $plan['decision_source'],
                    'reason' => $plan['reason'],
                    'suggested_items' => $plan['suggested_items'],
                    'estimated_cost' => $plan['estimated_cost'],
                    'decided_by' => $plan['decided_by'],
                    'assignee' => $plan['assignee'],
                    'decided_at' => $plan['decided_at'],
                    'refurbish_order_id' => (int)$refurbishOrder->id,
                    'refurbish_order_no' => (string)$refurbishOrder->order_no,
                    'next_status' => ErpDict::INVENTORY_REFURBISHING,
                ], $now),
            ];
        }

        $beforeStatus = (string)$asset->inventory_status;
        $asset->save([
            'inventory_status' => ErpDict::INVENTORY_PENDING_PRICING,
            'version' => (int)$asset->version + 1,
            'update_at' => $now,
        ]);
        ErpAssetCycle::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->cycle_id],
        ])->update([
            'status' => ErpDict::INVENTORY_PENDING_PRICING,
            'update_at' => $now,
        ]);
        ErpStockLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => $this->makeNo('SL'),
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'stock_order_id' => $stockOrderId,
            'action' => 'skip_refurbishment',
            'before_status' => $beforeStatus,
            'after_status' => ErpDict::INVENTORY_PENDING_PRICING,
            'warehouse_id' => (int)$asset->warehouse_id,
            'location_id' => (int)$asset->location_id,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'occurred_at' => $now,
            'payload' => [
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $plan['reason'] ?: '默认无需整备，入库后直接进入待销售定价',
            ],
        ]);
        $this->writeOperation(
            (int)$asset->id,
            (int)$asset->cycle_id,
            'erp.refurbishment.skipped.v1',
            'skip_refurbishment',
            $stockOrderId,
            [
                'stock_order_id' => $stockOrderId,
                'required' => false,
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $plan['reason'] ?: '默认无需整备，入库后直接进入待销售定价',
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ]
        );

        return array_merge([
            $this->writeDecisionEvent($asset, 'erp.refurbishment.skipped.v1', $stockOrderId, [
                'required' => false,
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $plan['reason'] ?: '默认无需整备，入库后直接进入待销售定价',
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ], $now),
        ], $this->writeReadyForPhotoEvents($asset, $stockOrderId, $now));
    }

    private function writeReadyForPhotoEvents(ErpAsset $asset, int $documentId, int $now): array
    {
        $sourceSnapshot = (array)$asset->source_snapshot;
        if ((string)($sourceSnapshot['sale_destination'] ?? '') !== ErpDict::SALE_DESTINATION_MALL) {
            return [];
        }
        return [
            $this->writeDecisionEvent($asset, 'erp.asset.ready_for_photo.v1', $documentId, [
                'source_device_id' => (int)$asset->source_device_id,
                'sale_destination' => ErpDict::SALE_DESTINATION_MALL,
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ], $now),
        ];
    }

    private function normalizeRefurbishmentPlan(array $plan): array
    {
        $items = $plan['suggested_items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }
        return [
            'required' => (bool)($plan['required'] ?? false),
            'decision_source' => (string)($plan['decision_source'] ?? 'default'),
            'reason' => trim((string)($plan['reason'] ?? '')),
            'suggested_items' => array_values($items),
            'estimated_cost' => ErpMoney::normalize($plan['estimated_cost'] ?? 0),
            'decided_by' => (array)($plan['decided_by'] ?? []),
            'assignee' => (array)($plan['assignee'] ?? []),
            'decided_at' => (int)($plan['decided_at'] ?? 0),
        ];
    }

    private function createRefurbishmentOrderFromDecision(ErpAsset $asset, array $plan, int $stockOrderId, int $now): ErpRefurbishOrder
    {
        $assignee = (array)$plan['assignee'];
        $assignedUid = (int)($assignee['id'] ?? 0);
        $assignedName = trim((string)($assignee['name'] ?? ''));
        if ($assignedUid <= 0) {
            $assignedUid = $this->uid;
            $assignedName = $this->username ?: '';
        }
        if ($assignedName === '') {
            $assignedName = $assignedUid > 0 ? ('员工#' . $assignedUid) : '待分配';
        }

        $order = ErpRefurbishOrder::create([
            'site_id' => $this->site_id,
            'order_no' => $this->makeNo('ZB'),
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'status' => ErpDict::REFURBISH_PROCESSING,
            'assigned_uid' => $assignedUid,
            'assigned_name' => $assignedName,
            'planned_finish_at' => 0,
            'started_at' => $now,
            'remark' => trim((string)$plan['reason']),
            'create_at' => $now,
            'update_at' => $now,
        ]);

        foreach ($plan['suggested_items'] as $item) {
            $name = is_array($item) ? trim((string)($item['item_name'] ?? $item['name'] ?? '')) : trim((string)$item);
            if ($name === '') {
                continue;
            }
            ErpRefurbishItem::create([
                'site_id' => $this->site_id,
                'order_id' => (int)$order->id,
                'item_type' => is_array($item) ? (string)($item['item_type'] ?? $item['type'] ?? 'other') : 'other',
                'item_name' => $name,
                'amount' => '0.00',
                'remark' => '',
                'create_at' => $now,
                'update_at' => $now,
            ]);
        }

        $beforeStatus = (string)$asset->inventory_status;
        $asset->save([
            'inventory_status' => ErpDict::INVENTORY_REFURBISHING,
            'version' => (int)$asset->version + 1,
            'update_at' => $now,
        ]);
        ErpAssetCycle::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->cycle_id],
        ])->update([
            'status' => ErpDict::INVENTORY_REFURBISHING,
            'update_at' => $now,
        ]);
        ErpStockLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => $this->makeNo('SL'),
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'stock_order_id' => $stockOrderId,
            'action' => 'auto_create_refurbishment',
            'before_status' => $beforeStatus,
            'after_status' => ErpDict::INVENTORY_REFURBISHING,
            'warehouse_id' => (int)$asset->warehouse_id,
            'location_id' => (int)$asset->location_id,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'occurred_at' => $now,
            'payload' => [
                'refurbish_order_id' => (int)$order->id,
                'refurbish_order_no' => (string)$order->order_no,
                'reason' => $plan['reason'],
                'estimated_cost' => $plan['estimated_cost'],
            ],
        ]);

        return $order;
    }

    private function writeDecisionEvent(ErpAsset $asset, string $eventName, int $stockOrderId, array $payload, int $now): int
    {
        $event = ErpDomainEvent::create(
            $this->site_id,
            $eventName,
            $this->makeEventId(str_replace('.', '-', $eventName), (int)$asset->id),
            'asset',
            (int)$asset->id,
            ['type' => 'staff', 'id' => $this->uid, 'name' => $this->username ?: ''],
            ['plugin' => 'hsx_erp', 'type' => 'stock_in', 'id' => $stockOrderId],
            array_merge([
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'stock_order_id' => $stockOrderId,
                'source_device_id' => (int)$asset->source_device_id,
                'counterparty_id' => (int)$asset->counterparty_id,
            ], $payload),
            $now
        );
        return (int)ErpDomainEvent::writeOutbox($event, $now)->id;
    }

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT) . random_int(100000, 999999);
    }

    private function makeEventId(string $prefix, int $id): string
    {
        return $prefix . '-' . $id . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
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
