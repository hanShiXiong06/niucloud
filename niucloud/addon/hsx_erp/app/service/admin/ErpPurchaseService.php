<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPartyMember;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpPurchaseReturnItem;
use addon\hsx_erp\app\model\ErpPurchaseReturnOrder;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpPurchaseReturnPolicy;
use app\model\member\Member;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpPurchaseService extends BaseAdminService
{
    public function getPage(array $where): array
    {
        $orderTable = (new ErpPurchaseOrder())->getTable();
        $partyTable = (new ErpParty())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $payableTable = (new ErpPayable())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($orderTable . ' o', 'o.id = a.purchase_order_id AND o.site_id = a.site_id')
            ->leftJoin($partyTable . ' p', 'p.id = o.party_id AND p.site_id = o.site_id')
            ->where([['a.site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|a.party_name|a.warehouse_name|a.location_name|o.purchase_no|o.m_no|p.m_no', '%' . $kw . '%');
        }
        if (!empty($where['finance_status'])) {
            $financeStatus = (string)$where['finance_status'];
            if (in_array($financeStatus, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL, ErpDict::STATUS_SETTLED], true)) {
                $hasAssetPayableExpr = "EXISTS(SELECT 1 FROM {$payableTable} xp INNER JOIN {$assetTable} xa ON xa.id = xp.source_id AND xa.site_id = xp.site_id WHERE xp.site_id = a.site_id AND xp.source_type = 'purchase_asset' AND xa.purchase_order_id = o.id)";
                $assetTotalExpr = "(SELECT SUM(ep.amount) FROM {$payableTable} ep INNER JOIN {$assetTable} ea ON ea.id = ep.source_id AND ea.site_id = ep.site_id WHERE ep.site_id = a.site_id AND ep.source_type = 'purchase_asset' AND ep.status <> 'void' AND ea.purchase_order_id = o.id)";
                $assetPaidExpr = "(SELECT SUM(ep.settled_amount) FROM {$payableTable} ep INNER JOIN {$assetTable} ea ON ea.id = ep.source_id AND ea.site_id = ep.site_id WHERE ep.site_id = a.site_id AND ep.source_type = 'purchase_asset' AND ep.status <> 'void' AND ea.purchase_order_id = o.id)";
                $legacyTotalExpr = "(SELECT SUM(lp.amount) FROM {$payableTable} lp WHERE lp.site_id = a.site_id AND lp.source_type = 'purchase' AND lp.status <> 'void' AND lp.source_id = o.id)";
                $legacyPaidExpr = "(SELECT SUM(lp.settled_amount) FROM {$payableTable} lp WHERE lp.site_id = a.site_id AND lp.source_type = 'purchase' AND lp.status <> 'void' AND lp.source_id = o.id)";
                $effectiveTotal = "CASE WHEN {$hasAssetPayableExpr} THEN COALESCE({$assetTotalExpr}, 0) ELSE COALESCE({$legacyTotalExpr}, 0) END";
                $effectivePaid = "CASE WHEN {$hasAssetPayableExpr} THEN COALESCE({$assetPaidExpr}, 0) ELSE COALESCE({$legacyPaidExpr}, 0) END";
                $statusExpr = "CASE WHEN {$effectiveTotal} <= 0 OR {$effectivePaid} >= {$effectiveTotal} THEN 'settled' WHEN {$effectivePaid} > 0 THEN 'partial' ELSE 'pending' END";
                $query->whereRaw("{$statusExpr} = '{$financeStatus}'");
            }
        }
        if (!empty($where['status'])) {
            $query->where('o.status', '=', (string)$where['status']);
        }
        foreach ([
            'asset_no' => 'a.asset_no',
            'imei' => 'a.imei',
            'sn' => 'a.sn',
            'model' => 'a.model',
            'spec' => 'a.spec',
            'party_name' => 'a.party_name',
            'purchase_no' => 'o.purchase_no',
            'warehouse_name' => 'a.warehouse_name',
            'purchaser_name' => 'o.purchaser_name',
        ] as $key => $column) {
            if (!empty($where[$key])) {
                $query->whereLike($column, '%' . trim((string)$where[$key]) . '%');
            }
        }
        if (!empty($where['m_no'])) {
            $mNo = '%' . trim((string)$where['m_no']) . '%';
            $query->where(function ($q) use ($mNo) {
                $q->whereLike('o.m_no', $mNo)->whereOr('p.m_no', 'like', $mNo);
            });
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
        if (!empty($where['purchaser_uid'])) {
            $query->where('o.purchaser_uid', '=', (int)$where['purchaser_uid']);
        }
        if (($where['min_amount'] ?? '') !== '') {
            $query->where('a.total_cost', '>=', (float)$where['min_amount']);
        }
        if (($where['max_amount'] ?? '') !== '') {
            $query->where('a.total_cost', '<=', (float)$where['max_amount']);
        }
        $purchaseTimeExpr = 'COALESCE(NULLIF(o.purchase_at, 0), NULLIF(a.stock_in_at, 0), NULLIF(o.create_at, 0), a.create_at)';
        if (!empty($where['start_at'])) {
            $query->whereRaw($purchaseTimeExpr . ' >= ' . (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->whereRaw($purchaseTimeExpr . ' <= ' . (int)$where['end_at']);
        }
        $page = $query->field([
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
            'a.refurbish_status',
            'a.total_cost',
            'a.status',
            'a.stock_in_at',
            'a.create_at',
            'o.status as order_status',
            'o.purchase_no',
            'o.origin_plugin',
            'o.origin_plugin_name',
            'o.origin_type',
            'o.origin_name',
            'o.origin_id',
            'o.origin_no',
            "COALESCE(NULLIF(o.m_no, ''), p.m_no, '') as m_no",
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
        $this->appendPurchasePaymentSummary($page['data']);
        $this->appendPurchaseReturnSummary($page['data']);
        return $page;
    }

    private function appendPurchasePaymentSummary(array &$rows): void
    {
        $assetIds = array_values(array_filter(array_map(static fn($row) => (int)($row['id'] ?? 0), $rows)));
        $purchaseIds = array_values(array_unique(array_filter(array_map(static fn($row) => (int)($row['purchase_order_id'] ?? 0), $rows))));
        if (empty($assetIds)) {
            return;
        }
        $orderAssets = empty($purchaseIds) ? [] : ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('purchase_order_id', $purchaseIds)
            ->field('id,purchase_order_id,purchase_cost,status')->select()->toArray();
        $allAssetIds = array_values(array_filter(array_map(static fn(array $row): int => (int)$row['id'], $orderAssets)));
        $payables = ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'purchase_asset'],
        ])->whereIn('source_id', $allAssetIds ?: $assetIds)
            ->field('source_id,amount,settled_amount,status')
            ->select()
            ->toArray();
        $map = [];
        foreach ($payables as $payable) {
            $map[(int)$payable['source_id']] = $payable;
        }
        $orderTotals = [];
        $orderStates = [];
        $assetPayableOrderSeen = [];
        foreach ($orderAssets as $orderAsset) {
            $purchaseId = (int)$orderAsset['purchase_order_id'];
            $orderStates[$purchaseId] ??= ['count' => 0, 'returned' => 0, 'void' => 0];
            $orderStates[$purchaseId]['count']++;
            if ((string)$orderAsset['status'] === ErpDict::ASSET_RETURNED) $orderStates[$purchaseId]['returned']++;
            if ((string)$orderAsset['status'] === ErpDict::ASSET_VOID) $orderStates[$purchaseId]['void']++;
            $payable = $map[(int)$orderAsset['id']] ?? null;
            if ($payable) $assetPayableOrderSeen[$purchaseId] = true;
            if (!$payable || (string)$payable['status'] === ErpDict::STATUS_VOID) continue;
            $orderTotals[$purchaseId] ??= ['amount' => 0.0, 'settled_amount' => 0.0, 'count' => 0];
            $orderTotals[$purchaseId]['amount'] = round($orderTotals[$purchaseId]['amount'] + (float)$payable['amount'], 2);
            $orderTotals[$purchaseId]['settled_amount'] = round($orderTotals[$purchaseId]['settled_amount'] + (float)$payable['settled_amount'], 2);
            $orderTotals[$purchaseId]['count']++;
        }
        $legacyIds = array_values(array_filter($purchaseIds, static fn(int $id): bool => empty($assetPayableOrderSeen[$id])));
        if ($legacyIds !== []) {
            $legacyRows = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase'],
            ])->whereIn('source_id', $legacyIds)
                ->where('status', '<>', ErpDict::STATUS_VOID)
                ->field('source_id,amount,settled_amount')->select()->toArray();
            foreach ($legacyRows as $legacy) {
                $purchaseId = (int)$legacy['source_id'];
                $orderTotals[$purchaseId] ??= ['amount' => 0.0, 'settled_amount' => 0.0, 'count' => 0];
                $orderTotals[$purchaseId]['amount'] = round($orderTotals[$purchaseId]['amount'] + (float)$legacy['amount'], 2);
                $orderTotals[$purchaseId]['settled_amount'] = round($orderTotals[$purchaseId]['settled_amount'] + (float)$legacy['settled_amount'], 2);
                $orderTotals[$purchaseId]['count']++;
            }
        }
        foreach ($rows as &$row) {
            $payable = $map[(int)($row['id'] ?? 0)] ?? [];
            $amount = round((float)($payable['amount'] ?? $row['purchase_cost'] ?? 0), 2);
            $paid = round((float)($payable['settled_amount'] ?? 0), 2);
            $row['asset_payable_amount'] = $amount;
            $row['asset_paid_amount'] = $paid;
            $row['asset_unpaid_amount'] = max(0, round($amount - $paid, 2));
            $totals = $orderTotals[(int)($row['purchase_order_id'] ?? 0)] ?? ['amount' => 0.0, 'settled_amount' => 0.0];
            $row['order_effective_amount'] = round((float)$totals['amount'], 2);
            $row['paid_amount'] = round((float)$totals['settled_amount'], 2);
            $row['payable_amount'] = max(0, round((float)$totals['amount'] - (float)$totals['settled_amount'], 2));
            $row['finance_status'] = ErpDict::financeStatus((float)$totals['amount'], (float)$totals['settled_amount']);
            $state = $orderStates[(int)($row['purchase_order_id'] ?? 0)] ?? ['count' => 0, 'returned' => 0, 'void' => 0];
            $effectiveCount = max(0, (int)$state['count'] - (int)$state['returned'] - (int)$state['void']);
            $row['order_asset_count'] = (int)$state['count'];
            $row['order_effective_asset_count'] = $effectiveCount;
            $row['order_returned_count'] = (int)$state['returned'];
            $row['order_business_status_label'] = (int)$state['returned'] <= 0
                ? '采购完成'
                : ($effectiveCount > 0 ? '采购完成 · 部分退货' : '已全部退货');
            $row['return_flow'] = ErpPurchaseReturnPolicy::assess($row, $amount, $paid);
        }
        unset($row);
    }

    private function appendPurchaseReturnSummary(array &$rows): void
    {
        $assetIds = array_values(array_filter(array_map(static fn($row) => (int)($row['id'] ?? 0), $rows)));
        if (empty($assetIds)) {
            return;
        }
        $returnTable = (new ErpPurchaseReturnOrder())->getTable();
        $returnRows = ErpPurchaseReturnItem::alias('i')
            ->leftJoin($returnTable . ' r', 'r.id = i.return_id AND r.site_id = i.site_id')
            ->where([['i.site_id', '=', $this->site_id]])
            ->whereIn('i.asset_id', $assetIds)
            ->field([
                'i.asset_id',
                'i.return_cost',
                'i.paid_amount',
                'r.id as return_id',
                'r.return_no',
                'r.status as return_status',
                'r.refund_mode',
                'r.total_amount as return_total_amount',
                'r.settled_amount as return_settled_amount',
                'r.occurred_at as return_at',
            ])
            ->order('r.id desc')
            ->select()
            ->toArray();
        $map = [];
        foreach ($returnRows as $row) {
            $assetId = (int)($row['asset_id'] ?? 0);
            if ($assetId > 0 && !isset($map[$assetId])) {
                $map[$assetId] = $row;
            }
        }
        foreach ($rows as &$row) {
            $summary = $map[(int)($row['id'] ?? 0)] ?? null;
            if (!$summary) {
                $row['is_returned'] = (string)($row['status'] ?? '') === ErpDict::ASSET_RETURNED;
                continue;
            }
            $row['is_returned'] = true;
            $row['return_id'] = (int)($summary['return_id'] ?? 0);
            $row['return_no'] = (string)($summary['return_no'] ?? '');
            $row['return_status'] = (string)($summary['return_status'] ?? '');
            $row['refund_mode'] = (string)($summary['refund_mode'] ?? '');
            $row['return_cost'] = (float)($summary['return_cost'] ?? 0);
            $row['return_paid_amount'] = (float)($summary['paid_amount'] ?? 0);
            $row['return_settled_amount'] = (float)($summary['return_settled_amount'] ?? 0);
            $row['return_at'] = (int)($summary['return_at'] ?? 0);
        }
        unset($row);
    }

    public function info(int $id): array
    {
        $order = $this->findOrder($id)->toArray();
        $assetTable = (new ErpAsset())->getTable();
        $order['items'] = ErpPurchaseItem::alias('i')
            ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
            ->where([
                ['i.site_id', '=', $this->site_id],
                ['i.purchase_order_id', '=', $id],
            ])
            ->field([
                'i.*',
                'a.asset_no',
                'a.status as asset_status',
                'a.warehouse_id as asset_warehouse_id',
                'a.warehouse_name as asset_warehouse_name',
                'a.location_id as asset_location_id',
                'a.location_name as asset_location_name',
                'a.adjust_cost as asset_adjust_cost',
                'a.refurbish_cost',
                'a.refurbish_status',
                'a.total_cost as asset_total_cost',
                'a.stock_in_at',
                'a.update_at as asset_update_at',
            ])
            ->order('i.id asc')
            ->select()
            ->toArray();
        foreach ($order['items'] as &$item) {
            if (!empty($item['asset_status'])) {
                $item['status'] = $item['asset_status'];
            }
            if (!empty($item['asset_warehouse_name'])) {
                $item['warehouse_id'] = (int)($item['asset_warehouse_id'] ?? 0);
                $item['warehouse_name'] = (string)$item['asset_warehouse_name'];
                $item['location_id'] = (int)($item['asset_location_id'] ?? 0);
                $item['location_name'] = (string)($item['asset_location_name'] ?? '');
            }
            if (isset($item['asset_adjust_cost'])) {
                $item['adjust_cost'] = $item['asset_adjust_cost'];
            }
            if (isset($item['asset_total_cost'])) {
                $item['total_cost'] = $item['asset_total_cost'];
            }
        }
        unset($item);
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
        $hasAssetPayables = false;
        $effectivePayables = [];
        foreach ($order['payables'] as $payableRow) {
            if ((string)($payableRow['source_type'] ?? '') === 'purchase_asset') {
                $hasAssetPayables = true;
                if ((string)($payableRow['status'] ?? '') !== ErpDict::STATUS_VOID) $effectivePayables[] = $payableRow;
            }
        }
        if (!$hasAssetPayables) {
            $effectivePayables = array_values(array_filter($order['payables'], static fn(array $row): bool =>
                (string)($row['source_type'] ?? '') === 'purchase' && (string)($row['status'] ?? '') !== ErpDict::STATUS_VOID
            ));
        }
        $effectiveAmount = round(array_sum(array_map(static fn(array $row): float => (float)($row['amount'] ?? 0), $effectivePayables)), 2);
        $effectivePaid = round(array_sum(array_map(static fn(array $row): float => (float)($row['settled_amount'] ?? 0), $effectivePayables)), 2);
        $order['original_total_cost'] = round((float)($order['total_cost'] ?? 0), 2);
        $order['effective_purchase_amount'] = $effectiveAmount;
        $order['paid_amount'] = $effectivePaid;
        $order['payable_amount'] = max(0, round($effectiveAmount - $effectivePaid, 2));
        $order['finance_status'] = ErpDict::financeStatus($effectiveAmount, $effectivePaid);
        if (trim((string)($order['m_no'] ?? '')) === '' && (int)($order['party_id'] ?? 0) > 0) {
            $order['m_no'] = (string)ErpParty::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$order['party_id']],
            ])->value('m_no');
        }
        $returnedCount = count(array_filter($order['items'], static fn(array $item): bool => (string)($item['status'] ?? '') === ErpDict::ASSET_RETURNED));
        $voidCount = count(array_filter($order['items'], static fn(array $item): bool => (string)($item['status'] ?? '') === ErpDict::ASSET_VOID));
        $effectiveCount = max(0, count($order['items']) - $returnedCount - $voidCount);
        $order['returned_count'] = $returnedCount;
        $order['effective_asset_count'] = $effectiveCount;
        $order['business_status_label'] = $returnedCount <= 0 ? '采购完成' : ($effectiveCount > 0 ? '采购完成 · 部分退货' : '已全部退货');
        $payableMap = [];
        foreach ($order['payables'] as $payable) {
            if ((string)($payable['source_type'] ?? '') === 'purchase_asset') {
                $payableMap[(int)($payable['source_id'] ?? 0)] = $payable;
            }
        }
        foreach ($order['items'] as &$item) {
            $assetId = (int)($item['asset_id'] ?? 0);
            $payable = $payableMap[$assetId] ?? [];
            $amount = round((float)($payable['amount'] ?? $item['purchase_cost'] ?? 0), 2);
            $paid = round((float)($payable['settled_amount'] ?? 0), 2);
            $item['payable_amount'] = $amount;
            $item['paid_amount'] = $paid;
            $item['unpaid_amount'] = max(0, round($amount - $paid, 2));
            $item['return_flow'] = ErpPurchaseReturnPolicy::assess($item, $amount, $paid);
        }
        unset($item);
        return $order;
    }

    public function create(array $data): int
    {
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $existingId = $this->existingPurchaseRequest($requestId);
        if ($existingId > 0) {
            return $existingId;
        }
        $data['request_id'] = $requestId !== '' ? $requestId : null;
        $items = $this->normalizePurchaseItems((array)($data['items'] ?? []));
        if (empty($items)) {
            throw new CommonException('请至少录入一台机器');
        }
        $partyName = trim((string)($data['party_name'] ?? ''));
        if ($partyName === '') {
            throw new CommonException('请填写采购渠道/客户');
        }
        $now = time();
        $erpRules = (new ErpConfigService())->getRules();
        $orderId = 0;
        $cashSettlementCreated = false;
        $financeService = new ErpFinanceService();
        try {
            Db::transaction(function () use ($data, $items, $partyName, $now, $erpRules, $financeService, &$orderId, &$cashSettlementCreated) {
            $party = $this->ensureParty(
                (int)($data['party_id'] ?? 0),
                $partyName,
                (string)($data['m_no'] ?? ''),
                'supplier',
                (int)($data['member_id'] ?? 0),
                (string)($data['contact_name'] ?? ''),
                (string)($data['contact_mobile'] ?? ''),
                (string)($data['source_plugin'] ?? $data['origin_plugin'] ?? '')
            );
            $partyName = (string)$party->party_name;
            $purchaseNo = ErpLedgerService::makeNo('PO');
            $financeSourceService = new ErpFinanceSourceService();
            $purchaseSource = $financeSourceService->purchase([
                'origin_plugin' => (string)($data['origin_plugin'] ?? $data['source_plugin'] ?? 'hsx_erp'),
                'origin_plugin_name' => (string)($data['origin_plugin_name'] ?? ''),
                'origin_type' => (string)($data['origin_type'] ?? $data['source_type'] ?? ''),
                'origin_name' => (string)($data['origin_name'] ?? ''),
                'origin_id' => (string)($data['origin_id'] ?? $data['source_id'] ?? ''),
                'origin_no' => (string)($data['origin_no'] ?? $purchaseNo),
                'purchase_channel_key' => (string)($data['purchase_channel_key'] ?? ''),
                'purchase_channel' => (string)($data['purchase_channel'] ?? ''),
            ]);
            $totalCost = 0.0;
            foreach ($items as $item) {
                $totalCost += round((float)($item['purchase_cost'] ?? 0), 2);
            }
            if ($totalCost <= 0) {
                throw new CommonException('采购成本必须大于0');
            }
            $paidAmount = round((float)($data['paid_amount'] ?? 0), 2);
            $settleMode = (string)($data['settle_mode'] ?? 'credit');
            if (!in_array($settleMode, ['credit', 'cash'], true)) {
                throw new CommonException('采购结算方式不正确');
            }
            if ($paidAmount < 0) {
                throw new CommonException('本次付款不能小于0');
            }
            if ($paidAmount > $totalCost + 0.0001) {
                throw new CommonException('本次付款不能大于采购成本');
            }
            if ($settleMode === 'cash' && $paidAmount <= 0) {
                throw new CommonException('现结采购必须填写本次付款金额');
            }
            if ($settleMode === 'credit') {
                $paidAmount = 0.0;
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
            foreach ($items as $index => $item) {
                $this->assertAssetIdentityAvailable($item);
                $itemWarehouseId = (int)($item['warehouse_id'] ?? 0);
                $itemLocationId = (int)($item['location_id'] ?? 0);
                if ($itemWarehouseId <= 0 || $itemLocationId <= 0) {
                    throw new CommonException('第' . ($index + 1) . '台设备请选择入库仓库和库位');
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
            $purchaseAt = (int)($data['purchase_at'] ?? 0);
            if ($purchaseAt <= 0) {
                $purchaseAt = $now;
            }
            $order = ErpPurchaseOrder::create([
                'site_id' => $this->site_id,
                'request_id' => $data['request_id'],
                'purchase_no' => $purchaseNo,
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'm_no' => trim((string)($data['m_no'] ?? '')) ?: (string)$party->m_no,
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
                'origin_plugin' => (string)$purchaseSource['origin_plugin'],
                'origin_plugin_name' => (string)$purchaseSource['origin_plugin_name'],
                'origin_type' => (string)$purchaseSource['origin_type'],
                'origin_name' => (string)$purchaseSource['origin_name'],
                'origin_id' => (string)$purchaseSource['origin_id'],
                'origin_no' => (string)$purchaseSource['origin_no'],
                'origin_event_id' => trim((string)($data['origin_event_id'] ?? $data['event_id'] ?? '')),
                'operator_uid' => (int)$this->uid,
                'operator_name' => (string)$this->username,
                'purchase_at' => $purchaseAt,
                'remark' => trim((string)($data['remark'] ?? '')),
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $orderId = (int)$order->id;
            $payableApplications = [];
            foreach ($resolvedItems as $resolved) {
                $item = $resolved['item'];
                $warehouse = $resolved['warehouse'];
                $location = $resolved['location'];
                $assetFlow = $resolved['asset_flow'];
                if (array_key_exists('refurbish_required', $item) && (int)($erpRules['refurbish']['enabled'] ?? 1) === 1) {
                    $assetFlow['refurbish_status'] = !empty($item['refurbish_required']) ? 'pending' : 'none';
                }
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
                $color = trim((string)($item['color'] ?? ''));
                $battery = max(0, min(100, (int)($item['battery'] ?? 0)));
                $warranty = max(0, (int)($item['warranty'] ?? 0));
                $specJson = $this->normalizeSpecJson($item['spec_json'] ?? []);
                $catalogProductId = max(0, (int)($item['catalog_product_id'] ?? 0));
                $catalog = $catalogProductId > 0
                    ? (new ErpGoodsCatalogService())->productSnapshot($catalogProductId)
                    : ['category_name' => '', 'category_path' => '', 'product_name' => ''];
                $modelName = trim((string)($item['model'] ?? '')) ?: (string)$catalog['product_name'];
                $categoryName = $catalogProductId > 0 ? (string)$catalog['category_name'] : trim((string)($item['category_name'] ?? ''));
                $categoryPath = $catalogProductId > 0 ? (string)$catalog['category_path'] : $this->normalizeCategoryPath($item['category_path'] ?? []);
                $purchaseItem = ErpPurchaseItem::create([
                    'site_id' => $this->site_id,
                    'purchase_order_id' => $orderId,
                    'warehouse_id' => $itemWarehouseId,
                    'warehouse_name' => $itemWarehouseName,
                    'location_id' => $itemLocationId,
                    'location_name' => $itemLocationName,
                    'imei' => trim((string)($item['imei'] ?? '')),
                    'sn' => trim((string)($item['sn'] ?? '')),
                    'model' => $modelName,
                    'spec' => trim((string)($item['spec'] ?? '')),
                    'spec_json' => $specJson,
                    'color' => $color,
                    'battery' => $battery,
                    'warranty' => $warranty,
                    'catalog_product_id' => $catalogProductId,
                    'category_name' => $categoryName,
                    'category_path' => $categoryPath,
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
                    'model' => $modelName,
                    'spec' => trim((string)($item['spec'] ?? '')),
                    'spec_json' => $specJson,
                    'color' => $color,
                    'battery' => $battery,
                    'warranty' => $warranty,
                    'catalog_product_id' => $catalogProductId,
                    'category_name' => $categoryName,
                    'category_path' => $categoryPath,
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
                    'refurbish_pending_at' => $assetFlow['refurbish_status'] === 'pending' ? $now : 0,
                    'refurbish_remark' => $assetFlow['refurbish_status'] === 'pending' ? mb_substr(trim((string)($item['refurbish_reason'] ?? '')), 0, 500) : '',
                    'sale_target' => $assetFlow['sale_target'],
                    'listing_status' => $assetFlow['refurbish_status'] === 'pending'
                        ? 'none'
                        : $this->listingStatusByWarehouse($warehouse, $assetFlow['sale_target'], trim((string)($item['image_urls'] ?? '')), $estimateSalePrice),
                    'status' => ErpDict::ASSET_IN_STOCK,
                    'source_plugin' => (string)($data['source_plugin'] ?? 'erp'),
                    'source_type' => (string)($data['source_type'] ?? 'manual'),
                    'source_id' => (string)($data['source_id'] ?? ''),
                    'remark' => trim((string)($item['remark'] ?? '')),
                    'stock_in_at' => $purchaseAt,
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
                    'occurred_at' => $purchaseAt,
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
                $payable = ErpPayable::create(array_merge([
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
                    'occurred_at' => $purchaseAt,
                    'remark' => '设备采购应付',
                    'create_at' => $now,
                    'update_at' => $now,
                ], $financeSourceService->persistable($purchaseSource)));
                $payableApplications[] = [
                    'payable_id' => (int)$payable->id,
                    'amount' => $cost,
                ];
            }
            if ($paidAmount > 0) {
                $remainingPayment = $paidAmount;
                $itemsToPay = [];
                foreach ($payableApplications as $application) {
                    if ($remainingPayment <= 0.0001) break;
                    $applyAmount = min($remainingPayment, (float)$application['amount']);
                    $itemsToPay[] = ['payable_id' => (int)$application['payable_id'], 'amount' => round($applyAmount, 2)];
                    $remainingPayment = round($remainingPayment - $applyAmount, 2);
                }
                if ($remainingPayment > 0.0001) throw new CommonException('现结金额分配失败，请重新提交');
                $financeService->confirmPayableItemsInTransaction((int)$party->id, $itemsToPay, [
                    'capital_account_id' => $capitalAccountId,
                    'voucher_urls' => (string)($data['voucher_urls'] ?? ''),
                    'request_id' => 'purchase-cash:' . ((string)($data['request_id'] ?? '') ?: $purchaseNo),
                    'remark' => '采购开单现结付款',
                ]);
                $cashSettlementCreated = true;
                (new ErpOperationLogService())->record('purchase_cash_settled', 'purchase', $orderId, $purchaseNo, '采购开单已完成现结付款', [
                    'paid_amount' => $paidAmount,
                    'capital_account_id' => $capitalAccountId,
                    'capital_account_name' => $capitalAccountName,
                ]);
            }
            });
        } catch (\Throwable $e) {
            $existingId = $this->existingPurchaseRequest($requestId);
            if ($existingId > 0) {
                return $existingId;
            }
            throw $e;
        }
        if ($cashSettlementCreated) {
            $financeService->flushPendingSettlementDomainEvents();
        }
        return $orderId;
    }

    public function cancel(int $id, string $remark = ''): bool
    {
        Db::transaction(function () use ($id, $remark) {
            $now = time();
            $order = $this->findOrder($id, true);
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
                ->lock(true)
                ->select()
                ->toArray();
            foreach ($payables as $payable) {
                if ((float)$payable['settled_amount'] > 0) {
                    throw new CommonException('该采购单已有付款或折账记录，不能直接撤销');
                }
            }

            $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['purchase_order_id', '=', $id]])->lock(true)->select();
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

    public function adjustCost(int $itemId, float $amount, string $remark = '', bool $syncPayable = true, string $requestId = '', string $costType = 'purchase_adjust'): bool
    {
        $requestId = ErpIdempotency::normalize($requestId);
        if ($this->existingCostAdjustmentRequest($requestId, $itemId)) {
            return true;
        }
        $amount = round($amount, 2);
        $remark = trim($remark);
        if (abs($amount) < 0.0001) {
            throw new CommonException('调整金额不能为0');
        }
        if ($remark === '') {
            throw new CommonException('请填写成本调整原因');
        }
        if (!in_array($costType, ['purchase_adjust', 'internal_adjust'], true)) {
            throw new CommonException('采购成本调整类型不正确');
        }
        $syncPayable = $costType === 'purchase_adjust';
        try {
            Db::transaction(function () use ($itemId, $amount, $remark, $syncPayable, $requestId, $costType) {
            $now = time();
            $item = ErpPurchaseItem::where([['site_id', '=', $this->site_id], ['id', '=', $itemId]])->lock(true)->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('采购明细不存在');
            }
            $order = $this->findOrder((int)$item->purchase_order_id, true);
            if ((string)$order->status !== ErpDict::STATUS_COMPLETED) {
                throw new CommonException('只有有效采购单可以调整成本');
            }
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty()) {
                throw new CommonException('采购设备不存在');
            }
            if ((string)$asset->status === ErpDict::ASSET_RETURNED) {
                throw new CommonException('设备已完成采购退货，不能再调整供应商采购价');
            }
            if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
                throw new CommonException('只有仍在库存中的设备可以调整采购成本；已售设备请走售后利润调整流程');
            }

            $payable = null;
            if ($syncPayable) {
                $payable = ErpPayable::where([
                    ['site_id', '=', $this->site_id],
                    ['source_type', '=', 'purchase_asset'],
                    ['source_id', '=', (int)$item->asset_id],
                ])->lock(true)->findOrEmpty();
                if ($payable->isEmpty()) {
                    $payable = ErpPayable::where([
                        ['site_id', '=', $this->site_id],
                        ['source_type', '=', 'purchase'],
                        ['source_id', '=', (int)$order->id],
                    ])->lock(true)->findOrEmpty();
                }
                if (!$payable->isEmpty() && (float)$payable->settled_amount > 0) {
                    throw new CommonException('该采购已形成付款或折账，供应商调价不能回写原应付；如该金额是维修、配件或人工费用，请改选“整备费用”');
                }
            }

            $newAdjust = round((float)$item->adjust_cost + $amount, 2);
            $newTotal = round((float)$item->purchase_cost + $newAdjust, 2);
            if ($newTotal <= 0) {
                throw new CommonException('调整后成本必须大于0');
            }
            $item->save(['adjust_cost' => $newAdjust, 'total_cost' => $newTotal, 'update_at' => $now]);
            $beforeTotalCost = round((float)$asset->total_cost, 2);
            $asset->save([
                'adjust_cost' => round((float)$asset->adjust_cost + $amount, 2),
                'total_cost' => round((float)$asset->total_cost + $amount, 2),
                'update_at' => $now,
            ]);
            (new ErpLedgerService())->asset([
                'asset_id' => (int)$asset->id,
                'request_id' => $requestId !== '' ? $requestId : null,
                'action' => 'cost_adjust',
                'before_status' => (string)$asset->status,
                'after_status' => (string)$asset->status,
                'before_total_cost' => $beforeTotalCost,
                'after_total_cost' => round($beforeTotalCost + $amount, 2),
                'cost_delta' => $amount,
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'source_type' => $costType,
                'source_id' => $itemId,
                'source_no' => (string)$order->purchase_no,
                'remark' => $remark,
                'extra' => ['cost_type' => $costType],
            ]);
            if ($syncPayable) {
                $newOrderCost = round((float)$order->total_cost + $amount, 2);
                $newPayableAmount = round($newOrderCost - (float)$order->paid_amount, 2);
                $order->save([
                    'total_cost' => $newOrderCost,
                    'payable_amount' => max(0, $newPayableAmount),
                    'finance_status' => ErpDict::financeStatus($newOrderCost, (float)$order->paid_amount),
                    'update_at' => $now,
                ]);
                if ($payable !== null && !$payable->isEmpty()) {
                    $newAmount = round((float)$payable->amount + $amount, 2);
                    $payable->save([
                        'amount' => $newAmount,
                        'status' => ErpDict::financeStatus($newAmount, 0),
                        'update_at' => $now,
                    ]);
                }
            }
            (new ErpLedgerService())->account([
                'biz_type' => $costType === 'purchase_adjust' ? 'supplier_adjust' : 'internal_adjust',
                'direction' => $amount > 0 ? 'increase' : 'decrease',
                'amount' => abs(round($amount, 2)),
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'asset_id' => (int)$item->asset_id,
                'source_type' => $costType,
                'source_id' => $itemId,
                'source_no' => (string)$order->purchase_no,
                'remark' => $remark,
            ]);
            });
        } catch (\Throwable $e) {
            if ($this->existingCostAdjustmentRequest($requestId, $itemId)) {
                return true;
            }
            throw $e;
        }
        return true;
    }

    private function existingPurchaseRequest(string $requestId): int
    {
        if ($requestId === '') {
            return 0;
        }
        $order = ErpPurchaseOrder::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
        ])->findOrEmpty();
        return $order->isEmpty() ? 0 : (int)$order->id;
    }

    private function existingCostAdjustmentRequest(string $requestId, int $itemId): bool
    {
        if ($requestId === '') {
            return false;
        }
        $ledger = ErpAssetLedger::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
        ])->findOrEmpty();
        if ($ledger->isEmpty()) {
            return false;
        }
        if ((string)$ledger->action !== 'cost_adjust' || (int)$ledger->source_id !== $itemId) {
            throw new CommonException('request_id已用于其他资产业务');
        }
        return true;
    }

    private function ensureParty(
        int $id,
        string $name,
        string $mNo,
        string $type,
        int $memberId = 0,
        string $contactName = '',
        string $contactMobile = '',
        string $sourcePlugin = ''
    ): ErpParty
    {
        $contactName = trim($contactName) ?: $name;
        $contactMobile = trim($contactMobile) ?: trim($mNo);
        if ($memberId > 0) {
            $member = Member::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])
                ->field('member_id,nickname,username,mobile')->findOrEmpty();
            if (!$member->isEmpty()) {
                $memberName = trim((string)($member->nickname ?: $member->username));
                if ($memberName !== '') $name = $memberName;
                if (trim((string)$member->mobile) !== '') $contactMobile = trim((string)$member->mobile);
                $contactName = $name;
                $relation = ErpPartyMember::where([
                    ['site_id', '=', $this->site_id], ['member_id', '=', $memberId], ['status', '=', 1],
                ])->findOrEmpty();
                if (!$relation->isEmpty()) {
                    $boundParty = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', (int)$relation->party_id]])->findOrEmpty();
                    if (!$boundParty->isEmpty()) {
                        $this->syncPartyIdentity($boundParty, $name, $contactName, $contactMobile, $sourcePlugin);
                        return $boundParty;
                    }
                }
            } else {
                // 外部事件不能凭空创建会员账号；会员不存在时只创建普通 ERP 主体。
                $memberId = 0;
            }
        }
        if ($id > 0) {
            $party = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
            if (!$party->isEmpty()) {
                $this->syncPartyIdentity($party, $name, $contactName, $contactMobile, $sourcePlugin);
                if ($memberId > 0) $this->bindPartyMember((int)$party->id, $memberId);
                return $party;
            }
        }
        if ($contactMobile !== '') {
            $party = ErpParty::where([['site_id', '=', $this->site_id], ['contact_mobile', '=', $contactMobile]])->findOrEmpty();
            if ($party->isEmpty()) {
                $party = ErpParty::where([['site_id', '=', $this->site_id], ['m_no', '=', $contactMobile]])->findOrEmpty();
            }
            if (!$party->isEmpty()) {
                $this->syncPartyIdentity($party, $name, $contactName, $contactMobile, $sourcePlugin);
                if ($memberId > 0) $this->bindPartyMember((int)$party->id, $memberId);
                return $party;
            }
        }
        $party = ErpParty::where([['site_id', '=', $this->site_id], ['party_name', '=', $name]])->findOrEmpty();
        if (!$party->isEmpty()) {
            $this->syncPartyIdentity($party, $name, $contactName, $contactMobile, $sourcePlugin);
            if ($memberId > 0) $this->bindPartyMember((int)$party->id, $memberId);
            return $party;
        }
        $now = time();
        $party = ErpParty::create([
            'site_id' => $this->site_id,
            'party_no' => ErpLedgerService::makeNo('PT'),
            'party_name' => $name,
            'party_type' => $type,
            'role_flags' => $sourcePlugin === 'hsx_recycle' ? 'purchase_supplier,recycle_customer' : 'purchase_supplier',
            'contact_name' => $contactName,
            'contact_mobile' => $contactMobile,
            'm_no' => $mNo,
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ]);
        if ($memberId > 0) $this->bindPartyMember((int)$party->id, $memberId);
        return $party;
    }

    private function syncPartyIdentity(ErpParty $party, string $name, string $contactName, string $mobile, string $sourcePlugin): void
    {
        $save = [];
        $currentName = trim((string)$party->party_name);
        if (($currentName === '' || str_starts_with($currentName, '来源客户#')) && $name !== '') $save['party_name'] = $name;
        if (trim((string)$party->contact_name) === '' && $contactName !== '') $save['contact_name'] = $contactName;
        if (trim((string)$party->contact_mobile) === '' && $mobile !== '') $save['contact_mobile'] = $mobile;
        if (trim((string)$party->m_no) === '' && $mobile !== '') $save['m_no'] = $mobile;
        $roles = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$party->role_flags)))));
        foreach ($sourcePlugin === 'hsx_recycle' ? ['purchase_supplier', 'recycle_customer'] : ['purchase_supplier'] as $role) {
            if (!in_array($role, $roles, true)) $roles[] = $role;
        }
        if (implode(',', $roles) !== (string)$party->role_flags) $save['role_flags'] = implode(',', $roles);
        if ($save !== []) $party->save(array_merge($save, ['update_at' => time()]));
    }

    private function bindPartyMember(int $partyId, int $memberId): void
    {
        if ($partyId <= 0 || $memberId <= 0) return;
        $now = time();
        $relation = ErpPartyMember::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])->findOrEmpty();
        $values = ['party_id' => $partyId, 'relation_role' => 'business', 'status' => 1, 'update_at' => $now];
        if (!$relation->isEmpty()) {
            $relation->save($values);
            return;
        }
        ErpPartyMember::create(array_merge($values, ['site_id' => $this->site_id, 'member_id' => $memberId, 'create_at' => $now]));
    }

    private function normalizePurchaseItems(array $items): array
    {
        $seen = [];
        foreach ($items as $index => &$item) {
            $item = (array)$item;
            $item['imei'] = trim((string)($item['imei'] ?? ''));
            $item['sn'] = trim((string)($item['sn'] ?? ''));
            $model = trim((string)($item['model'] ?? ''));
            $item['model'] = $model;
            if ($model === '') {
                throw new CommonException('第' . ($index + 1) . '台机器缺少型号');
            }
            if ($item['imei'] === '' && $item['sn'] === '') {
                throw new CommonException('第' . ($index + 1) . '台机器必须填写 IMEI 或 SN');
            }
            foreach (['imei' => 'IMEI', 'sn' => 'SN'] as $field => $label) {
                $value = strtolower((string)$item[$field]);
                if ($value === '') {
                    continue;
                }
                $key = $field . ':' . $value;
                if (isset($seen[$key])) {
                    throw new CommonException($label . '【' . $item[$field] . '】在本次采购中重复');
                }
                $seen[$key] = true;
            }
        }
        unset($item);
        return $items;
    }

    private function assertAssetIdentityAvailable(array $item): void
    {
        $imei = (string)($item['imei'] ?? '');
        $sn = (string)($item['sn'] ?? '');
        $query = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [ErpDict::ASSET_IN_STOCK, 'available_for_sale']);
        $query->where(function ($where) use ($imei, $sn) {
            if ($imei !== '') {
                $where->where('imei', '=', $imei);
            }
            if ($sn !== '') {
                $imei !== '' ? $where->whereOr('sn', '=', $sn) : $where->where('sn', '=', $sn);
            }
        });
        $conflict = $query->lock(true)->findOrEmpty();
        if (!$conflict->isEmpty()) {
            $identity = $imei !== '' ? 'IMEI【' . $imei . '】' : 'SN【' . $sn . '】';
            throw new CommonException($identity . '已存在于资产【' . (string)$conflict->asset_no . '】，不能重复入库');
        }
    }

    private function findOrder(int $id, bool $forUpdate = false): ErpPurchaseOrder
    {
        $query = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]]);
        if ($forUpdate) {
            $query->lock(true);
        }
        $order = $query->findOrEmpty();
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

    private function normalizeSpecJson($value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (!is_array($value)) {
            return '';
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
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

}
