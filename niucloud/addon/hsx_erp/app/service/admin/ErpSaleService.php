<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleReturnItem;
use addon\hsx_erp\app\model\ErpSaleReturnOrder;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\support\ErpIdempotency;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpSaleService extends BaseAdminService
{
    /** @var int[] 当前服务实例在业务事务内排队的跨插件事件 */
    private array $domainOutboxIds = [];

    public function stockPage(array $where): array
    {
        $warehouseTable = (new ErpWarehouse())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($warehouseTable . ' w', 'w.id = a.warehouse_id AND w.site_id = a.site_id')
            ->where([
                ['a.site_id', '=', $this->site_id],
                ['a.status', '=', ErpDict::ASSET_IN_STOCK],
            ])
            ->whereNotIn('a.refurbish_status', ['pending', 'processing', 'failed'])
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
            $query->where('i.status', '=', ErpDict::ASSET_SOLD);
        }
        if (!empty($where['status'])) {
            $query->where('o.status', '=', (string)$where['status']);
        }
        if (!empty($where['party_id'])) {
            $query->where('o.party_id', '=', (int)$where['party_id']);
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
        $page = $query->field([
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
            'o.sale_channel_key',
            'o.channel_source_plugin',
            'o.channel_source_key',
            'o.origin_plugin',
            'o.origin_plugin_name',
            'o.origin_type',
            'o.origin_name',
            'o.origin_id',
            'o.origin_no',
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
        $page['data'] = $this->appendReturnContext((array)($page['data'] ?? []));
        return $page;
    }

    public function info(int $id): array
    {
        $order = $this->findOrder($id)->toArray();
        $order['items'] = ErpSaleItem::where([
            ['site_id', '=', $this->site_id],
            ['sale_order_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        $order['items'] = $this->appendReturnContext((array)$order['items']);
        $order['gross_total_amount'] = round((float)($order['total_amount'] ?? 0), 2);
        $order['sale_compensation_amount'] = round(array_sum(array_column($order['items'], 'sale_compensation_amount')), 2);
        $order['net_total_amount'] = max(0, round((float)$order['gross_total_amount'] - (float)$order['sale_compensation_amount'], 2));
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
        $channel = $this->resolveSaleChannel($data);
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? $data['event_id'] ?? '');
        $data['request_id'] = $requestId !== '' ? $requestId : null;
        $existing = $this->existingSaleRequest($requestId);
        if ($existing !== null) {
            $this->assertSameSaleRequest($existing, $data, $items, $channel);
            return (int)$existing->id;
        }
        try {
        Db::transaction(function () use ($data, $items, $partyName, $channel, $now, &$orderId) {
            $saleAt = (int)($data['sale_at'] ?? 0);
            if ($saleAt <= 0) {
                $saleAt = $now;
            }
            $party = $this->ensureParty((int)($data['party_id'] ?? 0), $partyName);
            $partyName = (string)$party->party_name;
            $saleNo = ErpLedgerService::makeNo('SO');
            $financeSourceService = new ErpFinanceSourceService();
            $saleSource = $financeSourceService->sale([
                'origin_plugin' => (string)($data['origin_plugin'] ?? 'hsx_erp'),
                'origin_plugin_name' => (string)($data['origin_plugin_name'] ?? ''),
                'origin_type' => (string)($data['origin_type'] ?? ''),
                'origin_name' => (string)($data['origin_name'] ?? ''),
                'origin_id' => (string)($data['origin_id'] ?? ''),
                'origin_no' => (string)($data['origin_no'] ?? $saleNo),
                'sale_channel_key' => (string)$channel['key'],
                'sale_channel' => (string)$channel['name'],
            ]);
            $salesman = (new ErpStaffService())->resolve((int)($data['salesman_uid'] ?? 0), '制单员');
            $totalAmount = 0.0;
            $totalCost = 0.0;
            $resolved = [];
            $seenAssetIds = [];
            foreach ($items as $item) {
                $assetId = (int)($item['asset_id'] ?? 0);
                if ($assetId <= 0) {
                    throw new CommonException('请选择有效的库存机器');
                }
                if (isset($seenAssetIds[$assetId])) {
                    throw new CommonException('同一台库存机器不能重复加入销售单');
                }
                $seenAssetIds[$assetId] = true;
                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $assetId],
                    ['status', '=', ErpDict::ASSET_IN_STOCK],
                ])->whereNotIn('refurbish_status', ['pending', 'processing', 'failed'])->lock(true)->findOrEmpty();
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
                'request_id' => $data['request_id'],
                'sale_no' => $saleNo,
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'sale_channel' => (string)$channel['name'],
                'sale_channel_key' => (string)$channel['key'],
                'channel_source_plugin' => (string)$channel['source_plugin'],
                'channel_source_key' => (string)$channel['source_key'],
                'origin_plugin' => (string)$saleSource['origin_plugin'],
                'origin_plugin_name' => (string)$saleSource['origin_plugin_name'],
                'origin_type' => (string)$saleSource['origin_type'],
                'origin_name' => (string)$saleSource['origin_name'],
                'origin_id' => (string)$saleSource['origin_id'],
                'origin_no' => (string)$saleSource['origin_no'],
                'origin_event_id' => trim((string)($data['origin_event_id'] ?? $data['event_id'] ?? '')),
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
                'sale_at' => $saleAt,
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
                    'occurred_at' => $saleAt,
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
                $this->queueAssetDomainEvent('erp.asset.sold.v1', $asset, [
                    'sale_order_id' => $orderId,
                    'sale_item_id' => (int)$item->id,
                    'outbound_no' => $saleNo,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'sale_price' => $price,
                    'cost' => $cost,
                    'settle_method' => trim((string)($data['settle_method'] ?? '')),
                    'sale_channel_key' => (string)$channel['key'],
                    'channel_source_plugin' => (string)$channel['source_plugin'],
                    'origin_plugin' => (string)$saleSource['origin_plugin'],
                    'build_mall_order' => false,
                    'result_status' => 'sold',
                ]);
            }
            ErpReceivable::create(array_merge([
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
                'occurred_at' => $saleAt,
                'remark' => '销售应收',
                'create_at' => $now,
                'update_at' => $now,
            ], $financeSourceService->persistable($saleSource)));
        });
        } catch (\Throwable $e) {
            $existing = $this->existingSaleRequest($requestId);
            if ($existing !== null) {
                $this->assertSameSaleRequest($existing, $data, $items, $channel);
                return (int)$existing->id;
            }
            throw $e;
        }
        $this->flushDomainEvents();
        return $orderId;
    }

    public function cancel(int $id, string $remark = ''): bool
    {
        Db::transaction(function () use ($id, $remark) {
            $now = time();
            $order = $this->findOrder($id, true);
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
            ])->lock(true)->select();
            foreach ($receivables as $receivable) {
                if ((float)$receivable->settled_amount > 0) {
                    throw new CommonException('该销售单已有收款或折账记录，不能直接撤销');
                }
            }

            $items = ErpSaleItem::where([
                ['site_id', '=', $this->site_id],
                ['sale_order_id', '=', $id],
                ['status', '=', ErpDict::ASSET_SOLD],
            ])->lock(true)->select();
            if ($items->isEmpty()) {
                throw new CommonException('销售单内没有可取消的在售设备');
            }
            foreach ($items as $item) {
                $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->lock(true)->findOrEmpty();
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
            ErpSaleItem::where([
                ['site_id', '=', $this->site_id],
                ['sale_order_id', '=', $id],
                ['status', '=', ErpDict::ASSET_SOLD],
            ])->update([
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
                $this->queueAssetDomainEvent('erp.asset.returned.v1', $asset, [
                    'sale_order_id' => $id,
                    'sale_item_id' => (int)$item->id,
                    'outbound_no' => (string)$order->sale_no,
                    'party_id' => (int)$order->party_id,
                    'party_name' => (string)$order->party_name,
                    'return_reason' => $remark !== '' ? $remark : '销售单撤销',
                    'return_type' => 'sale_cancel',
                ]);
            }

            (new ErpOperationLogService())->record('sale_cancel', 'sale', $id, (string)$order->sale_no, $remark, [
                'party_name' => (string)$order->party_name,
                'asset_count' => count($items),
                'amount' => (float)$order->total_amount,
            ]);
        });
        $this->flushDomainEvents();
        return true;
    }

    public function cancelItem(int $itemId, string $remark = ''): array
    {
        $result = ['order_cancelled' => false];
        Db::transaction(function () use ($itemId, $remark, &$result) {
            $now = time();
            $item = ErpSaleItem::where([['site_id', '=', $this->site_id], ['id', '=', $itemId]])->lock(true)->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('销售明细不存在');
            }
            if ((string)$item->status !== ErpDict::ASSET_SOLD) {
                throw new CommonException('只有已售设备可以撤销销售');
            }

            $order = $this->findOrder((int)$item->sale_order_id, true);
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
            ])->lock(true)->select();
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

            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->lock(true)->findOrEmpty();
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
            $this->queueAssetDomainEvent('erp.asset.returned.v1', $asset, [
                'sale_order_id' => (int)$order->id,
                'sale_item_id' => (int)$item->id,
                'outbound_no' => (string)$order->sale_no,
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'return_reason' => $remark !== '' ? $remark : '单台撤销销售',
                'return_type' => 'sale_item_cancel',
            ]);
            (new ErpOperationLogService())->record('sale_item_cancel', 'sale_item', (int)$item->id, (string)$order->sale_no, $remark, [
                'party_name' => (string)$order->party_name,
                'asset_id' => (int)$asset->id,
                'amount' => (float)$item->sale_price,
                'order_total_amount' => $totalAmount,
            ]);
        });
        $this->flushDomainEvents();
        return $result;
    }

    /** 在销售事务内记录设备状态事件；插件消费失败不回滚已确认的 ERP 业务事实。 */
    private function queueAssetDomainEvent(string $eventName, ErpAsset $asset, array $context): void
    {
        $required = (string)$asset->sale_target === 'mall'
            || (string)($context['origin_plugin'] ?? '') === 'phone_shop'
            ? ['phone_shop.erp_asset_state']
            : [];
        $sourceDeviceId = (string)$asset->source_plugin === 'hsx_recycle' && is_numeric((string)$asset->source_id)
            ? (int)$asset->source_id
            : 0;
        $queued = (new ErpIntegrationService())->enqueueDomainEvent(
            $eventName,
            'asset',
            (int)$asset->id,
            array_merge([
                'asset_id' => (int)$asset->id,
                'asset_no' => (string)$asset->asset_no,
                'source_device_id' => $sourceDeviceId,
                'imei' => (string)$asset->imei,
                'model' => (string)$asset->model,
                'spec' => (string)$asset->spec,
                'warehouse_id' => (int)$asset->warehouse_id,
                'location_id' => (int)$asset->location_id,
                'sale_target' => (string)$asset->sale_target,
                'snapshot_at' => time(),
            ], $context),
            [],
            $required
        );
        $this->domainOutboxIds[] = (int)$queued['id'];
    }

    /** 只在最外层事务提交后派发；嵌套的整单撤销由外层调用统一刷新。 */
    private function flushDomainEvents(): void
    {
        try {
            if (Db::connect()->getPdo()->inTransaction()) return;
        } catch (\Throwable $e) {
            // 无活动连接时继续尝试派发，dispatch 会记录失败状态。
        }
        $ids = array_values(array_unique(array_filter($this->domainOutboxIds)));
        $this->domainOutboxIds = [];
        $integration = new ErpIntegrationService();
        foreach ($ids as $id) {
            $integration->dispatchDomainEvent((int)$id);
        }
    }

    private function existingSaleRequest(string $requestId): ?ErpSaleOrder
    {
        if ($requestId === '') return null;
        $order = ErpSaleOrder::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
        ])->findOrEmpty();
        return $order->isEmpty() ? null : $order;
    }

    /** 同一个幂等键只能代表同一批设备、金额、客户、渠道和外部来源。 */
    private function assertSameSaleRequest(ErpSaleOrder $order, array $data, array $items, array $channel): void
    {
        $expectedItems = [];
        foreach ($items as $item) {
            $assetId = (int)($item['asset_id'] ?? 0);
            if ($assetId <= 0) continue;
            $expectedItems[$assetId] = number_format(round((float)($item['sale_price'] ?? 0), 2), 2, '.', '');
        }
        ksort($expectedItems);
        $storedItems = [];
        foreach (ErpSaleItem::where([
            ['site_id', '=', $this->site_id],
            ['sale_order_id', '=', (int)$order->id],
        ])->field('asset_id,sale_price')->select()->toArray() as $item) {
            $storedItems[(int)$item['asset_id']] = number_format(round((float)$item['sale_price'], 2), 2, '.', '');
        }
        ksort($storedItems);

        $partyMismatch = (int)($data['party_id'] ?? 0) > 0
            ? (int)$order->party_id !== (int)$data['party_id']
            : (trim((string)($data['party_name'] ?? '')) !== '' && (string)$order->party_name !== trim((string)$data['party_name']));
        $originChecks = [
            'origin_plugin' => 'origin_plugin',
            'origin_type' => 'origin_type',
            'origin_id' => 'origin_id',
            'origin_no' => 'origin_no',
        ];
        $originMismatch = false;
        foreach ($originChecks as $inputKey => $field) {
            $expected = trim((string)($data[$inputKey] ?? ''));
            if ($expected !== '' && (string)$order->{$field} !== $expected) {
                $originMismatch = true;
                break;
            }
        }
        if ($partyMismatch
            || (string)$order->sale_channel_key !== (string)$channel['key']
            || $originMismatch
            || $expectedItems !== $storedItems
        ) {
            throw new CommonException('request_id已被不同销售事实占用，请刷新后使用新的幂等键');
        }
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

    private function resolveSaleChannel(array $data): array
    {
        $options = (new ErpConfigService())->getSaleChannelOptions();
        $key = trim((string)($data['sale_channel_key'] ?? ''));
        $name = trim((string)($data['sale_channel'] ?? ''));
        foreach ($options as $option) {
            if ((int)($option['enabled'] ?? 1) !== 1) continue;
            if (($key !== '' && (string)$option['key'] === $key) || ($key === '' && $name !== '' && (string)$option['name'] === $name)) {
                return $option;
            }
        }
        if ($key !== '' || $name !== '') {
            throw new CommonException('所选销售渠道已停用或所属插件当前不可用，请重新选择');
        }
        foreach ($options as $option) {
            if ((int)($option['enabled'] ?? 1) === 1 && (int)($option['is_default'] ?? 0) === 1) return $option;
        }
        throw new CommonException('没有可用的销售渠道，请先在业务规则中配置或安装提供渠道的插件');
    }

    /**
     * 给销售明细补充退货上下文。列表以设备状态为主，避免把批次收款状态误展示到已退/已取消设备。
     */
    private function appendReturnContext(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $itemIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows)));
        if ($itemIds === []) {
            return $rows;
        }
        $returnTable = (new ErpSaleReturnOrder())->getTable();
        $returnRows = ErpSaleReturnItem::alias('ri')
            ->leftJoin($returnTable . ' r', 'r.id = ri.return_id AND r.site_id = ri.site_id')
            ->where('ri.site_id', '=', $this->site_id)
            ->whereIn('ri.sale_item_id', $itemIds)
            ->where('r.status', '<>', 'cancelled')
            ->field('ri.sale_item_id,ri.return_id,ri.return_price,ri.reason,r.return_no,r.business_type,r.status as return_status,r.occurred_at as return_at')
            ->order('ri.id desc')
            ->select()
            ->toArray();
        $returnMap = [];
        $compensationMap = [];
        foreach ($returnRows as $returnRow) {
            $saleItemId = (int)($returnRow['sale_item_id'] ?? 0);
            if ((string)($returnRow['business_type'] ?? '') === 'after_sale_compensation') {
                $compensationMap[$saleItemId] = round((float)($compensationMap[$saleItemId] ?? 0) + (float)($returnRow['return_price'] ?? 0), 2);
            } elseif ($saleItemId > 0 && !isset($returnMap[$saleItemId])) {
                $returnMap[$saleItemId] = $returnRow;
            }
        }
        foreach ($rows as &$row) {
            $context = $returnMap[(int)($row['id'] ?? 0)] ?? [];
            $row['return_id'] = (int)($context['return_id'] ?? 0);
            $row['return_no'] = (string)($context['return_no'] ?? '');
            $row['return_status'] = (string)($context['return_status'] ?? '');
            $row['return_price'] = round((float)($context['return_price'] ?? 0), 2);
            $row['return_reason'] = (string)($context['reason'] ?? '');
            $row['return_at'] = (int)($context['return_at'] ?? 0);
            $row['sale_compensation_amount'] = max(0, round((float)($compensationMap[(int)($row['id'] ?? 0)] ?? 0), 2));
            $row['net_sale_amount'] = max(0, round((float)($row['sale_price'] ?? 0) - (float)$row['sale_compensation_amount'], 2));
        }
        unset($row);
        return $rows;
    }

    private function findOrder(int $id, bool $forUpdate = false): ErpSaleOrder
    {
        $query = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]]);
        if ($forUpdate) {
            $query->lock(true);
        }
        $order = $query->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('销售单不存在');
        }
        return $order;
    }
}
