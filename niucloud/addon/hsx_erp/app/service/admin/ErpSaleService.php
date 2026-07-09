<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpWarehouse;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpSaleService extends BaseAdminService
{
    public function stockPage(array $where): array
    {
        (new ErpStockService())->ensureSchema();
        (new ErpWarehouseService())->ensureReady();
        $warehouseTable = (new ErpWarehouse())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($warehouseTable . ' w', 'w.id = a.warehouse_id AND w.site_id = a.site_id')
            ->where([
                ['a.site_id', '=', $this->site_id],
                ['a.status', '=', ErpDict::ASSET_IN_STOCK],
            ])
            ->whereNotIn('a.refurbish_status', ['pending', 'processing'])
            ->where('w.allow_direct_sale', '=', 1);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|a.category_name|a.party_name|a.warehouse_name|a.location_name', '%' . $kw . '%');
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('a.warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['party_id'])) {
            $query->where('a.party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('a.location_id', '=', (int)$where['location_id']);
        }
        if (!empty($where['category_id'])) {
            $categoryId = (int)$where['category_id'];
            $query->where(function ($q) use ($categoryId) {
                $q->where('a.category_id', '=', $categoryId)
                    ->whereOr('a.category_path', 'like', '%,' . $categoryId . ',%')
                    ->whereOr('a.category_path', 'like', $categoryId . ',%')
                    ->whereOr('a.category_path', 'like', '%,' . $categoryId)
                    ->whereOr('a.category_path', '=', (string)$categoryId);
            });
        }
        foreach ([
            'asset_no' => 'a.asset_no',
            'imei' => 'a.imei',
            'sn' => 'a.sn',
            'model' => 'a.model',
            'spec' => 'a.spec',
            'party_name' => 'a.party_name',
            'warehouse_name' => 'a.warehouse_name',
            'location_name' => 'a.location_name',
        ] as $key => $column) {
            if (!empty($where[$key])) {
                $query->whereLike($column, '%' . trim((string)$where[$key]) . '%');
            }
        }
        return $query->field('a.*')->order('a.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function getPage(array $where): array
    {
        $orderTable = (new ErpSaleOrder())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpSaleItem::alias('i')
            ->leftJoin($orderTable . ' o', 'o.id = i.sale_order_id AND o.site_id = i.site_id')
            ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
            ->where([['i.site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('o.sale_no|o.party_name|o.sale_channel|o.salesman_name|o.operator_name|i.model|i.imei|a.asset_no|a.sn|a.spec', '%' . $kw . '%');
        }
        if (!empty($where['finance_status'])) {
            $query->where('o.finance_status', '=', (string)$where['finance_status']);
        }
        if (!empty($where['status'])) {
            $query->where('o.status', '=', (string)$where['status']);
        }
        foreach ([
            'asset_no' => 'a.asset_no',
            'imei' => 'i.imei',
            'sn' => 'a.sn',
            'model' => 'i.model',
            'spec' => 'a.spec',
            'party_name' => 'o.party_name',
            'sale_no' => 'o.sale_no',
            'sale_channel' => 'o.sale_channel',
            'warehouse_name' => 'a.warehouse_name',
            'salesman_name' => 'o.salesman_name',
            'operator_name' => 'o.operator_name',
        ] as $key => $column) {
            if (!empty($where[$key])) {
                $query->whereLike($column, '%' . trim((string)$where[$key]) . '%');
            }
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('a.warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('a.location_id', '=', (int)$where['location_id']);
        }
        if (!empty($where['category_id'])) {
            $categoryId = (int)$where['category_id'];
            $query->where(function ($q) use ($categoryId) {
                $q->where('a.category_id', '=', $categoryId)
                    ->whereOr('a.category_path', 'like', '%,' . $categoryId . ',%')
                    ->whereOr('a.category_path', 'like', $categoryId . ',%')
                    ->whereOr('a.category_path', 'like', '%,' . $categoryId)
                    ->whereOr('a.category_path', '=', (string)$categoryId);
            });
        }
        if (!empty($where['salesman_uid'])) {
            $query->where('o.salesman_uid', '=', (int)$where['salesman_uid']);
        }
        if (!empty($where['operator_uid'])) {
            $query->where('o.operator_uid', '=', (int)$where['operator_uid']);
        }
        if (($where['min_amount'] ?? '') !== '') {
            $query->where('i.sale_price', '>=', (float)$where['min_amount']);
        }
        if (($where['max_amount'] ?? '') !== '') {
            $query->where('i.sale_price', '<=', (float)$where['max_amount']);
        }
        if (($where['min_profit'] ?? '') !== '') {
            $query->where('i.profit', '>=', (float)$where['min_profit']);
        }
        if (($where['max_profit'] ?? '') !== '') {
            $query->where('i.profit', '<=', (float)$where['max_profit']);
        }
        if (!empty($where['start_at'])) {
            $query->where('o.sale_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('o.sale_at', '<=', (int)$where['end_at']);
        }
        return $query->field([
            'i.id',
            'i.sale_order_id',
            'i.asset_id',
            'i.imei',
            'i.model',
            'i.cost',
            'i.sale_price',
            'i.profit',
            'i.status',
            'i.remark',
            'i.create_at',
            'a.asset_no',
            'a.sn',
            'a.spec',
            'a.category_id',
            'a.category_name',
            'a.category_path',
            'a.warehouse_id',
            'a.warehouse_name',
            'a.location_id',
            'a.location_name',
            'o.sale_no',
            'o.status as order_status',
            'o.party_id',
            'o.party_name',
            'o.sale_channel',
            'o.settle_method',
            'o.salesman_uid',
            'o.salesman_name',
            'o.total_amount as order_total_amount',
            'o.total_cost as order_total_cost',
            'o.profit as order_profit',
            'o.received_amount',
            'o.receivable_amount',
            'o.finance_status',
            'o.operator_uid',
            'o.operator_name',
            'o.sale_at',
        ])->order('i.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $order = $this->findOrder($id)->toArray();
        $order['items'] = ErpSaleItem::where([
            ['site_id', '=', $this->site_id],
            ['sale_order_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        $order['receivables'] = ErpReceivable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'sale'],
            ['source_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        return $order;
    }

    public function create(array $data): int
    {
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请选择要出库的机器');
        }
        $partyName = trim((string)($data['party_name'] ?? ''));
        if ($partyName === '') {
            throw new CommonException('请填写销售客户/渠道');
        }
        $now = time();
        $orderId = 0;
        Db::transaction(function () use ($data, $items, $partyName, $now, &$orderId) {
            $party = $this->ensureParty((int)($data['party_id'] ?? 0), $partyName);
            $partyName = (string)$party->party_name;
            $saleNo = ErpLedgerService::makeNo('SO');
            $salesman = (new ErpStaffService())->resolve((int)($data['salesman_uid'] ?? 0), '制单员');
            $totalAmount = 0.0;
            $totalCost = 0.0;
            $resolved = [];
            foreach ($items as $item) {
                $assetId = (int)($item['asset_id'] ?? 0);
                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $assetId],
                    ['status', '=', ErpDict::ASSET_IN_STOCK],
                ])->whereNotIn('refurbish_status', ['pending', 'processing'])->findOrEmpty();
                if ($asset->isEmpty()) {
                    throw new CommonException('库存机器不存在或不可销售');
                }
                $warehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset->warehouse_id]])->findOrEmpty();
                if ($warehouse->isEmpty() || (int)$warehouse->allow_direct_sale !== 1) {
                    throw new CommonException('该设备所在仓库不允许直接销售');
                }
                $price = round((float)($item['sale_price'] ?? 0), 2);
                if ($price <= 0) {
                    throw new CommonException('销售金额必须大于0');
                }
                $cost = round((float)$asset->total_cost, 2);
                $totalAmount += $price;
                $totalCost += $cost;
                $resolved[] = [$asset, $price, trim((string)($item['remark'] ?? ''))];
            }
            $profit = round($totalAmount - $totalCost, 2);
            $order = ErpSaleOrder::create([
                'site_id' => $this->site_id,
                'sale_no' => $saleNo,
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'sale_channel' => trim((string)($data['sale_channel'] ?? '')),
                'settle_method' => trim((string)($data['settle_method'] ?? '')),
                'salesman_uid' => (int)$salesman['uid'],
                'salesman_name' => (string)$salesman['name'],
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'received_amount' => 0,
                'receivable_amount' => $totalAmount,
                'finance_status' => ErpDict::STATUS_PENDING,
                'status' => ErpDict::STATUS_COMPLETED,
                'operator_uid' => (int)$this->uid,
                'operator_name' => (string)$this->username,
                'sale_at' => (int)($data['sale_at'] ?? $now),
                'remark' => trim((string)($data['remark'] ?? '')),
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $orderId = (int)$order->id;
            foreach ($resolved as [$asset, $price, $remark]) {
                $cost = round((float)$asset->total_cost, 2);
                $item = ErpSaleItem::create([
                    'site_id' => $this->site_id,
                    'sale_order_id' => $orderId,
                    'asset_id' => (int)$asset->id,
                    'imei' => (string)$asset->imei,
                    'model' => (string)$asset->model,
                    'cost' => $cost,
                    'sale_price' => $price,
                    'profit' => round($price - $cost, 2),
                    'status' => ErpDict::ASSET_SOLD,
                    'remark' => $remark,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $asset->save([
                    'sale_order_id' => $orderId,
                    'sale_item_id' => (int)$item->id,
                    'sale_price' => $price,
                    'profit' => round($price - $cost, 2),
                    'status' => ErpDict::ASSET_SOLD,
                    'update_at' => $now,
                ]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'sold',
                    'before_status' => ErpDict::ASSET_IN_STOCK,
                    'after_status' => ErpDict::ASSET_SOLD,
                    'before_total_cost' => $cost,
                    'after_total_cost' => $cost,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'source_type' => 'sale',
                    'source_id' => $orderId,
                    'source_no' => $saleNo,
                    'occurred_at' => (int)($data['sale_at'] ?? $now),
                    'remark' => '销售出库',
                ]);
                (new ErpLedgerService())->account([
                    'biz_type' => 'sale',
                    'direction' => 'increase',
                    'amount' => $price,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'sale',
                    'source_id' => $orderId,
                    'source_no' => $saleNo,
                    'remark' => '销售应收',
                ]);
            }
            ErpReceivable::create([
                'site_id' => $this->site_id,
                'receivable_no' => ErpLedgerService::makeNo('AR'),
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'source_type' => 'sale',
                'source_id' => $orderId,
                'source_no' => $saleNo,
                'amount' => $totalAmount,
                'settled_amount' => 0,
                'status' => ErpDict::STATUS_PENDING,
                'occurred_at' => (int)($data['sale_at'] ?? $now),
                'remark' => '销售应收',
                'create_at' => $now,
                'update_at' => $now,
            ]);
        });
        return $orderId;
    }

    public function cancel(int $id, string $remark = ''): bool
    {
        Db::transaction(function () use ($id, $remark) {
            $now = time();
            $order = $this->findOrder($id);
            if ((string)$order->status !== ErpDict::STATUS_COMPLETED) {
                throw new CommonException('只有已完成且未撤销的销售单可以撤销');
            }
            if ((float)$order->received_amount > 0 || (string)$order->finance_status !== ErpDict::STATUS_PENDING) {
                throw new CommonException('该销售单已经形成财务事实，请走退货流程');
            }

            $receivables = ErpReceivable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'sale'],
                ['source_id', '=', $id],
            ])->select();
            foreach ($receivables as $receivable) {
                if ((float)$receivable->settled_amount > 0) {
                    throw new CommonException('该销售单已有收款或折账记录，不能直接撤销');
                }
            }

            $items = ErpSaleItem::where([['site_id', '=', $this->site_id], ['sale_order_id', '=', $id]])->select();
            foreach ($items as $item) {
                $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
                if ($asset->isEmpty() || (int)$asset->sale_order_id !== $id || (string)$asset->status !== ErpDict::ASSET_SOLD) {
                    throw new CommonException('销售单内设备状态异常，不能直接撤销');
                }
            }

            $order->save([
                'status' => ErpDict::STATUS_VOID,
                'receivable_amount' => 0,
                'finance_status' => ErpDict::STATUS_VOID,
                'update_at' => $now,
            ]);
            ErpReceivable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'sale'], ['source_id', '=', $id]])->update([
                'status' => ErpDict::STATUS_VOID,
                'update_at' => $now,
            ]);
            ErpSaleItem::where([['site_id', '=', $this->site_id], ['sale_order_id', '=', $id]])->update([
                'status' => ErpDict::STATUS_VOID,
                'update_at' => $now,
            ]);

            foreach ($items as $item) {
                $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
                if ($asset->isEmpty()) {
                    continue;
                }
                $asset->save([
                    'sale_order_id' => 0,
                    'sale_item_id' => 0,
                    'sale_price' => 0,
                    'profit' => 0,
                    'status' => ErpDict::ASSET_IN_STOCK,
                    'update_at' => $now,
                ]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'sale_cancel',
                    'before_status' => ErpDict::ASSET_SOLD,
                    'after_status' => ErpDict::ASSET_IN_STOCK,
                    'before_total_cost' => (float)$asset->total_cost,
                    'after_total_cost' => (float)$asset->total_cost,
                    'party_id' => (int)$order->party_id,
                    'party_name' => (string)$order->party_name,
                    'source_type' => 'sale_cancel',
                    'source_id' => $id,
                    'source_no' => (string)$order->sale_no,
                    'remark' => $remark !== '' ? $remark : '销售单撤销，设备回到原仓库',
                ]);
                (new ErpLedgerService())->account([
                    'biz_type' => 'sale_cancel',
                    'direction' => 'decrease',
                    'amount' => (float)$item->sale_price,
                    'party_id' => (int)$order->party_id,
                    'party_name' => (string)$order->party_name,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'sale_cancel',
                    'source_id' => $id,
                    'source_no' => (string)$order->sale_no,
                    'remark' => $remark !== '' ? $remark : '撤销销售应收',
                ]);
            }

            (new ErpOperationLogService())->record('sale_cancel', 'sale', $id, (string)$order->sale_no, $remark, [
                'party_name' => (string)$order->party_name,
                'asset_count' => count($items),
                'amount' => (float)$order->total_amount,
            ]);
        });
        return true;
    }

    public function cancelItem(int $itemId, string $remark = ''): array
    {
        $result = ['order_cancelled' => false];
        Db::transaction(function () use ($itemId, $remark, &$result) {
            $now = time();
            $item = ErpSaleItem::where([['site_id', '=', $this->site_id], ['id', '=', $itemId]])->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('销售明细不存在');
            }
            if ((string)$item->status !== ErpDict::ASSET_SOLD) {
                throw new CommonException('只有已售设备可以撤销销售');
            }

            $order = $this->findOrder((int)$item->sale_order_id);
            if ((string)$order->status !== ErpDict::STATUS_COMPLETED) {
                throw new CommonException('只有已完成且未撤销的销售单可以撤销设备');
            }
            if ((float)$order->received_amount > 0 || (string)$order->finance_status !== ErpDict::STATUS_PENDING) {
                throw new CommonException('该销售单已经形成财务事实，请走退货流程');
            }

            $receivables = ErpReceivable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'sale'],
                ['source_id', '=', (int)$order->id],
            ])->select();
            foreach ($receivables as $receivable) {
                if ((float)$receivable->settled_amount > 0) {
                    throw new CommonException('该销售单已有收款或折账记录，不能直接撤销设备');
                }
            }

            $activeCount = (int)ErpSaleItem::where([
                ['site_id', '=', $this->site_id],
                ['sale_order_id', '=', (int)$order->id],
                ['status', '=', ErpDict::ASSET_SOLD],
            ])->count();
            if ($activeCount <= 1) {
                $this->cancel((int)$order->id, $remark !== '' ? $remark : '销售单最后一台设备撤销');
                $result = ['order_cancelled' => true];
                return;
            }

            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
            if ($asset->isEmpty() || (int)$asset->sale_order_id !== (int)$order->id || (int)$asset->sale_item_id !== (int)$item->id || (string)$asset->status !== ErpDict::ASSET_SOLD) {
                throw new CommonException('设备销售状态异常，不能直接撤销');
            }

            $item->save([
                'status' => ErpDict::STATUS_VOID,
                'remark' => $remark !== '' ? $remark : '单台撤销销售',
                'update_at' => $now,
            ]);
            $asset->save([
                'sale_order_id' => 0,
                'sale_item_id' => 0,
                'sale_price' => 0,
                'profit' => 0,
                'status' => ErpDict::ASSET_IN_STOCK,
                'update_at' => $now,
            ]);

            $summary = ErpSaleItem::where([
                ['site_id', '=', $this->site_id],
                ['sale_order_id', '=', (int)$order->id],
                ['status', '=', ErpDict::ASSET_SOLD],
            ])->field('SUM(sale_price) as total_amount,SUM(cost) as total_cost,SUM(profit) as profit')->find();
            $totalAmount = round((float)($summary['total_amount'] ?? 0), 2);
            $totalCost = round((float)($summary['total_cost'] ?? 0), 2);
            $profit = round((float)($summary['profit'] ?? 0), 2);
            $order->save([
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'receivable_amount' => $totalAmount,
                'finance_status' => ErpDict::STATUS_PENDING,
                'update_at' => $now,
            ]);
            ErpReceivable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'sale'],
                ['source_id', '=', (int)$order->id],
            ])->update([
                'amount' => $totalAmount,
                'status' => $totalAmount > 0 ? ErpDict::STATUS_PENDING : ErpDict::STATUS_VOID,
                'update_at' => $now,
            ]);

            (new ErpLedgerService())->asset([
                'asset_id' => (int)$asset->id,
                'action' => 'sale_item_cancel',
                'before_status' => ErpDict::ASSET_SOLD,
                'after_status' => ErpDict::ASSET_IN_STOCK,
                'before_total_cost' => (float)$asset->total_cost,
                'after_total_cost' => (float)$asset->total_cost,
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'source_type' => 'sale_item_cancel',
                'source_id' => (int)$item->id,
                'source_no' => (string)$order->sale_no,
                'remark' => $remark !== '' ? $remark : '单台撤销销售，设备回到原仓库',
            ]);
            (new ErpLedgerService())->account([
                'biz_type' => 'sale_item_cancel',
                'direction' => 'decrease',
                'amount' => (float)$item->sale_price,
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'asset_id' => (int)$asset->id,
                'source_type' => 'sale_item_cancel',
                'source_id' => (int)$item->id,
                'source_no' => (string)$order->sale_no,
                'remark' => $remark !== '' ? $remark : '单台撤销销售应收',
            ]);
            (new ErpOperationLogService())->record('sale_item_cancel', 'sale_item', (int)$item->id, (string)$order->sale_no, $remark, [
                'party_name' => (string)$order->party_name,
                'asset_id' => (int)$asset->id,
                'amount' => (float)$item->sale_price,
                'order_total_amount' => $totalAmount,
            ]);
        });
        return $result;
    }

    private function ensureParty(int $id, string $name): ErpParty
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
            'party_type' => 'customer',
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function findOrder(int $id): ErpSaleOrder
    {
        $order = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('销售单不存在');
        }
        return $order;
    }
}
