<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpOutboxEvent;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\model\ErpSettlementLink;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpPurchaseReturnPolicy;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpStockService extends BaseAdminService
{
    /** 串号追踪：同一 IMEI/SN 允许多次入库，每次资产记录独立展示，最新在前。 */
    public function serialTracePage(array $where): array
    {
        $query = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where(function ($sub) { $sub->where('imei', '<>', '')->whereOr('sn', '<>', ''); });
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->whereLike('imei|sn|asset_no|model|spec|party_name', '%' . $keyword . '%');
        }
        $page = $query->field('id,asset_no,imei,sn,model,spec,status,party_id,party_name,purchase_order_id,warehouse_name,location_name,stock_in_at,create_at,update_at,total_cost')
            ->order('stock_in_at desc,id desc')->paginate([
                'list_rows' => (int)($where['limit'] ?? 15),
                'page' => (int)($where['page'] ?? 1),
            ])->toArray();
        $serialCounts = [];
        foreach ($page['data'] ?? [] as $row) {
            $serial = trim((string)($row['imei'] ?: $row['sn']));
            if ($serial !== '' && !isset($serialCounts[$serial])) {
                $serialCounts[$serial] = (int)ErpAsset::where('site_id', '=', $this->site_id)
                    ->where(function ($sub) use ($serial) { $sub->where('imei', '=', $serial)->whereOr('sn', '=', $serial); })->count();
            }
        }
        foreach ($page['data'] as &$row) {
            $serial = trim((string)($row['imei'] ?: $row['sn']));
            $row['serial_no'] = $serial;
            $row['inbound_count'] = $serialCounts[$serial] ?? 1;
        }
        unset($row);
        return $page;
    }

    /**
     * 串号生命周期：聚合同一 IMEI/SN 的全部入库周期和库存动作。
     * 设备重复入库时会生成新的资产记录，因此不能只读取当前 asset_id 的流水。
     */
    public function serialTraceDetail(int $id): array
    {
        $selected = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($selected->isEmpty()) throw new CommonException('设备记录不存在');

        $serial = trim((string)($selected->imei ?: $selected->sn));
        if ($serial === '') throw new CommonException('该设备未录入 IMEI 或序列号');

        $assets = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where(function ($query) use ($serial) {
                $query->where('imei', '=', $serial)->whereOr('sn', '=', $serial);
            })
            ->field('id,asset_no,imei,sn,model,spec,status,party_id,party_name,purchase_order_id,warehouse_name,location_name,stock_in_at,create_at,update_at,purchase_cost,total_cost,sale_price,profit')
            ->order('stock_in_at asc,id asc')->select()->toArray();

        $assetIds = array_values(array_map(static fn(array $asset): int => (int)$asset['id'], $assets));
        $ledgerGroups = [];
        if ($assetIds !== []) {
            $ledgers = $this->enrichAssetLedgers(
                ErpAssetLedger::where([['site_id', '=', $this->site_id]])
                    ->whereIn('asset_id', $assetIds)
                    ->order('occurred_at asc,id asc')->select()->toArray()
            );
            foreach ($ledgers as $ledger) {
                $ledgerGroups[(int)($ledger['asset_id'] ?? 0)][] = $ledger;
            }
        }

        $timeline = [];
        foreach ($assets as $index => &$asset) {
            $cycle = $index + 1;
            $asset['cycle_no'] = $cycle;
            $asset['serial_no'] = $serial;
            $asset['is_selected'] = (int)$asset['id'] === $id;
            $asset['is_current'] = $index === count($assets) - 1;
            $asset['ledgers'] = $ledgerGroups[(int)$asset['id']] ?? [];
            foreach ($asset['ledgers'] as $ledger) {
                $ledger['cycle_no'] = $cycle;
                $ledger['asset_no'] = (string)$asset['asset_no'];
                $ledger['model'] = (string)$asset['model'];
                if (trim((string)($ledger['party_name'] ?? '')) === '') {
                    $ledger['party_name'] = (string)$asset['party_name'];
                }
                $timeline[] = $ledger;
            }
        }
        unset($asset);

        usort($timeline, static function (array $left, array $right): int {
            $leftAt = (int)($left['occurred_at'] ?? $left['create_at'] ?? 0);
            $rightAt = (int)($right['occurred_at'] ?? $right['create_at'] ?? 0);
            if ($leftAt === $rightAt) return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
            return $leftAt <=> $rightAt;
        });

        $current = $assets === [] ? [] : $assets[count($assets) - 1];
        return [
            'serial_no' => $serial,
            'model' => (string)($current['model'] ?? $selected->model),
            'spec' => (string)($current['spec'] ?? $selected->spec),
            'current_status' => (string)($current['status'] ?? $selected->status),
            'current_asset_id' => (int)($current['id'] ?? $id),
            'inbound_count' => count($assets),
            'sale_count' => count(array_filter($timeline, static fn(array $row): bool => (string)($row['action'] ?? '') === 'sold')),
            'after_sale_count' => count(array_filter($timeline, static fn(array $row): bool => (string)($row['action'] ?? '') === 'sale_return')),
            'purchase_return_count' => count(array_filter($timeline, static fn(array $row): bool => (string)($row['action'] ?? '') === 'purchase_return')),
            'cycles' => $assets,
            'timeline' => $timeline,
        ];
    }

    public function getPage(array $where): array
    {
        $saleTable = (new ErpSaleOrder())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($saleTable . ' s', 's.id = a.sale_order_id AND s.site_id = a.site_id')
            ->where([['a.site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|a.category_name|a.party_name|a.warehouse_name|a.location_name|s.sale_no|s.party_name', '%' . $kw . '%');
        }
        if (!empty($where['status'])) {
            $query->where('a.status', '=', (string)$where['status']);
        }
        if (!empty($where['refurbish_status'])) {
            $query->where('a.refurbish_status', '=', (string)$where['refurbish_status']);
        }
        if (!empty($where['sale_target'])) {
            $query->where('a.sale_target', '=', (string)$where['sale_target']);
        }
        if (!empty($where['listing_status'])) {
            $query->where('a.listing_status', '=', (string)$where['listing_status']);
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('a.warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('a.location_id', '=', (int)$where['location_id']);
        }
        if (!empty($where['party_id'])) {
            $query->where('a.party_id', '=', (int)$where['party_id']);
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
            'category_name' => 'a.category_name',
        ] as $key => $column) {
            if (!empty($where[$key])) {
                $query->whereLike($column, '%' . trim((string)$where[$key]) . '%');
            }
        }
        if (($where['min_cost'] ?? '') !== '') {
            $query->where('a.total_cost', '>=', (float)$where['min_cost']);
        }
        if (($where['max_cost'] ?? '') !== '') {
            $query->where('a.total_cost', '<=', (float)$where['max_cost']);
        }
        if (($where['min_price'] ?? '') !== '') {
            $minPrice = (float)$where['min_price'];
            $query->where(function ($q) use ($minPrice) {
                $q->where('a.retail_price', '>=', $minPrice)->whereOr('a.estimate_sale_price', '>=', $minPrice);
            });
        }
        if (($where['max_price'] ?? '') !== '') {
            $maxPrice = (float)$where['max_price'];
            $query->where(function ($q) use ($maxPrice) {
                $q->where('a.retail_price', '<=', $maxPrice)->whereOr('a.estimate_sale_price', '<=', $maxPrice);
            });
        }
        if (($where['stock_age_min'] ?? '') !== '') {
            $query->where('a.stock_in_at', '<=', time() - max(0, (int)$where['stock_age_min']) * 86400);
        }
        if (($where['stock_age_max'] ?? '') !== '') {
            $query->where('a.stock_in_at', '>=', time() - max(0, (int)$where['stock_age_max']) * 86400);
        }
        if (!empty($where['start_at'])) {
            $query->where('a.stock_in_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('a.stock_in_at', '<=', (int)$where['end_at']);
        }
        $page = $query->field([
            'a.*',
            's.sale_no',
            's.party_name as sale_party_name',
            's.sale_channel',
            's.sale_at',
            's.finance_status as sale_finance_status',
        ])->order('a.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        $page['data'] = $this->appendLifecycleContext((array)($page['data'] ?? []));
        $page['data'] = $this->appendListingSyncState((array)$page['data']);
        return $page;
    }

    /**
     * 库存中心统一补齐设备入库与最近一次销售出库快照。
     * 即使设备尚未销售，也返回稳定的空出库字段，前端无需按状态拼接多套结构。
     */
    private function appendLifecycleContext(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $assetIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows)));
        $purchaseOrderIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['purchase_order_id'] ?? 0), $rows))));
        $purchaseItemIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['purchase_item_id'] ?? 0), $rows))));

        $purchaseOrderMap = [];
        if ($purchaseOrderIds !== []) {
            $purchaseOrders = ErpPurchaseOrder::where('site_id', '=', $this->site_id)
                ->whereIn('id', $purchaseOrderIds)
                ->field('id,purchase_no,party_name,purchaser_name,total_cost,purchase_at,create_at,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_no')
                ->select()->toArray();
            foreach ($purchaseOrders as $order) {
                $purchaseOrderMap[(int)$order['id']] = $order;
            }
        }
        $purchaseItemMap = [];
        if ($purchaseItemIds !== []) {
            $purchaseItems = ErpPurchaseItem::where('site_id', '=', $this->site_id)
                ->whereIn('id', $purchaseItemIds)
                ->field('id,warehouse_name,location_name')
                ->select()->toArray();
            foreach ($purchaseItems as $item) {
                $purchaseItemMap[(int)$item['id']] = $item;
            }
        }

        $compensationMap = [];
        if ($assetIds !== []) {
            $compensationRows = ErpAccountLedger::where([
                ['site_id', '=', $this->site_id],
                ['biz_type', '=', 'sale_compensation'],
            ])->whereIn('asset_id', $assetIds)
                ->field('asset_id,direction,amount')->select()->toArray();
            foreach ($compensationRows as $compensationRow) {
                $assetId = (int)$compensationRow['asset_id'];
                $signedAmount = (string)$compensationRow['direction'] === 'decrease'
                    ? -(float)$compensationRow['amount']
                    : (float)$compensationRow['amount'];
                $compensationMap[$assetId] = round((float)($compensationMap[$assetId] ?? 0) + $signedAmount, 2);
            }
        }

        $latestSaleItemMap = [];
        $saleOrderIds = [];
        if ($assetIds !== []) {
            $saleItems = ErpSaleItem::where('site_id', '=', $this->site_id)
                ->whereIn('asset_id', $assetIds)
                ->field('id,sale_order_id,asset_id,cost,sale_price,profit,status,remark,create_at,update_at')
                ->order('id desc')->select()->toArray();
            foreach ($saleItems as $item) {
                $assetId = (int)$item['asset_id'];
                if (!isset($latestSaleItemMap[$assetId])) {
                    $latestSaleItemMap[$assetId] = $item;
                    $saleOrderIds[] = (int)$item['sale_order_id'];
                }
            }
        }
        $saleOrderMap = [];
        $saleOrderIds = array_values(array_unique(array_filter($saleOrderIds)));
        if ($saleOrderIds !== []) {
            $saleOrders = ErpSaleOrder::where('site_id', '=', $this->site_id)
                ->whereIn('id', $saleOrderIds)
                ->field('id,sale_no,party_name,sale_channel,salesman_name,finance_status,status,sale_at,create_at,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_no')
                ->select()->toArray();
            foreach ($saleOrders as $order) {
                $saleOrderMap[(int)$order['id']] = $order;
            }
        }

        $payableMap = [];
        if ($assetIds !== []) {
            $payables = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase_asset'],
            ])->whereIn('source_id', $assetIds)
                ->field('source_id,amount,settled_amount,status')
                ->select()->toArray();
            foreach ($payables as $payable) {
                $payableMap[(int)$payable['source_id']] = $payable;
            }
        }

        // 兼容历史整单应付：新数据按设备建应付，旧数据可能只在采购单维度记录折账。
        $orderPayableMap = [];
        if ($purchaseOrderIds !== []) {
            $orderPayables = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase'],
            ])->whereIn('source_id', $purchaseOrderIds)
                ->field('source_id,amount,settled_amount,status')
                ->select()->toArray();
            foreach ($orderPayables as $orderPayable) {
                $orderPayableMap[(int)$orderPayable['source_id']] = $orderPayable;
            }
        }

        $receivableMap = [];
        $receiptAssetMap = [];
        $receiptExplicitTotalMap = [];
        if ($saleOrderIds !== []) {
            $receivables = ErpReceivable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'sale'],
            ])->whereIn('source_id', $saleOrderIds)
                ->field('id,source_id,amount,settled_amount,status')
                ->select()->toArray();
            $receivableIds = [];
            foreach ($receivables as $receivable) {
                $receivableMap[(int)$receivable['source_id']] = $receivable;
                $receivableIds[] = (int)$receivable['id'];
            }
            $receivableIds = array_values(array_unique(array_filter($receivableIds)));
            if ($receivableIds !== []) {
                $receiptRows = ErpAccountLedger::where([
                    ['site_id', '=', $this->site_id],
                    ['biz_type', '=', 'receipt'],
                    ['source_type', '=', 'receivable'],
                ])->whereIn('source_id', $receivableIds)
                    ->where('asset_id', '>', 0)
                    ->field('source_id,asset_id,SUM(amount) as amount')
                    ->group('source_id,asset_id')
                    ->select()->toArray();
                foreach ($receiptRows as $receiptRow) {
                    $receivableId = (int)$receiptRow['source_id'];
                    $assetId = (int)$receiptRow['asset_id'];
                    $amount = round((float)$receiptRow['amount'], 2);
                    $receiptAssetMap[$receivableId][$assetId] = $amount;
                    $receiptExplicitTotalMap[$receivableId] = round(($receiptExplicitTotalMap[$receivableId] ?? 0) + $amount, 2);
                }
            }
        }

        foreach ($rows as &$row) {
            $purchaseOrder = $purchaseOrderMap[(int)($row['purchase_order_id'] ?? 0)] ?? [];
            $purchaseItem = $purchaseItemMap[(int)($row['purchase_item_id'] ?? 0)] ?? [];
            $saleItem = $latestSaleItemMap[(int)($row['id'] ?? 0)] ?? [];
            $saleOrder = $saleOrderMap[(int)($saleItem['sale_order_id'] ?? 0)] ?? [];
            $payable = $payableMap[(int)($row['id'] ?? 0)] ?? [];
            $orderPayable = $orderPayableMap[(int)($row['purchase_order_id'] ?? 0)] ?? [];
            $receivable = $receivableMap[(int)($saleItem['sale_order_id'] ?? 0)] ?? [];
            $row['inbound_purchase_no'] = (string)($purchaseOrder['purchase_no'] ?? '');
            $row['inbound_origin_name'] = (string)($purchaseOrder['origin_name'] ?? '');
            $row['inbound_origin_plugin_name'] = (string)($purchaseOrder['origin_plugin_name'] ?? '');
            $row['inbound_origin_no'] = (string)($purchaseOrder['origin_no'] ?? '');
            $row['inbound_party_name'] = (string)($purchaseOrder['party_name'] ?? $row['party_name'] ?? '');
            $row['inbound_operator_name'] = (string)($purchaseOrder['purchaser_name'] ?? '');
            $row['inbound_at'] = (int)($purchaseOrder['purchase_at'] ?? 0) ?: (int)($purchaseOrder['create_at'] ?? $row['stock_in_at'] ?? 0);
            $row['inbound_warehouse_name'] = (string)($purchaseItem['warehouse_name'] ?? $row['warehouse_name'] ?? '');
            $row['inbound_location_name'] = (string)($purchaseItem['location_name'] ?? $row['location_name'] ?? '');
            $row['inbound_settlement_amount'] = round((float)($payable['amount'] ?? $row['purchase_cost'] ?? 0), 2);
            if ($payable !== []) {
                $row['inbound_settled_amount'] = min((float)$row['inbound_settlement_amount'], round((float)$payable['settled_amount'], 2));
            } elseif ($orderPayable !== []) {
                $orderTotal = round((float)($purchaseOrder['total_cost'] ?? $orderPayable['amount'] ?? 0), 2);
                $orderSettled = round((float)($orderPayable['settled_amount'] ?? 0), 2);
                $row['inbound_settled_amount'] = $orderTotal > 0.0001
                    ? min((float)$row['inbound_settlement_amount'], round((float)$row['inbound_settlement_amount'] / $orderTotal * $orderSettled, 2))
                    : 0.0;
            } else {
                $row['inbound_settled_amount'] = 0.0;
            }
            $row['inbound_finance_status'] = (float)$row['inbound_settlement_amount'] > 0
                ? ErpDict::financeStatus((float)$row['inbound_settlement_amount'], (float)$row['inbound_settled_amount'])
                : '';
            $row['outbound_sale_item_id'] = (int)($saleItem['id'] ?? 0);
            $row['outbound_sale_no'] = (string)($saleOrder['sale_no'] ?? '');
            $row['outbound_origin_name'] = (string)($saleOrder['origin_name'] ?? '');
            $row['outbound_origin_plugin_name'] = (string)($saleOrder['origin_plugin_name'] ?? '');
            $row['outbound_origin_no'] = (string)($saleOrder['origin_no'] ?? '');
            $row['outbound_party_name'] = (string)($saleOrder['party_name'] ?? '');
            $row['outbound_channel'] = (string)($saleOrder['sale_channel'] ?? '');
            $row['outbound_operator_name'] = (string)($saleOrder['salesman_name'] ?? '');
            $row['outbound_status'] = (string)($saleItem['status'] ?? '');
            $row['outbound_finance_status'] = (string)($saleOrder['finance_status'] ?? '');
            $row['outbound_at'] = (string)($saleItem['status'] ?? '') === ErpDict::STATUS_VOID
                ? ((int)($saleItem['update_at'] ?? 0) ?: (int)($saleItem['create_at'] ?? 0))
                : ((int)($saleOrder['sale_at'] ?? 0) ?: (int)($saleOrder['create_at'] ?? $saleItem['create_at'] ?? 0));
            $row['outbound_sale_price'] = round((float)($saleItem['sale_price'] ?? 0), 2);
            $row['outbound_compensation_amount'] = max(0, round((float)($compensationMap[(int)($row['id'] ?? 0)] ?? 0), 2));
            $row['outbound_net_sale_amount'] = max(0, round((float)$row['outbound_sale_price'] - (float)$row['outbound_compensation_amount'], 2));
            $row['outbound_cost'] = round((float)($saleItem['cost'] ?? 0), 2);
            $row['outbound_profit'] = round((float)($saleItem['profit'] ?? 0), 2);
            $row['outbound_remark'] = (string)($saleItem['remark'] ?? '');
            $salePrice = round((float)($saleItem['sale_price'] ?? 0), 2);
            $receivableId = (int)($receivable['id'] ?? 0);
            $explicitSettled = round((float)($receiptAssetMap[$receivableId][(int)($row['id'] ?? 0)] ?? 0), 2);
            $fallbackSettled = max(0, round((float)($receivable['settled_amount'] ?? 0) - (float)($receiptExplicitTotalMap[$receivableId] ?? 0), 2));
            $receivableAmount = round((float)($receivable['amount'] ?? 0), 2);
            $fallbackAllocated = $receivableAmount > 0.0001
                ? round($salePrice / $receivableAmount * $fallbackSettled, 2)
                : 0.0;
            $row['outbound_settlement_amount'] = $salePrice;
            $row['outbound_settled_amount'] = min($salePrice, round($explicitSettled + $fallbackAllocated, 2));
            $row['outbound_finance_status'] = (string)($saleItem['status'] ?? '') === ErpDict::ASSET_SOLD && $salePrice > 0
                ? ErpDict::financeStatus($salePrice, (float)$row['outbound_settled_amount'])
                : '';
        }
        unset($row);
        return $rows;
    }

    public function info(int $id): array
    {
        $asset = $this->findAsset($id)->toArray();
        $asset['purchase_order'] = null;
        $asset['sale_order'] = null;
        if ((int)$asset['purchase_order_id'] > 0) {
            $purchase = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset['purchase_order_id']]])->findOrEmpty();
            $asset['purchase_order'] = $purchase->isEmpty() ? null : $purchase->toArray();
        }
        if ((int)$asset['sale_order_id'] > 0) {
            $sale = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset['sale_order_id']]])->findOrEmpty();
            $asset['sale_order'] = $sale->isEmpty() ? null : $sale->toArray();
        }
        $asset['last_sale_item'] = null;
        if ($asset['sale_order'] === null) {
            $lastSaleItem = ErpSaleItem::where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $id],
            ])->order('id desc')->findOrEmpty();
            if (!$lastSaleItem->isEmpty()) {
                $asset['last_sale_item'] = $lastSaleItem->toArray();
                $lastSale = ErpSaleOrder::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$lastSaleItem->sale_order_id],
                ])->findOrEmpty();
                $asset['sale_order'] = $lastSale->isEmpty() ? null : $lastSale->toArray();
            }
        }
        $asset['asset_ledgers'] = $this->enrichAssetLedgers(
            ErpAssetLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])
                ->order('id desc')->limit(30)->select()->toArray()
        );
        $accountLedgers = ErpAccountLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])
            ->order('id desc')->limit(30)->select()->toArray();
        $asset['account_ledgers'] = $this->enrichAssetAccountLedgers($accountLedgers, $asset);
        $compensationAmount = 0.0;
        foreach ($accountLedgers as $accountLedger) {
            if (strtolower((string)($accountLedger['biz_type'] ?? '')) !== 'sale_compensation') continue;
            $signedAmount = (string)($accountLedger['direction'] ?? '') === 'decrease'
                ? -(float)($accountLedger['amount'] ?? 0)
                : (float)($accountLedger['amount'] ?? 0);
            $compensationAmount = round($compensationAmount + $signedAmount, 2);
        }
        $grossSaleAmount = round((float)($asset['sale_price'] ?? $asset['last_sale_item']['sale_price'] ?? 0), 2);
        $asset['sale_compensation_amount'] = max(0, $compensationAmount);
        $asset['net_sale_amount'] = max(0, round($grossSaleAmount - (float)$asset['sale_compensation_amount'], 2));
        $supplierAmount = round((float)($asset['purchase_cost'] ?? 0), 2);
        $paidAmount = 0.0;
        $payable = ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'purchase_asset'],
            ['source_id', '=', $id],
        ])->findOrEmpty();
        if (!$payable->isEmpty()) {
            $supplierAmount = round((float)$payable->amount, 2);
            $paidAmount = round((float)$payable->settled_amount, 2);
        }
        $purchaseCost = round((float)($asset['purchase_cost'] ?? 0), 2);
        $refurbishCost = round((float)($asset['refurbish_cost'] ?? 0), 2);
        $totalCost = round((float)($asset['total_cost'] ?? 0), 2);
        $asset['cost_summary'] = [
            'purchase_cost' => $purchaseCost,
            'supplier_adjust_cost' => round($supplierAmount - $purchaseCost, 2),
            'supplier_amount' => $supplierAmount,
            'refurbish_cost' => $refurbishCost,
            'internal_adjust_cost' => round($totalCost - $supplierAmount - $refurbishCost, 2),
            'total_cost' => $totalCost,
        ];
        $asset['return_flow'] = ErpPurchaseReturnPolicy::assess($asset, $supplierAmount, $paidAmount);
        $asset = $this->appendListingSyncState([$asset])[0] ?? $asset;
        return $asset;
    }

    /** 将拍照定价 Outbox 的最新投递状态回显给业务用户，而不只提供一个盲点重试按钮。 */
    private function appendListingSyncState(array $rows): array
    {
        if ($rows === []) return [];
        $assetIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows))));
        $stateMap = [];
        if ($assetIds !== []) {
            $events = ErpOutboxEvent::where([
                ['site_id', '=', $this->site_id],
                ['event_name', '=', 'erp.asset.ready_for_photo.v1'],
            ])->order('id desc')->limit(max(100, count($assetIds) * 20))->select()->toArray();
            foreach ($events as $event) {
                $stored = json_decode((string)($event['payload_json'] ?? ''), true);
                if (!is_array($stored)) continue;
                $assetId = (int)($stored['aggregate_id'] ?? $stored['payload']['asset_id'] ?? 0);
                if ($assetId <= 0 || !in_array($assetId, $assetIds, true) || isset($stateMap[$assetId])) continue;
                $delivery = (array)($stored['_delivery'] ?? []);
                $status = (string)($event['status'] ?? 'pending');
                $stateMap[$assetId] = [
                    'event_id' => (string)($event['event_id'] ?? ''),
                    'status' => $status,
                    'status_label' => match ($status) {
                        'done', 'processed' => '已同步',
                        'failed' => '同步失败',
                        'processing' => '同步中',
                        default => '待同步',
                    },
                    'attempts' => (int)($delivery['attempts'] ?? 0),
                    'last_error' => mb_substr(trim((string)($delivery['last_error'] ?? '')), 0, 500),
                    'updated_at' => (int)($event['update_at'] ?? $event['create_at'] ?? 0),
                ];
            }
        }
        foreach ($rows as &$row) {
            $row['listing_sync'] = $stateMap[(int)($row['id'] ?? 0)] ?? [
                'event_id' => '',
                'status' => 'not_synced',
                'status_label' => (string)($row['sale_target'] ?? '') === 'mall' ? '尚未同步' : '无需同步',
                'attempts' => 0,
                'last_error' => '',
                'updated_at' => 0,
            ];
        }
        unset($row);
        return $rows;
    }

    private function enrichAssetAccountLedgers(array $rows, array $asset): array
    {
        if ($rows === []) return [];
        $assetId = (int)($asset['id'] ?? 0);
        $purchaseOrderId = (int)($asset['purchase_order_id'] ?? 0);
        $saleOrderId = (int)($asset['sale_order_id'] ?? 0);
        if ($saleOrderId <= 0) {
            // 销售撤销/退货会清空资产当前销售单，但设备档案仍需关联最近一次销售账务。
            $saleOrderId = (int)($asset['last_sale_item']['sale_order_id'] ?? 0);
        }

        $payables = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->where(function ($query) use ($assetId, $purchaseOrderId) {
                $query->where('asset_id', '=', $assetId)
                    ->whereOr(function ($sub) use ($assetId) { $sub->where('source_type', '=', 'purchase_asset')->where('source_id', '=', $assetId); });
                if ($purchaseOrderId > 0) $query->whereOr(function ($sub) use ($purchaseOrderId) { $sub->where('source_type', '=', 'purchase')->where('source_id', '=', $purchaseOrderId); });
            })->field('id,asset_id,source_type,source_id')->select()->toArray();
        $saleOrderIds = [];
        if ($saleOrderId > 0) $saleOrderIds[] = $saleOrderId;
        foreach ($rows as $ledgerRow) {
            if (strtolower((string)($ledgerRow['biz_type'] ?? '')) === 'sale' && (int)($ledgerRow['source_id'] ?? 0) > 0) {
                $saleOrderIds[] = (int)$ledgerRow['source_id'];
            }
        }
        $saleOrderIds = array_values(array_unique($saleOrderIds));
        $receivables = $saleOrderIds !== []
            ? ErpReceivable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'sale']])->whereIn('source_id', $saleOrderIds)->field('id,source_id')->select()->toArray()
            : [];

        $targetGroups = ['purchase' => [], 'sale_compensation' => [], 'refurbish' => [], 'sale' => []];
        $payableSourceMap = [];
        $targetLookup = [];
        foreach ($payables as $payable) {
            $sourceType = (string)$payable['source_type'];
            $sourceId = (int)$payable['source_id'];
            $group = $sourceType === 'sale_return' ? 'sale_compensation' : ($sourceType === 'refurbish' ? 'refurbish' : 'purchase');
            $targetGroups[$group][] = (int)$payable['id'];
            $payableSourceMap[(int)$payable['id']] = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ];
            $contextKey = $group === 'purchase' ? 'purchase' : ($group . '_' . $sourceId);
            $targetLookup[ErpDict::TARGET_PAYABLE . '_' . (int)$payable['id']] = $contextKey;
        }
        $targetGroups['sale'] = array_map(static fn(array $row): int => (int)$row['id'], $receivables);
        foreach ($receivables as $receivable) {
            $targetLookup[ErpDict::TARGET_RECEIVABLE . '_' . (int)$receivable['id']] = 'sale_' . (int)$receivable['source_id'];
        }
        $links = [];
        if ($targetLookup !== []) {
            $links = ErpSettlementLink::where([['site_id', '=', $this->site_id]])
                ->where(function ($query) use ($targetGroups) {
                    $payableIds = array_values(array_unique(array_merge($targetGroups['purchase'], $targetGroups['sale_compensation'], $targetGroups['refurbish'])));
                    if ($payableIds !== []) $query->where(function ($sub) use ($payableIds) { $sub->where('target_type', '=', ErpDict::TARGET_PAYABLE)->whereIn('target_id', $payableIds); });
                    if ($targetGroups['sale'] !== []) $query->whereOr(function ($sub) use ($targetGroups) { $sub->where('target_type', '=', ErpDict::TARGET_RECEIVABLE)->whereIn('target_id', $targetGroups['sale']); });
                })->select()->toArray();
        }
        $settlementIds = array_values(array_unique(array_filter(array_map(static fn(array $link): int => (int)$link['settlement_id'], $links))));
        $settlementMap = $settlementIds === [] ? [] : ErpSettlement::where([['site_id', '=', $this->site_id]])->whereIn('id', $settlementIds)->column('settlement_type', 'id');
        $methodMap = [];
        $methodLabels = ['payment' => '实际付款', 'receipt' => '实际收款', 'offset' => '折账结清'];
        foreach ($links as $link) {
            $group = $targetLookup[(string)$link['target_type'] . '_' . (int)$link['target_id']] ?? '';
            $type = (string)($settlementMap[(int)$link['settlement_id']] ?? '');
            if ($group !== '' && isset($methodLabels[$type])) $methodMap[$group][$methodLabels[$type]] = $methodLabels[$type];
        }
        foreach ($rows as &$row) {
            $type = strtolower((string)($row['biz_type'] ?? ''));
            $sourceType = strtolower((string)($row['source_type'] ?? ''));
            $direction = strtolower((string)($row['direction'] ?? ''));
            $row['biz_type_text'] = FinanceDict::bizTypeText($type);
            $row['source_type_text'] = FinanceDict::sourceTypeText($sourceType);
            $row['direction_text'] = $direction === 'decrease' ? '账款减少' : '账款增加';
            $row['amount_sign'] = $direction === 'decrease' ? '-' : '+';
            $row['signed_amount'] = $direction === 'decrease'
                ? -round((float)($row['amount'] ?? 0), 2)
                : round((float)($row['amount'] ?? 0), 2);
            if ($type === 'sale_compensation' && (int)($row['source_id'] ?? 0) > 0) {
                $row['lifecycle_key'] = 'sale_return_' . (int)$row['source_id'];
            } elseif ($type === 'payment' && (string)($row['source_type'] ?? '') === 'payable') {
                $payableSource = $payableSourceMap[(int)($row['source_id'] ?? 0)] ?? null;
                if (($payableSource['source_type'] ?? '') === 'sale_return' && (int)($payableSource['source_id'] ?? 0) > 0) {
                    $row['lifecycle_key'] = 'sale_return_' . (int)$payableSource['source_id'];
                }
            }
            if ($type === 'payment') {
                // 付款流水本身就是资金事实，不能继承采购应付的折账方式。
                $row['settlement_methods'] = ['实际付款'];
                continue;
            }
            if ($type === 'receipt') {
                $row['settlement_methods'] = ['实际收款'];
                continue;
            }
            if ($type === 'offset') {
                $row['settlement_methods'] = ['折账结清'];
                continue;
            }
            if (in_array($type, ['sale_cancel', 'sale_item_cancel', 'sale_return', 'purchase_cancel', 'purchase_return'], true)) {
                $row['settlement_methods'] = ['冲销完成'];
                continue;
            }
            if ($type === 'purchase_return_loss') {
                $row['settlement_methods'] = ['计入退货损失'];
                continue;
            }
            if ($type === 'sale_compensation') {
                $contextKey = 'sale_compensation_' . (int)($row['source_id'] ?? 0);
            } elseif ($type === 'sale') {
                $contextKey = 'sale_' . (int)($row['source_id'] ?? 0);
            } elseif ($type === 'refurbish') {
                $contextKey = 'refurbish_' . (int)($row['source_id'] ?? 0);
            } else {
                $contextKey = 'purchase';
            }
            $row['settlement_methods'] = array_values($methodMap[$contextKey] ?? []);
        }
        unset($row);
        return $this->markReversedAccountLedgers($rows);
    }

    /**
     * 将销售形成的应收与后续撤销/退货冲回配对，避免历史应收继续显示“待结算”。
     * 一台设备允许多次销售，因此按发生时间逐次配对最近一笔尚未冲销的销售记录。
     */
    private function markReversedAccountLedgers(array $rows): array
    {
        if ($rows === []) return [];
        $indexes = array_keys($rows);
        usort($indexes, static function ($left, $right) use ($rows): int {
            $leftAt = (int)($rows[$left]['occurred_at'] ?? $rows[$left]['create_at'] ?? 0);
            $rightAt = (int)($rows[$right]['occurred_at'] ?? $rows[$right]['create_at'] ?? 0);
            return [$leftAt, (int)($rows[$left]['id'] ?? 0)] <=> [$rightAt, (int)($rows[$right]['id'] ?? 0)];
        });

        $openSales = [];
        foreach ($indexes as $index) {
            $type = strtolower((string)($rows[$index]['biz_type'] ?? ''));
            if ($type === 'sale') {
                $openSales[] = $index;
                continue;
            }
            if (!in_array($type, ['sale_cancel', 'sale_item_cancel', 'sale_return'], true)) continue;

            $matchedPosition = null;
            $sourceNo = (string)($rows[$index]['source_no'] ?? '');
            for ($position = count($openSales) - 1; $position >= 0; --$position) {
                $saleIndex = $openSales[$position];
                $saleSourceNo = (string)($rows[$saleIndex]['source_no'] ?? '');
                if ($type === 'sale_return' || $sourceNo === '' || $saleSourceNo === '' || $saleSourceNo === $sourceNo) {
                    $matchedPosition = $position;
                    break;
                }
            }

            $rows[$index]['business_state'] = 'reversal_completed';
            $rows[$index]['business_state_text'] = '冲销完成';
            $rows[$index]['settlement_methods'] = array_values(array_unique(array_merge(
                (array)($rows[$index]['settlement_methods'] ?? []),
                ['冲销完成']
            )));
            if ($matchedPosition === null) continue;

            $saleIndex = $openSales[$matchedPosition];
            array_splice($openSales, $matchedPosition, 1);
            $rows[$saleIndex]['business_state'] = 'reversed';
            $rows[$saleIndex]['business_state_text'] = '已冲销';
            $rows[$saleIndex]['reversed_by_ledger_no'] = (string)($rows[$index]['ledger_no'] ?? '');
            $rows[$saleIndex]['settlement_methods'] = array_values(array_unique(array_merge(
                (array)($rows[$saleIndex]['settlement_methods'] ?? []),
                ['已冲销']
            )));
            $rows[$index]['reverses_ledger_no'] = (string)($rows[$saleIndex]['ledger_no'] ?? '');
        }
        return $rows;
    }

    public function ledgerPage(array $where): array
    {
        $query = ErpAssetLedger::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('ledger_no|asset_no|imei|model|party_name|source_no|remark', '%' . $kw . '%');
        }
        if (!empty($where['asset_id'])) {
            $query->where('asset_id', '=', (int)$where['asset_id']);
        }
        if (!empty($where['action'])) {
            $query->where('action', '=', (string)$where['action']);
        }
        if (!empty($where['source_type'])) {
            $query->where('source_type', '=', (string)$where['source_type']);
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        $page['data'] = $this->enrichAssetLedgers((array)($page['data'] ?? []));
        return $page;
    }

    /**
     * 给设备账本补充稳定中文字典，PC/移动端可直接消费，未知插件值也不会裸露英文。
     */
    private function enrichAssetLedgers(array $rows): array
    {
        $costTypeMap = ErpDict::getCostTypeMap();
        foreach ($rows as &$row) {
            $action = trim((string)($row['action'] ?? ''));
            $sourceType = trim((string)($row['source_type'] ?? ''));
            $extra = json_decode((string)($row['extra_json'] ?? ''), true);
            $costType = trim((string)(is_array($extra) ? ($extra['cost_type'] ?? '') : ''));
            if ($costType === '' && isset($costTypeMap[$sourceType])) $costType = $sourceType;
            if ($costType === '' && isset($costTypeMap[$action])) $costType = $action;
            $row['action_text'] = ErpDict::ledgerActionText($action);
            $row['source_type_text'] = FinanceDict::sourceTypeText($sourceType);
            $row['before_status_text'] = ErpDict::assetStatusText((string)($row['before_status'] ?? ''));
            $row['after_status_text'] = ErpDict::assetStatusText((string)($row['after_status'] ?? ''));
            $row['cost_type'] = $costType;
            $row['cost_type_text'] = $costType !== '' ? ErpDict::costTypeText($costType) : '';
        }
        unset($row);
        return $rows;
    }

    public function updateFlow(int $id, array $data): void
    {
        $asset = $this->findAsset($id);
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
            throw new CommonException('只有库存中的设备才能调整流转状态');
        }
        $refurbishStatus = (string)($data['refurbish_status'] ?? '');
        $saleTarget = (string)($data['sale_target'] ?? '');
        $listingStatus = (string)($data['listing_status'] ?? '');
        $allowedRefurbish = ['none', 'pending', 'processing', 'done'];
        $allowedTarget = ['unset', 'peer', 'mall'];
        $allowedListing = ['none', 'need_photo', 'need_price', 'ready', 'listed'];
        $save = ['update_at' => time()];
        $changes = [];
        if ($refurbishStatus !== '') {
            if (!in_array($refurbishStatus, $allowedRefurbish, true)) {
                throw new CommonException('整备状态不正确');
            }
            if ($refurbishStatus !== (string)$asset->refurbish_status) {
                $save['refurbish_status'] = $refurbishStatus;
                $changes[] = '整备状态';
            }
        }
        if ($saleTarget !== '') {
            if (!in_array($saleTarget, $allowedTarget, true)) {
                throw new CommonException('销售去向不正确');
            }
            if ($saleTarget !== (string)$asset->sale_target) {
                $save['sale_target'] = $saleTarget;
                $changes[] = '销售去向';
            }
        }
        if ($listingStatus !== '') {
            if (!in_array($listingStatus, $allowedListing, true)) {
                throw new CommonException('上架状态不正确');
            }
            if ($listingStatus !== (string)$asset->listing_status) {
                $save['listing_status'] = $listingStatus;
                $changes[] = '上架状态';
            }
        }
        if (array_key_exists('estimate_sale_price', $data) && $data['estimate_sale_price'] !== null) {
            $estimate = round((float)$data['estimate_sale_price'], 2);
            if ($estimate < 0) {
                throw new CommonException('预计卖价不能小于0');
            }
            if (abs($estimate - round((float)$asset->estimate_sale_price, 2)) > 0.0001) {
                $save['estimate_sale_price'] = $estimate;
                $changes[] = '预计卖价';
            }
        }
        if (array_key_exists('retail_price', $data) && $data['retail_price'] !== null) {
            $retailPrice = round((float)$data['retail_price'], 2);
            if ($retailPrice < 0) throw new CommonException('零售价不能小于0');
            if (abs($retailPrice - round((float)$asset->retail_price, 2)) > 0.0001) {
                $save['retail_price'] = $retailPrice;
                $changes[] = '零售价';
            }
        }
        if (array_key_exists('image_urls', $data) && $data['image_urls'] !== null) {
            $imageUrls = trim((string)$data['image_urls']);
            if ($imageUrls !== (string)$asset->image_urls) {
                $save['image_urls'] = $imageUrls;
                $changes[] = '图片';
            }
        }
        if (array_key_exists('quality_remark', $data) && $data['quality_remark'] !== null) {
            $qualityRemark = trim((string)$data['quality_remark']);
            if ($qualityRemark !== (string)$asset->quality_remark) {
                $save['quality_remark'] = $qualityRemark;
                $changes[] = '备注';
            }
        }
        foreach (['remark_public' => '对外说明', 'remark_internal' => '对内备注'] as $field => $label) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $value = trim((string)$data[$field]);
                if ($value !== (string)$asset->{$field}) {
                    $save[$field] = $value;
                    $changes[] = $label;
                }
            }
        }
        // 使用“本次请求应用后的值”推导上架状态，避免同一次上传图片/填写价格时仍被判成待拍照。
        if ($listingStatus === '') {
            $projectedTarget = (string)($save['sale_target'] ?? $asset->sale_target);
            $projectedImages = trim((string)($save['image_urls'] ?? $asset->image_urls));
            $projectedEstimate = (float)($save['estimate_sale_price'] ?? $asset->estimate_sale_price);
            $autoListingStatus = '';
            if ($projectedTarget === 'mall') {
                $autoListingStatus = $projectedImages === '' ? 'need_photo' : ($projectedEstimate <= 0 ? 'need_price' : 'ready');
            } elseif ($saleTarget !== '' && in_array($projectedTarget, ['peer', 'unset'], true)) {
                $autoListingStatus = 'none';
            }
            if ($autoListingStatus !== '' && $autoListingStatus !== (string)$asset->listing_status) {
                $save['listing_status'] = $autoListingStatus;
                $changes[] = '上架状态';
            }
        }
        if (count($save) <= 1) {
            throw new CommonException('没有需要保存的内容');
        }
        $before = (string)$asset->refurbish_status . '/' . (string)$asset->sale_target . '/' . (string)$asset->listing_status;
        $asset->save($save);
        $afterAsset = $this->findAsset($id);
        $after = (string)$afterAsset->refurbish_status . '/' . (string)$afterAsset->sale_target . '/' . (string)$afterAsset->listing_status;
        (new ErpLedgerService())->asset([
            'asset_id' => $id,
            'action' => 'flow',
            'before_status' => $before,
            'after_status' => $after,
            'source_type' => 'asset',
            'source_id' => $id,
            'remark' => trim((string)($data['remark'] ?? '')) ?: ('更新' . implode('、', array_unique($changes))),
        ]);
        if ((string)$afterAsset->sale_target === 'mall') {
            $this->syncListing($id);
        }
    }

    /** 通过牛云领域事件把ERP设备幂等推送到拍照定价中台。 */
    public function syncListing(int $id): array
    {
        $asset = $this->findAsset($id);
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
            throw new CommonException('只有库存中的设备可以同步拍照定价');
        }
        if ((string)$asset->sale_target !== 'mall') {
            throw new CommonException('请先将设备销售去向设置为上商城');
        }
        return (new ErpIntegrationService())->publishDomainEvent(
            'erp.asset.ready_for_photo.v1',
            'asset',
            $id,
            [
                'asset_id' => $id,
                'asset_no' => (string)$asset->asset_no,
                'imei' => (string)$asset->imei,
                'sn' => (string)$asset->sn,
                'model' => (string)$asset->model,
                'spec' => (string)$asset->spec,
                'spec_json' => $asset->spec_json,
                'color' => (string)$asset->color,
                'battery' => (int)$asset->battery,
                'warranty' => (int)$asset->warranty,
                'category_id' => (int)$asset->category_id,
                'category_name' => (string)$asset->category_name,
                'warehouse_id' => (int)$asset->warehouse_id,
                'warehouse_name' => (string)$asset->warehouse_name,
                'location_id' => (int)$asset->location_id,
                'location_name' => (string)$asset->location_name,
                'purchase_cost' => round((float)$asset->purchase_cost, 2),
                'current_cost' => round((float)$asset->total_cost, 2),
                'estimate_sale_price' => round((float)$asset->estimate_sale_price, 2),
                'retail_price' => round((float)$asset->retail_price, 2),
                'image_urls' => $asset->image_urls,
                'quality_remark' => (string)$asset->quality_remark,
                'remark_public' => (string)$asset->remark_public,
                'remark_internal' => (string)$asset->remark_internal,
                'sale_target' => (string)$asset->sale_target,
                'listing_status' => (string)$asset->listing_status,
                'source_plugin' => (string)$asset->source_plugin,
                'source_type' => (string)$asset->source_type,
                'source_id' => (string)$asset->source_id,
                'snapshot_at' => time(),
            ],
            [],
            ['hsx_device_asset.ready_for_photo']
        );
    }

    public function adjustCost(int $id, float $afterCost, string $reason = '', bool $syncPayable = true, string $requestId = '', string $costType = 'internal_adjust', array $context = []): bool
    {
        if ($afterCost <= 0) {
            throw new CommonException('调整后成本必须大于0');
        }
        $asset = $this->findAsset($id);
        $allowedStatuses = [ErpDict::ASSET_IN_STOCK, 'available_for_sale'];
        if ((string)$asset->status === ErpDict::ASSET_RETURNED) {
            throw new CommonException('设备已完成采购退货，不能再调整成本');
        }
        if (!in_array((string)$asset->status, $allowedStatuses, true)) {
            throw new CommonException('只有仍在库存中的设备可以调整成本');
        }
        $beforeCost = round((float)$asset->total_cost, 2);
        if ($costType === 'refurbish' && !empty($context['refurbish_items'])) {
            $items = [];
            $itemTotal = 0.0;
            foreach ((array)$context['refurbish_items'] as $rawItem) {
                if (!is_array($rawItem)) continue;
                $name = mb_substr(trim((string)($rawItem['name'] ?? '')), 0, 40);
                $amount = round((float)($rawItem['amount'] ?? 0), 2);
                if ($name === '' || $amount <= 0) throw new CommonException('请完整填写每一项整备项目和金额');
                $partyId = (int)($rawItem['party_id'] ?? 0);
                if ($partyId <= 0) throw new CommonException('请为每一项整备项目选择服务商');
                $items[] = ['name' => $name, 'amount' => $amount, 'party_id' => $partyId];
                $itemTotal = round($itemTotal + $amount, 2);
            }
            if ($items === [] || $itemTotal <= 0) throw new CommonException('请至少填写一项整备项目');
            $context['refurbish_items'] = $items;
            $context['expense_type_key'] = count($items) > 1 ? 'refurbish_mixed' : (trim((string)($context['expense_type_key'] ?? '')) ?: 'refurbish_mixed');
            $afterCost = round($beforeCost + $itemTotal, 2);
        }
        $delta = round($afterCost - $beforeCost, 2);
        if (abs($delta) <= 0) {
            throw new CommonException('调整金额不能为0');
        }
        if (!in_array($costType, ['purchase_adjust', 'refurbish', 'internal_adjust'], true)) {
            throw new CommonException('成本类型不正确');
        }
        if ($costType === 'refurbish') {
            return $this->addRefurbishCost($id, $afterCost, $reason, $requestId, $context);
        }
        $purchaseItemId = (int)$asset->purchase_item_id;
        if ($purchaseItemId <= 0) {
            $item = ErpPurchaseItem::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('采购明细不存在，无法调整成本');
            }
            $purchaseItemId = (int)$item->id;
        }

        return (new ErpPurchaseService())->adjustCost(
            $purchaseItemId,
            $delta,
            $reason !== '' ? $reason : '移动端成本调整',
            $costType === 'purchase_adjust',
            $requestId,
            $costType
        );
    }

    private function addRefurbishCost(int $id, float $afterCost, string $reason, string $requestId, array $context): bool
    {
        $requestId = ErpIdempotency::normalize($requestId);
        if ($requestId !== '' && ErpAssetLedger::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
            ['action', '=', 'refurbish'],
        ])->count() > 0) {
            return true;
        }
        $categoryKey = trim((string)($context['expense_type_key'] ?? '')) ?: 'refurbish_labor';
        $category = (new ErpConfigService())->findFinanceCategory($categoryKey);
        if (!$category || (string)$category['direction'] !== 'expense' || (string)$category['scope'] !== 'refurbish' || (int)$category['affects_asset_cost'] !== 1) {
            throw new CommonException('请选择有效的整备支出类型');
        }
        $refurbishItems = (array)($context['refurbish_items'] ?? []);
        if ($refurbishItems === []) {
            throw new CommonException('请至少填写一项整备项目');
        }
        $partyIds = array_values(array_unique(array_map(static fn(array $item): int => (int)($item['party_id'] ?? 0), $refurbishItems)));
        if (in_array(0, $partyIds, true)) {
            throw new CommonException('请为每一项整备项目选择服务商');
        }
        $partyRows = ErpParty::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->whereIn('id', $partyIds)
            ->field('id,party_name')
            ->select()
            ->toArray();
        $partyMap = array_column($partyRows, 'party_name', 'id');
        foreach ($refurbishItems as &$refurbishItem) {
            $itemPartyId = (int)($refurbishItem['party_id'] ?? 0);
            if (!isset($partyMap[$itemPartyId])) {
                throw new CommonException('整备服务商不存在或已停用，请重新选择');
            }
            $refurbishItem['party_name'] = (string)$partyMap[$itemPartyId];
        }
        unset($refurbishItem);
        $refurbishTotal = round(array_sum(array_map(static fn(array $item): float => (float)($item['amount'] ?? 0), $refurbishItems)), 2);
        Db::transaction(function () use ($id, $afterCost, $reason, $requestId, $category, $refurbishItems, $refurbishTotal) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty() || !in_array((string)$asset->status, [ErpDict::ASSET_IN_STOCK, 'available_for_sale'], true)) {
                throw new CommonException('只有仍在库存中的设备可以登记整备费用');
            }
            $beforeCost = round((float)$asset->total_cost, 2);
            // 多项目整备以服务端明细合计为唯一事实；锁内基于最新成本累加，避免并发调整覆盖。
            $delta = $refurbishTotal > 0 ? $refurbishTotal : round($afterCost - $beforeCost, 2);
            if ($delta <= 0.0001) {
                throw new CommonException('整备费用只能增加成本；冲销整备费请走红字调整');
            }
            $asset->save([
                'refurbish_cost' => round((float)$asset->refurbish_cost + $delta, 2),
                'total_cost' => round($beforeCost + $delta, 2),
                'refurbish_status' => 'done',
                'update_at' => time(),
            ]);
            $ledger = new ErpLedgerService();
            $expenseNo = ErpLedgerService::makeNo('RF');
            $financeSourceService = new ErpFinanceSourceService();
            $uniquePartyIds = array_values(array_unique(array_column($refurbishItems, 'party_id')));
            $assetPartyId = count($uniquePartyIds) === 1 ? (int)$uniquePartyIds[0] : 0;
            $assetPartyName = count($uniquePartyIds) === 1 ? (string)$refurbishItems[0]['party_name'] : '多个整备服务商';
            $assetLedgerId = $ledger->asset([
                'asset_id' => $id,
                'request_id' => $requestId !== '' ? $requestId : null,
                'action' => 'refurbish',
                'before_status' => (string)$asset->status,
                'after_status' => (string)$asset->status,
                'before_total_cost' => $beforeCost,
                'after_total_cost' => round($beforeCost + $delta, 2),
                'cost_delta' => $delta,
                'source_type' => 'refurbish',
                'party_id' => $assetPartyId,
                'party_name' => $assetPartyName,
                'source_id' => $id,
                'source_no' => $expenseNo,
                'remark' => $reason !== '' ? $reason : (string)$category['name'],
                'extra' => ['cost_type' => 'refurbish', 'expense_type_key' => $category['key'], 'expense_type_name' => $category['name'], 'source_plugin' => $category['source_plugin'], 'source_key' => $category['source_key'], 'refurbish_cost' => $delta, 'refurbish_items' => $refurbishItems],
            ]);
            foreach ($refurbishItems as $index => $refurbishItem) {
                $itemName = (string)$refurbishItem['name'];
                $itemAmount = round((float)$refurbishItem['amount'], 2);
                $itemPartyId = (int)$refurbishItem['party_id'];
                $itemPartyName = (string)$refurbishItem['party_name'];
                $itemExpenseNo = ErpLedgerService::makeNo('RF');
                $businessReason = '整备项目【' . $itemName . '】计入设备【' . (string)($asset->model ?: $asset->asset_no)
                    . ' / IMEI ' . (string)($asset->imei ?: '-') . '】成本，应付服务商【' . $itemPartyName . '】。';
                $refurbishSource = $financeSourceService->refurbish($category, [
                    'origin_no' => $itemExpenseNo,
                    'channel_code' => 'refurbish_service',
                    'channel_name' => '整备服务',
                    'business_reason' => $businessReason,
                ]);
                $ledger->account([
                    'biz_type' => 'refurbish',
                    'direction' => 'increase',
                    'amount' => $itemAmount,
                    'party_id' => $itemPartyId,
                    'party_name' => $itemPartyName,
                    'asset_id' => $id,
                    'source_type' => 'refurbish',
                    'source_id' => $assetLedgerId,
                    'source_no' => $itemExpenseNo,
                    'remark' => $itemName . '：' . ($reason !== '' ? $reason : '设备整备成本增加'),
                ]);
                if ((int)$category['creates_finance'] !== 1) continue;
                ErpPayable::create(array_merge([
                    'site_id' => $this->site_id,
                    'payable_no' => ErpLedgerService::makeNo('AP'),
                    'party_id' => $itemPartyId,
                    'party_name' => $itemPartyName,
                    'source_type' => 'refurbish',
                    'source_id' => $assetLedgerId,
                    'source_no' => $itemExpenseNo,
                    'asset_id' => $id,
                    'amount' => $itemAmount,
                    'settled_amount' => 0,
                    'status' => ErpDict::STATUS_PENDING,
                    'occurred_at' => time(),
                    'remark' => $itemName . '：' . (string)($asset->model ?: $asset->asset_no) . ' / IMEI ' . (string)($asset->imei ?: '-'),
                    'create_at' => time(),
                    'update_at' => time(),
                ], $financeSourceService->persistable($refurbishSource)));
            }
        });
        return true;
    }

    private function findAsset(int $id): ErpAsset
    {
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('设备不存在');
        }
        return $asset;
    }

}
