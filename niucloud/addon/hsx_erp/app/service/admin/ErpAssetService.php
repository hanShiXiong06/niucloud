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
use addon\hsx_erp\app\model\ErpOutboundItem;
use addon\hsx_erp\app\model\ErpOutboundOrder;
use addon\hsx_erp\app\model\ErpRefurbishItem;
use addon\hsx_erp\app\model\ErpRefurbishOrder;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\model\ErpStockOrder;
use addon\hsx_erp\app\model\ErpStockOrderItem;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
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
        $query = ErpAsset::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('asset_no|imei|imei2|sn|model', '%' . $keyword . '%')
                    ->whereOr('source_device_id', '=', is_numeric($keyword) ? (int)$keyword : 0);
            });
        }
        if (!empty($where['inventory_status'])) {
            if ((string)$where['inventory_status'] === 'sold') {
                // 已售/下架 = 已锁定(挂单) + 已出库
                $query->whereIn('inventory_status', [ErpDict::INVENTORY_LOCKED, ErpDict::INVENTORY_OUTBOUND]);
            } else {
                $query->where('inventory_status', '=', (string)$where['inventory_status']);
            }
        }
        // 可出库设备(在库/待定价/可售)，供出库选择用
        if (!empty($where['sellable'])) {
            $query->whereIn('inventory_status', [
                ErpDict::INVENTORY_IN_STOCK,
                ErpDict::INVENTORY_PENDING_PRICING,
                ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
            ]);
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
        // 合计(对当前筛选的全集, 非仅当前页)
        $aggQuery = clone $query;
        $totalCount = (clone $aggQuery)->count();
        $totalCost = round((float)(clone $aggQuery)->sum('current_cost'), 2);
        $totalSale = round((float)(clone $aggQuery)->sum('current_sale_price'), 2);

        // 排序(白名单字段, 防注入)
        $sortMap = [
            'current_cost' => 'current_cost', 'current_sale_price' => 'current_sale_price',
            'stock_in_at' => 'stock_in_at', 'stock_out_at' => 'stock_out_at', 'id' => 'id',
        ];
        $sortField = $sortMap[(string)($where['sort_field'] ?? '')] ?? 'id';
        $sortOrder = strtolower((string)($where['sort_order'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $query->order($sortField, $sortOrder);

        $result = $this->pageQuery($query);
        $this->appendCounterparties($result['data']);
        $this->appendWarehouseNames($result['data']);
        $this->appendStatusLabels($result['data']);
        $result['summary'] = [
            'count'      => (int)$totalCount,
            'total_cost' => $totalCost,
            'total_sale' => $totalSale,
        ];
        return $result;
    }

    /**
     * 细化状态展示：给每行补 status_text(明确中文) + status_type(标签色)。
     * 出库要 join 出库单区分 已售(同行)/报废/其他出库；盘亏→盘亏丢失。
     */
    private function appendStatusLabels(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        // 出库的资产，查它最近一张出库明细对应的出库类型，区分已售/报废
        $outAssetIds = [];
        foreach ($rows as $r) {
            if ((string)($r['inventory_status'] ?? '') === ErpDict::INVENTORY_OUTBOUND) {
                $outAssetIds[] = (int)$r['id'];
            }
        }
        $dispMap = [];
        if (!empty($outAssetIds)) {
            $items = ErpOutboundItem::where([['site_id', '=', $this->site_id]])
                ->whereIn('asset_id', array_values(array_unique($outAssetIds)))
                ->order('id desc')->field('asset_id,outbound_id')->select()->toArray();
            $obIds = array_values(array_unique(array_filter(array_column($items, 'outbound_id'))));
            $typeMap = [];
            if (!empty($obIds)) {
                foreach (ErpOutboundOrder::where([['site_id', '=', $this->site_id]])->whereIn('id', $obIds)->field('id,outbound_type')->select()->toArray() as $o) {
                    $typeMap[(int)$o['id']] = (string)$o['outbound_type'];
                }
            }
            foreach ($items as $it) {
                $aid = (int)$it['asset_id'];
                if (!isset($dispMap[$aid])) { // 取最近一条(已按 id desc)
                    $dispMap[$aid] = $typeMap[(int)$it['outbound_id']] ?? '';
                }
            }
        }

        $textMap = [
            ErpDict::INVENTORY_PENDING_IN          => '待入库',
            ErpDict::INVENTORY_INBOUND_REJECTED    => '入库驳回',
            ErpDict::INVENTORY_IN_STOCK            => '在库',
            ErpDict::INVENTORY_REFURBISHING        => '整备中',
            ErpDict::INVENTORY_PENDING_PRICING     => '待销售定价',
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE  => '在售',
            ErpDict::INVENTORY_LOCKED              => '销售锁定',
            ErpDict::INVENTORY_LOST                => '丢失',
        ];
        $typeTag = [
            ErpDict::INVENTORY_PENDING_IN          => 'warning',
            ErpDict::INVENTORY_INBOUND_REJECTED    => 'danger',
            ErpDict::INVENTORY_IN_STOCK            => 'success',
            ErpDict::INVENTORY_REFURBISHING        => 'warning',
            ErpDict::INVENTORY_PENDING_PRICING     => 'primary',
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE  => 'success',
            ErpDict::INVENTORY_LOCKED              => 'info',
            ErpDict::INVENTORY_LOST                => 'danger',
        ];
        foreach ($rows as &$row) {
            $st = (string)($row['inventory_status'] ?? '');
            if ($st === ErpDict::INVENTORY_OUTBOUND) {
                $disp = $dispMap[(int)$row['id']] ?? '';
                if ($disp === ErpDict::OUTBOUND_TYPE_PEER_SALE) {
                    $row['status_text'] = '已售(同行)';
                    $row['status_type'] = 'info';
                } elseif ($disp === ErpDict::OUTBOUND_TYPE_SCRAP) {
                    $row['status_text'] = '已报废';
                    $row['status_type'] = 'danger';
                } else {
                    $row['status_text'] = '已出库';
                    $row['status_type'] = 'info';
                }
            } else {
                $row['status_text'] = $textMap[$st] ?? ($st ?: '-');
                $row['status_type'] = $typeTag[$st] ?? 'info';
            }
        }
        unset($row);
    }

    /**
     * 给资产列表行补上仓库/库位名称（资产表只存 id），供前端调拨弹框等反显当前库位。
     */
    private function appendWarehouseNames(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        $whIds = array_values(array_unique(array_filter(array_map(fn($r) => (int)($r['warehouse_id'] ?? 0), $rows))));
        $locIds = array_values(array_unique(array_filter(array_map(fn($r) => (int)($r['location_id'] ?? 0), $rows))));
        $whMap = [];
        if (!empty($whIds)) {
            foreach (ErpWarehouse::where([['site_id', '=', $this->site_id]])->whereIn('id', $whIds)->field('id,warehouse_name')->select()->toArray() as $w) {
                $whMap[(int)$w['id']] = (string)($w['warehouse_name'] ?? '');
            }
        }
        $locMap = [];
        if (!empty($locIds)) {
            foreach (ErpWarehouseLocation::where([['site_id', '=', $this->site_id]])->whereIn('id', $locIds)->field('id,location_name')->select()->toArray() as $l) {
                $locMap[(int)$l['id']] = (string)($l['location_name'] ?? '');
            }
        }
        foreach ($rows as &$row) {
            $row['warehouse_name'] = $whMap[(int)($row['warehouse_id'] ?? 0)] ?? '';
            $row['location_name'] = $locMap[(int)($row['location_id'] ?? 0)] ?? '';
        }
        unset($row);
    }

    /**
     * 实时调整在库设备成本（写成本流水留痕，不改已出库/已售/盘亏的）。
     * @param int $assetId 资产ID
     * @param float $newCost 新成本(>=0)
     * @param string $reason 调整原因
     */
    public function adjustCost(int $assetId, float $newCost, string $reason = ''): array
    {
        if ($newCost < 0) {
            throw new CommonException('成本不能为负');
        }
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('ERP资产不存在');
        }
        $blocked = [ErpDict::INVENTORY_OUTBOUND, ErpDict::INVENTORY_LOST, ErpDict::INVENTORY_PENDING_IN, ErpDict::INVENTORY_INBOUND_REJECTED];
        if (in_array((string)$asset->inventory_status, $blocked, true)) {
            throw new CommonException('该设备当前状态不可调成本');
        }
        $before = round((float)$asset->current_cost, 2);
        $newCost = round($newCost, 2);
        if (abs($newCost - $before) < 0.001) {
            throw new CommonException('成本未变化');
        }
        $now = time();
        Db::transaction(function () use ($asset, $before, $newCost, $reason, $now) {
            $asset->save([
                'current_cost' => $newCost,
                'version'      => (int)$asset->version + 1,
                'update_at'    => $now,
            ]);
            ErpCostLedger::create([
                'site_id'         => $this->site_id,
                'ledger_no'       => $this->makeNo('CL'),
                'asset_id'        => (int)$asset->id,
                'cycle_id'        => (int)$asset->cycle_id,
                'cost_type'       => 'manual_adjust',
                'amount_delta'    => round($newCost - $before, 2),
                'before_cost'     => $before,
                'after_cost'      => $newCost,
                'source_type'     => 'manual',
                'source_id'       => 0,
                'counterparty_id' => (int)$asset->counterparty_id,
                'operator_id'     => $this->uid,
                'operator_name'   => $this->username ?: '',
                'occurred_at'     => $now,
                'remark'          => '手动调成本' . ($reason !== '' ? '：' . $reason : ''),
            ]);
        });
        return ['asset_id' => (int)$asset->id, 'before_cost' => $before, 'after_cost' => $newCost];
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

        // 定价归属（避免多处重复定价）：
        //  - 商城销路 → 委托中台拍照定价，仍置待定价，由中台定价回流后转可售；
        //  - 非商城销路且已带售价（回收侧已定价 / 手工建档已敲价）→ 入库即可售，直接用该售价，不再走 ERP 定价。
        $sourceSnapshot = (array)$asset->source_snapshot;
        $saleDestination = (string)($sourceSnapshot['sale_destination'] ?? '');
        $suggestedPrice = round((float)($sourceSnapshot['suggested_sale_price'] ?? $asset->current_sale_price ?? 0), 2);
        $isMall = $saleDestination === ErpDict::SALE_DESTINATION_MALL;
        $directSellable = !$isMall && $suggestedPrice > 0;
        $nextStatus = $directSellable ? ErpDict::INVENTORY_AVAILABLE_FOR_SALE : ErpDict::INVENTORY_PENDING_PRICING;
        $decisionReason = $plan['reason'] ?: ($directSellable
            ? '默认无需整备，已带售价，入库即可售'
            : '默认无需整备，入库后进入待销售定价');

        $assetSave = [
            'inventory_status' => $nextStatus,
            'version' => (int)$asset->version + 1,
            'update_at' => $now,
        ];
        if ($directSellable) {
            $assetSave['current_sale_price'] = $suggestedPrice;
        }
        $asset->save($assetSave);
        ErpAssetCycle::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->cycle_id],
        ])->update([
            'status' => $nextStatus,
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
            'after_status' => $nextStatus,
            'warehouse_id' => (int)$asset->warehouse_id,
            'location_id' => (int)$asset->location_id,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'occurred_at' => $now,
            'payload' => [
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $decisionReason,
                'sale_price' => $directSellable ? $suggestedPrice : null,
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
                'reason' => $decisionReason,
                'next_status' => $nextStatus,
            ]
        );

        return array_merge([
            $this->writeDecisionEvent($asset, 'erp.refurbishment.skipped.v1', $stockOrderId, [
                'required' => false,
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $decisionReason,
                'next_status' => $nextStatus,
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
