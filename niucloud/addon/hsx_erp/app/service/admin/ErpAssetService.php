<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\job\PublishOutboxEvent;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpOutboxEvent;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\model\ErpStockOrder;
use addon\hsx_erp\app\model\ErpStockOrderItem;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpAssetService extends BaseAdminService
{
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
        return $this->pageQuery($query);
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

        return [
            'asset' => $asset->toArray(),
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
                $asset->save([
                    'inventory_status' => ErpDict::INVENTORY_IN_STOCK,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
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
                    'amount_delta' => (float)$asset->purchase_cost,
                    'before_cost' => 0,
                    'after_cost' => (float)$asset->current_cost,
                    'source_plugin' => (string)$order->source_plugin,
                    'source_type' => (string)$order->source_type,
                    'source_id' => (int)$item->source_device_id,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'occurred_at' => $now,
                    'remark' => '确认入库生成初始采购成本',
                ]);
                $this->writeOperation(
                    (int)$asset->id,
                    (int)$asset->cycle_id,
                    'erp.asset.stocked',
                    'confirm_stock_in',
                    $stockOrderId,
                    ['warehouse_id' => $warehouseId, 'location_id' => $locationId]
                );
                $outbox = ErpOutboxEvent::create([
                    'site_id' => $this->site_id,
                    'event_id' => $this->makeEventId('stocked', (int)$asset->id),
                    'event_name' => 'erp.asset.stocked',
                    'aggregate_type' => 'asset',
                    'aggregate_id' => (int)$asset->id,
                    'status' => 'pending',
                    'payload' => [
                        'asset_id' => (int)$asset->id,
                        'cycle_id' => (int)$asset->cycle_id,
                        'stock_order_id' => $stockOrderId,
                        'source_device_id' => (int)$asset->source_device_id,
                    ],
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $outboxIds[] = (int)$outbox->id;
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
                $orderData = [
                    'status' => $pendingCount > 0 ? 'partial_confirmed' : ErpDict::STOCK_ORDER_CONFIRMED,
                    'warehouse_id' => $warehouseId,
                    'remark' => $remark ?: (string)$order->remark,
                    'update_at' => $now,
                ];
                if ($pendingCount === 0) {
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

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT) . random_int(100000, 999999);
    }

    private function makeEventId(string $prefix, int $id): string
    {
        return $prefix . '-' . $id . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
    }
}
