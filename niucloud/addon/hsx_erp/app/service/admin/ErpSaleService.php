<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpQuantityStockFlow;
use addon\hsx_erp\app\model\ErpQuantityStock;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleReturnItem;
use addon\hsx_erp\app\model\ErpSaleReturnOrder;
use addon\hsx_erp\app\model\ErpSiteCatalogProduct;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpPartyMemberNames;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpSaleService extends BaseAdminService
{
    /** @var int[] 当前服务实例在业务事务内排队的跨插件事件 */
    private array $domainOutboxIds = [];

    public function stockPage(array $where): array
    {
        if ((string)($where['item_type'] ?? 'device') === 'standard') {
            return (new ErpQuantityInventoryService())->saleStockPage($where);
        }
        $warehouseTable = (new ErpWarehouse())->getTable();
        $catalogTable = (new ErpSiteCatalogProduct())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($warehouseTable . ' w', 'w.id = a.warehouse_id AND w.site_id = a.site_id')
            ->leftJoin($catalogTable . ' c', 'c.site_product_id = a.catalog_product_id AND c.site_id = a.site_id')
            ->where([
                ['a.site_id', '=', $this->site_id],
                ['a.status', '=', ErpDict::ASSET_IN_STOCK],
            ])
            ->whereNotIn('a.refurbish_status', ['pending', 'processing', 'failed'])
            ->where('w.allow_direct_sale', '=', 1);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|a.category_name|c.product_name|a.party_name|a.warehouse_name|a.location_name', '%' . $kw . '%');
        }
        $assetIds = array_values(array_unique(array_filter(array_map('intval', (array)($where['asset_ids'] ?? [])))));
        if ($assetIds !== []) {
            $query->whereIn('a.id', $assetIds);
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
        if (!empty($where['catalog_product_id'])) $query->where('a.catalog_product_id', '=', (int)$where['catalog_product_id']);
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
        $page = $query->field([
            'a.*',
            "COALESCE(c.product_name, '') as catalog_product_name",
        ])->order('a.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        foreach ($page['data'] as &$asset) {
            $ownershipType = (string)($asset['ownership_type'] ?? 'owned');
            $settlementAmount = $ownershipType === 'consigned'
                ? $this->resolveConsignmentSettlementSnapshot((string)($asset['spec_json'] ?? ''))
                : 0.0;
            $asset['ownership_type'] = $ownershipType;
            $asset['consignment_settlement_amount'] = $settlementAmount;
            $asset['sale_cost_basis'] = $ownershipType === 'consigned'
                ? $settlementAmount
                : round((float)($asset['total_cost'] ?? 0), 2);
        }
        unset($asset);
        return $page;
    }

    public function getPage(array $where): array
    {
        $orderTable = (new ErpSaleOrder())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpSaleItem::alias('i')
            ->leftJoin($orderTable . ' o', 'o.id = i.sale_order_id AND o.site_id = i.site_id')
            ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
            ->where([['i.site_id', '=', $this->site_id]]);
        if (in_array((string)($where['item_type'] ?? ''), ['device', 'standard'], true)) {
            $query->where('i.item_type', '=', (string)$where['item_type']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('o.sale_no|o.party_name|o.sale_channel|o.salesman_name|o.operator_name|i.model|i.product_code|i.imei|a.asset_no|a.sn|a.spec', '%' . $kw . '%');
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
            'salesman_name' => 'o.salesman_name',
            'operator_name' => 'o.operator_name',
        ] as $key => $column) {
            if (!empty($where[$key])) {
                $query->whereLike($column, '%' . trim((string)$where[$key]) . '%');
            }
        }
        if (!empty($where['warehouse_name'])) {
            $keyword = trim((string)$where['warehouse_name']);
            $query->whereRaw(
                "IF(i.item_type = 'standard', i.warehouse_name, a.warehouse_name) LIKE ?",
                ['%' . $keyword . '%']
            );
        }
        if (!empty($where['warehouse_id'])) {
            $query->whereRaw(
                "IF(i.item_type = 'standard', i.warehouse_id, a.warehouse_id) = ?",
                [(int)$where['warehouse_id']]
            );
        }
        if (!empty($where['location_id'])) {
            $query->whereRaw(
                "IF(i.item_type = 'standard', i.location_id, a.location_id) = ?",
                [(int)$where['location_id']]
            );
        }
        if (!empty($where['catalog_product_id'])) $query->where('a.catalog_product_id', '=', (int)$where['catalog_product_id']);
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
            'i.item_type',
            'i.asset_id',
            'i.quantity_product_id',
            'i.product_code',
            'i.unit',
            'i.external_goods_id',
            'i.external_sku_id',
            'i.external_line_id',
            'i.supplier_id',
            'i.inventory_source',
            'i.quantity',
            'i.imei',
            'i.model',
            'i.ownership_type',
            'i.owner_party_id',
            'i.owner_party_name',
            'i.cost',
            'i.sale_price',
            'i.profit',
            'i.refunded_amount',
            'i.refunded_cost',
            'i.consignment_settlement_amount',
            'i.consignment_service_fee',
            'i.consignment_payable_id',
            'i.status',
            'i.remark',
            'i.create_at',
            'a.asset_no',
            'a.sn',
            'a.spec',
            'a.catalog_product_id',
            'a.category_name',
            'a.category_path',
            "IF(i.item_type = 'standard', i.warehouse_id, a.warehouse_id) as warehouse_id",
            "IF(i.item_type = 'standard', i.warehouse_name, a.warehouse_name) as warehouse_name",
            "IF(i.item_type = 'standard', i.location_id, a.location_id) as location_id",
            "IF(i.item_type = 'standard', i.location_name, a.location_name) as location_name",
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
        ErpPartyMemberNames::append($this->site_id, $page['data']);
        return $page;
    }

    public function info(int $id): array
    {
        $order = $this->findOrder($id)->toArray();
        $assetTable = (new ErpAsset())->getTable();
        $order['items'] = ErpSaleItem::alias('i')
            ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
            ->where([
                ['i.site_id', '=', $this->site_id],
                ['i.sale_order_id', '=', $id],
            ])->field([
                'i.*',
                'a.asset_no', 'a.sn', 'a.spec',
                "IF(i.item_type = 'standard', i.warehouse_id, a.warehouse_id) as display_warehouse_id",
                "IF(i.item_type = 'standard', i.warehouse_name, a.warehouse_name) as display_warehouse_name",
                "IF(i.item_type = 'standard', i.location_id, a.location_id) as display_location_id",
                "IF(i.item_type = 'standard', i.location_name, a.location_name) as display_location_name",
            ])->order('i.id asc')->select()->toArray();
        foreach ($order['items'] as &$item) {
            $item['warehouse_id'] = (int)($item['display_warehouse_id'] ?? $item['warehouse_id'] ?? 0);
            $item['warehouse_name'] = (string)($item['display_warehouse_name'] ?? $item['warehouse_name'] ?? '');
            $item['location_id'] = (int)($item['display_location_id'] ?? $item['location_id'] ?? 0);
            $item['location_name'] = (string)($item['display_location_name'] ?? $item['location_name'] ?? '');
            $item['sale_no'] = (string)($order['sale_no'] ?? '');
            $item['party_id'] = (int)($order['party_id'] ?? 0);
            $item['party_name'] = (string)($order['party_name'] ?? '');
        }
        unset($item);
        $order['items'] = $this->appendReturnContext((array)$order['items']);
        $order['gross_total_amount'] = round((float)($order['total_amount'] ?? 0), 2);
        $order['sale_compensation_amount'] = round(array_sum(array_column($order['items'], 'sale_compensation_amount')), 2);
        $order['external_refunded_amount'] = max(0, round((float)($order['refunded_amount'] ?? 0), 2));
        $order['net_total_amount'] = max(0, round(
            (float)$order['gross_total_amount']
            - (float)$order['sale_compensation_amount']
            - (float)$order['external_refunded_amount'],
            2
        ));
        $order['receivables'] = ErpReceivable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'sale'],
            ['source_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        $partyRows = [$order];
        ErpPartyMemberNames::append($this->site_id, $partyRows);
        $order = $partyRows[0];
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
        $financeService = null;
        $channel = $this->resolveSaleChannel($data);
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? $data['event_id'] ?? '');
        $data['request_id'] = $requestId !== '' ? $requestId : null;
        $existing = $this->existingSaleRequest($requestId);
        if ($existing !== null) {
            $this->assertSameSaleRequest($existing, $data, $items, $channel);
            return (int)$existing->id;
        }
        try {
        Db::transaction(function () use ($data, $items, $partyName, $channel, $now, &$orderId, &$financeService) {
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
            $seenStandardStocks = [];
            foreach ($items as $itemIndex => $item) {
                $itemType = (string)($item['item_type'] ?? 'device');
                if ($itemType === 'standard') {
                    $stockId = (int)($item['stock_id'] ?? $item['quantity_stock_id'] ?? 0);
                    if ($stockId <= 0) throw new CommonException('请选择有效的标品库存');
                    if (isset($seenStandardStocks[$stockId])) throw new CommonException('同一标品库存位置不能重复加入销售单');
                    $seenStandardStocks[$stockId] = true;
                    $quantity = round((float)($item['quantity'] ?? 0), 3);
                    $price = round((float)($item['sale_price'] ?? 0), 2);
                    if ($quantity <= 0) throw new CommonException('标品销售数量必须大于0');
                    if ($price <= 0) throw new CommonException('标品销售总价必须大于0');
                    $inventory = (new ErpQuantityInventoryService())->saleOutbound([
                        'event_id' => 'sale-standard:' . $this->site_id . ':' . $saleNo . ':' . $itemIndex,
                        'stock_id' => $stockId,
                        'quantity' => $quantity,
                        'biz_no' => $saleNo,
                        'operator_uid' => (int)$this->uid,
                        'operator_name' => (string)$this->username,
                        'occurred_at' => $saleAt,
                    ]);
                    $cost = round((float)($inventory['cost_amount'] ?? 0), 2);
                    $totalAmount += $price;
                    $totalCost += $cost;
                    $resolved[] = [
                        'item_type' => 'standard',
                        'inventory' => $inventory,
                        'price' => $price,
                        'cost' => $cost,
                        'external_line_id' => mb_substr(trim((string)($item['external_line_id'] ?? $item['source_line_id'] ?? '')), 0, 80),
                        'remark' => trim((string)($item['remark'] ?? '')),
                        'ownership_type' => 'owned',
                        'owner_party_id' => 0,
                        'owner_party_name' => '',
                        'consignment_settlement_amount' => 0,
                        'consignment_service_fee' => 0,
                    ];
                    continue;
                }
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
                $ownershipType = (string)($asset->ownership_type ?? 'owned');
                $ownerPartyId = $ownershipType === 'consigned' ? (int)$asset->owner_party_id : 0;
                $ownerPartyName = $ownershipType === 'consigned' ? trim((string)$asset->owner_party_name) : '';
                $consignmentSettlement = 0.0;
                if ($ownershipType === 'consigned') {
                    if ($ownerPartyId <= 0 || $ownerPartyName === '') {
                        throw new CommonException('代卖设备缺少货主主体，不能销售：' . ($asset->imei ?: $asset->model));
                    }
                    $consignmentSettlement = $this->resolveConsignmentSettlement($asset, (array)$item);
                    if ($consignmentSettlement <= 0) {
                        throw new CommonException('代卖设备必须填写给客户的结算金额：' . ($asset->imei ?: $asset->model));
                    }
                    if ($consignmentSettlement > $price) {
                        throw new CommonException('代卖设备结算金额不能大于销售金额：' . ($asset->imei ?: $asset->model));
                    }
                }
                $cost = $ownershipType === 'consigned'
                    ? $consignmentSettlement
                    : round((float)$asset->total_cost, 2);
                $totalAmount += $price;
                $totalCost += $cost;
                $resolved[] = [
                    'item_type' => 'device',
                    'asset' => $asset,
                    'price' => $price,
                    'cost' => $cost,
                    'external_line_id' => mb_substr(trim((string)($item['external_line_id'] ?? $item['source_line_id'] ?? '')), 0, 80),
                    'remark' => trim((string)($item['remark'] ?? '')),
                    'ownership_type' => $ownershipType,
                    'owner_party_id' => $ownerPartyId,
                    'owner_party_name' => $ownerPartyName,
                    'consignment_settlement_amount' => $consignmentSettlement,
                    'consignment_service_fee' => $ownershipType === 'consigned' ? round($price - $consignmentSettlement, 2) : 0,
                ];
            }
            $settleMode = in_array((string)($data['settle_mode'] ?? ''), ['credit', 'cash'], true)
                ? (string)$data['settle_mode']
                : (str_contains((string)($data['settle_method'] ?? ''), '现结') ? 'cash' : 'credit');
            if ($settleMode === 'cash') {
                $receivedAmount = round((float)($data['received_amount'] ?? 0), 2);
                if ($receivedAmount <= 0 || $receivedAmount > round($totalAmount, 2) + 0.0001) {
                    throw new CommonException('现结收款必须大于0且不能超过销售总额');
                }
                if ((int)($data['capital_account_id'] ?? 0) <= 0) {
                    throw new CommonException('现结销售必须选择收款账户');
                }
            }
            (new ErpCustomerCreditService())->assertSaleAllowed($party, array_merge($data, ['settle_mode' => $settleMode]), round($totalAmount, 2));
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
                'payment_mode' => trim((string)($data['payment_mode'] ?? '')),
                'payment_trade_no' => trim((string)($data['payment_trade_no'] ?? '')),
                'payment_gross_amount' => round((float)($data['payment_gross_amount'] ?? 0), 2),
                'payment_fee_amount' => round((float)($data['payment_fee_amount'] ?? 0), 2),
                'payment_fee_bearer' => trim((string)($data['payment_fee_bearer'] ?? '')),
                'merchant_net_amount' => round((float)($data['merchant_net_amount'] ?? 0), 2),
                'settle_method' => $settleMode === 'cash' ? '现结' : '挂账',
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
            foreach ($resolved as $resolvedItem) {
                if ((string)($resolvedItem['item_type'] ?? 'device') === 'standard') {
                    $inventory = (array)$resolvedItem['inventory'];
                    $price = (float)$resolvedItem['price'];
                    $cost = (float)$resolvedItem['cost'];
                    $item = ErpSaleItem::create([
                        'site_id' => $this->site_id,
                        'sale_order_id' => $orderId,
                        'item_type' => 'standard',
                        'asset_id' => 0,
                        'quantity_product_id' => (int)$inventory['product_id'],
                        'product_code' => (string)$inventory['product_code'],
                        'unit' => (string)$inventory['unit'],
                        'warehouse_id' => (int)$inventory['warehouse_id'],
                        'warehouse_name' => (string)$inventory['warehouse_name'],
                        'location_id' => (int)$inventory['location_id'],
                        'location_name' => (string)$inventory['location_name'],
                        'imei' => '',
                        'model' => (string)$inventory['product_name'],
                        'external_line_id' => (string)$resolvedItem['external_line_id'],
                        'inventory_source' => 'erp_quantity',
                        'quantity' => (float)$inventory['quantity'],
                        'ownership_type' => 'owned',
                        'cost' => $cost,
                        'sale_price' => $price,
                        'profit' => round($price - $cost, 2),
                        'status' => ErpDict::ASSET_SOLD,
                        'remark' => (string)$resolvedItem['remark'],
                        'create_at' => $now,
                        'update_at' => $now,
                    ]);
                    ErpQuantityStockFlow::where([
                        ['site_id', '=', $this->site_id], ['id', '=', (int)$inventory['flow_id']],
                    ])->update(['biz_id' => (int)$item->id]);
                    (new ErpLedgerService())->account([
                        'biz_type' => 'sale',
                        'direction' => 'increase',
                        'amount' => $price,
                        'party_id' => (int)$party->id,
                        'party_name' => $partyName,
                        'asset_id' => 0,
                        'source_type' => 'sale',
                        'source_id' => $orderId,
                        'source_no' => $saleNo,
                        'remark' => '标品销售应收',
                    ]);
                    continue;
                }
                /** @var ErpAsset $asset */
                $asset = $resolvedItem['asset'];
                $price = (float)$resolvedItem['price'];
                $cost = (float)$resolvedItem['cost'];
                $remark = (string)$resolvedItem['remark'];
                $item = ErpSaleItem::create([
                    'site_id' => $this->site_id,
                    'sale_order_id' => $orderId,
                    'asset_id' => (int)$asset->id,
                    'imei' => (string)$asset->imei,
                    'model' => (string)$asset->model,
                    'external_line_id' => (string)$resolvedItem['external_line_id'],
                    'ownership_type' => (string)$resolvedItem['ownership_type'],
                    'owner_party_id' => (int)$resolvedItem['owner_party_id'],
                    'owner_party_name' => (string)$resolvedItem['owner_party_name'],
                    'cost' => $cost,
                    'sale_price' => $price,
                    'profit' => round($price - $cost, 2),
                    'consignment_settlement_amount' => (float)$resolvedItem['consignment_settlement_amount'],
                    'consignment_service_fee' => (float)$resolvedItem['consignment_service_fee'],
                    'consignment_payable_id' => 0,
                    'status' => ErpDict::ASSET_SOLD,
                    'remark' => $remark,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $consignmentPayable = null;
                if ((string)$resolvedItem['ownership_type'] === 'consigned') {
                    $sourceSnapshot = json_decode((string)($asset->spec_json ?? ''), true);
                    if (!is_array($sourceSnapshot)) $sourceSnapshot = [];
                    $consignmentSnapshot = is_array($sourceSnapshot['consignment'] ?? null) ? (array)$sourceSnapshot['consignment'] : [];
                    $originPlugin = trim((string)$asset->source_plugin) ?: 'hsx_erp';
                    $originNo = trim((string)($consignmentSnapshot['order_no'] ?? '')) ?: (string)$asset->ownership_source_no;
                    $meta = $financeSourceService->consignmentSale([
                        'origin_plugin' => $originPlugin,
                        'origin_plugin_name' => $originPlugin === 'hsx_recycle' ? '回收插件' : '',
                        'origin_id' => (string)($consignmentSnapshot['order_id'] ?? $asset->source_id),
                        'origin_no' => $originNo,
                        'business_reason' => sprintf(
                            '代卖设备%s已售出，成交¥%.2f，应付货主¥%.2f，代卖收益¥%.2f。',
                            (string)($asset->imei ?: $asset->asset_no),
                            $price,
                            (float)$resolvedItem['consignment_settlement_amount'],
                            (float)$resolvedItem['consignment_service_fee']
                        ),
                    ]);
                    $consignmentPayable = ErpPayable::create(array_merge([
                        'site_id' => $this->site_id,
                        'payable_no' => ErpLedgerService::makeNo('AP'),
                        'party_id' => (int)$resolvedItem['owner_party_id'],
                        'party_name' => (string)$resolvedItem['owner_party_name'],
                        'source_type' => 'consignment_sale',
                        'source_id' => (int)$item->id,
                        'source_no' => $saleNo,
                        'settlement_mode' => 'credit',
                        'settlement_mode_name' => '财务结算',
                        'business_operator_uid' => (int)$this->uid,
                        'business_operator_name' => (string)$this->username,
                        'asset_id' => (int)$asset->id,
                        'amount' => (float)$resolvedItem['consignment_settlement_amount'],
                        'settled_amount' => 0,
                        'status' => ErpDict::STATUS_PENDING,
                        'occurred_at' => $saleAt,
                        'remark' => '代卖成交应付货主',
                        'create_at' => $now,
                        'update_at' => $now,
                    ], $financeSourceService->persistable($meta)));
                    $item->save(['consignment_payable_id' => (int)$consignmentPayable->id, 'update_at' => $now]);
                    (new ErpLedgerService())->account([
                        'biz_type' => 'consignment_sale',
                        'direction' => 'increase',
                        'amount' => (float)$resolvedItem['consignment_settlement_amount'],
                        'party_id' => (int)$resolvedItem['owner_party_id'],
                        'party_name' => (string)$resolvedItem['owner_party_name'],
                        'asset_id' => (int)$asset->id,
                        'source_type' => 'consignment_sale',
                        'source_id' => (int)$item->id,
                        'source_no' => $saleNo,
                        'remark' => '代卖成交应付货主',
                    ]);
                }
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
                    'ownership_type' => (string)$resolvedItem['ownership_type'],
                    'owner_party_id' => (int)$resolvedItem['owner_party_id'],
                    'owner_party_name' => (string)$resolvedItem['owner_party_name'],
                    'consignment_settlement_amount' => (float)$resolvedItem['consignment_settlement_amount'],
                    'consignment_service_fee' => (float)$resolvedItem['consignment_service_fee'],
                    'consignment_payable_id' => $consignmentPayable ? (int)$consignmentPayable->id : 0,
                    'consignment_payable_no' => $consignmentPayable ? (string)$consignmentPayable->payable_no : '',
                    'settle_method' => $settleMode === 'cash' ? '现结' : '挂账',
                    'sale_channel_key' => (string)$channel['key'],
                    'channel_source_plugin' => (string)$channel['source_plugin'],
                    'origin_plugin' => (string)$saleSource['origin_plugin'],
                    'build_mall_order' => false,
                    'result_status' => 'sold',
                ]);
            }
            $receivable = ErpReceivable::create(array_merge([
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
            if ($settleMode === 'cash') {
                $financeService = new ErpFinanceService();
                $settlementId = $financeService->confirmReceivableItemsInTransaction((int)$party->id, [[
                    'receivable_id' => (int)$receivable->id,
                    'amount' => round((float)$data['received_amount'], 2),
                ]], [
                    'request_id' => ErpIdempotency::child((string)($data['request_id'] ?? ''), 'sale-receipt') ?: null,
                    'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
                    'voucher_urls' => $data['voucher_urls'] ?? '',
                    'remark' => trim((string)($data['remark'] ?? '')) ?: '销售现结收款',
                ]);
                $this->recordOnlinePaymentAdjustments($data, $order, $party, $settlementId, $now);
            }
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
        if ($financeService instanceof ErpFinanceService) $financeService->flushPendingSettlementDomainEvents();
        (new ErpPrintService())->triggerSafely('sale_created', 'sale', $orderId);
        return $orderId;
    }

    /**
     * 线上收款的商品销售额由应收结清；客户额外承担的手续费补款和支付渠道
     * 实扣手续费单独走资金流水，避免把手续费混进设备售价及销售毛利。
     */
    private function recordOnlinePaymentAdjustments(array $data, ErpSaleOrder $order, ErpParty $party, int $settlementId, int $now): void
    {
        if ((string)($data['payment_mode'] ?? '') !== 'wechat_online') return;
        $accountId = (int)($data['capital_account_id'] ?? 0);
        $account = ErpCapitalAccount::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $accountId],
            ['status', '=', 1],
        ])->lock(true)->findOrEmpty();
        if ($account->isEmpty()) throw new CommonException('微信支付清算账户不存在或已停用');

        $gross = round((float)($data['payment_gross_amount'] ?? 0), 2);
        $fee = round((float)($data['payment_fee_amount'] ?? 0), 2);
        $saleAmount = round((float)$order->total_amount, 2);
        if ($gross <= 0) $gross = $saleAmount;
        if ($fee < 0 || $fee > $gross + 0.0001) throw new CommonException('线上支付手续费金额不正确');
        $extra = max(0, round($gross - $saleAmount, 2));
        $customerFeeRecovery = (string)($data['payment_fee_bearer'] ?? '') === 'customer'
            ? min($fee, $extra)
            : 0.0;
        $otherSurcharge = max(0, round($extra - $customerFeeRecovery, 2));
        $ledger = ErpLedgerService::forSite($this->site_id, (int)$this->uid, (string)$this->username);

        if ($otherSurcharge > 0) {
            $balance = round((float)$account->balance + $otherSurcharge, 2);
            $account->save(['balance' => $balance, 'update_at' => $now]);
            $ledger->money([
                'settlement_id' => $settlementId,
                'capital_account_id' => (int)$account->id,
                'capital_account_name' => (string)$account->account_name,
                'direction' => 'in',
                'category_key' => 'mall_order_surcharge',
                'category_name' => '商城配送及附加收款',
                'category_statement_group' => 'sales_revenue',
                'category_source_plugin' => 'phone_shop',
                'category_source_key' => 'order_surcharge',
                'amount' => $otherSurcharge,
                'balance_after' => $balance,
                'party_id' => (int)$party->id,
                'party_name' => (string)$party->party_name,
                'occurred_at' => (int)$order->sale_at,
                'remark' => '商城订单 ' . (string)$order->origin_no . ' 配送及附加收款',
            ]);
        }
        if ($customerFeeRecovery > 0) {
            $balance = round((float)$account->balance + $customerFeeRecovery, 2);
            $account->save(['balance' => $balance, 'update_at' => $now]);
            $ledger->money([
                'settlement_id' => $settlementId,
                'capital_account_id' => (int)$account->id,
                'capital_account_name' => (string)$account->account_name,
                'direction' => 'in',
                'category_key' => 'channel_payment_fee_reimbursement',
                'category_name' => '客户承担支付手续费',
                'category_statement_group' => 'selling_expense_offset',
                'category_source_plugin' => 'phone_shop',
                'category_source_key' => 'wechat_payment_fee_reimbursement',
                'amount' => $customerFeeRecovery,
                'balance_after' => $balance,
                'party_id' => (int)$party->id,
                'party_name' => (string)$party->party_name,
                'occurred_at' => (int)$order->sale_at,
                'remark' => '商城订单 ' . (string)$order->origin_no . ' 客户承担支付手续费',
            ]);
        }
        if ($fee > 0) {
            $balance = round((float)$account->balance - $fee, 2);
            $account->save(['balance' => $balance, 'update_at' => $now]);
            $ledger->money([
                'settlement_id' => $settlementId,
                'capital_account_id' => (int)$account->id,
                'capital_account_name' => (string)$account->account_name,
                'direction' => 'out',
                'category_key' => 'channel_payment_fee',
                'category_name' => '线上支付手续费',
                'category_statement_group' => 'selling_expense',
                'category_source_plugin' => 'phone_shop',
                'category_source_key' => 'wechat_payment_fee',
                'amount' => $fee,
                'balance_after' => $balance,
                'party_name' => '微信支付',
                'occurred_at' => (int)$order->sale_at,
                'remark' => '商城订单 ' . (string)$order->origin_no . ' 微信支付手续费',
            ]);
        }
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
                throw new CommonException('销售单内没有可取消的在售商品');
            }
            foreach ($items as $item) {
                if ((string)($item->item_type ?? 'device') === 'standard') continue;
                $this->voidConsignmentPayable($item, $remark !== '' ? $remark : '销售单撤销');
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
                if ((string)($item->item_type ?? 'device') === 'standard') {
                    (new ErpQuantityInventoryService())->saleRestore([
                        'event_id' => 'sale-standard-cancel:' . $this->site_id . ':' . (int)$item->id,
                        'product_id' => (int)$item->quantity_product_id,
                        'warehouse_id' => (int)$item->warehouse_id,
                        'location_id' => (int)$item->location_id,
                        'quantity' => (float)$item->quantity,
                        'cost_amount' => (float)$item->cost,
                        'biz_id' => (int)$item->id,
                        'biz_no' => (string)$order->sale_no,
                        'operator_uid' => (int)$this->uid,
                        'operator_name' => (string)$this->username,
                        'remark' => $remark !== '' ? $remark : '销售单撤销，标品返库',
                    ]);
                    (new ErpLedgerService())->account([
                        'biz_type' => 'sale_cancel',
                        'direction' => 'decrease',
                        'amount' => (float)$item->sale_price,
                        'party_id' => (int)$order->party_id,
                        'party_name' => (string)$order->party_name,
                        'asset_id' => 0,
                        'source_type' => 'sale_cancel',
                        'source_id' => $id,
                        'source_no' => (string)$order->sale_no,
                        'remark' => $remark !== '' ? $remark : '撤销标品销售应收',
                    ]);
                    continue;
                }
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
                    'ownership_type' => (string)($item->ownership_type ?? 'owned'),
                    'consignment_payable_id' => (int)($item->consignment_payable_id ?? 0),
                ]);
            }

            (new ErpOperationLogService())->record('sale_cancel', 'sale', $id, (string)$order->sale_no, $remark, [
                'party_name' => (string)$order->party_name,
                'asset_count' => count($items),
                'amount' => (float)$order->total_amount,
            ]);
        });
        $this->flushDomainEvents();
        (new ErpPrintService())->triggerSafely('sale_cancelled', 'sale', $id);
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
                $this->cancel((int)$order->id, $remark !== '' ? $remark : '销售单最后一项商品撤销');
                $result = ['order_cancelled' => true];
                return;
            }

            if ((string)($item->item_type ?? 'device') === 'standard') {
                (new ErpQuantityInventoryService())->saleRestore([
                    'event_id' => 'sale-standard-item-cancel:' . $this->site_id . ':' . (int)$item->id,
                    'product_id' => (int)$item->quantity_product_id,
                    'warehouse_id' => (int)$item->warehouse_id,
                    'location_id' => (int)$item->location_id,
                    'quantity' => (float)$item->quantity,
                    'cost_amount' => (float)$item->cost,
                    'biz_id' => (int)$item->id,
                    'biz_no' => (string)$order->sale_no,
                    'operator_uid' => (int)$this->uid,
                    'operator_name' => (string)$this->username,
                    'remark' => $remark !== '' ? $remark : '单项撤销销售，标品返库',
                ]);
                $item->save([
                    'status' => ErpDict::STATUS_VOID,
                    'remark' => $remark !== '' ? $remark : '单项撤销销售',
                    'update_at' => $now,
                ]);
                $summary = ErpSaleItem::where([
                    ['site_id', '=', $this->site_id],
                    ['sale_order_id', '=', (int)$order->id],
                    ['status', '=', ErpDict::ASSET_SOLD],
                ])->field('SUM(sale_price) as total_amount,SUM(cost) as total_cost,SUM(profit) as profit')->find();
                $totalAmount = round((float)($summary['total_amount'] ?? 0), 2);
                $order->save([
                    'total_amount' => $totalAmount,
                    'total_cost' => round((float)($summary['total_cost'] ?? 0), 2),
                    'profit' => round((float)($summary['profit'] ?? 0), 2),
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
                (new ErpLedgerService())->account([
                    'biz_type' => 'sale_item_cancel',
                    'direction' => 'decrease',
                    'amount' => (float)$item->sale_price,
                    'party_id' => (int)$order->party_id,
                    'party_name' => (string)$order->party_name,
                    'asset_id' => 0,
                    'source_type' => 'sale_item_cancel',
                    'source_id' => (int)$item->id,
                    'source_no' => (string)$order->sale_no,
                    'remark' => $remark !== '' ? $remark : '撤销标品销售应收',
                ]);
                (new ErpOperationLogService())->record('sale_item_cancel', 'sale_item', (int)$item->id, (string)$order->sale_no, $remark, [
                    'party_name' => (string)$order->party_name,
                    'product_id' => (int)$item->quantity_product_id,
                    'quantity' => (float)$item->quantity,
                    'amount' => (float)$item->sale_price,
                    'order_total_amount' => $totalAmount,
                ]);
                return;
            }

            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty() || (int)$asset->sale_order_id !== (int)$order->id || (int)$asset->sale_item_id !== (int)$item->id || (string)$asset->status !== ErpDict::ASSET_SOLD) {
                throw new CommonException('设备销售状态异常，不能直接撤销');
            }

            $this->voidConsignmentPayable($item, $remark !== '' ? $remark : '单台撤销销售');

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
                'ownership_type' => (string)($item->ownership_type ?? 'owned'),
                'consignment_payable_id' => (int)($item->consignment_payable_id ?? 0),
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
        // 回收侧当前只强消费代卖成交/撤销镜像；普通回收采购的销售状态不应
        // 因监听器没有业务动作而把 ERP Outbox 误判为投递失败。
        if ((string)$asset->source_plugin === 'hsx_recycle'
            && in_array($eventName, ['erp.asset.sold.v1', 'erp.asset.returned.v1'], true)
            && (string)($context['ownership_type'] ?? '') === 'consigned') {
            $required[] = 'hsx_recycle';
        }
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

    private function resolveConsignmentSettlement(ErpAsset $asset, array $item): float
    {
        $explicit = round((float)($item['consignment_settlement_amount'] ?? $item['settlement_amount'] ?? 0), 2);
        if ($explicit > 0) return $explicit;

        return $this->resolveConsignmentSettlementSnapshot((string)($asset->spec_json ?? ''));
    }

    private function resolveConsignmentSettlementSnapshot(string $specJson): float
    {
        $snapshot = json_decode($specJson, true);
        if (!is_array($snapshot)) return 0.0;
        $consignment = is_array($snapshot['consignment'] ?? null) ? (array)$snapshot['consignment'] : [];
        foreach (['settlement_amount', 'min_settlement_price', 'expected_price', 'listing_price'] as $field) {
            $amount = round((float)($consignment[$field] ?? 0), 2);
            if ($amount > 0) return $amount;
        }
        return 0.0;
    }

    private function voidConsignmentPayable(ErpSaleItem $item, string $remark): void
    {
        $payableId = (int)($item->consignment_payable_id ?? 0);
        if ($payableId <= 0) return;
        $payable = ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $payableId],
            ['asset_id', '=', (int)$item->asset_id],
        ])->lock(true)->findOrEmpty();
        if ($payable->isEmpty() || (string)$payable->status === ErpDict::STATUS_VOID) return;
        if ((float)$payable->settled_amount > 0) {
            throw new CommonException('代卖货款已经付款或折账，不能直接撤销销售，请走销售退货及追回款流程');
        }
        $payable->save(['status' => ErpDict::STATUS_VOID, 'update_at' => time(), 'remark' => $remark]);
        (new ErpLedgerService())->account([
            'biz_type' => 'consignment_sale_cancel',
            'direction' => 'decrease',
            'amount' => (float)$payable->amount,
            'party_id' => (int)$payable->party_id,
            'party_name' => (string)$payable->party_name,
            'asset_id' => (int)$item->asset_id,
            'source_type' => 'consignment_sale_cancel',
            'source_id' => (int)$item->id,
            'source_no' => (string)$payable->source_no,
            'remark' => $remark,
        ]);
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
            if ((string)($item['item_type'] ?? 'device') === 'standard') {
                $stock = ErpQuantityStock::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)($item['stock_id'] ?? $item['quantity_stock_id'] ?? 0)],
                ])->findOrEmpty();
                $productId = (int)($item['quantity_product_id'] ?? ($stock->product_id ?? 0));
                $warehouseId = (int)($item['warehouse_id'] ?? ($stock->warehouse_id ?? 0));
                $locationId = (int)($item['location_id'] ?? ($stock->location_id ?? 0));
                $key = 'standard:' . $productId . ':' . $warehouseId . ':' . $locationId;
                $expectedItems[$key] = number_format(round((float)($item['sale_price'] ?? 0), 2), 2, '.', '')
                    . '@' . number_format(round((float)($item['quantity'] ?? 0), 3), 3, '.', '');
                continue;
            }
            $assetId = (int)($item['asset_id'] ?? 0);
            if ($assetId <= 0) continue;
            $expectedItems['device:' . $assetId] = number_format(round((float)($item['sale_price'] ?? 0), 2), 2, '.', '');
        }
        ksort($expectedItems);
        $storedItems = [];
        foreach (ErpSaleItem::where([
            ['site_id', '=', $this->site_id],
            ['sale_order_id', '=', (int)$order->id],
        ])->field('item_type,asset_id,quantity_product_id,warehouse_id,location_id,quantity,sale_price')->select()->toArray() as $item) {
            if ((string)($item['item_type'] ?? 'device') === 'standard') {
                $key = 'standard:' . (int)$item['quantity_product_id'] . ':' . (int)$item['warehouse_id'] . ':' . (int)$item['location_id'];
                $storedItems[$key] = number_format(round((float)$item['sale_price'], 2), 2, '.', '')
                    . '@' . number_format(round((float)$item['quantity'], 3), 3, '.', '');
                continue;
            }
            $storedItems['device:' . (int)$item['asset_id']] = number_format(round((float)$item['sale_price'], 2), 2, '.', '');
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
        $expectedSettleMode = in_array((string)($data['settle_mode'] ?? ''), ['credit', 'cash'], true)
            ? (string)$data['settle_mode']
            : (str_contains((string)($data['settle_method'] ?? ''), '现结') ? 'cash' : 'credit');
        $storedSettleMode = str_contains((string)$order->settle_method, '现结') ? 'cash' : 'credit';
        if ($partyMismatch
            || (string)$order->sale_channel_key !== (string)$channel['key']
            || $storedSettleMode !== $expectedSettleMode
            || $originMismatch
            || $expectedItems !== $storedItems
        ) {
            throw new CommonException('request_id已被不同销售事实占用，请刷新后使用新的幂等键');
        }
    }

    private function ensureParty(int $id, string $name): ErpParty
    {
        if ($id > 0) {
            $party = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
            if (!$party->isEmpty()) {
                return $party;
            }
        }
        $party = ErpParty::where([['site_id', '=', $this->site_id], ['party_name', '=', $name]])->lock(true)->findOrEmpty();
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
    public function appendReturnContext(array $rows): array
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
            $row['external_refunded_amount'] = max(0, round((float)($row['refunded_amount'] ?? 0), 2));
            $row['net_sale_amount'] = max(0, round(
                (float)($row['sale_price'] ?? 0)
                - (float)$row['sale_compensation_amount']
                - (float)$row['external_refunded_amount'],
                2
            ));
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
