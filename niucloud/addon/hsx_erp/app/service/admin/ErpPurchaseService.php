<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpWarehouse;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpPurchaseService extends BaseAdminService
{
    private static bool $schemaEnsured = false;

    public function getPage(array $where): array
    {
        (new ErpWarehouseService())->ensureReady();
        $this->ensureSchema();
        $orderTable = (new ErpPurchaseOrder())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($orderTable . ' o', 'o.id = a.purchase_order_id AND o.site_id = a.site_id')
            ->where([['a.site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|a.party_name|a.warehouse_name|a.location_name|o.purchase_no|o.m_no', '%' . $kw . '%');
        }
        if (!empty($where['finance_status'])) {
            $query->where('o.finance_status', '=', (string)$where['finance_status']);
        }
        if (!empty($where['status'])) {
            $query->where('o.status', '=', (string)$where['status']);
        }
        return $query->field([
            'a.id',
            'a.asset_no',
            'a.purchase_order_id',
            'a.purchase_item_id',
            'a.party_id',
            'a.party_name',
            'a.warehouse_id',
            'a.warehouse_name',
            'a.location_id',
            'a.location_name',
            'a.imei',
            'a.sn',
            'a.model',
            'a.spec',
            'a.inspector_uid',
            'a.inspector_name',
            'a.estimate_sale_price',
            'a.image_urls',
            'a.quality_remark',
            'a.purchase_cost',
            'a.adjust_cost',
            'a.refurbish_cost',
            'a.total_cost',
            'a.status',
            'a.create_at',
            'o.status as order_status',
            'o.purchase_no',
            'o.m_no',
            'o.total_cost as order_total_cost',
            'o.paid_amount',
            'o.payable_amount',
            'o.finance_status',
            'o.purchaser_name',
            'o.purchase_at',
            'o.capital_account_name',
        ])->order('a.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $order = $this->findOrder($id)->toArray();
        $order['items'] = ErpPurchaseItem::where([
            ['site_id', '=', $this->site_id],
            ['purchase_order_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        $assetIds = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['purchase_order_id', '=', $id],
        ])->column('id');
        $payableQuery = ErpPayable::where([['site_id', '=', $this->site_id]]);
        if (!empty($assetIds)) {
            $payableQuery->where(function ($query) use ($id, $assetIds) {
                $query->where([['source_type', '=', 'purchase'], ['source_id', '=', $id]])
                    ->whereOr(function ($q) use ($assetIds) {
                        $q->where('source_type', '=', 'purchase_asset')->whereIn('source_id', $assetIds);
                    });
            });
        } else {
            $payableQuery->where([['source_type', '=', 'purchase'], ['source_id', '=', $id]]);
        }
        $order['payables'] = $payableQuery->order('id asc')->select()->toArray();
        return $order;
    }

    public function create(array $data): int
    {
        $this->ensureSchema();
        (new ErpStockService())->ensureSchema();
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请至少录入一台机器');
        }
        $partyName = trim((string)($data['party_name'] ?? ''));
        if ($partyName === '') {
            throw new CommonException('请填写采购渠道/客户');
        }
        $now = time();
        $orderId = 0;
        Db::transaction(function () use ($data, $items, $partyName, $now, &$orderId) {
            $party = $this->ensureParty((int)($data['party_id'] ?? 0), $partyName, (string)($data['m_no'] ?? ''), 'supplier');
            $partyName = (string)$party->party_name;
            $purchaseNo = ErpLedgerService::makeNo('PO');
            $totalCost = 0.0;
            foreach ($items as $item) {
                $totalCost += round((float)($item['purchase_cost'] ?? 0), 2);
            }
            if ($totalCost <= 0) {
                throw new CommonException('采购成本必须大于0');
            }
            $paidAmount = round((float)($data['paid_amount'] ?? 0), 2);
            if ($paidAmount < 0) {
                throw new CommonException('本次付款不能小于0');
            }
            if ($paidAmount > $totalCost + 0.0001) {
                throw new CommonException('本次付款不能大于采购成本');
            }
            $capitalAccountId = (int)($data['capital_account_id'] ?? 0);
            $capitalAccountName = '';
            $settleMethod = trim((string)($data['settle_method'] ?? ''));
            if ($paidAmount > 0) {
                $account = $this->resolveCapitalAccount($capitalAccountId);
                $capitalAccountId = (int)$account->id;
                $capitalAccountName = (string)$account->account_name;
                $settleMethod = $this->accountTypeLabel((string)$account->account_type);
            }
            $warehouseService = new ErpWarehouseService();
            $resolvedItems = [];
            foreach ($items as $item) {
                $itemWarehouseId = (int)($item['warehouse_id'] ?? 0);
                $itemLocationId = (int)($item['location_id'] ?? 0);
                if ($itemWarehouseId <= 0) {
                    $itemWarehouseId = (int)($data['warehouse_id'] ?? 0);
                    $itemLocationId = (int)($data['location_id'] ?? 0);
                }
                [$itemWarehouse, $itemLocation] = $warehouseService->validateInboundLocation(
                    $itemWarehouseId,
                    $itemLocationId
                );
                if ((string)$itemWarehouse->warehouse_type === 'consignment') {
                    throw new CommonException('代卖仓不能走采购开单，请使用代卖登记流程');
                }
                $resolvedItems[] = [
                    'item' => $item,
                    'warehouse' => $itemWarehouse,
                    'location' => $itemLocation,
                    'asset_flow' => $this->warehouseAssetFlow($itemWarehouse),
                ];
            }
            $firstWarehouse = $resolvedItems[0]['warehouse'];
            $firstLocation = $resolvedItems[0]['location'];
            $sameWarehouse = true;
            $sameLocation = true;
            foreach ($resolvedItems as $resolved) {
                if ((int)$resolved['warehouse']->id !== (int)$firstWarehouse->id) {
                    $sameWarehouse = false;
                    $sameLocation = false;
                    break;
                }
                if ((int)$resolved['location']->id !== (int)$firstLocation->id) {
                    $sameLocation = false;
                }
            }
            $purchaser = (new ErpStaffService())->resolve((int)($data['purchaser_uid'] ?? 0), '采购员');
            $warehouseId = $sameWarehouse ? (int)$firstWarehouse->id : 0;
            $warehouseName = $sameWarehouse ? (string)$firstWarehouse->warehouse_name : '多仓库';
            $locationId = ($sameWarehouse && $sameLocation) ? (int)$firstLocation->id : 0;
            $locationName = ($sameWarehouse && $sameLocation) ? (string)$firstLocation->location_name : ($sameWarehouse ? '多库位' : '');
            $order = ErpPurchaseOrder::create([
                'site_id' => $this->site_id,
                'purchase_no' => $purchaseNo,
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'm_no' => trim((string)($data['m_no'] ?? '')),
                'purchase_channel' => trim((string)($data['purchase_channel'] ?? '')),
                'settle_method' => $settleMethod,
                'capital_account_id' => $capitalAccountId,
                'capital_account_name' => $capitalAccountName,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouseName,
                'location_id' => $locationId,
                'location_name' => $locationName,
                'purchaser_uid' => (int)$purchaser['uid'],
                'purchaser_name' => (string)$purchaser['name'],
                'inspector_uid' => 0,
                'inspector_name' => '',
                'total_cost' => $totalCost,
                'paid_amount' => 0,
                'payable_amount' => $totalCost,
                'finance_status' => ErpDict::STATUS_PENDING,
                'status' => ErpDict::STATUS_COMPLETED,
                'source_plugin' => (string)($data['source_plugin'] ?? 'erp'),
                'source_type' => (string)($data['source_type'] ?? 'manual'),
                'source_id' => (string)($data['source_id'] ?? ''),
                'operator_uid' => (int)$this->uid,
                'operator_name' => (string)$this->username,
                'purchase_at' => (int)($data['purchase_at'] ?? $now),
                'remark' => trim((string)($data['remark'] ?? '')),
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $orderId = (int)$order->id;
            $payableItems = [];
            foreach ($resolvedItems as $resolved) {
                $item = $resolved['item'];
                $warehouse = $resolved['warehouse'];
                $location = $resolved['location'];
                $assetFlow = $resolved['asset_flow'];
                $itemWarehouseId = (int)$warehouse->id;
                $itemWarehouseName = (string)$warehouse->warehouse_name;
                $itemLocationId = (int)$location->id;
                $itemLocationName = (string)$location->location_name;
                $cost = round((float)($item['purchase_cost'] ?? 0), 2);
                if ($cost <= 0) {
                    throw new CommonException('机器采购成本必须大于0');
                }
                $inspectorUid = (int)($item['inspector_uid'] ?? 0);
                $inspector = $inspectorUid > 0 ? (new ErpStaffService())->resolve($inspectorUid, '质检员') : ['uid' => 0, 'name' => ''];
                $estimateSalePrice = round((float)($item['estimate_sale_price'] ?? 0), 2);
                if ($estimateSalePrice < 0) {
                    throw new CommonException('预计卖价不能小于0');
                }
                $purchaseItem = ErpPurchaseItem::create([
                    'site_id' => $this->site_id,
                    'purchase_order_id' => $orderId,
                    'warehouse_id' => $itemWarehouseId,
                    'warehouse_name' => $itemWarehouseName,
                    'location_id' => $itemLocationId,
                    'location_name' => $itemLocationName,
                    'imei' => trim((string)($item['imei'] ?? '')),
                    'sn' => trim((string)($item['sn'] ?? '')),
                    'model' => trim((string)($item['model'] ?? '')),
                    'spec' => trim((string)($item['spec'] ?? '')),
                    'category_id' => (int)($item['category_id'] ?? 0),
                    'category_name' => trim((string)($item['category_name'] ?? '')),
                    'category_path' => $this->normalizeCategoryPath($item['category_path'] ?? []),
                    'inspector_uid' => (int)$inspector['uid'],
                    'inspector_name' => (string)$inspector['name'],
                    'estimate_sale_price' => $estimateSalePrice,
                    'image_urls' => trim((string)($item['image_urls'] ?? '')),
                    'quality_remark' => trim((string)($item['quality_remark'] ?? '')),
                    'purchase_cost' => $cost,
                    'adjust_cost' => 0,
                    'total_cost' => $cost,
                    'status' => ErpDict::ASSET_IN_STOCK,
                    'remark' => trim((string)($item['remark'] ?? '')),
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $asset = ErpAsset::create([
                    'site_id' => $this->site_id,
                    'asset_no' => ErpLedgerService::makeNo('AS'),
                    'purchase_order_id' => $orderId,
                    'purchase_item_id' => (int)$purchaseItem->id,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'warehouse_id' => $itemWarehouseId,
                    'warehouse_name' => $itemWarehouseName,
                    'location_id' => $itemLocationId,
                    'location_name' => $itemLocationName,
                    'imei' => trim((string)($item['imei'] ?? '')),
                    'sn' => trim((string)($item['sn'] ?? '')),
                    'model' => trim((string)($item['model'] ?? '')),
                    'spec' => trim((string)($item['spec'] ?? '')),
                    'category_id' => (int)($item['category_id'] ?? 0),
                    'category_name' => trim((string)($item['category_name'] ?? '')),
                    'category_path' => $this->normalizeCategoryPath($item['category_path'] ?? []),
                    'inspector_uid' => (int)$inspector['uid'],
                    'inspector_name' => (string)$inspector['name'],
                    'estimate_sale_price' => $estimateSalePrice,
                    'retail_price' => round((float)($item['retail_price'] ?? 0), 2),
                    'image_urls' => trim((string)($item['image_urls'] ?? '')),
                    'quality_remark' => trim((string)($item['quality_remark'] ?? '')),
                    'remark_public' => trim((string)($item['remark_public'] ?? '')),
                    'remark_internal' => trim((string)($item['remark_internal'] ?? '')),
                    'purchase_cost' => $cost,
                    'total_cost' => $cost,
                    'refurbish_status' => $assetFlow['refurbish_status'],
                    'sale_target' => $assetFlow['sale_target'],
                    'listing_status' => $this->listingStatusByWarehouse($warehouse, $assetFlow['sale_target'], trim((string)($item['image_urls'] ?? '')), $estimateSalePrice),
                    'status' => ErpDict::ASSET_IN_STOCK,
                    'source_plugin' => (string)($data['source_plugin'] ?? 'erp'),
                    'source_type' => (string)($data['source_type'] ?? 'manual'),
                    'source_id' => (string)($data['source_id'] ?? ''),
                    'remark' => trim((string)($item['remark'] ?? '')),
                    'stock_in_at' => (int)($data['purchase_at'] ?? $now),
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $purchaseItem->save(['asset_id' => (int)$asset->id, 'update_at' => $now]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'inbound',
                    'after_status' => ErpDict::ASSET_IN_STOCK,
                    'source_type' => 'purchase',
                    'source_id' => $orderId,
                    'source_no' => $purchaseNo,
                    'after_warehouse_id' => $itemWarehouseId,
                    'after_warehouse_name' => $itemWarehouseName,
                    'after_location_id' => $itemLocationId,
                    'after_location_name' => $itemLocationName,
                    'after_total_cost' => $cost,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'occurred_at' => (int)($data['purchase_at'] ?? $now),
                    'remark' => '采购入库',
                ]);
                (new ErpLedgerService())->account([
                    'biz_type' => 'purchase',
                    'direction' => 'increase',
                    'amount' => $cost,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'purchase',
                    'source_id' => $orderId,
                    'source_no' => $purchaseNo,
                    'remark' => '采购成本',
                ]);
                $payable = ErpPayable::create([
                    'site_id' => $this->site_id,
                    'payable_no' => ErpLedgerService::makeNo('AP'),
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'source_type' => 'purchase_asset',
                    'source_id' => (int)$asset->id,
                    'source_no' => (string)$asset->asset_no,
                    'amount' => $cost,
                    'settled_amount' => 0,
                    'status' => ErpDict::STATUS_PENDING,
                    'occurred_at' => (int)($data['purchase_at'] ?? $now),
                    'remark' => '设备采购应付',
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $payableItems[] = [
                    'payable_id' => (int)$payable->id,
                    'remain' => $cost,
                ];
            }
            if ($paidAmount > 0) {
                $paymentItems = [];
                $left = $paidAmount;
                foreach ($payableItems as $item) {
                    if ($left <= 0) {
                        break;
                    }
                    $apply = min($left, (float)$item['remain']);
                    if ($apply <= 0) {
                        continue;
                    }
                    $paymentItems[] = ['payable_id' => (int)$item['payable_id'], 'amount' => $apply];
                    $left = round($left - $apply, 2);
                }
                (new ErpFinanceService())->confirmPayableItemsInTransaction((int)$party->id, $paymentItems, [
                    'capital_account_id' => $capitalAccountId,
                    'confirmed_at' => (int)($data['purchase_at'] ?? $now),
                    'remark' => '采购开单付款',
                ]);
            }
        });
        return $orderId;
    }

    public function cancel(int $id, string $remark = ''): bool
    {
        Db::transaction(function () use ($id, $remark) {
            $now = time();
            $order = $this->findOrder($id);
            if ((string)$order->status !== ErpDict::STATUS_COMPLETED) {
                throw new CommonException('只有已完成且未撤销的采购单可以撤销');
            }
            if ((float)$order->paid_amount > 0 || (string)$order->finance_status !== ErpDict::STATUS_PENDING) {
                throw new CommonException('该采购单已经形成财务事实，请走退货或成本调整');
            }

            $payables = ErpPayable::alias('p')
                ->leftJoin((new ErpAsset())->getTable() . ' a', "p.source_type = 'purchase_asset' AND a.id = p.source_id AND a.site_id = p.site_id")
                ->where([['p.site_id', '=', $this->site_id]])
                ->where(function ($query) use ($id) {
                    $query->where([['p.source_type', '=', 'purchase'], ['p.source_id', '=', $id]])
                        ->whereOr(function ($q) use ($id) {
                            $q->where('p.source_type', '=', 'purchase_asset')->where('a.purchase_order_id', '=', $id);
                        });
                })
                ->field('p.*')
                ->select()
                ->toArray();
            foreach ($payables as $payable) {
                if ((float)$payable['settled_amount'] > 0) {
                    throw new CommonException('该采购单已有付款或折账记录，不能直接撤销');
                }
            }

            $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['purchase_order_id', '=', $id]])->select();
            foreach ($assets as $asset) {
                if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
                    throw new CommonException('采购单内已有设备不在库存中，不能直接撤销');
                }
            }

            $order->save([
                'status' => ErpDict::STATUS_VOID,
                'payable_amount' => 0,
                'finance_status' => ErpDict::STATUS_VOID,
                'update_at' => $now,
            ]);
            ErpPurchaseItem::where([['site_id', '=', $this->site_id], ['purchase_order_id', '=', $id]])->update([
                'status' => ErpDict::STATUS_VOID,
                'update_at' => $now,
            ]);
            $payableIds = array_values(array_filter(array_map(static fn($row) => (int)($row['id'] ?? 0), $payables)));
            if (!empty($payableIds)) {
                ErpPayable::where([['site_id', '=', $this->site_id]])->whereIn('id', $payableIds)->update([
                    'status' => ErpDict::STATUS_VOID,
                    'update_at' => $now,
                ]);
            }

            foreach ($assets as $asset) {
                $asset->save([
                    'status' => ErpDict::STATUS_VOID,
                    'update_at' => $now,
                ]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'purchase_cancel',
                    'before_status' => ErpDict::ASSET_IN_STOCK,
                    'after_status' => ErpDict::STATUS_VOID,
                    'before_total_cost' => (float)$asset->total_cost,
                    'after_total_cost' => (float)$asset->total_cost,
                    'party_id' => (int)$order->party_id,
                    'party_name' => (string)$order->party_name,
                    'source_type' => 'purchase_cancel',
                    'source_id' => $id,
                    'source_no' => (string)$order->purchase_no,
                    'remark' => $remark !== '' ? $remark : '采购单撤销',
                ]);
                (new ErpLedgerService())->account([
                    'biz_type' => 'purchase_cancel',
                    'direction' => 'decrease',
                    'amount' => (float)$asset->total_cost,
                    'party_id' => (int)$order->party_id,
                    'party_name' => (string)$order->party_name,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'purchase_cancel',
                    'source_id' => $id,
                    'source_no' => (string)$order->purchase_no,
                    'remark' => $remark !== '' ? $remark : '撤销采购应付',
                ]);
            }

            (new ErpOperationLogService())->record('purchase_cancel', 'purchase', $id, (string)$order->purchase_no, $remark, [
                'party_name' => (string)$order->party_name,
                'asset_count' => count($assets),
                'amount' => (float)$order->total_cost,
            ]);
        });
        return true;
    }

    public function adjustCost(int $itemId, float $amount, string $remark = ''): bool
    {
        if (abs($amount) <= 0) {
            throw new CommonException('调整金额不能为0');
        }
        Db::transaction(function () use ($itemId, $amount, $remark) {
            $now = time();
            $item = ErpPurchaseItem::where([['site_id', '=', $this->site_id], ['id', '=', $itemId]])->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('采购明细不存在');
            }
            $order = $this->findOrder((int)$item->purchase_order_id);
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
            $newAdjust = round((float)$item->adjust_cost + $amount, 2);
            $newTotal = round((float)$item->purchase_cost + $newAdjust, 2);
            if ($newTotal < 0) {
                throw new CommonException('调整后成本不能小于0');
            }
            $item->save(['adjust_cost' => $newAdjust, 'total_cost' => $newTotal, 'update_at' => $now]);
            if (!$asset->isEmpty()) {
                $beforeTotalCost = round((float)$asset->total_cost, 2);
                $asset->save([
                    'adjust_cost' => round((float)$asset->adjust_cost + $amount, 2),
                    'total_cost' => round((float)$asset->total_cost + $amount, 2),
                    'update_at' => $now,
                ]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'cost_adjust',
                    'before_status' => (string)$asset->status,
                    'after_status' => (string)$asset->status,
                    'before_total_cost' => $beforeTotalCost,
                    'after_total_cost' => round($beforeTotalCost + $amount, 2),
                    'cost_delta' => round($amount, 2),
                    'party_id' => (int)$order->party_id,
                    'party_name' => (string)$order->party_name,
                    'source_type' => 'purchase_adjust',
                    'source_id' => $itemId,
                    'source_no' => (string)$order->purchase_no,
                    'remark' => $remark !== '' ? $remark : '采购成本调整',
                ]);
            }
            $newOrderCost = round((float)$order->total_cost + $amount, 2);
            $newPayableAmount = round($newOrderCost - (float)$order->paid_amount, 2);
            $order->save([
                'total_cost' => $newOrderCost,
                'payable_amount' => max(0, $newPayableAmount),
                'finance_status' => ErpDict::financeStatus($newOrderCost, (float)$order->paid_amount),
                'update_at' => $now,
            ]);
            $payable = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase_asset'],
                ['source_id', '=', (int)$item->asset_id],
            ])->findOrEmpty();
            if ($payable->isEmpty()) {
                $payable = ErpPayable::where([
                    ['site_id', '=', $this->site_id],
                    ['source_type', '=', 'purchase'],
                    ['source_id', '=', (int)$order->id],
                ])->findOrEmpty();
            }
            if (!$payable->isEmpty()) {
                $newAmount = round((float)$payable->amount + $amount, 2);
                $payable->save([
                    'amount' => max(0, $newAmount),
                    'status' => ErpDict::financeStatus(max(0, $newAmount), (float)$payable->settled_amount),
                    'update_at' => $now,
                ]);
            }
            (new ErpLedgerService())->account([
                'biz_type' => 'adjust',
                'direction' => $amount > 0 ? 'increase' : 'decrease',
                'amount' => abs(round($amount, 2)),
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'asset_id' => (int)$item->asset_id,
                'source_type' => 'purchase_adjust',
                'source_id' => $itemId,
                'source_no' => (string)$order->purchase_no,
                'remark' => $remark,
            ]);
        });
        return true;
    }

    private function ensureParty(int $id, string $name, string $mNo, string $type): ErpParty
    {
        if ($id > 0) {
            $party = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
            if (!$party->isEmpty()) {
                return $party;
            }
        }
        $party = ErpParty::where([['site_id', '=', $this->site_id], ['party_name', '=', $name]])->findOrEmpty();
        if (!$party->isEmpty()) {
            return $party;
        }
        $now = time();
        return ErpParty::create([
            'site_id' => $this->site_id,
            'party_no' => ErpLedgerService::makeNo('PT'),
            'party_name' => $name,
            'party_type' => $type,
            'm_no' => $mNo,
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function findOrder(int $id): ErpPurchaseOrder
    {
        $order = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('采购单不存在');
        }
        return $order;
    }

    private function resolveCapitalAccount(int $id): ErpCapitalAccount
    {
        if ($id <= 0) {
            throw new CommonException('请选择付款账户');
        }
        $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id], ['status', '=', 1]])->findOrEmpty();
        if ($account->isEmpty()) {
            throw new CommonException('付款账户不存在或已停用');
        }
        return $account;
    }

    private function accountTypeLabel(string $type): string
    {
        return match ($type) {
            'wechat' => '微信',
            'alipay' => '支付宝',
            'bank' => '银行卡',
            'cash' => '现金',
            default => '其他',
        };
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;
        $purchaseTable = (new ErpPurchaseOrder())->getTable();
        $this->ensureColumn($purchaseTable, 'capital_account_id', "`capital_account_id` int NOT NULL DEFAULT 0 COMMENT '本次付款账户' AFTER `settle_method`");
        $this->ensureColumn($purchaseTable, 'capital_account_name', "`capital_account_name` varchar(100) NOT NULL DEFAULT '' COMMENT '本次付款账户名称' AFTER `capital_account_id`");
        $assetTable = (new ErpAsset())->getTable();
        $itemTable = (new ErpPurchaseItem())->getTable();
        $this->ensureColumn($itemTable, 'warehouse_id', "`warehouse_id` int NOT NULL DEFAULT 0 COMMENT '明细入库仓库ID' AFTER `asset_id`");
        $this->ensureColumn($itemTable, 'warehouse_name', "`warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '明细入库仓库名称快照' AFTER `warehouse_id`");
        $this->ensureColumn($itemTable, 'location_id', "`location_id` int NOT NULL DEFAULT 0 COMMENT '明细入库库位ID' AFTER `warehouse_name`");
        $this->ensureColumn($itemTable, 'location_name', "`location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '明细入库库位名称快照' AFTER `location_id`");
        foreach ([$assetTable, $itemTable] as $table) {
            $this->ensureColumn($table, 'category_id', "`category_id` int NOT NULL DEFAULT 0 COMMENT '商品分类ID' AFTER `spec`");
            $this->ensureColumn($table, 'category_name', "`category_name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品分类名称快照' AFTER `category_id`");
            $this->ensureColumn($table, 'category_path', "`category_path` varchar(255) NOT NULL DEFAULT '' COMMENT '商品分类路径' AFTER `category_name`");
            $this->ensureColumn($table, 'inspector_uid', "`inspector_uid` int NOT NULL DEFAULT 0 COMMENT '质检员UID' AFTER `spec`");
            $this->ensureColumn($table, 'inspector_name', "`inspector_name` varchar(60) NOT NULL DEFAULT '' COMMENT '质检员名称快照' AFTER `inspector_uid`");
            $this->ensureColumn($table, 'estimate_sale_price', "`estimate_sale_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '入库预估售价' AFTER `inspector_name`");
            $this->ensureColumn($table, 'image_urls', "`image_urls` text COMMENT '入库图片JSON/逗号分隔' AFTER `estimate_sale_price`");
            $this->ensureColumn($table, 'quality_remark', "`quality_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '质检/外观备注' AFTER `image_urls`");
        }
    }

    private function normalizeCategoryPath(mixed $path): string
    {
        if (is_string($path)) {
            return trim($path);
        }
        if (!is_array($path)) {
            return '';
        }
        return implode(',', array_values(array_filter(array_map(static fn($id) => (string)(int)$id, $path))));
    }

    private function warehouseAssetFlow(ErpWarehouse $warehouse): array
    {
        $rules = (new ErpConfigService())->getRules();
        $refurbishStatus = ((int)($rules['refurbish']['enabled'] ?? 0) === 1 && (int)($rules['refurbish']['default_required'] ?? 0) === 1) ? 'pending' : 'none';
        $saleTarget = (string)($warehouse->default_sale_target ?: 'unset');
        if (!in_array($saleTarget, ['unset', 'peer', 'mall'], true)) {
            $saleTarget = 'unset';
        }
        return [
            'refurbish_status' => $refurbishStatus,
            'sale_target' => $saleTarget,
        ];
    }

    private function listingStatusByWarehouse(ErpWarehouse $warehouse, string $saleTarget, string $imageUrls, float $estimateSalePrice): string
    {
        if ($saleTarget !== 'mall') {
            return 'none';
        }
        if ((int)$warehouse->need_photo === 1 && $imageUrls === '') {
            return 'need_photo';
        }
        if ((int)$warehouse->need_pricing === 1 && $estimateSalePrice <= 0) {
            return 'need_price';
        }
        return 'ready';
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $rows = Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!empty($rows)) {
            return;
        }
        Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }
}
