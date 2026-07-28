<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use addon\hsx_erp\app\model\ErpOffset;
use addon\hsx_erp\app\model\ErpOffsetLink;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPartyMember;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpPurchaseReturnItem;
use addon\hsx_erp\app\model\ErpPurchaseReturnOrder;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleReturnOrder;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\model\ErpSettlementLink;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpMoney;
use addon\hsx_erp\app\support\ErpPartyMemberNames;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpFinanceService extends BaseAdminService
{
    /** @var int[] 当前请求在事务内写入、待事务提交后派发的结算领域事件。 */
    private array $settlementOutboxIds = [];

    public function dashboard(array $where = []): array
    {
        $range = $this->dashboardRange($where);
        $purchaseQuery = ErpPurchaseOrder::where([['site_id', '=', $this->site_id]]);
        $saleQuery = ErpSaleOrder::where([['site_id', '=', $this->site_id]]);
        $settlementQuery = ErpSettlement::where([['site_id', '=', $this->site_id]]);
        $stockQuery = ErpAsset::where([['site_id', '=', $this->site_id]]);
        $operatingExpenseQuery = ErpPayable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'hsx_erp.operating_expense']]);
        $operatingIncomeQuery = ErpReceivable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'hsx_erp.operating_income']]);
        $purchaseTable = (new ErpPurchaseOrder())->getTable();
        $saleTable = (new ErpSaleOrder())->getTable();
        $effectivePurchaseQuery = ErpAsset::alias('a')
            ->leftJoin($purchaseTable . ' o', 'o.id = a.purchase_order_id AND o.site_id = a.site_id')
            ->where('a.site_id', '=', $this->site_id)
            ->whereNotIn('a.status', [ErpDict::ASSET_RETURNED, ErpDict::ASSET_VOID]);
        $effectiveSaleQuery = ErpSaleItem::alias('i')
            ->leftJoin($saleTable . ' o', 'o.id = i.sale_order_id AND o.site_id = i.site_id')
            ->where('i.site_id', '=', $this->site_id)
            ->where('i.status', '=', ErpDict::ASSET_SOLD);

        if ($range['start_at'] > 0) {
            $purchaseQuery->where('purchase_at', '>=', $range['start_at']);
            $saleQuery->where('sale_at', '>=', $range['start_at']);
            $settlementQuery->whereRaw('IF(confirmed_at > 0, confirmed_at, create_at) >= ' . (int)$range['start_at']);
            $effectivePurchaseQuery->where('o.purchase_at', '>=', $range['start_at']);
            $effectiveSaleQuery->where('o.sale_at', '>=', $range['start_at']);
            $operatingExpenseQuery->where('occurred_at', '>=', $range['start_at']);
            $operatingIncomeQuery->where('occurred_at', '>=', $range['start_at']);
        }
        if ($range['end_at'] > 0) {
            $purchaseQuery->where('purchase_at', '<=', $range['end_at']);
            $saleQuery->where('sale_at', '<=', $range['end_at']);
            $settlementQuery->whereRaw('IF(confirmed_at > 0, confirmed_at, create_at) <= ' . (int)$range['end_at']);
            $effectivePurchaseQuery->where('o.purchase_at', '<=', $range['end_at']);
            $effectiveSaleQuery->where('o.sale_at', '<=', $range['end_at']);
            $operatingExpenseQuery->where('occurred_at', '<=', $range['end_at']);
            $operatingIncomeQuery->where('occurred_at', '<=', $range['end_at']);
        }

        $effectivePurchaseIds = array_values(array_unique(array_map('intval', (clone $effectivePurchaseQuery)->column('a.purchase_order_id'))));
        $effectiveSaleIds = array_values(array_unique(array_map('intval', (clone $effectiveSaleQuery)->column('i.sale_order_id'))));
        $purchaseAmount = (float)(clone $effectivePurchaseQuery)->sum('a.purchase_cost');
        // 销售原价保留在 sale_price 中用于审计；经营口径必须使用当前净销售收入。
        // 售后补差只会调整设备毛利，净销售收入 = 成本 + 当前毛利。
        $saleOriginalAmount = (float)(clone $effectiveSaleQuery)->sum('i.sale_price');
        $saleAmount = (float)(clone $effectiveSaleQuery)->sum(Db::raw('i.cost + i.profit'));
        $profitAmount = (float)(clone $effectiveSaleQuery)->sum('i.profit');
        $receiptAmount = (float)(clone $settlementQuery)->where('settlement_type', '=', ErpDict::SETTLEMENT_RECEIPT)->sum('amount');
        $paymentAmount = (float)(clone $settlementQuery)->where('settlement_type', '=', ErpDict::SETTLEMENT_PAYMENT)->sum('amount');
        $offsetAmount = (float)(clone $settlementQuery)->where('settlement_type', '=', ErpDict::SETTLEMENT_OFFSET)->sum('amount');
        $operatingExpenseAmount = (float)(clone $operatingExpenseQuery)->where('status', '<>', ErpDict::STATUS_VOID)->sum('amount');
        $operatingIncomeAmount = (float)(clone $operatingIncomeQuery)->where('status', '<>', ErpDict::STATUS_VOID)->sum('amount');
        $turnover = $this->todayTurnoverMetrics();
        $stockTurnover = (new ErpTurnoverService())->summary();
        $refurbishRules = (array)((new ErpConfigService())->getRules()['refurbish'] ?? []);
        $refurbishThreshold = max(1, (int)($refurbishRules['daily_reminder_threshold'] ?? 25));
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayPendingRefurbish = (int)ErpAsset::where([
            ['site_id', '=', $this->site_id], ['status', '=', ErpDict::ASSET_IN_STOCK],
        ])->where('refurbish_status', '=', 'pending')
            ->where(function ($query) use ($todayStart) {
                $query->where('refurbish_pending_at', '>=', $todayStart)
                    ->whereOr(function ($legacy) use ($todayStart) {
                        $legacy->where('refurbish_pending_at', '=', 0)->where('stock_in_at', '>=', $todayStart);
                    });
            })->count();
        $pendingRefurbish = (int)ErpAsset::where([
            ['site_id', '=', $this->site_id], ['status', '=', ErpDict::ASSET_IN_STOCK], ['refurbish_status', '=', 'pending'],
        ])->count();
        $processingRefurbish = (int)ErpAsset::where([
            ['site_id', '=', $this->site_id], ['status', '=', ErpDict::ASSET_IN_STOCK], ['refurbish_status', '=', 'processing'],
        ])->count();
        $showRefurbishReminder = (int)($refurbishRules['daily_reminder_enabled'] ?? 1) === 1
            && (string)($refurbishRules['reminder_dismiss_date'] ?? '') !== date('Y-m-d')
            && $todayPendingRefurbish >= $refurbishThreshold;

        $payableBase = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->whereRaw('amount > settled_amount');
        $receivableBase = ErpReceivable::where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->whereRaw('amount > settled_amount');
        $payableRemain = (float)(clone $payableBase)->sum('amount') - (float)(clone $payableBase)->sum('settled_amount');
        $receivableRemain = (float)(clone $receivableBase)->sum('amount') - (float)(clone $receivableBase)->sum('settled_amount');

        return [
            'range' => $range,
            'summary' => [
                'purchase_count' => count($effectivePurchaseIds),
                'purchase_amount' => round($purchaseAmount, 2),
                'sale_count' => count($effectiveSaleIds),
                'sale_amount' => round($saleAmount, 2),
                'sale_original_amount' => round($saleOriginalAmount, 2),
                'sale_compensation_amount' => round(max(0, $saleOriginalAmount - $saleAmount), 2),
                'profit_amount' => round($profitAmount, 2),
                'operating_income_amount' => round($operatingIncomeAmount, 2),
                'operating_expense_amount' => round($operatingExpenseAmount, 2),
                'operating_profit_amount' => round($profitAmount + $operatingIncomeAmount - $operatingExpenseAmount, 2),
                'receipt_amount' => round($receiptAmount, 2),
                'payment_amount' => round($paymentAmount, 2),
                'offset_amount' => round($offsetAmount, 2),
                'payable_remain' => round(max(0, $payableRemain), 2),
                'receivable_remain' => round(max(0, $receivableRemain), 2),
                'stock_count' => (int)(clone $stockQuery)->whereIn('status', ['in_stock', 'pending_sale', 'available_for_sale'])->count(),
                'stock_cost' => round((float)(clone $stockQuery)->whereIn('status', ['in_stock', 'pending_sale', 'available_for_sale'])->sum('total_cost'), 2),
                'today_sold_count' => $turnover['sold_count'],
                'opening_stock_count' => $turnover['opening_stock_count'],
                'turnover_rate' => $turnover['rate'],
                'average_stock_age_days' => $stockTurnover['average_age_days'],
                'turnover_warning_count' => $stockTurnover['warning_total_count'],
                'turnover_warning_cost' => $stockTurnover['warning_total_cost'],
            ],
            'todo' => [
                'payable_count' => (int)(clone $payableBase)->count(),
                'receivable_count' => (int)(clone $receivableBase)->count(),
                'offset_party_count' => $this->offsetPartyCount(),
                'refurbish_pending_count' => $pendingRefurbish,
                'refurbish_processing_count' => $processingRefurbish,
            ],
            'reminders' => [
                'refurbish' => [
                    'visible' => $showRefurbishReminder,
                    'today_count' => $todayPendingRefurbish,
                    'pending_count' => $pendingRefurbish,
                    'processing_count' => $processingRefurbish,
                    'threshold' => $refurbishThreshold,
                    'tracking_mode' => (string)($refurbishRules['tracking_mode'] ?? 'simple'),
                ],
                'turnover' => [
                    'visible' => (bool)$stockTurnover['reminder_visible'],
                    'warning_count' => (int)$stockTurnover['warning_count'],
                    'critical_count' => (int)$stockTurnover['critical_count'],
                    'warning_total_count' => (int)$stockTurnover['warning_total_count'],
                    'warning_total_cost' => (float)$stockTurnover['warning_total_cost'],
                    'average_age_days' => (float)$stockTurnover['average_age_days'],
                    'thresholds' => $stockTurnover['thresholds'],
                ],
            ],
            'recent' => [
                'purchases' => $this->dashboardRecentPurchases($effectivePurchaseIds),
                'sales' => $this->dashboardRecentSales($effectiveSaleIds),
                'settlements' => $this->dashboardRecentSettlements(clone $settlementQuery),
            ],
        ];
    }

    private function dashboardRecentPurchases(array $purchaseIds): array
    {
        if ($purchaseIds === []) return [];
        $rows = ErpPurchaseOrder::where('site_id', '=', $this->site_id)
            ->whereIn('id', $purchaseIds)
            ->field('id,purchase_no,party_name,total_cost,finance_status,purchase_at')
            ->order('purchase_at desc,id desc')->limit(5)->select()->toArray();
        foreach ($rows as &$row) {
            $totals = $this->effectivePurchasePayableTotals((int)$row['id']);
            $row['total_cost'] = $totals['amount'];
            $row['finance_status'] = ErpDict::financeStatus($totals['amount'], $totals['settled_amount']);
        }
        unset($row);
        ErpPartyMemberNames::append($this->site_id, $rows);
        return $rows;
    }

    /** 今日动销率 = 今日有效销量 ÷ 今日零点库存。零点库存由当前库存反推当天状态流水。 */
    private function todayTurnoverMetrics(): array
    {
        $start = strtotime(date('Y-m-d 00:00:00'));
        $end = strtotime(date('Y-m-d 23:59:59'));
        $sellableStatuses = [ErpDict::ASSET_IN_STOCK, 'pending_sale', 'available_for_sale'];
        $currentStock = (int)ErpAsset::where('site_id', '=', $this->site_id)->whereIn('status', $sellableStatuses)->count();
        $ledgers = ErpAssetLedger::where('site_id', '=', $this->site_id)
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<=', $end)
            ->field('before_status,after_status')->select()->toArray();
        $netChange = 0;
        foreach ($ledgers as $ledger) {
            $before = in_array((string)($ledger['before_status'] ?? ''), $sellableStatuses, true) ? 1 : 0;
            $after = in_array((string)($ledger['after_status'] ?? ''), $sellableStatuses, true) ? 1 : 0;
            $netChange += $after - $before;
        }
        $openingStock = max(0, $currentStock - $netChange);
        $saleTable = (new ErpSaleOrder())->getTable();
        $soldCount = (int)ErpSaleItem::alias('i')->leftJoin($saleTable . ' o', 'o.id = i.sale_order_id AND o.site_id = i.site_id')
            ->where('i.site_id', '=', $this->site_id)->where('i.status', '=', ErpDict::ASSET_SOLD)
            ->where('o.sale_at', '>=', $start)->where('o.sale_at', '<=', $end)->count();
        return ['sold_count' => $soldCount, 'opening_stock_count' => $openingStock, 'rate' => $openingStock > 0 ? round($soldCount / $openingStock * 100, 2) : 0.0];
    }

    private function dashboardRecentSettlements($query): array
    {
        $rows = $query->field('id,settlement_no,party_id,party_name,settlement_type,amount,cash_direction,capital_account_name,IF(confirmed_at > 0, confirmed_at, create_at) as confirmed_at')
            ->order('confirmed_at desc,id desc')->limit(6)->select()->toArray();
        if ($rows === []) return [];
        $links = ErpSettlementLink::where('site_id', '=', $this->site_id)
            ->whereIn('settlement_id', array_column($rows, 'id'))
            ->field('settlement_id,target_type,target_id,asset_id,biz_scene,category_name,applied_amount')
            ->order('id asc')->select()->toArray();
        $linkMap = [];
        foreach ($links as $link) $linkMap[(int)$link['settlement_id']][] = $link;
        foreach ($rows as &$row) {
            $targets = $linkMap[(int)$row['id']] ?? [];
            $row['targets'] = $targets;
            $row['target_count'] = count($targets);
            $row['target_type'] = (string)($targets[0]['target_type'] ?? '');
            $row['target_id'] = (int)($targets[0]['target_id'] ?? 0);
            $row['target_source_no'] = '';
            if ($row['target_id'] > 0 && $row['target_type'] === ErpDict::TARGET_PAYABLE) {
                $row['target_source_no'] = (string)(ErpPayable::where([['site_id', '=', $this->site_id], ['id', '=', $row['target_id']]])->value('source_no') ?: '');
            } elseif ($row['target_id'] > 0 && $row['target_type'] === ErpDict::TARGET_RECEIVABLE) {
                $row['target_source_no'] = (string)(ErpReceivable::where([['site_id', '=', $this->site_id], ['id', '=', $row['target_id']]])->value('source_no') ?: '');
            }
            $row['biz_scene'] = count(array_unique(array_filter(array_column($targets, 'biz_scene')))) === 1 ? (string)($targets[0]['biz_scene'] ?? '') : 'mixed';
        }
        unset($row);
        ErpPartyMemberNames::append($this->site_id, $rows);
        return $rows;
    }

    private function dashboardRecentSales(array $saleIds): array
    {
        if ($saleIds === []) return [];
        $rows = ErpSaleOrder::where('site_id', '=', $this->site_id)
            ->whereIn('id', $saleIds)
            ->field('id,sale_no,party_name,total_amount,profit,finance_status,sale_at')
            ->order('sale_at desc,id desc')->limit(5)->select()->toArray();
        foreach ($rows as &$row) {
            $items = ErpSaleItem::where([['site_id', '=', $this->site_id], ['sale_order_id', '=', (int)$row['id']], ['status', '=', ErpDict::ASSET_SOLD]])
                ->field('sale_price,cost,profit')->select()->toArray();
            $row['original_total_amount'] = round(array_sum(array_column($items, 'sale_price')), 2);
            $row['total_amount'] = round(array_sum(array_map(static fn(array $item): float => (float)$item['cost'] + (float)$item['profit'], $items)), 2);
            $row['compensation_amount'] = round(max(0, (float)$row['original_total_amount'] - (float)$row['total_amount']), 2);
            $row['profit'] = round(array_sum(array_column($items, 'profit')), 2);
            $receivable = ErpReceivable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'sale'], ['source_id', '=', (int)$row['id']]])->findOrEmpty();
            if (!$receivable->isEmpty()) {
                $row['finance_status'] = ErpDict::financeStatus((float)$receivable->amount, (float)$receivable->settled_amount);
            }
        }
        unset($row);
        ErpPartyMemberNames::append($this->site_id, $rows);
        return $rows;
    }

    public function payablePage(array $where): array
    {
        return $this->payableBatchPage($where);
    }

    public function receivablePage(array $where): array
    {
        return $this->receivableBatchPage($where);
    }

    public function receivableItems(int $receivableId): array
    {
        $receivable = $this->findReceivable($receivableId);
        $settlements = $this->receivableSettlementDetails($receivableId);
        if ((string)$receivable->source_type === 'purchase_return' && (int)$receivable->source_id > 0) {
            return [
                'source_type' => 'purchase_return',
                'items' => $this->purchaseReturnReceivableItems($receivable),
                'settlements' => $settlements,
            ];
        }
        if ((string)$receivable->source_type !== 'sale' || (int)$receivable->source_id <= 0) {
            $assetId = (int)($receivable->asset_id ?? 0);
            if ($assetId <= 0) {
                return ['source_type' => (string)$receivable->source_type, 'items' => [], 'settlements' => $settlements];
            }
            $asset = ErpAsset::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $assetId],
            ])->field('id,asset_no,imei,sn,model,spec,status,warehouse_name,location_name,total_cost')->findOrEmpty();
            if ($asset->isEmpty()) {
                return ['source_type' => (string)$receivable->source_type, 'items' => [], 'settlements' => $settlements];
            }
            $item = $asset->toArray();
            $item['asset_id'] = $assetId;
            $item['sale_item_id'] = 0;
            $item['sale_price'] = round((float)$receivable->amount, 2);
            $item['cost'] = 0.0;
            $item['profit'] = 0.0;
            $item['allocated_settled'] = round((float)$receivable->settled_amount, 2);
            $item['allocated_remain'] = max(0, round((float)$receivable->amount - (float)$receivable->settled_amount, 2));
            $item['remark'] = (string)($receivable->business_reason ?? $receivable->remark ?? '');
            return ['source_type' => (string)$receivable->source_type, 'items' => [$item], 'settlements' => $settlements];
        }

        $assetTable = (new ErpAsset())->getTable();
        $items = ErpSaleItem::alias('i')
            ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
            ->where([
                ['i.site_id', '=', $this->site_id],
                ['i.sale_order_id', '=', (int)$receivable->source_id],
            ])
            ->field([
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
                'a.asset_no',
                'a.sn',
                'a.spec',
                'a.warehouse_name',
                'a.location_name',
            ])
            ->order('i.id asc')
            ->select()
            ->toArray();
        $totalAmount = (float)$receivable->amount;
        $settledAmount = (float)$receivable->settled_amount;
        $itemSettledMap = $this->receivableItemSettledMap((int)$receivable->id);
        $itemSettledTotal = round(array_sum($itemSettledMap), 2);
        $fallbackSettled = max(0, round($settledAmount - $itemSettledTotal, 2));
        foreach ($items as &$item) {
            $allocated = round((float)($itemSettledMap[(int)$item['asset_id']] ?? 0) + $this->allocatedAmount((float)$item['sale_price'], $totalAmount, $fallbackSettled), 2);
            $item['allocated_settled'] = $allocated;
            $item['allocated_remain'] = max(0, round((float)$item['sale_price'] - $allocated, 2));
        }
        unset($item);

        return ['source_type' => 'sale', 'items' => $items, 'settlements' => $settlements];
    }

    public function receivableInfo(int $receivableId): array
    {
        $receivable = $this->findReceivable($receivableId);
        $row = $receivable->toArray();
        $row['source_label'] = $this->receivableSourceLabel((string)$receivable->source_type);
        $row['remain_amount'] = max(0, round((float)$receivable->amount - (float)$receivable->settled_amount, 2));

        $itemsData = $this->receivableItems($receivableId);
        $row['items'] = $itemsData['items'] ?? [];
        $row['settlements'] = $itemsData['settlements'] ?? [];

        $summaryMap = $this->settlementSummaryForTargets(ErpDict::TARGET_RECEIVABLE, [$receivableId]);
        $summary = $summaryMap[$receivableId] ?? [];
        $row['settle_summary'] = $summary['text'] ?? $this->emptySettleSummary((float)$receivable->settled_amount);
        $row['settle_summary_items'] = $summary['items'] ?? [];
        $row['source_order'] = null;

        if ((string)$receivable->source_type === 'sale' && (int)$receivable->source_id > 0) {
            $order = ErpSaleOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$receivable->source_id],
            ])->field('id,sale_no,sale_channel,sale_channel_key,channel_source_plugin,channel_source_key,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_id,origin_no,settle_method,salesman_name,total_amount,received_amount,receivable_amount,status,finance_status,remark,sale_at,create_at,update_at')->find();
            if ($order) {
                $row['source_order'] = $order->toArray();
                $row['batch_no'] = (string)($row['source_order']['sale_no'] ?? $receivable->source_no);
            }
        }

        if ((string)$receivable->source_type === 'purchase_return' && (int)$receivable->source_id > 0) {
            $order = ErpPurchaseReturnOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$receivable->source_id],
            ])->field('id,return_no,purchase_order_id,purchase_no,party_name,operator_name,refund_mode,total_amount,status,remark,occurred_at,create_at,update_at')->find();
            if ($order) {
                $row['source_order'] = $order->toArray();
                $row['batch_no'] = (string)($row['source_order']['return_no'] ?? $receivable->source_no);
                $row['purchase_no'] = (string)($row['source_order']['purchase_no'] ?? '');
                $row['return_remark'] = (string)($row['source_order']['remark'] ?? '');
                $row['business_reason'] = sprintf(
                    '采购退货已完成，已付款部分形成供货商退款应收 %s；财务需核对供货商实际退款到账。',
                    '¥' . number_format((float)$receivable->amount, 2, '.', '')
                );
            }
        }

        $sourceContext = array_merge($row, (array)($row['source_order'] ?? []));
        if (empty($sourceContext['channel_code'])) {
            $sourceContext['channel_code'] = (string)($sourceContext['sale_channel_key'] ?? '');
        }
        if (empty($sourceContext['channel_name'])) {
            $sourceContext['channel_name'] = (string)($sourceContext['sale_channel'] ?? '');
        }
        $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($sourceContext, 'receivable');
        $row['source_label'] = (string)$row['source_meta']['finance_type_name'];
        $row['business_reason'] = (string)($row['source_meta']['business_reason'] ?? $row['business_reason'] ?? '');

        $partyRows = [$row];
        ErpPartyMemberNames::append($this->site_id, $partyRows);
        $row = $partyRows[0];

        return $row;
    }

    private function purchaseReturnReceivableItems(ErpReceivable $receivable): array
    {
        $items = ErpPurchaseReturnItem::where([
            ['site_id', '=', $this->site_id],
            ['return_id', '=', (int)$receivable->source_id],
        ])->order('id asc')->select()->toArray();
        if (empty($items)) {
            return [];
        }

        $totalAmount = (float)$receivable->amount;
        $settledAmount = (float)$receivable->settled_amount;
        $itemSettledMap = $this->receivableItemSettledMap((int)$receivable->id);
        $itemSettledTotal = round(array_sum($itemSettledMap), 2);
        $fallbackSettled = max(0, round($settledAmount - $itemSettledTotal, 2));
        foreach ($items as &$item) {
            $returnCost = round((float)($item['return_cost'] ?? 0), 2);
            $refundAmount = $this->purchaseReturnItemRefundAmount($item);
            $offsetAmount = round((float)($item['unpaid_offset_amount'] ?? 0), 2);
            $allocated = round((float)($itemSettledMap[(int)$item['asset_id']] ?? 0) + $this->allocatedAmount($refundAmount, $totalAmount, $fallbackSettled), 2);
            $item['id'] = (int)$item['asset_id'];
            $item['sale_price'] = $refundAmount;
            $item['return_cost'] = $returnCost;
            $item['refund_amount'] = $refundAmount;
            $item['allocated_settled'] = $allocated;
            $item['allocated_remain'] = max(0, round($refundAmount - $allocated, 2));
            $item['source_label'] = '采购退货退款';
            if ($refundAmount > 0.0001 && $offsetAmount > 0.0001) {
                $item['settlement_explanation'] = sprintf('未付款 ¥%.2f 已冲销应付；已付款部分需收回 ¥%.2f。', $offsetAmount, $refundAmount);
            } elseif ($refundAmount > 0.0001) {
                $item['settlement_explanation'] = sprintf('该设备采购款已支付，退货后需向供货商收回 ¥%.2f。', $refundAmount);
            } else {
                $item['settlement_explanation'] = sprintf('该设备未付款，已冲销应付 ¥%.2f，无需实际收款。', $offsetAmount);
            }
        }
        unset($item);

        return $items;
    }

    private function purchaseReturnItemRefundAmount(array $item): float
    {
        $policy = json_decode((string)($item['policy_json'] ?? ''), true);
        if (is_array($policy) && array_key_exists('refund_amount', $policy)) {
            return max(0, round((float)$policy['refund_amount'], 2));
        }
        $stored = round((float)($item['refund_receivable_amount'] ?? 0), 2);
        if ($stored > 0.0001 || (float)($item['paid_amount'] ?? 0) <= 0.0001) {
            return max(0, $stored);
        }
        return max(0, round((float)($item['paid_amount'] ?? 0), 2));
    }

    private function receivableItemSettledMap(int $receivableId): array
    {
        if ($receivableId <= 0) {
            return [];
        }
        $rows = ErpAccountLedger::where([
            ['site_id', '=', $this->site_id],
            ['biz_type', '=', 'receipt'],
            ['source_type', '=', 'receivable'],
            ['source_id', '=', $receivableId],
        ])
            ->where('asset_id', '>', 0)
            ->field('asset_id,SUM(amount) as amount')
            ->group('asset_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['asset_id']] = round((float)$row['amount'], 2);
        }
        return $map;
    }

    public function accountLedgerPage(array $where): array
    {
        $query = ErpAccountLedger::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('ledger_no|party_name|source_no|remark', '%' . $kw . '%');
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $bizType = trim((string)($row['biz_type'] ?? ''));
            $sourceType = trim((string)($row['source_type'] ?? ''));
            $direction = (string)($row['direction'] ?? '');
            $amount = round((float)($row['amount'] ?? 0), 2);
            $row['biz_type_text'] = FinanceDict::bizTypeText($bizType);
            $row['source_type_text'] = FinanceDict::sourceTypeText($sourceType);
            $row['direction_text'] = $direction === 'decrease' ? '账款减少' : '账款增加';
            $row['amount_sign'] = $direction === 'decrease' ? '-' : '+';
            $row['signed_amount'] = $direction === 'decrease' ? -$amount : $amount;
        }
        unset($row);
        return $page;
    }

    public function moneyLedgerPage(array $where): array
    {
        $query = ErpMoneyLedger::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('ledger_no|party_name|capital_account_name|category_name|remark', '%' . $kw . '%');
        }
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    /**
     * 结算明细：每一笔账最终是怎么结的（现金/微信/支付宝/银行卡/折账）。
     * 支持按往来单位、结算方式、设备(asset_id)、关键词、时间筛选。
     * 返回每笔结算的方式(带中文)、账户、金额、经手人、时间，并附抵扣的应付/应收明细。
     */
    public function settlementPage(array $where): array
    {
        $query = ErpSettlement::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['party_id'])) {
            $query->where('party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['settlement_type'])) {
            $query->where('settlement_type', '=', (string)$where['settlement_type']);
        }
        if (!empty($where['capital_account_id'])) {
            $query->where('capital_account_id', '=', (int)$where['capital_account_id']);
        }
        if (!empty($where['start_at'])) {
            $query->where('confirmed_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('confirmed_at', '<=', (int)$where['end_at']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('settlement_no|party_name|capital_account_name|remark', '%' . $kw . '%');
        }
        // 按设备筛：经 settlement_link → payable/receivable → asset。
        // 应付按台(purchase_asset.source_id=asset_id)；应收按单(sale.source_id=sale_order_id)，需先由设备取其销售单。
        if (!empty($where['asset_id'])) {
            $assetId = (int)$where['asset_id'];
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->findOrEmpty();
            $payableIds = ErpPayable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'purchase_asset'], ['source_id', '=', $assetId]])->column('id');
            $receivableIds = [];
            if (!$asset->isEmpty() && (int)$asset->sale_order_id > 0) {
                $receivableIds = ErpReceivable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'sale'], ['source_id', '=', (int)$asset->sale_order_id]])->column('id');
            }
            $linkQuery = ErpSettlementLink::where([['site_id', '=', $this->site_id]]);
            $linkQuery->where(function ($q) use ($payableIds, $receivableIds) {
                $has = false;
                if (!empty($payableIds)) { $q->whereOr(function ($qq) use ($payableIds) { $qq->where('target_type', 'payable')->whereIn('target_id', $payableIds); }); $has = true; }
                if (!empty($receivableIds)) { $q->whereOr(function ($qq) use ($receivableIds) { $qq->where('target_type', 'receivable')->whereIn('target_id', $receivableIds); }); $has = true; }
                if (!$has) { $q->where('settlement_id', '=', 0); }
            });
            $settlementIds = array_values(array_unique(array_map('intval', $linkQuery->column('settlement_id'))));
            $query->whereIn('id', !empty($settlementIds) ? $settlementIds : [0]);
        }

        $page = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();

        $ids = array_column($page['data'], 'id');
        $linksBySettlement = [];
        $moneyBySettlement = [];
        if (!empty($ids)) {
            $linksBySettlement = $this->settlementTargetDetails(array_map('intval', $ids));
            $moneyRows = ErpMoneyLedger::where([['site_id', '=', $this->site_id]])
                ->whereIn('settlement_id', array_map('intval', $ids))
                ->order('id asc')
                ->select()
                ->toArray();
            foreach ($moneyRows as $moneyRow) {
                $moneyBySettlement[(int)$moneyRow['settlement_id']][] = $moneyRow;
            }
        }
        foreach ($page['data'] as &$row) {
            $row['settlement_type_text'] = self::settlementTypeText((string)$row['settlement_type']);
            $row['pay_method_text'] = $this->payMethodText($row);
            $row['links'] = $linksBySettlement[(int)$row['id']] ?? [];
            $row['money_ledgers'] = $moneyBySettlement[(int)$row['id']] ?? [];
        }
        unset($row);
        return $page;
    }

    /**
     * 结算方式中文：付款/收款/折账。
     */
    public static function settlementTypeText(string $type): string
    {
        $map = [
            ErpDict::SETTLEMENT_PAYMENT => '付款',
            ErpDict::SETTLEMENT_RECEIPT => '收款',
            ErpDict::SETTLEMENT_OFFSET => '折账',
        ];
        return $map[$type] ?? ('未知(' . $type . ')');
    }

    private function receivableSourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'sale' => '销售收款',
            'purchase_return' => '采购退货退款',
            default => $sourceType !== '' ? $sourceType : '应收款',
        };
    }

    /**
     * "怎么结的"一句话：折账不走账户，现金收付显示账户名。
     */
    private function payMethodText(array $row): string
    {
        if (($row['settlement_type'] ?? '') === ErpDict::SETTLEMENT_OFFSET) {
            return '折账（不走现金）';
        }
        $account = trim((string)($row['capital_account_name'] ?? ''));
        return $account !== '' ? $account : '现金';
    }


    public function confirmPayment(int $payableId, float $amount, array $data): int
    {
        if ($amount <= 0) {
            throw new CommonException('付款金额必须大于0');
        }
        $settlementId = $this->runIdempotentSettlement($data, ErpDict::SETTLEMENT_PAYMENT, function (array $requestData) use ($payableId, $amount): int {
            $settlementId = 0;
            Db::transaction(function () use ($payableId, $amount, $requestData, &$settlementId) {
                $settlementId = $this->confirmPaymentInTransaction($payableId, $amount, $requestData);
            });
            return $settlementId;
        }, ['amount' => $amount, 'target_type' => ErpDict::TARGET_PAYABLE, 'target_ids' => [$payableId]]);
        $this->flushSettlementDomainEvents();
        (new ErpPrintService())->triggerSafely('payment_confirmed', 'payable', $payableId, [
            'settlement_id' => $settlementId,
            'amount' => number_format($amount, 2, '.', ''),
        ]);
        return $settlementId;
    }

    public function confirmPartyPayment(int $partyId, float $amount, array $data): array
    {
        if ($partyId <= 0) {
            throw new CommonException('请选择付款对象');
        }
        if ($amount <= 0) {
            throw new CommonException('付款金额必须大于0');
        }
        $expectedIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            (array)($data['payable_ids'] ?? []), [(int)($data['payable_id'] ?? 0)]
        )))));
        $settlementId = $this->runIdempotentSettlement($data, ErpDict::SETTLEMENT_PAYMENT, function (array $requestData) use ($partyId, $amount): int {
            $settlementId = 0;
            Db::transaction(function () use ($partyId, $amount, $requestData, &$settlementId) {
                $left = round($amount, 2);
                $items = [];
                $payables = $this->partyPaymentTargets($partyId, $requestData);
                foreach ($payables as $row) {
                    if ($left <= 0) {
                        break;
                    }
                    $remain = round((float)$row['amount'] - (float)$row['settled_amount'], 2);
                    if ($remain <= 0) {
                        continue;
                    }
                    $apply = min($left, $remain);
                    $items[] = ['payable_id' => (int)$row['id'], 'amount' => $apply];
                    $left = round($left - $apply, 2);
                }
                if ($left > 0.0001) {
                    throw new CommonException('付款金额不能大于当前来源批次剩余应付');
                }
                $settlementId = $this->confirmPayableItemsInTransaction($partyId, $items, $requestData);
            });
            return $settlementId;
        }, array_filter([
            'amount' => $amount,
            'party_id' => $partyId,
            'target_type' => $expectedIds !== [] ? ErpDict::TARGET_PAYABLE : '',
            'target_ids' => $expectedIds,
            'target_match' => 'subset',
            'source_type' => trim((string)($data['source_type'] ?? '')),
            'batch_id' => (int)($data['purchase_order_id'] ?? 0) > 0
                ? (int)$data['purchase_order_id']
                : ((int)($data['source_id'] ?? 0) > 0 ? (int)$data['source_id'] : (int)($data['batch_id'] ?? 0)),
            'batch_no' => trim((string)($data['batch_no'] ?? '')),
        ], static fn($value): bool => $value !== '' && $value !== []));
        $this->flushSettlementDomainEvents();
        $printPayableId = (int)(Db::name('erp_settlement_link')->where([
            ['site_id', '=', $this->site_id],
            ['settlement_id', '=', $settlementId],
            ['target_type', '=', ErpDict::TARGET_PAYABLE],
        ])->order('id asc')->value('target_id') ?? 0);
        if ($printPayableId > 0) {
            (new ErpPrintService())->triggerSafely('payment_confirmed', 'payable', $printPayableId, [
                'settlement_id' => $settlementId,
                'amount' => number_format($amount, 2, '.', ''),
            ]);
        }
        return [$settlementId];
    }

    /**
     * “整体付款”只能在用户明确选择的应付或当前来源批次内分配，绝不按主体跨批次猜账。
     *
     * 兼容两种请求：
     * 1. payable_ids / payable_id：前端明确传应付主键（推荐）；
     * 2. source_type + purchase_order_id|source_id|batch_id：旧整体付款按当前批次展开。
     */
    private function partyPaymentTargets(int $partyId, array $data): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', array_merge(
            (array)($data['payable_ids'] ?? []),
            [(int)($data['payable_id'] ?? 0)]
        )))));
        $sourceType = trim((string)($data['source_type'] ?? ''));
        $batchId = (int)($data['purchase_order_id'] ?? 0);
        if ($batchId <= 0) $batchId = (int)($data['source_id'] ?? 0);
        if ($batchId <= 0) $batchId = (int)($data['batch_id'] ?? 0);

        $query = ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['party_id', '=', $partyId],
        ])->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL]);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            if ($sourceType === '' || $batchId <= 0) {
                throw new CommonException('整体付款必须提交 payable_ids，或明确当前来源类型和批次');
            }
            $this->applyPayableBatchScope($query, $sourceType, $batchId);
        }

        $payables = $query->order('occurred_at asc,id asc')->lock(true)->select();
        if ($payables->isEmpty()) {
            throw new CommonException('当前来源批次没有可付款应付，请刷新后重试');
        }
        if ($ids !== [] && $payables->count() !== count($ids)) {
            throw new CommonException('部分应付款不存在、已结清或已变化，请刷新后重试');
        }

        $this->assertSinglePayableBatch($payables, $sourceType, $batchId, trim((string)($data['batch_no'] ?? '')));
        return $payables->toArray();
    }

    private function applyPayableBatchScope($query, string $sourceType, int $batchId): void
    {
        if ($sourceType === 'purchase') {
            $assetIds = ErpAsset::where([
                ['site_id', '=', $this->site_id],
                ['purchase_order_id', '=', $batchId],
            ])->column('id');
            $query->where(function ($scope) use ($batchId, $assetIds) {
                $scope->where(function ($purchase) use ($batchId) {
                    $purchase->where('source_type', '=', 'purchase')->where('source_id', '=', $batchId);
                });
                if ($assetIds !== []) {
                    $scope->whereOr(function ($assets) use ($assetIds) {
                        $assets->where('source_type', '=', 'purchase_asset')->whereIn('source_id', array_map('intval', $assetIds));
                    });
                }
            });
            return;
        }

        if (in_array($sourceType, ['refurbish', 'sale_return'], true)) {
            $query->where('source_type', '=', $sourceType)->where('source_id', '=', $batchId);
            return;
        }

        $query->where('source_id', '=', $batchId)->where(function ($scope) use ($sourceType) {
            $scope->where('source_type', '=', $sourceType)->whereOr('biz_scene', '=', $sourceType);
        });
    }

    private function assertSinglePayableBatch(iterable $payables, string $expectedSourceType = '', int $expectedBatchId = 0, string $expectedBatchNo = ''): void
    {
        $rows = [];
        $purchaseAssetIds = [];
        foreach ($payables as $payable) {
            $row = is_array($payable) ? $payable : $payable->toArray();
            $rows[] = $row;
            if ((string)($row['source_type'] ?? '') === 'purchase_asset') {
                $purchaseAssetIds[] = (int)($row['source_id'] ?? 0);
            }
        }
        $assetBatchMap = $purchaseAssetIds === [] ? [] : ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', array_values(array_unique($purchaseAssetIds)))
            ->column('purchase_order_id', 'id');

        $signatures = [];
        foreach ($rows as $row) {
            $rawType = (string)($row['source_type'] ?? '');
            $sourceType = match ($rawType) {
                'purchase', 'purchase_asset' => 'purchase',
                'refurbish' => 'refurbish',
                'sale_return' => 'sale_return',
                default => trim((string)($row['biz_scene'] ?? '')) ?: $rawType,
            };
            $batchId = $rawType === 'purchase_asset'
                ? (int)($assetBatchMap[(int)($row['source_id'] ?? 0)] ?? 0)
                : (int)($row['source_id'] ?? 0);
            $batchNo = trim((string)($row['source_no'] ?? $row['origin_no'] ?? ''));
            $signatures[$sourceType . ':' . $batchId] = [$sourceType, $batchId, $batchNo];
        }
        if (count($signatures) !== 1) {
            throw new CommonException('整体付款只能核销当前一个来源批次，请逐批付款');
        }
        [$actualSourceType, $actualBatchId, $actualBatchNo] = array_values($signatures)[0];
        if ($actualSourceType === 'purchase' && $actualBatchId > 0) {
            $actualBatchNo = (string)ErpPurchaseOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $actualBatchId],
            ])->value('purchase_no');
        }
        if ($expectedSourceType !== '' && $actualSourceType !== $expectedSourceType) {
            throw new CommonException('应付款来源已变化，请刷新后重试');
        }
        if ($expectedBatchId > 0 && $actualBatchId !== $expectedBatchId) {
            throw new CommonException('应付款批次已变化，请刷新后重试');
        }
        if ($expectedBatchNo !== '' && $actualBatchNo !== '' && $actualBatchNo !== $expectedBatchNo) {
            throw new CommonException('应付款来源单号已变化，请刷新后重试');
        }
    }

    public function confirmPayableItemsPayment(int $partyId, array $items, array $data): int
    {
        $expectedIds = [];
        $expectedAmount = 0.0;
        foreach ($items as $item) {
            $payableId = (int)($item['payable_id'] ?? 0);
            $itemAmount = round((float)($item['amount'] ?? 0), 2);
            if ($payableId <= 0 || $itemAmount <= 0) continue;
            $expectedIds[] = $payableId;
            $expectedAmount = round($expectedAmount + $itemAmount, 2);
        }
        $settlementId = $this->runIdempotentSettlement($data, ErpDict::SETTLEMENT_PAYMENT, function (array $requestData) use ($partyId, $items): int {
            $settlementId = 0;
            Db::transaction(function () use ($partyId, $items, $requestData, &$settlementId) {
                $settlementId = $this->confirmPayableItemsInTransaction($partyId, $items, $requestData);
            });
            return $settlementId;
        }, [
            'amount' => $expectedAmount,
            'party_id' => $partyId,
            'target_type' => ErpDict::TARGET_PAYABLE,
            'target_ids' => array_values(array_unique($expectedIds)),
        ]);
        $this->flushSettlementDomainEvents();
        return $settlementId;
    }

    public function confirmPayableItemsInTransaction(int $partyId, array $items, array $data): int
    {
        if ($partyId <= 0) {
            throw new CommonException('请选择付款对象');
        }
        $applyMap = [];
        foreach ($items as $item) {
            $payableId = (int)($item['payable_id'] ?? 0);
            $amount = round((float)($item['amount'] ?? 0), 2);
            if ($payableId <= 0 || $amount <= 0) {
                continue;
            }
            $applyMap[$payableId] = round(($applyMap[$payableId] ?? 0) + $amount, 2);
        }
        if (empty($applyMap)) {
            throw new CommonException('请选择要付款的应付明细');
        }
        $payables = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', array_keys($applyMap))
            ->order('id asc')
            ->lock(true)
            ->select();
        if ($payables->count() !== count($applyMap)) {
            throw new CommonException('应付款不存在或已变化');
        }

        $totalAmount = 0.0;
        $firstPayable = null;
        foreach ($payables as $payable) {
            if ($firstPayable === null) {
                $firstPayable = $payable;
            }
            if ((int)$payable->party_id !== $partyId) {
                throw new CommonException('只能处理同一个往来主体的应付款');
            }
            if (!in_array((string)$payable->status, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)) {
                throw new CommonException('只能付款待付款或部分付款的应付明细');
            }
            $amount = (float)$applyMap[(int)$payable->id];
            $remain = round((float)$payable->amount - (float)$payable->settled_amount, 2);
            if ($amount > $remain + 0.0001) {
                throw new CommonException('付款金额不能大于该明细剩余应付');
            }
            $totalAmount = round($totalAmount + $amount, 2);
        }
        if ($totalAmount <= 0) {
            throw new CommonException('付款金额必须大于0');
        }

        $account = $this->resolveAccount((int)($data['capital_account_id'] ?? 0));
        $settlement = $this->createSettlement($firstPayable, ErpDict::SETTLEMENT_PAYMENT, $totalAmount, 'out', $account, $data);
        $settlementId = (int)$settlement->id;
        $purchaseIds = [];

        foreach ($payables as $payable) {
            $amount = (float)$applyMap[(int)$payable->id];
            $this->applyPayable($payable, $amount, $settlementId);
            (new ErpLedgerService())->account([
                'biz_type' => 'payment',
                'direction' => 'decrease',
                'amount' => $amount,
                'party_id' => (int)$payable->party_id,
                'party_name' => (string)$payable->party_name,
                'asset_id' => $this->assetIdFromPayable($payable),
                'source_type' => 'payable',
                'source_id' => (int)$payable->id,
                'source_no' => (string)$payable->payable_no,
                'remark' => (string)($data['remark'] ?? '财务确认付款'),
            ]);
            $purchaseId = $this->purchaseIdFromPayable($payable);
            if ($purchaseId > 0) {
                $purchaseIds[$purchaseId] = $purchaseId;
            }
        }

        $balanceAfter = $this->adjustCapitalAccount((int)($account['id'] ?? 0), 'out', $totalAmount);
        $categoryMeta = $this->combinedCategoryMeta($payables);
        (new ErpLedgerService())->money(array_merge([
            'settlement_id' => $settlementId,
            'capital_account_id' => (int)($account['id'] ?? 0),
            'capital_account_name' => (string)($account['name'] ?? ''),
            'direction' => 'out',
            'amount' => $totalAmount,
            'balance_after' => $balanceAfter,
            'party_id' => $partyId,
            'party_name' => (string)$firstPayable->party_name,
            'voucher_urls' => $data['voucher_urls'] ?? '',
            'remark' => (string)($data['remark'] ?? '确认付款'),
        ], $categoryMeta));
        foreach ($purchaseIds as $purchaseId) {
            $this->refreshPurchaseFinance($purchaseId);
        }
        $this->queueSettlementCompletedEvent($settlementId);
        return $settlementId;
    }

    public function confirmPaymentInTransaction(int $payableId, float $amount, array $data): int
    {
        if ($amount <= 0) {
            throw new CommonException('付款金额必须大于0');
        }
        $payable = $this->findPayable($payableId, true);
        if (!in_array((string)$payable->status, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)) {
            throw new CommonException('只能付款待付款或部分付款的应付款');
        }
        $remain = round((float)$payable->amount - (float)$payable->settled_amount, 2);
        if ($amount > $remain + 0.0001) {
            throw new CommonException('付款金额不能大于剩余应付');
        }
        $account = $this->resolveAccount((int)($data['capital_account_id'] ?? 0));
        $settlement = $this->createSettlement($payable, ErpDict::SETTLEMENT_PAYMENT, $amount, 'out', $account, $data);
        $settlementId = (int)$settlement->id;
        $this->applyPayable($payable, $amount, $settlementId);
        $balanceAfter = $this->adjustCapitalAccount((int)($account['id'] ?? 0), 'out', $amount);
        (new ErpLedgerService())->money(array_merge([
            'settlement_id' => $settlementId,
            'capital_account_id' => (int)($account['id'] ?? 0),
            'capital_account_name' => (string)($account['name'] ?? ''),
            'direction' => 'out',
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'party_id' => (int)$payable->party_id,
            'party_name' => (string)$payable->party_name,
            'voucher_urls' => $data['voucher_urls'] ?? '',
            'remark' => (string)($data['remark'] ?? '确认付款'),
        ], $this->targetCategoryMeta($payable)));
        (new ErpLedgerService())->account([
            'biz_type' => 'payment',
            'direction' => 'decrease',
            'amount' => $amount,
            'party_id' => (int)$payable->party_id,
            'party_name' => (string)$payable->party_name,
            'asset_id' => $this->assetIdFromPayable($payable),
            'source_type' => 'payable',
            'source_id' => (int)$payable->id,
            'source_no' => (string)$payable->payable_no,
            'remark' => (string)($data['remark'] ?? '财务确认付款'),
        ]);
        $this->refreshPurchaseByPayable($payable);
        $this->queueSettlementCompletedEvent($settlementId);
        return $settlementId;
    }

    public function confirmReceipt(int $receivableId, float $amount, array $data): int
    {
        if ($amount <= 0) {
            throw new CommonException('收款金额必须大于0');
        }
        $expectedAmount = round(array_sum(array_map(
            static fn(array $item): float => max(0, round((float)($item['amount'] ?? 0), 2)),
            array_values(array_filter((array)($data['items'] ?? []), 'is_array'))
        )), 2);
        if ($expectedAmount <= 0) $expectedAmount = $amount;
        $settlementId = $this->runIdempotentSettlement($data, ErpDict::SETTLEMENT_RECEIPT, function (array $requestData) use ($receivableId, $amount): int {
            $settlementId = 0;
            Db::transaction(function () use ($receivableId, $amount, $requestData, &$settlementId) {
            $receivable = $this->findReceivable($receivableId, true);
            if (!in_array((string)$receivable->status, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)) {
                throw new CommonException('只能收款待收款或部分收款的应收款');
            }
            $receiptApply = $this->applyReceiptItems($receivable, (array)($requestData['items'] ?? []), $amount);
            $amount = (float)$receiptApply['amount'];
            $receiptItems = (array)$receiptApply['items'];
            $remain = round((float)$receivable->amount - (float)$receivable->settled_amount, 2);
            if ($amount > $remain + 0.0001) {
                throw new CommonException('收款金额不能大于剩余应收');
            }
            $account = $this->resolveAccount((int)($requestData['capital_account_id'] ?? 0));
            $settlement = $this->createSettlement($receivable, ErpDict::SETTLEMENT_RECEIPT, $amount, 'in', $account, $requestData);
            $settlementId = (int)$settlement->id;
            $this->applyReceivable($receivable, $amount, $settlementId);
            $balanceAfter = $this->adjustCapitalAccount((int)($account['id'] ?? 0), 'in', $amount);
            (new ErpLedgerService())->money(array_merge([
                'settlement_id' => $settlementId,
                'capital_account_id' => (int)($account['id'] ?? 0),
                'capital_account_name' => (string)($account['name'] ?? ''),
                'direction' => 'in',
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'party_id' => (int)$receivable->party_id,
                'party_name' => (string)$receivable->party_name,
                'voucher_urls' => $requestData['voucher_urls'] ?? '',
                'remark' => (string)($requestData['remark'] ?? '确认收款'),
            ], $this->targetCategoryMeta($receivable)));
            $this->writeReceiptAccountLedgers($receivable, $amount, $receiptItems, (string)($requestData['remark'] ?? '财务确认收款'));
            if ((string)$receivable->source_type === 'sale') {
                $this->refreshSaleFinance((int)$receivable->source_id);
            }
            $this->queueSettlementCompletedEvent($settlementId);
            });
            return $settlementId;
        }, ['amount' => $expectedAmount, 'target_type' => ErpDict::TARGET_RECEIVABLE, 'target_ids' => [$receivableId]]);
        $this->flushSettlementDomainEvents();
        (new ErpPrintService())->triggerSafely('receipt_confirmed', 'receivable', $receivableId, [
            'settlement_id' => $settlementId,
            'amount' => number_format($amount, 2, '.', ''),
        ]);
        return $settlementId;
    }

    private function applyReceiptItems(ErpReceivable $receivable, array $items, float $amount): array
    {
        if ((string)$receivable->source_type === 'purchase_return') {
            return $this->applyReceiptPurchaseReturnItems($receivable, $items, $amount);
        }
        if ((string)$receivable->source_type !== 'sale') {
            $assetId = (int)($receivable->asset_id ?? 0);
            if ($assetId <= 0) return ['amount' => $amount, 'items' => []];
            $itemAmount = $amount;
            foreach ($items as $item) {
                if ((int)($item['asset_id'] ?? $item['id'] ?? 0) !== $assetId) continue;
                $candidate = round((float)($item['amount'] ?? 0), 2);
                if ($candidate > 0) $itemAmount = $candidate;
                break;
            }
            return ['amount' => $itemAmount, 'items' => [['asset_id' => $assetId, 'amount' => $itemAmount]]];
        }
        return $this->applyReceiptSaleItems($receivable, $items, $amount);
    }

    private function applyReceiptSaleItems(ErpReceivable $receivable, array $items, float $amount): array
    {
        if (empty($items) || (string)$receivable->source_type !== 'sale' || (int)$receivable->source_id <= 0) {
            return ['amount' => $amount, 'items' => []];
        }

        $itemMap = [];
        $receiptTotal = 0.0;
        foreach ($items as $item) {
            $itemId = (int)($item['sale_item_id'] ?? $item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $salePrice = round((float)($item['sale_price'] ?? 0), 2);
            $receiptAmount = round((float)($item['amount'] ?? 0), 2);
            if ($salePrice <= 0) {
                throw new CommonException('设备销售价必须大于0');
            }
            if ($receiptAmount < 0) {
                throw new CommonException('设备收款金额不能小于0');
            }
            $itemMap[$itemId] = ['sale_price' => $salePrice, 'amount' => $receiptAmount];
            $receiptTotal = round($receiptTotal + $receiptAmount, 2);
        }
        if (empty($itemMap)) {
            return ['amount' => $amount, 'items' => []];
        }
        if ($receiptTotal <= 0) {
            throw new CommonException('请选择要收款的设备');
        }

        $saleId = (int)$receivable->source_id;
        $saleItems = ErpSaleItem::where([['site_id', '=', $this->site_id], ['sale_order_id', '=', $saleId]])
            ->order('id asc')
            ->select();
        if ($saleItems->isEmpty()) {
            return ['amount' => $receiptTotal, 'items' => []];
        }

        $now = time();
        $totalAmount = 0.0;
        $totalCost = 0.0;
        $receiptItems = [];
        $itemSettledMap = $this->receivableItemSettledMap((int)$receivable->id);
        foreach ($saleItems as $saleItem) {
            $newPrice = isset($itemMap[(int)$saleItem->id]) ? (float)$itemMap[(int)$saleItem->id]['sale_price'] : (float)$saleItem->sale_price;
            $cost = (float)$saleItem->cost;
            $totalAmount = round($totalAmount + $newPrice, 2);
            $totalCost = round($totalCost + $cost, 2);
            if (isset($itemMap[(int)$saleItem->id])) {
                $receiptAmount = (float)$itemMap[(int)$saleItem->id]['amount'];
                $itemSettled = (float)($itemSettledMap[(int)$saleItem->asset_id] ?? 0);
                if ($receiptAmount > 0 && round($itemSettled + $receiptAmount, 2) > $newPrice + 0.0001) {
                    throw new CommonException('设备本次收款不能大于该设备剩余应收');
                }
                if ($receiptAmount > 0) {
                    $receiptItems[] = [
                        'sale_item_id' => (int)$saleItem->id,
                        'asset_id' => (int)$saleItem->asset_id,
                        'amount' => $receiptAmount,
                    ];
                }
            }
            if (isset($itemMap[(int)$saleItem->id]) && abs($newPrice - (float)$saleItem->sale_price) > 0.0001) {
                $saleItem->save([
                    'sale_price' => $newPrice,
                    'profit' => round($newPrice - $cost, 2),
                    'update_at' => $now,
                ]);
                ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$saleItem->asset_id]])->update([
                    'sale_price' => $newPrice,
                    'profit' => round($newPrice - $cost, 2),
                    'update_at' => $now,
                ]);
            }
        }

        if ($totalAmount < (float)$receivable->settled_amount - 0.0001) {
            throw new CommonException('调整后的销售总额不能小于已结算金额');
        }

        ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $saleId]])->update([
            'total_amount' => $totalAmount,
            'profit' => round($totalAmount - $totalCost, 2),
            'receivable_amount' => max(0, round($totalAmount - (float)$receivable->settled_amount, 2)),
            'finance_status' => ErpDict::financeStatus($totalAmount, (float)$receivable->settled_amount),
            'update_at' => $now,
        ]);
        $receivable->save([
            'amount' => $totalAmount,
            'status' => ErpDict::financeStatus($totalAmount, (float)$receivable->settled_amount),
            'update_at' => $now,
        ]);
        return ['amount' => $receiptTotal, 'items' => $receiptItems];
    }

    private function applyReceiptPurchaseReturnItems(ErpReceivable $receivable, array $items, float $amount): array
    {
        if (empty($items) || (int)$receivable->source_id <= 0) {
            return ['amount' => $amount, 'items' => []];
        }

        $applyMap = [];
        $receiptTotal = 0.0;
        foreach ($items as $item) {
            $assetId = (int)($item['asset_id'] ?? $item['id'] ?? 0);
            $receiptAmount = round((float)($item['amount'] ?? 0), 2);
            if ($assetId <= 0 || $receiptAmount <= 0) {
                continue;
            }
            $applyMap[$assetId] = round(($applyMap[$assetId] ?? 0) + $receiptAmount, 2);
            $receiptTotal = round($receiptTotal + $receiptAmount, 2);
        }
        if (empty($applyMap)) {
            return ['amount' => $amount, 'items' => []];
        }

        $returnItems = ErpPurchaseReturnItem::where([
            ['site_id', '=', $this->site_id],
            ['return_id', '=', (int)$receivable->source_id],
        ])->whereIn('asset_id', array_keys($applyMap))->select();
        if ($returnItems->count() !== count($applyMap)) {
            throw new CommonException('部分退货设备不存在，请刷新后重试');
        }

        $itemSettledMap = $this->receivableItemSettledMap((int)$receivable->id);
        $receiptItems = [];
        foreach ($returnItems as $returnItem) {
            $assetId = (int)$returnItem->asset_id;
            $receiptAmount = (float)$applyMap[$assetId];
            $settled = (float)($itemSettledMap[$assetId] ?? 0);
            $refundAmount = $this->purchaseReturnItemRefundAmount($returnItem->toArray());
            if (round($settled + $receiptAmount, 2) > $refundAmount + 0.0001) {
                throw new CommonException('设备本次收款不能大于该设备剩余应收');
            }
            $receiptItems[] = [
                'asset_id' => $assetId,
                'amount' => $receiptAmount,
            ];
        }

        return ['amount' => $receiptTotal, 'items' => $receiptItems];
    }

    private function writeReceiptAccountLedgers(ErpReceivable $receivable, float $amount, array $items, string $remark): void
    {
        $service = new ErpLedgerService();
        if (!empty($items)) {
            foreach ($items as $item) {
                $itemAmount = round((float)($item['amount'] ?? 0), 2);
                if ($itemAmount <= 0) {
                    continue;
                }
                $service->account([
                    'biz_type' => 'receipt',
                    'direction' => 'decrease',
                    'amount' => $itemAmount,
                    'party_id' => (int)$receivable->party_id,
                    'party_name' => (string)$receivable->party_name,
                    'asset_id' => (int)($item['asset_id'] ?? 0),
                    'source_type' => 'receivable',
                    'source_id' => (int)$receivable->id,
                    'source_no' => (string)$receivable->receivable_no,
                    'remark' => $remark,
                ]);
            }
            return;
        }

        $service->account([
            'biz_type' => 'receipt',
            'direction' => 'decrease',
            'amount' => $amount,
            'party_id' => (int)$receivable->party_id,
            'party_name' => (string)$receivable->party_name,
            'source_type' => 'receivable',
            'source_id' => (int)$receivable->id,
            'source_no' => (string)$receivable->receivable_no,
            'remark' => $remark,
        ]);
    }

    public function payablePartyItems(int $partyId, array $where): array
    {
        if ($partyId <= 0) {
            throw new CommonException('请选择供应商');
        }
        // 未指定业务类型时用于折账：必须返回该主体全部应付事实，不能只返回采购本金。
        if (trim((string)($where['source_type'] ?? '')) === '') {
            return $this->allPayableItems($partyId, $where);
        }
        if ((string)($where['source_type'] ?? '') === 'sale_return') {
            return $this->saleReturnPayableItems($partyId, $where);
        }
        if ((string)($where['source_type'] ?? '') === 'refurbish') {
            return $this->refurbishPayableItems($partyId, $where);
        }
        if (!empty($where['source_type']) && (string)$where['source_type'] !== 'purchase') {
            return $this->externalPayableItems($partyId, $where);
        }
        $orderTable = (new ErpPurchaseOrder())->getTable();
        $payableTable = (new ErpPayable())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($orderTable . ' o', 'o.id = a.purchase_order_id AND o.site_id = a.site_id')
            ->leftJoin($payableTable . ' p', "p.source_type = 'purchase_asset' AND p.source_id = a.id AND p.site_id = a.site_id")
            ->leftJoin($payableTable . ' po', "po.source_type = 'purchase' AND po.source_id = a.purchase_order_id AND po.site_id = a.site_id")
            ->where([['a.site_id', '=', $this->site_id], ['a.party_id', '=', $partyId]]);
        if (!empty($where['purchase_order_id'])) {
            $query->where('a.purchase_order_id', '=', (int)$where['purchase_order_id']);
        }
        if (!empty($where['payable_id'])) {
            $query->whereRaw('IFNULL(p.id, po.id) = ' . (int)$where['payable_id']);
        }
        $this->applyFinanceFilters($query, $where, 'a', 'o', 'p');
        $page = $query->field([
            'a.id',
            'a.asset_no',
            'a.imei',
            'a.sn',
            'a.model',
            'a.spec',
            'a.spec_json',
            'a.total_cost',
            'a.status',
            'a.warehouse_name',
            'a.location_name',
            'o.purchase_no',
            'o.purchase_channel',
            'o.purchase_at',
            'o.total_cost as order_total_cost',
            'IFNULL(p.id, po.id) as payable_id',
            'COALESCE(p.payable_no, po.payable_no) as payable_no',
            'IFNULL(p.amount, a.total_cost) as payable_amount',
            'IFNULL(p.settled_amount, po.settled_amount) as settled_amount',
            'IFNULL(p.status, po.status) as payable_status',
            'p.id as asset_payable_id',
            'po.id as order_payable_id',
            'COALESCE(p.origin_plugin,po.origin_plugin,o.origin_plugin) as origin_plugin',
            'COALESCE(p.origin_plugin_name,po.origin_plugin_name,o.origin_plugin_name) as origin_plugin_name',
            'COALESCE(p.origin_type,po.origin_type,o.origin_type) as origin_type',
            'COALESCE(p.origin_name,po.origin_name,o.origin_name) as origin_name',
            'COALESCE(p.origin_id,po.origin_id,o.origin_id) as origin_id',
            'COALESCE(p.origin_no,po.origin_no,o.origin_no) as origin_no',
            'COALESCE(p.biz_scene,po.biz_scene) as biz_scene',
            'COALESCE(p.category_key,po.category_key) as category_key',
            'COALESCE(p.category_name,po.category_name) as category_name',
            'COALESCE(p.category_statement_group,po.category_statement_group) as category_statement_group',
            'COALESCE(p.category_source_plugin,po.category_source_plugin) as category_source_plugin',
            'COALESCE(p.category_source_key,po.category_source_key) as category_source_key',
            'COALESCE(p.channel_code,po.channel_code) as channel_code',
            'COALESCE(p.channel_name,po.channel_name,o.purchase_channel) as channel_name',
            'COALESCE(p.business_reason,po.business_reason) as business_reason',
        ])->order('a.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        $fallbackPayeeMethods = $this->payeeMethodsForParty($partyId);
        foreach ($page['data'] as &$row) {
            if (!empty($row['asset_payable_id'])) {
                $row['allocated_paid'] = round((float)$row['settled_amount'], 2);
                $row['allocated_remain'] = max(0, round((float)$row['payable_amount'] - (float)$row['allocated_paid'], 2));
            } else {
                $row['allocated_paid'] = $this->allocatedAmount((float)$row['total_cost'], (float)$row['order_total_cost'], (float)$row['settled_amount']);
                $row['allocated_remain'] = max(0, round((float)$row['total_cost'] - (float)$row['allocated_paid'], 2));
            }
            $row['source_no'] = (string)($row['purchase_no'] ?? '');
            $row['source_type'] = 'purchase';
            $row['payee_methods'] = $this->payeeMethodsFromSpec($row['spec_json'] ?? '');
            if (empty($row['payee_methods'])) $row['payee_methods'] = $fallbackPayeeMethods;
            unset($row['spec_json']);
            if (empty($row['channel_name'])) $row['channel_name'] = (string)($row['purchase_channel'] ?? '');
            $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($row, 'payable');
        }
        unset($row);
        $payableIds = array_values(array_unique(array_filter(array_map(static fn($row) => (int)($row['payable_id'] ?? 0), $page['data']))));
        $settlementMap = $this->payableSettlementDetails($payableIds);
        foreach ($page['data'] as &$row) {
            $row['settlements'] = $settlementMap[(int)($row['payable_id'] ?? 0)] ?? [];
        }
        unset($row);
        return $page;
    }

    public function payableInfo(int $id): array
    {
        $payable = $this->findPayable($id)->toArray();
        $sourceType = (string)($payable['source_type'] ?? '');
        $purchaseOrderId = 0;
        if ($sourceType === 'purchase_asset') {
            $assetId = (int)($payable['asset_id'] ?? 0);
            if ($assetId <= 0) $assetId = (int)($payable['source_id'] ?? 0);
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])
                ->field('id,purchase_order_id')->findOrEmpty();
            if (!$asset->isEmpty()) $purchaseOrderId = (int)$asset->purchase_order_id;
            $sourceType = 'purchase';
        } elseif ($sourceType === 'purchase') {
            $purchaseOrderId = (int)($payable['source_id'] ?? 0);
        }
        $row = [
            'id' => (int)$payable['id'],
            'payable_id' => (int)$payable['id'],
            'payable_no' => (string)$payable['payable_no'],
            'party_id' => (int)$payable['party_id'],
            'party_name' => (string)$payable['party_name'],
            'source_type' => $sourceType,
            'source_id' => (int)($payable['source_id'] ?? 0),
            'source_no' => (string)($payable['source_no'] ?? ''),
            'purchase_order_id' => $purchaseOrderId,
            'amount' => (float)$payable['amount'],
            'settled_amount' => (float)$payable['settled_amount'],
            'status' => (string)$payable['status'],
        ];
        $partyRows = [$row];
        ErpPartyMemberNames::append($this->site_id, $partyRows);
        return $partyRows[0];
    }

    /**
     * 折账候选统一事实列表：采购、整备、销售退货及插件支出全部按 payable_id 返回。
     * 不再经采购设备反推应付，避免整备费用错绑到采购应付。
     */
    private function allPayableItems(int $partyId, array $where): array
    {
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpPayable::alias('p')
            ->leftJoin($assetTable . ' a', "a.id = IF(p.asset_id > 0, p.asset_id, IF(p.source_type = 'purchase_asset', p.source_id, 0)) AND a.site_id = p.site_id")
            ->where([
                ['p.site_id', '=', $this->site_id],
                ['p.party_id', '=', $partyId],
            ]);
        if (!empty($where['status'])) {
            $query->where('p.status', '=', (string)$where['status']);
        } else {
            $query->whereIn('p.status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL]);
        }
        if (!empty($where['payable_id'])) {
            $query->where('p.id', '=', (int)$where['payable_id']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('p.payable_no|p.source_no|p.origin_no|p.business_reason|p.remark|a.asset_no|a.imei|a.sn|a.model|a.spec', '%' . $kw . '%');
        }
        $page = $query->field([
            'a.id', 'a.asset_no', 'a.imei', 'a.sn', 'a.model', 'a.spec', 'a.spec_json', 'a.status',
            'a.warehouse_name', 'a.location_name', 'a.total_cost as current_total_cost',
            'p.id as payable_id', 'p.payable_no', 'p.amount as payable_amount', 'p.amount as total_cost',
            'p.settled_amount', 'p.status as payable_status', 'p.id as asset_payable_id', '0 as order_payable_id',
            'p.source_type', 'p.source_id', 'p.source_no', 'p.source_no as purchase_no', 'p.asset_id',
            'p.remark', 'p.occurred_at as purchase_at', 'p.origin_plugin', 'p.origin_plugin_name',
            'p.origin_type', 'p.origin_name', 'p.origin_id', 'p.origin_no', 'p.biz_scene',
            'p.category_key', 'p.category_name', 'p.category_statement_group', 'p.category_source_plugin',
            'p.category_source_key', 'p.channel_code', 'p.channel_name', 'p.business_reason',
        ])->order('p.occurred_at asc,p.id asc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 200),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();

        $fallbackPayeeMethods = $this->payeeMethodsForParty($partyId);
        foreach ($page['data'] as &$row) {
            $row['allocated_paid'] = round((float)$row['settled_amount'], 2);
            $row['allocated_remain'] = max(0, round((float)$row['payable_amount'] - (float)$row['allocated_paid'], 2));
            $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($row, 'payable');
            $row['payee_methods'] = $this->payeeMethodsFromSpec($row['spec_json'] ?? '');
            if (empty($row['payee_methods'])) $row['payee_methods'] = $fallbackPayeeMethods;
            unset($row['spec_json']);
        }
        unset($row);
        $this->appendFinanceDevices($page['data'], ErpDict::TARGET_PAYABLE, 'payable_id');
        $payableIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)$row['payable_id'], $page['data']))));
        $settlementMap = $this->payableSettlementDetails($payableIds);
        foreach ($page['data'] as &$row) {
            $row['settlements'] = $settlementMap[(int)$row['payable_id']] ?? [];
        }
        unset($row);
        return $page;
    }

    private function payeeMethodsFromSpec($specJson): array
    {
        if (is_array($specJson)) $spec = $specJson;
        else {
            $spec = json_decode((string)$specJson, true);
            if (!is_array($spec)) return [];
        }
        $methods = array_values(array_filter((array)($spec['payee_methods'] ?? []), 'is_array'));
        return array_values(array_filter(array_map(static fn(array $item): array => [
            'pay_type' => trim((string)($item['pay_type'] ?? '')),
            'account' => trim((string)($item['account'] ?? '')),
            'qrcode_image' => trim((string)($item['qrcode_image'] ?? '')),
            'is_default' => (int)($item['is_default'] ?? 0),
        ], $methods), static fn(array $item): bool => $item['pay_type'] !== '' || $item['account'] !== '' || $item['qrcode_image'] !== ''));
    }

    private function payeeMethodsForParty(int $partyId): array
    {
        $relation = ErpPartyMember::where([
            ['site_id', '=', $this->site_id], ['party_id', '=', $partyId], ['status', '=', 1],
        ])->order('id asc')->findOrEmpty();
        if ($relation->isEmpty()) return [];
        $memberId = (int)$relation->member_id;
        try {
            $responses = array_values(array_filter((array)event('GetRecyclePaymentMethods', [
                'site_id' => $this->site_id,
                'member_ids' => [$memberId],
            ]), 'is_array'));
        } catch (\Throwable $e) {
            return [];
        }
        foreach ($responses as $response) {
            if (!empty($response[$memberId]) && is_array($response[$memberId])) {
                return array_values($response[$memberId]);
            }
        }
        return [];
    }

    /** 销售退货退款应付：一条应付只对应一台设备。 */
    private function saleReturnPayableItems(int $partyId, array $where): array
    {
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpPayable::alias('p')
            ->leftJoin($assetTable . ' a', 'a.id = p.asset_id AND a.site_id = p.site_id')
            ->where([
                ['p.site_id', '=', $this->site_id],
                ['p.party_id', '=', $partyId],
                ['p.source_type', '=', 'sale_return'],
            ]);
        if (!empty($where['purchase_order_id'])) {
            $query->where('p.source_id', '=', (int)$where['purchase_order_id']);
        }
        if (!empty($where['status'])) {
            $query->where('p.status', '=', (string)$where['status']);
        } else {
            $query->where('p.status', '<>', ErpDict::STATUS_VOID);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('p.payable_no|p.source_no|p.remark|a.asset_no|a.imei|a.sn|a.model|a.spec', '%' . $kw . '%');
        }
        $page = $query->field([
            'a.id',
            'a.asset_no',
            'a.imei',
            'a.sn',
            'a.model',
            'a.spec',
            'a.status',
            'a.warehouse_name',
            'a.location_name',
            'p.source_no as purchase_no',
            'p.id as payable_id',
            'p.amount as payable_amount',
            'p.amount as total_cost',
            'p.settled_amount',
            'p.status as payable_status',
            'p.id as asset_payable_id',
            '0 as order_payable_id',
            'p.remark',
            'p.occurred_at as purchase_at',
            'p.source_type', 'p.source_no', 'p.origin_plugin', 'p.origin_plugin_name', 'p.origin_type', 'p.origin_name',
            'p.origin_id', 'p.origin_no', 'p.biz_scene', 'p.category_key', 'p.category_name',
            'p.category_statement_group', 'p.category_source_plugin', 'p.category_source_key',
            'p.channel_code', 'p.channel_name', 'p.business_reason',
        ])->order('p.id asc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $row['allocated_paid'] = round((float)$row['settled_amount'], 2);
            $row['allocated_remain'] = max(0, round((float)$row['payable_amount'] - (float)$row['allocated_paid'], 2));
            $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($row, 'payable');
        }
        unset($row);
        $payableIds = array_values(array_filter(array_map(static fn($row) => (int)($row['payable_id'] ?? 0), $page['data'])));
        $settlementMap = $this->payableSettlementDetails($payableIds);
        foreach ($page['data'] as &$row) {
            $row['settlements'] = $settlementMap[(int)$row['payable_id']] ?? [];
        }
        unset($row);
        return $page;
    }

    /** 整备费用应付：应付记录本身已经按设备保存 asset_id。 */
    private function refurbishPayableItems(int $partyId, array $where): array
    {
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpPayable::alias('p')
            ->leftJoin($assetTable . ' a', 'a.id = p.asset_id AND a.site_id = p.site_id')
            ->where([
                ['p.site_id', '=', $this->site_id],
                ['p.party_id', '=', $partyId],
                ['p.source_type', '=', 'refurbish'],
            ]);
        if (!empty($where['purchase_order_id'])) {
            $query->where('p.source_id', '=', (int)$where['purchase_order_id']);
        }
        if (!empty($where['status'])) {
            $query->where('p.status', '=', (string)$where['status']);
        } else {
            $query->where('p.status', '<>', ErpDict::STATUS_VOID);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('p.payable_no|p.source_no|p.remark|a.asset_no|a.imei|a.sn|a.model|a.spec', '%' . $kw . '%');
        }
        $page = $query->field([
            'a.id', 'a.asset_no', 'a.imei', 'a.sn', 'a.model', 'a.spec', 'a.status',
            'a.warehouse_name', 'a.location_name', 'a.total_cost as current_total_cost',
            'p.source_no as purchase_no', 'p.id as payable_id', 'p.amount as payable_amount',
            'p.amount as total_cost', 'p.settled_amount', 'p.status as payable_status',
            'p.id as asset_payable_id', '0 as order_payable_id', 'p.remark', 'p.occurred_at as purchase_at',
            'p.source_type', 'p.source_no', 'p.origin_plugin', 'p.origin_plugin_name', 'p.origin_type', 'p.origin_name',
            'p.origin_id', 'p.origin_no', 'p.biz_scene', 'p.category_key', 'p.category_name',
            'p.category_statement_group', 'p.category_source_plugin', 'p.category_source_key',
            'p.channel_code', 'p.channel_name', 'p.business_reason',
        ])->order('p.id asc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $row['allocated_paid'] = round((float)$row['settled_amount'], 2);
            $row['allocated_remain'] = max(0, round((float)$row['payable_amount'] - (float)$row['allocated_paid'], 2));
            $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($row, 'payable');
        }
        unset($row);
        $payableIds = array_values(array_filter(array_map(static fn($row) => (int)($row['payable_id'] ?? 0), $page['data'])));
        $settlementMap = $this->payableSettlementDetails($payableIds);
        foreach ($page['data'] as &$row) {
            $row['settlements'] = $settlementMap[(int)$row['payable_id']] ?? [];
        }
        unset($row);
        return $page;
    }

    /** 维修、检测及未来插件费用：直接以应付快照为事实，不伪装成采购批次。 */
    private function externalPayableItems(int $partyId, array $where): array
    {
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpPayable::alias('p')
            ->leftJoin($assetTable . ' a', 'a.id = p.asset_id AND a.site_id = p.site_id')
            ->where([['p.site_id', '=', $this->site_id], ['p.party_id', '=', $partyId]])
            ->where(function ($query) use ($where) {
                $sourceType = (string)$where['source_type'];
                $query->where('p.biz_scene', '=', $sourceType)->whereOr('p.source_type', '=', $sourceType);
            });
        if (!empty($where['purchase_order_id'])) $query->where('p.source_id', '=', (int)$where['purchase_order_id']);
        if (!empty($where['status'])) $query->where('p.status', '=', (string)$where['status']);
        else $query->where('p.status', '<>', ErpDict::STATUS_VOID);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('p.payable_no|p.source_no|p.origin_no|p.business_reason|p.remark|a.asset_no|a.imei|a.sn|a.model|a.spec', '%' . $kw . '%');
        }
        $page = $query->field([
            'a.id', 'a.asset_no', 'a.imei', 'a.sn', 'a.model', 'a.spec', 'a.status',
            'a.warehouse_name', 'a.location_name', 'a.total_cost as current_total_cost',
            'p.source_no as purchase_no', 'p.id as payable_id', 'p.amount as payable_amount',
            'p.amount as total_cost', 'p.settled_amount', 'p.status as payable_status',
            'p.id as asset_payable_id', '0 as order_payable_id', 'p.remark', 'p.occurred_at as purchase_at',
            'p.source_type', 'p.source_no', 'p.origin_plugin', 'p.origin_plugin_name', 'p.origin_type', 'p.origin_name',
            'p.origin_id', 'p.origin_no', 'p.biz_scene', 'p.category_key', 'p.category_name',
            'p.category_statement_group', 'p.category_source_plugin', 'p.category_source_key',
            'p.channel_code', 'p.channel_name', 'p.business_reason',
        ])->order('p.id asc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $row['allocated_paid'] = round((float)$row['settled_amount'], 2);
            $row['allocated_remain'] = max(0, round((float)$row['payable_amount'] - (float)$row['allocated_paid'], 2));
            $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($row, 'payable');
        }
        unset($row);
        $payableIds = array_values(array_filter(array_map(static fn($row) => (int)($row['payable_id'] ?? 0), $page['data'])));
        $settlementMap = $this->payableSettlementDetails($payableIds);
        foreach ($page['data'] as &$row) $row['settlements'] = $settlementMap[(int)$row['payable_id']] ?? [];
        unset($row);
        return $page;
    }

    public function confirmOffset(array $payableIds, array $receivableIds, float $amount, string $remark = '', array $data = []): int
    {
        if ($amount <= 0) {
            throw new CommonException('折账金额必须大于0');
        }
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $existingOffsetId = $this->existingOffsetId($requestId);
        if ($existingOffsetId > 0) {
            $this->assertOffsetReplayMatches($existingOffsetId, $payableIds, $receivableIds, $amount, !empty($data['settle_diff']));
            return $existingOffsetId;
        }
        $data['request_id'] = $requestId !== '' ? $requestId : null;
        $offsetId = 0;
        try {
            Db::transaction(function () use ($payableIds, $receivableIds, $amount, $remark, $data, &$offsetId) {
                $payables = $this->openPayables($payableIds);
                $receivables = $this->openReceivables($receivableIds);
                if (empty($payables) || empty($receivables)) {
                    throw new CommonException('折账必须同时选择应付和应收');
                }
                $partyId = (int)$payables[0]['party_id'];
                foreach (array_merge($payables, $receivables) as $row) {
                    if ((int)$row['party_id'] !== $partyId) {
                        throw new CommonException('折账只能处理同一个往来单位');
                    }
                }
                $payableRemain = round(array_sum(array_map(fn($r) => round((float)$r['amount'] - (float)$r['settled_amount'], 2), $payables)), 2);
                $receivableRemain = round(array_sum(array_map(fn($r) => round((float)$r['amount'] - (float)$r['settled_amount'], 2), $receivables)), 2);
                $offsetLimit = min($payableRemain, $receivableRemain);
                if ($amount > $offsetLimit + 0.0001) {
                    if (!empty($data['settle_diff']) && $amount <= max($payableRemain, $receivableRemain) + 0.0001) {
                        $amount = $offsetLimit;
                    } else {
                        throw new CommonException('折账金额不能大于可折账金额');
                    }
                }
                $partyName = (string)$payables[0]['party_name'];
                $settlement = ErpSettlement::create([
                    'site_id' => $this->site_id,
                    'request_id' => $data['request_id'],
                    'settlement_no' => ErpLedgerService::makeNo('ST'),
                    'party_id' => $partyId,
                    'party_name' => $partyName,
                    'settlement_type' => ErpDict::SETTLEMENT_OFFSET,
                    'amount' => $amount,
                    'cash_direction' => 'none',
                    'status' => 'confirmed',
                    'operator_uid' => (int)$this->uid,
                    'operator_name' => (string)$this->username,
                    'confirmed_at' => time(),
                    'remark' => $remark,
                    'create_at' => time(),
                ]);
                $offset = ErpOffset::create([
                    'site_id' => $this->site_id,
                    'offset_no' => ErpLedgerService::makeNo('OF'),
                    'settlement_id' => (int)$settlement->id,
                    'party_id' => $partyId,
                    'party_name' => $partyName,
                    'amount' => $amount,
                    'operator_uid' => (int)$this->uid,
                    'operator_name' => (string)$this->username,
                    'confirmed_at' => time(),
                    'remark' => $remark,
                    'create_at' => time(),
                ]);
                $offsetId = (int)$offset->id;
                $this->consumeOffsetTargets($payables, ErpDict::TARGET_PAYABLE, $amount, (int)$settlement->id, $offsetId);
                $this->consumeOffsetTargets($receivables, ErpDict::TARGET_RECEIVABLE, $amount, (int)$settlement->id, $offsetId);
                if (!empty($data['settle_diff'])) {
                    $this->settleOffsetDifference($payableIds, $receivableIds, $payableRemain, $receivableRemain, $amount, $data);
                }
                (new ErpLedgerService())->account([
                    'biz_type' => 'offset',
                    'direction' => 'decrease',
                    'amount' => $amount,
                    'party_id' => $partyId,
                    'party_name' => $partyName,
                    'source_type' => 'offset',
                    'source_id' => $offsetId,
                    'source_no' => (string)$offset->offset_no,
                    'remark' => $remark ?: '应收应付折账',
                ]);
                $this->queueSettlementCompletedEvent((int)$settlement->id);
            });
        } catch (\Throwable $e) {
            $existingOffsetId = $this->existingOffsetId($requestId);
            if ($existingOffsetId > 0) {
                $this->assertOffsetReplayMatches($existingOffsetId, $payableIds, $receivableIds, $amount, !empty($data['settle_diff']));
                return $existingOffsetId;
            }
            throw $e;
        }
        $this->flushSettlementDomainEvents();
        $offset = ErpOffset::where([['site_id', '=', $this->site_id], ['id', '=', $offsetId]])->findOrEmpty();
        if (!$offset->isEmpty()) {
            (new ErpPrintService())->triggerSafely('offset_confirmed', 'offset', $offsetId, [
                'settlement_id' => (int)$offset->settlement_id,
                'amount' => number_format((float)$offset->amount, 2, '.', ''),
            ]);
        }
        return $offsetId;
    }

    private function receivableBatchPage(array $where): array
    {
        $partyTable = (new ErpParty())->getTable();
        $orderTable = (new ErpSaleOrder())->getTable();
        $returnTable = (new ErpPurchaseReturnOrder())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpReceivable::alias('r')
            ->leftJoin($partyTable . ' party', 'party.id = r.party_id AND party.site_id = r.site_id')
            ->leftJoin($orderTable . ' o', "r.source_type = 'sale' AND o.id = r.source_id AND o.site_id = r.site_id")
            ->leftJoin($returnTable . ' pr', "r.source_type = 'purchase_return' AND pr.id = r.source_id AND pr.site_id = r.site_id")
            ->where([['r.site_id', '=', $this->site_id]]);

        $invalidSourceSql = "(r.source_type = 'sale' AND COALESCE(o.status, '') IN ('returned','void'))"
            . " OR (r.source_type = 'purchase_return' AND COALESCE(pr.status, '') IN ('cancelled','void'))";
        if ((string)($where['status'] ?? '') === ErpDict::STATUS_VOID) {
            $query->where(function ($voidQuery) use ($invalidSourceSql) {
                $voidQuery->where('r.status', '=', ErpDict::STATUS_VOID)->whereOrRaw($invalidSourceSql);
            });
        } elseif (!empty($where['status'])) {
            $query->where('r.status', '=', (string)$where['status'])->whereRaw('NOT (' . $invalidSourceSql . ')');
            if (in_array((string)$where['status'], [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)) {
                $query->whereRaw('r.amount > r.settled_amount');
            }
        } elseif (!empty($where['only_effective'])) {
            $query->where('r.status', '<>', ErpDict::STATUS_VOID)->whereRaw('NOT (' . $invalidSourceSql . ')');
        }
        if (!empty($where['party_id'])) {
            $query->where('r.party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['source_type'])) {
            $query->where('r.source_type', '=', (string)$where['source_type']);
        }
        if (!empty($where['finance_type_key'])) {
            $query->where('r.category_key', '=', (string)$where['finance_type_key']);
        }
        if (!empty($where['business_source_key'])) {
            $query->where('r.origin_type', '=', (string)$where['business_source_key']);
        }
        if (!empty($where['source_plugin'])) {
            $query->where('r.origin_plugin', '=', (string)$where['source_plugin']);
        }
        if (!empty($where['channel_code'])) {
            $channelCode = (string)$where['channel_code'];
            $query->where(function ($q) use ($channelCode) {
                $q->where('r.channel_code', '=', $channelCode)->whereOr('o.sale_channel_key', '=', $channelCode);
            });
        }
        if (!empty($where['biz_scene'])) {
            $query->where('r.biz_scene', '=', (string)$where['biz_scene']);
        }
        if (!empty($where['start_at'])) {
            $query->where('r.occurred_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('r.occurred_at', '<=', (int)$where['end_at']);
        }
        if (!empty($where['party_name'])) {
            $query->whereLike('r.party_name', '%' . trim((string)$where['party_name']) . '%');
        }
        if (!empty($where['source_no'])) {
            $query->whereLike('r.source_no|o.sale_no', '%' . trim((string)$where['source_no']) . '%');
        }
        if (!empty($where['m_no'])) {
            $query->whereLike('party.m_no', '%' . trim((string)$where['m_no']) . '%');
        }
        if (!empty($where['contact_mobile'])) {
            $query->whereLike('party.contact_mobile', '%' . trim((string)$where['contact_mobile']) . '%');
        }
        if (!empty($where['salesman_name'])) {
            $operatorName = trim((string)$where['salesman_name']);
            $query->whereLike('o.salesman_name|r.business_operator_name', '%' . $operatorName . '%');
        }
        if (!empty($where['salesman_uid'])) {
            $query->where('o.salesman_uid', '=', (int)$where['salesman_uid']);
        }
        if (!empty($where['operator_uid'])) {
            $operatorUid = (int)$where['operator_uid'];
            $query->where(function ($q) use ($operatorUid) {
                $q->where(function ($sale) use ($operatorUid) {
                    $sale->where('r.source_type', '=', 'sale')->where('o.salesman_uid', '=', $operatorUid);
                })->whereOr(function ($purchaseReturn) use ($operatorUid) {
                    $purchaseReturn->where('r.source_type', '=', 'purchase_return')->where('pr.operator_id', '=', $operatorUid);
                })->whereOr(function ($external) use ($operatorUid) {
                    $external->whereNotIn('r.source_type', ['sale', 'purchase_return'])
                        ->where('r.business_operator_uid', '=', $operatorUid);
                });
            });
        }
        if (!empty($where['imei'])) {
            $imei = trim((string)$where['imei']);
            $matchedSaleIds = ErpSaleItem::where([['site_id', '=', $this->site_id]])
                ->whereLike('imei', '%' . $imei . '%')->column('sale_order_id');
            $matchedReturnIds = ErpPurchaseReturnItem::where([['site_id', '=', $this->site_id]])
                ->whereLike('imei|asset_no|model', '%' . $imei . '%')->column('return_id');
            if (empty($matchedSaleIds) && empty($matchedReturnIds)) {
                $query->where('r.id', '=', 0);
            } else {
                $query->where(function ($q) use ($matchedSaleIds, $matchedReturnIds) {
                    if (!empty($matchedSaleIds)) {
                        $q->where(function ($sale) use ($matchedSaleIds) {
                            $sale->where('r.source_type', '=', 'sale')->whereIn('r.source_id', array_values(array_unique(array_map('intval', $matchedSaleIds))));
                        });
                    }
                    if (!empty($matchedReturnIds)) {
                        $method = empty($matchedSaleIds) ? 'where' : 'whereOr';
                        $q->{$method}(function ($purchaseReturn) use ($matchedReturnIds) {
                            $purchaseReturn->where('r.source_type', '=', 'purchase_return')->whereIn('r.source_id', array_values(array_unique(array_map('intval', $matchedReturnIds))));
                        });
                    }
                });
            }
        }
        if (($where['min_amount'] ?? '') !== '') {
            $query->where('r.amount', '>=', (float)$where['min_amount']);
        }
        if (($where['max_amount'] ?? '') !== '') {
            $query->where('r.amount', '<=', (float)$where['max_amount']);
        }
        if (($where['min_remain'] ?? '') !== '') {
            $query->whereRaw('(r.amount - r.settled_amount) >= ' . round((float)$where['min_remain'], 2));
        }
        if (($where['max_remain'] ?? '') !== '') {
            $query->whereRaw('(r.amount - r.settled_amount) <= ' . round((float)$where['max_remain'], 2));
        }
        if (!empty($where['can_offset'])) {
            $partyIds = ErpPayable::where([['site_id', '=', $this->site_id]])
                ->whereNotIn('status', [ErpDict::STATUS_SETTLED, ErpDict::STATUS_VOID])
                ->group('party_id')
                ->having('SUM(amount - settled_amount) > 0')
                ->column('party_id');
            $query->whereIn('r.party_id', array_map('intval', $partyIds ?: [0]));
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $matchedSaleIds = ErpSaleItem::alias('i')
                ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
                ->where([['i.site_id', '=', $this->site_id]])
                ->whereLike('i.imei|i.model|a.asset_no|a.sn|a.spec', '%' . $kw . '%')
                ->column('i.sale_order_id');
            $query->where(function ($q) use ($kw, $matchedSaleIds) {
                $q->whereLike('r.receivable_no|r.party_name|r.source_no|r.remark|party.contact_name|party.contact_mobile|party.m_no|o.sale_no|o.sale_channel|o.salesman_name', '%' . $kw . '%');
                if (!empty($matchedSaleIds)) {
                    $q->whereOr('r.source_id', 'in', array_values(array_unique(array_map('intval', $matchedSaleIds))));
                }
            });
        }

        $page = $query->field([
            'r.id',
            'r.receivable_no',
            'r.party_id',
            'r.party_name',
            'party.contact_name',
            'party.contact_mobile',
            'party.m_no',
            'r.source_type',
            'r.source_id',
            'r.source_no',
            'r.asset_id',
            'r.task_stage_key',
            'r.task_assignee_uid',
            'r.task_assignee_name',
            'r.task_assigned_at',
            'r.origin_plugin',
            'r.origin_plugin_name',
            'r.origin_type',
            'r.origin_name',
            'r.origin_id',
            'r.origin_no',
            'r.biz_scene',
            'r.category_key',
            'r.category_name',
            'r.category_statement_group',
            'r.category_source_plugin',
            'r.category_source_key',
            'r.channel_code',
            'r.channel_name',
            'r.business_reason',
            'r.settlement_mode',
            'r.settlement_mode_name',
            'r.business_operator_uid',
            'r.business_operator_name',
            'r.amount',
            'r.settled_amount',
            'r.status',
            'r.occurred_at',
            'r.remark',
            'o.sale_no',
            'o.sale_channel',
            'o.sale_channel_key',
            'o.origin_plugin as order_origin_plugin',
            'o.origin_plugin_name as order_origin_plugin_name',
            'o.origin_type as order_origin_type',
            'o.origin_name as order_origin_name',
            'o.origin_id as order_origin_id',
            'o.origin_no as order_origin_no',
            'o.settle_method',
            'o.salesman_uid',
            'o.salesman_name',
            'o.sale_at',
            'o.total_amount as sale_total_amount',
            'o.received_amount',
            'o.receivable_amount',
            'o.finance_status',
            'o.status as sale_status',
            'pr.operator_id as return_operator_uid',
            'pr.operator_name as return_operator_name',
            'pr.status as purchase_return_status',
        ])->order('r.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();

        $saleIds = [];
        $purchaseReturnIds = [];
        foreach ($page['data'] as $row) {
            if ((string)$row['source_type'] === 'sale' && (int)$row['source_id'] > 0) {
                $saleIds[] = (int)$row['source_id'];
            } elseif ((string)$row['source_type'] === 'purchase_return' && (int)$row['source_id'] > 0) {
                $purchaseReturnIds[] = (int)$row['source_id'];
            }
        }
        $saleIds = array_values(array_unique($saleIds));
        $purchaseReturnIds = array_values(array_unique($purchaseReturnIds));
        $itemCountMap = [];
        if (!empty($saleIds)) {
            $counts = ErpSaleItem::where([['site_id', '=', $this->site_id]])
                ->whereIn('sale_order_id', $saleIds)
                ->field('sale_order_id, COUNT(id) as item_count')
                ->group('sale_order_id')
                ->select()
                ->toArray();
            foreach ($counts as $count) {
                $itemCountMap[(int)$count['sale_order_id']] = (int)$count['item_count'];
            }
        }
        $returnMap = [];
        if (!empty($purchaseReturnIds)) {
            $returnRows = ErpPurchaseReturnOrder::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', $purchaseReturnIds)
                ->field('id,return_no,purchase_no,remark')
                ->select()
                ->toArray();
            foreach ($returnRows as $returnRow) {
                $returnMap[(int)$returnRow['id']] = $returnRow;
            }
            $returnCounts = ErpPurchaseReturnItem::where([['site_id', '=', $this->site_id]])
                ->whereIn('return_id', $purchaseReturnIds)
                ->field('return_id, COUNT(id) as item_count')
                ->group('return_id')
                ->select()
                ->toArray();
            foreach ($returnCounts as $count) {
                $itemCountMap['purchase_return_' . (int)$count['return_id']] = (int)$count['item_count'];
            }
        }

        foreach ($page['data'] as &$row) {
            $row['ledger_status'] = (string)($row['status'] ?? '');
            $sourceStatus = (string)$row['source_type'] === 'sale'
                ? (string)($row['sale_status'] ?? '')
                : ((string)$row['source_type'] === 'purchase_return' ? (string)($row['purchase_return_status'] ?? '') : '');
            $row['is_void'] = $row['ledger_status'] === ErpDict::STATUS_VOID
                || ((string)$row['source_type'] === 'sale' && in_array($sourceStatus, [ErpDict::ASSET_RETURNED, ErpDict::STATUS_VOID], true))
                || ((string)$row['source_type'] === 'purchase_return' && in_array($sourceStatus, ['cancelled', ErpDict::STATUS_VOID], true));
            $row['void_reason'] = !$row['is_void'] ? '' : ($row['ledger_status'] === ErpDict::STATUS_VOID ? '应收记录已作废' : '来源交易已撤回');
            $row['batch_no'] = $row['source_no'] ?: ($row['sale_no'] ?? '');
            if ((string)$row['source_type'] === 'purchase_return') {
                $returnRow = $returnMap[(int)$row['source_id']] ?? [];
                $row['batch_no'] = (string)($returnRow['return_no'] ?? $row['source_no'] ?? '');
                $row['purchase_no'] = (string)($returnRow['purchase_no'] ?? '');
                $row['item_count'] = $itemCountMap['purchase_return_' . (int)$row['source_id']] ?? 0;
                $row['opening_settle_method'] = '采购退货退款';
                $row['return_remark'] = (string)($returnRow['remark'] ?? '');
                $row['business_operator_uid'] = (int)($row['return_operator_uid'] ?? 0);
                $row['business_operator_name'] = (string)($row['return_operator_name'] ?? '');
            } elseif ((string)$row['source_type'] === 'sale') {
                $row['item_count'] = $itemCountMap[(int)$row['source_id']] ?? ((int)($row['asset_id'] ?? 0) > 0 ? 1 : 0);
                $row['opening_settle_method'] = (string)($row['settle_method'] ?? '');
                $row['business_operator_uid'] = (int)($row['salesman_uid'] ?? 0);
                $row['business_operator_name'] = (string)($row['salesman_name'] ?? '');
                foreach (['plugin', 'plugin_name', 'type', 'name', 'id', 'no'] as $originField) {
                    $field = 'origin_' . $originField;
                    $orderField = 'order_origin_' . $originField;
                    if (empty($row[$field]) && !empty($row[$orderField])) $row[$field] = $row[$orderField];
                }
                if (empty($row['channel_code'])) $row['channel_code'] = (string)($row['sale_channel_key'] ?? '');
                if (empty($row['channel_name'])) $row['channel_name'] = (string)($row['sale_channel'] ?? '');
            } else {
                // 插件财务事实不是 ERP 销售单，不能再从左连接失败的 sale_order 读取结算方式和销售员。
                // 新数据读取不可变快照；历史数据由插件只读 Hook 回填，业务说明只作最后兜底。
                $row['item_count'] = (int)((int)($row['asset_id'] ?? 0) > 0 ? 1 : 0);
                $row['opening_settle_method'] = (string)($row['settlement_mode_name'] ?? '');
                if ((string)$row['business_operator_name'] === '') {
                    $row['business_operator_name'] = $this->extractBusinessOperatorName((string)($row['business_reason'] ?? ''));
                }
            }
            $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($row, 'receivable');
            $row['source_label'] = (string)$row['source_meta']['finance_type_name'];
            $row['business_reason'] = (string)($row['source_meta']['business_reason'] ?? $row['business_reason'] ?? '');
            $row['remain_amount'] = max(0, round((float)$row['amount'] - (float)$row['settled_amount'], 2));
            $row['finance_status'] = $row['is_void'] ? ErpDict::STATUS_VOID : ErpDict::financeStatus((float)$row['amount'], (float)$row['settled_amount']);
            $row['status'] = $row['finance_status'];
        }
        unset($row);
        $this->enrichFinanceDisplayRows($page['data'], 'receivable');
        $this->appendFinanceDevices($page['data'], ErpDict::TARGET_RECEIVABLE);
        $summaryMap = $this->settlementSummaryForTargets(ErpDict::TARGET_RECEIVABLE, array_column($page['data'], 'id'));
        foreach ($page['data'] as &$row) {
            $summary = $summaryMap[(int)$row['id']] ?? [];
            $row['settle_summary'] = $summary['text'] ?? ((float)$row['amount'] <= 0.0001 ? '已冲销，无剩余应收' : $this->emptySettleSummary((float)$row['settled_amount']));
            $row['settle_summary_items'] = $summary['items'] ?? [];
            $row['settlement_operator_uid'] = (int)($summary['operator_uid'] ?? 0);
            $row['settlement_operator_name'] = (string)($summary['operator_name'] ?? '');
            $row['settlement_operator_names'] = (array)($summary['operator_names'] ?? []);
            $row['settlement_confirmed_at'] = (int)($summary['confirmed_at'] ?? 0);
        }
        unset($row);
        $this->fillOffsetState($page['data'], 'receivable');
        ErpPartyMemberNames::append($this->site_id, $page['data']);
        return $page;
    }

    private function financePage(string $modelClass, array $where): array
    {
        $query = $modelClass::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }
        if (!empty($where['party_id'])) {
            $query->where('party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('party_name|source_no|remark', '%' . $kw . '%');
        }
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    private function payableBatchPage(array $where): array
    {
        $partyTable = (new ErpParty())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $orderTable = (new ErpPurchaseOrder())->getTable();
        $saleReturnTable = (new ErpSaleReturnOrder())->getTable();
        $batchExpr = "CASE WHEN p.source_type = 'purchase_asset' THEN a.purchase_order_id ELSE p.source_id END";
        $sourceGroupExpr = "CASE WHEN p.source_type = 'sale_return' THEN 'sale_return' WHEN p.source_type = 'refurbish' THEN 'refurbish' WHEN p.source_type IN ('purchase','purchase_asset') THEN 'purchase' ELSE COALESCE(NULLIF(p.biz_scene,''),'external') END";
        $query = ErpPayable::alias('p')
            ->leftJoin($partyTable . ' party', 'party.id = p.party_id AND party.site_id = p.site_id')
            ->leftJoin($assetTable . ' a', "a.id = IF(p.asset_id > 0, p.asset_id, IF(p.source_type = 'purchase_asset', p.source_id, 0)) AND a.site_id = p.site_id")
            ->leftJoin($orderTable . ' o', "p.source_type IN ('purchase','purchase_asset') AND o.id = " . $batchExpr . ' AND o.site_id = p.site_id')
            ->leftJoin($saleReturnTable . ' sr', "p.source_type = 'sale_return' AND sr.id = p.source_id AND sr.site_id = p.site_id")
            ->where([['p.site_id', '=', $this->site_id]]);

        if (!empty($where['status'])) {
            $query->where('p.status', '=', (string)$where['status']);
        } else {
            $query->where('p.status', '<>', ErpDict::STATUS_VOID);
        }
        if (!empty($where['party_id'])) {
            $query->where('p.party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['source_type'])) {
            if ((string)$where['source_type'] === 'purchase') {
                $query->whereIn('p.source_type', ['purchase', 'purchase_asset']);
            } else {
                $query->where('p.source_type', '=', (string)$where['source_type']);
            }
        }
        if (!empty($where['finance_type_key'])) {
            $query->where('p.category_key', '=', (string)$where['finance_type_key']);
        }
        if (!empty($where['business_source_key'])) {
            $query->where('p.origin_type', '=', (string)$where['business_source_key']);
        }
        if (!empty($where['source_plugin'])) {
            $query->where('p.origin_plugin', '=', (string)$where['source_plugin']);
        }
        if (!empty($where['channel_code'])) {
            $channelCode = (string)$where['channel_code'];
            $query->where(function ($q) use ($channelCode) {
                $q->where('p.channel_code', '=', $channelCode)->whereOr('o.purchase_channel', '=', $channelCode);
            });
        }
        if (!empty($where['biz_scene'])) {
            $query->where('p.biz_scene', '=', (string)$where['biz_scene']);
        }
        if (!empty($where['start_at'])) {
            $query->where('p.occurred_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('p.occurred_at', '<=', (int)$where['end_at']);
        }
        if (!empty($where['party_name'])) {
            $query->whereLike('p.party_name', '%' . trim((string)$where['party_name']) . '%');
        }
        if (!empty($where['source_no'])) {
            $query->whereLike('p.source_no', '%' . trim((string)$where['source_no']) . '%');
        }
        if (!empty($where['m_no'])) {
            $query->whereLike('party.m_no', '%' . trim((string)$where['m_no']) . '%');
        }
        if (!empty($where['contact_mobile'])) {
            $query->whereLike('party.contact_mobile', '%' . trim((string)$where['contact_mobile']) . '%');
        }
        if (!empty($where['can_offset'])) {
            $partyIds = ErpReceivable::where([['site_id', '=', $this->site_id]])
                ->whereNotIn('status', [ErpDict::STATUS_SETTLED, ErpDict::STATUS_VOID])
                ->group('party_id')
                ->having('SUM(amount - settled_amount) > 0')
                ->column('party_id');
            $query->whereIn('p.party_id', array_map('intval', $partyIds ?: [0]));
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('p.payable_no|p.party_name|p.source_no|p.remark|party.contact_name|party.contact_mobile|party.m_no|a.asset_no|a.imei|a.sn|a.model|a.spec|o.purchase_no|o.purchase_channel|o.purchaser_name|sr.return_no|sr.sale_no|sr.operator_name', '%' . $kw . '%');
        }

        $page = $query->field([
            'p.party_id',
            $sourceGroupExpr . ' as source_type',
            $batchExpr . ' as purchase_order_id',
            'MAX(p.party_name) as party_name',
            'MAX(p.source_no) as payable_source_no',
            'MAX(p.remark) as payable_remark',
            'MAX(p.origin_plugin) as origin_plugin',
            'MAX(p.origin_plugin_name) as origin_plugin_name',
            'MAX(p.origin_type) as origin_type',
            'MAX(p.origin_name) as origin_name',
            'MAX(p.origin_id) as origin_id',
            'MAX(p.origin_no) as origin_no',
            'MAX(p.biz_scene) as biz_scene',
            'MAX(p.category_key) as category_key',
            'MAX(p.category_name) as category_name',
            'MAX(p.category_statement_group) as category_statement_group',
            'MAX(p.category_source_plugin) as category_source_plugin',
            'MAX(p.category_source_key) as category_source_key',
            'MAX(p.channel_code) as channel_code',
            'MAX(p.channel_name) as channel_name',
            'MAX(p.business_reason) as business_reason',
            'MAX(p.task_stage_key) as task_stage_key',
            'MAX(p.task_assignee_uid) as task_assignee_uid',
            'MAX(p.task_assignee_name) as task_assignee_name',
            'MAX(p.task_assigned_at) as task_assigned_at',
            'MAX(party.contact_name) as contact_name',
            'MAX(party.contact_mobile) as contact_mobile',
            'MAX(party.m_no) as m_no',
            'MAX(o.purchase_no) as purchase_no',
            'MAX(o.purchase_channel) as purchase_channel',
            'MAX(o.origin_plugin) as order_origin_plugin',
            'MAX(o.origin_plugin_name) as order_origin_plugin_name',
            'MAX(o.origin_type) as order_origin_type',
            'MAX(o.origin_name) as order_origin_name',
            'MAX(o.origin_id) as order_origin_id',
            'MAX(o.origin_no) as order_origin_no',
            'MAX(o.settle_method) as settle_method',
            'MAX(o.purchaser_name) as purchaser_name',
            'MAX(o.purchase_at) as purchase_at',
            'MAX(o.warehouse_name) as warehouse_name',
            'MAX(o.location_name) as location_name',
            'MAX(sr.return_no) as sale_return_no',
            'MAX(sr.business_type) as sale_return_business_type',
            'MAX(sr.sale_no) as sale_no',
            'MAX(sr.operator_name) as return_operator_name',
            'COUNT(p.id) as payable_count',
            "GROUP_CONCAT(CASE WHEN p.status IN ('pending','partial') AND p.amount > p.settled_amount THEN p.id ELSE NULL END ORDER BY p.id ASC) as open_payable_ids",
            'SUM(p.amount) as amount',
            'SUM(p.settled_amount) as settled_amount',
            'MAX(p.occurred_at) as latest_at',
            'MIN(p.occurred_at) as first_at',
        ])->group('p.party_id,' . $sourceGroupExpr . ',' . $batchExpr)->order('latest_at desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();

        // 整备费用没有采购单可提供采购员；经办人应来自创建该笔整备事实的设备账本。
        // 这里按整备来源单号一次性回填，避免列表逐行查询，同时让 PC/移动端看到真实操作人。
        $refurbishSourceNos = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => (string)($row['source_type'] ?? '') === 'refurbish'
                ? trim((string)($row['payable_source_no'] ?? ''))
                : '',
            (array)($page['data'] ?? [])
        ))));
        $refurbishOperatorMap = [];
        if (!empty($refurbishSourceNos)) {
            $refurbishOperatorMap = ErpAssetLedger::where([
                ['site_id', '=', $this->site_id],
                ['action', '=', 'refurbish'],
            ])->whereIn('source_no', $refurbishSourceNos)->column('operator_name', 'source_no');
        }

        foreach ($page['data'] as &$row) {
            $row['payable_ids'] = array_values(array_unique(array_filter(array_map(
                'intval',
                explode(',', (string)($row['open_payable_ids'] ?? ''))
            ))));
            unset($row['open_payable_ids']);
            if ((string)$row['source_type'] === 'sale_return') {
                $row['batch_no'] = (string)($row['sale_return_no'] ?: ('销售退货#' . (int)$row['purchase_order_id']));
                $isCompensation = (string)($row['sale_return_business_type'] ?? '') === 'after_sale_compensation';
                $row['purchase_channel'] = $isCompensation ? '售后补差付款' : '客户退货退款';
                $row['purchaser_name'] = (string)($row['return_operator_name'] ?? '');
                $row['purchase_at'] = (int)($row['latest_at'] ?? 0);
                $row['opening_settle_method'] = $isCompensation ? '公司向客户支付售后补差款' : '公司向客户退还销售货款';
            } elseif ((string)$row['source_type'] === 'refurbish') {
                $row['batch_no'] = (string)($row['payable_source_no'] ?: ('整备费用#' . (int)$row['purchase_order_id']));
                $row['purchase_channel'] = '设备整备支出';
                $row['purchaser_name'] = (string)($refurbishOperatorMap[(string)($row['payable_source_no'] ?? '')] ?? '系统登记');
                $row['purchase_at'] = (int)($row['latest_at'] ?? 0);
                $row['opening_settle_method'] = (string)($row['payable_remark'] ?: '公司应向整备服务商支付费用');
            } elseif ((string)$row['source_type'] === 'purchase') {
                $row['batch_no'] = $row['purchase_no'] ?: ('采购批次#' . (int)$row['purchase_order_id']);
                $row['opening_settle_method'] = (string)($row['settle_method'] ?? '');
                foreach (['plugin', 'plugin_name', 'type', 'name', 'id', 'no'] as $originField) {
                    $field = 'origin_' . $originField;
                    $orderField = 'order_origin_' . $originField;
                    if (empty($row[$field]) && !empty($row[$orderField])) $row[$field] = $row[$orderField];
                }
                if (empty($row['channel_name'])) $row['channel_name'] = (string)($row['purchase_channel'] ?? '');
            } else {
                $row['batch_no'] = (string)($row['origin_no'] ?: $row['payable_source_no'] ?: ($row['category_name'] . '#' . (int)$row['purchase_order_id']));
                $row['purchase_channel'] = (string)($row['channel_name'] ?: $row['origin_name'] ?: '插件业务');
                $row['purchaser_name'] = (string)($row['origin_plugin_name'] ?: '系统接入');
                $row['purchase_at'] = (int)($row['latest_at'] ?? 0);
                $row['opening_settle_method'] = (string)($row['business_reason'] ?: '插件业务形成应付，由财务确认实际付款。');
            }
            $row['source_no'] = (string)$row['batch_no'];
            $row['source_meta'] = (new ErpFinanceSourceService())->sourceMeta($row, 'payable');
            $row['source_label'] = (string)$row['source_meta']['finance_type_name'];
            $row['business_reason'] = (string)($row['source_meta']['business_reason'] ?? $row['business_reason'] ?? '');
            $row['remain_amount'] = max(0, round((float)$row['amount'] - (float)$row['settled_amount'], 2));
            $row['finance_status'] = ErpDict::financeStatus((float)$row['amount'], (float)$row['settled_amount']);
        }
        unset($row);
        $this->fillPayableBatchSettleSummary($page['data']);
        $this->fillOffsetState($page['data'], 'payable');
        ErpPartyMemberNames::append($this->site_id, $page['data']);
        return $page;
    }

    private function fillPayableBatchSettleSummary(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }

        $partyIds = array_values(array_unique(array_filter(array_map(static fn($row) => (int)($row['party_id'] ?? 0), $rows))));
        $purchaseIds = array_values(array_unique(array_filter(array_map(static fn($row) => (int)($row['purchase_order_id'] ?? 0), $rows))));
        if (empty($partyIds) || empty($purchaseIds)) {
            foreach ($rows as &$row) {
                $row['settle_summary'] = $this->emptySettleSummary((float)($row['settled_amount'] ?? 0));
                $row['settle_summary_items'] = [];
            }
            unset($row);
            return;
        }

        $assetTable = (new ErpAsset())->getTable();
        $batchKeys = [];
        foreach ($rows as $row) {
            $batchKeys[(int)($row['party_id'] ?? 0) . '_' . (int)($row['purchase_order_id'] ?? 0)] = true;
        }

        $payableRows = ErpPayable::alias('p')
            ->leftJoin($assetTable . ' a', "p.source_type = 'purchase_asset' AND a.id = p.source_id AND a.site_id = p.site_id")
            ->where([['p.site_id', '=', $this->site_id]])
            ->where('p.status', '<>', ErpDict::STATUS_VOID)
            ->whereIn('p.party_id', $partyIds)
            ->where(function ($q) use ($purchaseIds) {
                $q->where(function ($qq) use ($purchaseIds) {
                    $qq->where('p.source_type', '=', 'purchase')->whereIn('p.source_id', $purchaseIds);
                })->whereOr(function ($qq) use ($purchaseIds) {
                    $qq->where('p.source_type', '=', 'purchase_asset')->whereIn('a.purchase_order_id', $purchaseIds);
                });
            })
            ->field("p.id,p.party_id,IF(p.source_type = 'purchase_asset', a.purchase_order_id, p.source_id) as purchase_order_id")
            ->select()
            ->toArray();

        $payableToBatch = [];
        $payableIds = [];
        foreach ($payableRows as $row) {
            $key = (int)$row['party_id'] . '_' . (int)$row['purchase_order_id'];
            if (!isset($batchKeys[$key])) {
                continue;
            }
            $payableId = (int)$row['id'];
            $payableIds[] = $payableId;
            $payableToBatch[$payableId] = $key;
        }

        $summaryMap = $this->settlementSummaryForTargets(ErpDict::TARGET_PAYABLE, $payableIds);
        $batchItems = [];
        foreach ($summaryMap as $payableId => $summary) {
            $key = $payableToBatch[(int)$payableId] ?? '';
            if ($key === '') {
                continue;
            }
            foreach ($summary['items'] ?? [] as $item) {
                $itemKey = $item['label'];
                if (!isset($batchItems[$key][$itemKey])) {
                    $batchItems[$key][$itemKey] = ['label' => $item['label'], 'amount' => 0.0];
                }
                $batchItems[$key][$itemKey]['amount'] = round((float)$batchItems[$key][$itemKey]['amount'] + (float)$item['amount'], 2);
            }
        }

        foreach ($rows as &$row) {
            if ((string)($row['source_type'] ?? '') === 'sale_return') {
                $row['settle_summary_items'] = [];
                $row['settle_summary'] = $this->emptySettleSummary((float)($row['settled_amount'] ?? 0));
                continue;
            }
            $key = (int)($row['party_id'] ?? 0) . '_' . (int)($row['purchase_order_id'] ?? 0);
            $items = array_values($batchItems[$key] ?? []);
            $row['settle_summary_items'] = $items;
            $row['settle_summary'] = !empty($items) ? $this->formatSettleSummary($items) : $this->emptySettleSummary((float)($row['settled_amount'] ?? 0));
        }
        unset($row);
    }

    private function settlementSummaryForTargets(string $targetType, array $targetIds): array
    {
        $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds))));
        if (empty($targetIds)) {
            return [];
        }

        $settlementTable = (new ErpSettlement())->getTable();
        $rows = ErpSettlementLink::alias('l')
            ->leftJoin($settlementTable . ' s', 's.id = l.settlement_id AND s.site_id = l.site_id')
            ->where([
                ['l.site_id', '=', $this->site_id],
                ['l.target_type', '=', $targetType],
            ])
            ->whereIn('l.target_id', $targetIds)
            ->field('l.target_id,l.applied_amount,s.settlement_type,s.capital_account_name,s.operator_uid,s.operator_name,s.confirmed_at')
            ->order('s.confirmed_at asc,l.id asc')
            ->select()
            ->toArray();

        $itemsByTarget = [];
        $operatorNamesByTarget = [];
        $latestSettlementByTarget = [];
        foreach ($rows as $row) {
            $targetId = (int)$row['target_id'];
            $label = $this->settleSummaryLabel((string)($row['settlement_type'] ?? ''), (string)($row['capital_account_name'] ?? ''));
            if (!isset($itemsByTarget[$targetId][$label])) {
                $itemsByTarget[$targetId][$label] = ['label' => $label, 'amount' => 0.0];
            }
            $itemsByTarget[$targetId][$label]['amount'] = round((float)$itemsByTarget[$targetId][$label]['amount'] + (float)$row['applied_amount'], 2);
            $operatorName = trim((string)($row['operator_name'] ?? ''));
            if ($operatorName !== '') $operatorNamesByTarget[$targetId][$operatorName] = true;
            $latestSettlementByTarget[$targetId] = [
                'operator_uid' => (int)($row['operator_uid'] ?? 0),
                'operator_name' => $operatorName,
                'confirmed_at' => (int)($row['confirmed_at'] ?? 0),
            ];
        }

        $map = [];
        foreach ($itemsByTarget as $targetId => $items) {
            $items = array_values($items);
            $map[$targetId] = [
                'text' => $this->formatSettleSummary($items),
                'items' => $items,
                'operator_uid' => (int)($latestSettlementByTarget[$targetId]['operator_uid'] ?? 0),
                'operator_name' => (string)($latestSettlementByTarget[$targetId]['operator_name'] ?? ''),
                'operator_names' => array_keys($operatorNamesByTarget[$targetId] ?? []),
                'confirmed_at' => (int)($latestSettlementByTarget[$targetId]['confirmed_at'] ?? 0),
            ];
        }
        return $map;
    }

    /**
     * 允许来源插件批量补齐自身历史单据的展示快照，不让 ERP 直接依赖插件模型或数据表。
     * 返回格式：['rows' => [erp_receivable_id => ['opening_settle_method' => ..., ...]]]。
     */
    private function enrichFinanceDisplayRows(array &$rows, string $side): void
    {
        if (empty($rows)) return;
        $payloadRows = array_map(static fn(array $row): array => [
            'id' => (int)($row['id'] ?? 0),
            'source_type' => (string)($row['source_type'] ?? ''),
            'source_id' => (int)($row['source_id'] ?? 0),
            'source_no' => (string)($row['source_no'] ?? ''),
            'origin_plugin' => (string)($row['origin_plugin'] ?? ''),
            'origin_type' => (string)($row['origin_type'] ?? ''),
            'origin_id' => (string)($row['origin_id'] ?? ''),
            'origin_no' => (string)($row['origin_no'] ?? ''),
        ], $rows);
        $allowed = ['opening_settle_method', 'business_operator_uid', 'business_operator_name'];
        foreach ((array)event('HsxErpFinanceDisplayRows', [
            'site_id' => (int)$this->site_id,
            'side' => $side,
            'rows' => $payloadRows,
        ]) as $result) {
            if (!is_array($result)) continue;
            $patches = (array)($result['rows'] ?? $result);
            foreach ($rows as &$row) {
                $patch = $patches[(int)($row['id'] ?? 0)] ?? null;
                if (!is_array($patch)) continue;
                foreach ($allowed as $field) {
                    if (!array_key_exists($field, $patch)) continue;
                    if ($field === 'business_operator_uid') {
                        if ((int)($row[$field] ?? 0) <= 0) $row[$field] = max(0, (int)$patch[$field]);
                    } elseif (trim((string)($row[$field] ?? '')) === '') {
                        $row[$field] = trim((string)$patch[$field]);
                    }
                }
            }
            unset($row);
        }
    }

    private function extractBusinessOperatorName(string $reason): string
    {
        if ($reason === '') return '';
        return preg_match('/经办人[：:\s]*([^，；;]+)/u', $reason, $match) === 1
            ? mb_substr(trim((string)$match[1]), 0, 60)
            : '';
    }

    private function settleSummaryLabel(string $type, string $accountName): string
    {
        if ($type === ErpDict::SETTLEMENT_OFFSET) {
            return '折账';
        }
        $accountName = trim($accountName);
        if ($accountName !== '') {
            return $accountName;
        }
        return self::settlementTypeText($type);
    }

    private function formatSettleSummary(array $items): string
    {
        return implode(' + ', array_map(fn($item) => (string)$item['label'] . ' ¥' . number_format((float)$item['amount'], 2, '.', ''), $items));
    }

    private function emptySettleSummary(float $settledAmount): string
    {
        return $settledAmount > 0 ? ('已核销 ¥' . number_format($settledAmount, 2, '.', '')) : '待清算';
    }

    private function payablePartyPage(array $where): array
    {
        $partyTable = (new ErpParty())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $orderTable = (new ErpPurchaseOrder())->getTable();
        $query = ErpPayable::alias('p')
            ->leftJoin($partyTable . ' party', 'party.id = p.party_id AND party.site_id = p.site_id')
            ->where([['p.site_id', '=', $this->site_id]]);
        if (!empty($where['status'])) {
            $query->where('p.status', '=', (string)$where['status']);
        }
        if (!empty($where['party_id'])) {
            $query->where('p.party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['start_at'])) {
            $query->where('p.occurred_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('p.occurred_at', '<=', (int)$where['end_at']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $matchedPartyIds = ErpAsset::alias('a')
                ->leftJoin($orderTable . ' o', 'o.id = a.purchase_order_id AND o.site_id = a.site_id')
                ->where([['a.site_id', '=', $this->site_id]])
                ->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|o.purchase_no', '%' . $kw . '%')
                ->column('a.party_id');
            $query->where(function ($q) use ($kw, $matchedPartyIds) {
                $q->whereLike('p.party_name|p.source_no|p.remark|party.contact_name|party.contact_mobile|party.m_no', '%' . $kw . '%');
                if (!empty($matchedPartyIds)) {
                    $q->whereOr('p.party_id', 'in', array_values(array_unique(array_map('intval', $matchedPartyIds))));
                }
            });
        }
        $query->field([
            'p.party_id',
            'MAX(p.party_name) as party_name',
            'MAX(party.contact_name) as contact_name',
            'MAX(party.contact_mobile) as contact_mobile',
            'COUNT(p.id) as payable_count',
            'SUM(p.amount) as amount',
            'SUM(p.settled_amount) as settled_amount',
            'MAX(p.occurred_at) as latest_at',
            'MIN(p.occurred_at) as first_at',
        ])->group('p.party_id');
        if (($where['min_amount'] ?? '') !== '') {
            $query->having('SUM(p.amount) >= ' . round((float)$where['min_amount'], 2));
        }
        if (($where['max_amount'] ?? '') !== '') {
            $query->having('SUM(p.amount) <= ' . round((float)$where['max_amount'], 2));
        }
        if (($where['min_remain'] ?? '') !== '') {
            $query->having('SUM(p.amount - p.settled_amount) >= ' . round((float)$where['min_remain'], 2));
        }
        if (($where['max_remain'] ?? '') !== '') {
            $query->having('SUM(p.amount - p.settled_amount) <= ' . round((float)$where['max_remain'], 2));
        }
        $page = $query->order('latest_at desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();

        foreach ($page['data'] as &$row) {
            $row['remain_amount'] = max(0, round((float)$row['amount'] - (float)$row['settled_amount'], 2));
        }
        unset($row);
        $this->fillOffsetState($page['data'], 'payable');
        return $page;
    }

    private function fillOffsetState(array &$rows, string $currentSide): void
    {
        if (empty($rows)) {
            return;
        }

        $partyIds = array_values(array_unique(array_filter(array_map(static fn($row) => (int)($row['party_id'] ?? 0), $rows))));
        if (empty($partyIds)) {
            return;
        }

        $payableMap = $this->financeRemainMap(ErpPayable::class, $partyIds);
        $receivableMap = $this->financeRemainMap(ErpReceivable::class, $partyIds);
        foreach ($rows as &$row) {
            $partyId = (int)($row['party_id'] ?? 0);
            $payableRemain = round((float)($payableMap[$partyId] ?? 0), 2);
            $receivableRemain = round((float)($receivableMap[$partyId] ?? 0), 2);
            if ($currentSide === 'payable') {
                $payableRemain = max($payableRemain, (float)($row['remain_amount'] ?? 0));
            } elseif ($currentSide === 'receivable') {
                $receivableRemain = max($receivableRemain, (float)($row['remain_amount'] ?? 0));
            }
            $row['offset_payable_remain'] = $payableRemain;
            $row['offset_receivable_remain'] = $receivableRemain;
            $row['can_offset'] = $payableRemain > 0 && $receivableRemain > 0;
        }
        unset($row);
    }

    private function financeRemainMap(string $modelClass, array $partyIds): array
    {
        $rows = $modelClass::where([['site_id', '=', $this->site_id]])
            ->whereIn('party_id', $partyIds)
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->field('party_id, SUM(amount - settled_amount) as remain_amount')
            ->group('party_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['party_id']] = max(0, (float)$row['remain_amount']);
        }
        return $map;
    }

    private function dashboardRange(array $where): array
    {
        $start = (int)($where['start_at'] ?? 0);
        $end = (int)($where['end_at'] ?? 0);
        if ($start > 0 || $end > 0) {
            return ['start_at' => $start, 'end_at' => $end, 'period' => 'custom'];
        }
        $period = (string)($where['period'] ?? 'month');
        if ($period === 'today') {
            return ['start_at' => strtotime(date('Y-m-d 00:00:00')), 'end_at' => strtotime(date('Y-m-d 23:59:59')), 'period' => 'today'];
        }
        if ($period === 'yesterday') {
            return ['start_at' => strtotime('yesterday 00:00:00'), 'end_at' => strtotime('yesterday 23:59:59'), 'period' => 'yesterday'];
        }
        if ($period === 'last7') {
            return ['start_at' => strtotime('-6 days 00:00:00'), 'end_at' => strtotime('today 23:59:59'), 'period' => 'last7'];
        }
        if ($period === 'last_month') {
            return ['start_at' => strtotime('first day of last month 00:00:00'), 'end_at' => strtotime('last day of last month 23:59:59'), 'period' => 'last_month'];
        }
        if ($period === 'all') {
            return ['start_at' => 0, 'end_at' => 0, 'period' => 'all'];
        }
        return ['start_at' => strtotime(date('Y-m-01 00:00:00')), 'end_at' => strtotime(date('Y-m-t 23:59:59')), 'period' => 'month'];
    }

    private function offsetPartyCount(): int
    {
        $payableRows = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->field('party_id, SUM(amount - settled_amount) as remain_amount')
            ->group('party_id')
            ->select()
            ->toArray();
        $receivableMap = $this->financeRemainMap(ErpReceivable::class, array_column($payableRows, 'party_id'));
        $count = 0;
        foreach ($payableRows as $row) {
            $partyId = (int)($row['party_id'] ?? 0);
            if ((float)($row['remain_amount'] ?? 0) > 0 && (float)($receivableMap[$partyId] ?? 0) > 0) {
                $count++;
            }
        }
        return $count;
    }

    private function receivableSettlementDetails(int $receivableId): array
    {
        $settlementTable = (new ErpSettlement())->getTable();
        $links = ErpSettlementLink::alias('l')
            ->leftJoin($settlementTable . ' s', 's.id = l.settlement_id AND s.site_id = l.site_id')
            ->where([
                ['l.site_id', '=', $this->site_id],
                ['l.target_type', '=', ErpDict::TARGET_RECEIVABLE],
                ['l.target_id', '=', $receivableId],
            ])
            ->field([
                'l.settlement_id',
                'l.applied_amount',
                's.settlement_no',
                's.settlement_type',
                's.amount',
                's.cash_direction',
                's.capital_account_id',
                's.capital_account_name',
                's.operator_uid',
                's.operator_name',
                's.confirmed_at',
                's.remark',
            ])
            ->order('s.confirmed_at desc,l.id desc')
            ->select()
            ->toArray();

        if (empty($links)) {
            return [];
        }

        $settlementIds = array_values(array_unique(array_map(static fn($row) => (int)$row['settlement_id'], $links)));
        $moneyMap = [];
        $moneyRows = ErpMoneyLedger::where([['site_id', '=', $this->site_id]])
            ->whereIn('settlement_id', $settlementIds)
            ->field('settlement_id,ledger_no,direction,category_key,category_name,category_statement_group,amount,capital_account_name,balance_after,voucher_urls,occurred_at,remark')
            ->order('id asc')
            ->select()
            ->toArray();
        foreach ($moneyRows as $row) {
            $moneyMap[(int)$row['settlement_id']][] = $row;
        }

        $targetMap = $this->settlementTargetDetails($settlementIds);
        foreach ($links as &$row) {
            $row['settlement_type_text'] = self::settlementTypeText((string)$row['settlement_type']);
            $row['pay_method_text'] = $this->payMethodText($row);
            $row['money_ledgers'] = $moneyMap[(int)$row['settlement_id']] ?? [];
            $row['targets'] = $targetMap[(int)$row['settlement_id']] ?? [];
        }
        unset($row);
        return $links;
    }

    private function payableSettlementDetails(array $payableIds): array
    {
        $payableIds = array_values(array_unique(array_filter(array_map('intval', $payableIds))));
        if (empty($payableIds)) {
            return [];
        }

        $settlementTable = (new ErpSettlement())->getTable();
        $links = ErpSettlementLink::alias('l')
            ->leftJoin($settlementTable . ' s', 's.id = l.settlement_id AND s.site_id = l.site_id')
            ->where([
                ['l.site_id', '=', $this->site_id],
                ['l.target_type', '=', ErpDict::TARGET_PAYABLE],
            ])
            ->whereIn('l.target_id', $payableIds)
            ->field([
                'l.target_id',
                'l.settlement_id',
                'l.applied_amount',
                's.settlement_no',
                's.settlement_type',
                's.amount',
                's.cash_direction',
                's.capital_account_id',
                's.capital_account_name',
                's.operator_uid',
                's.operator_name',
                's.confirmed_at',
                's.remark',
            ])
            ->order('s.confirmed_at desc,l.id desc')
            ->select()
            ->toArray();

        if (empty($links)) {
            return [];
        }

        $settlementIds = array_values(array_unique(array_map(static fn($row) => (int)$row['settlement_id'], $links)));
        $moneyMap = [];
        $moneyRows = ErpMoneyLedger::where([['site_id', '=', $this->site_id]])
            ->whereIn('settlement_id', $settlementIds)
            ->field('settlement_id,ledger_no,direction,category_key,category_name,category_statement_group,amount,capital_account_name,balance_after,voucher_urls,occurred_at,remark')
            ->order('id asc')
            ->select()
            ->toArray();
        foreach ($moneyRows as $row) {
            $moneyMap[(int)$row['settlement_id']][] = $row;
        }

        $targetMap = $this->settlementTargetDetails($settlementIds);
        $map = [];
        foreach ($links as $row) {
            $row['settlement_type_text'] = self::settlementTypeText((string)$row['settlement_type']);
            $row['pay_method_text'] = $this->payMethodText($row);
            $row['money_ledgers'] = $moneyMap[(int)$row['settlement_id']] ?? [];
            $row['targets'] = $targetMap[(int)$row['settlement_id']] ?? [];
            $map[(int)$row['target_id']][] = $row;
        }
        return $map;
    }

    private function settlementTargetDetails(array $settlementIds): array
    {
        if (empty($settlementIds)) {
            return [];
        }

        $links = ErpSettlementLink::where([['site_id', '=', $this->site_id]])
            ->whereIn('settlement_id', $settlementIds)
            ->order('id asc')
            ->select()
            ->toArray();
        if (empty($links)) {
            return [];
        }

        $payableIds = [];
        $receivableIds = [];
        foreach ($links as $link) {
            if ((string)$link['target_type'] === ErpDict::TARGET_PAYABLE) {
                $payableIds[] = (int)$link['target_id'];
            } elseif ((string)$link['target_type'] === ErpDict::TARGET_RECEIVABLE) {
                $receivableIds[] = (int)$link['target_id'];
            }
        }

        $payableMap = [];
        if (!empty($payableIds)) {
            $rows = ErpPayable::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', array_values(array_unique($payableIds)))
                ->field('id,payable_no,party_name,asset_id,source_type,source_id,source_no,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_id,origin_no,biz_scene,category_key,category_name,category_statement_group,category_source_plugin,category_source_key,channel_code,channel_name,business_reason,amount,settled_amount,status')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $payableMap[(int)$row['id']] = $row;
            }
        }

        $receivableMap = [];
        if (!empty($receivableIds)) {
            $rows = ErpReceivable::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', array_values(array_unique($receivableIds)))
                ->field('id,receivable_no,party_name,source_type,source_id,source_no,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_id,origin_no,biz_scene,category_key,category_name,category_statement_group,category_source_plugin,category_source_key,channel_code,channel_name,business_reason,amount,settled_amount,status')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $receivableMap[(int)$row['id']] = $row;
            }
        }

        $targetDeviceMap = $this->settlementTargetDeviceMap($payableMap, $receivableMap);
        $map = [];
        foreach ($links as $link) {
            $targetType = (string)$link['target_type'];
            $targetId = (int)$link['target_id'];
            $target = $targetType === ErpDict::TARGET_PAYABLE ? ($payableMap[$targetId] ?? []) : ($receivableMap[$targetId] ?? []);
            $sourceMeta = (new ErpFinanceSourceService())->sourceMeta($target, $targetType === ErpDict::TARGET_PAYABLE ? 'payable' : 'receivable');
            $map[(int)$link['settlement_id']][] = [
                'target_type' => $targetType,
                'target_type_text' => $targetType === ErpDict::TARGET_PAYABLE ? '应付' : '应收',
                'target_id' => $targetId,
                'target_no' => (string)($target['payable_no'] ?? $target['receivable_no'] ?? ''),
                'party_name' => (string)($target['party_name'] ?? ''),
                'source_no' => (string)($target['source_no'] ?? ''),
                'amount' => (float)($target['amount'] ?? 0),
                'settled_amount' => (float)($target['settled_amount'] ?? 0),
                'status' => (string)($target['status'] ?? ''),
                'applied_amount' => (float)$link['applied_amount'],
                'source_meta' => $sourceMeta,
                'finance_type_name' => (string)($sourceMeta['finance_type_name'] ?? ''),
                'business_source_name' => (string)($sourceMeta['business_source_name'] ?? ''),
                'biz_scene' => (string)($target['biz_scene'] ?? ''),
                'category_statement_group' => (string)($target['category_statement_group'] ?? ''),
                'business_reason' => (string)($target['business_reason'] ?? ''),
                'devices' => $targetDeviceMap[$targetType . '_' . $targetId] ?? [],
            ];
        }
        return $map;
    }

    private function settlementTargetDeviceMap(array $payableMap, array $receivableMap): array
    {
        $payableDirectAssetIds = [];
        $payableAssetIds = [];
        $payablePurchaseIds = [];
        foreach ($payableMap as $id => $row) {
            if ((int)($row['asset_id'] ?? 0) > 0) {
                $payableDirectAssetIds[(int)$row['asset_id']][] = (int)$id;
            } elseif ((string)($row['source_type'] ?? '') === 'purchase_asset') {
                $payableAssetIds[(int)$row['source_id']][] = (int)$id;
            } elseif ((string)($row['source_type'] ?? '') === 'purchase') {
                $payablePurchaseIds[(int)$row['source_id']][] = (int)$id;
            }
        }

        $receivableDirectAssetIds = [];
        $receivableSaleIds = [];
        $receivablePurchaseReturnIds = [];
        foreach ($receivableMap as $id => $row) {
            if ((int)($row['asset_id'] ?? 0) > 0) {
                $receivableDirectAssetIds[(int)$row['asset_id']][] = (int)$id;
            } elseif ((string)($row['source_type'] ?? '') === 'sale') {
                $receivableSaleIds[(int)$row['source_id']][] = (int)$id;
            } elseif ((string)($row['source_type'] ?? '') === 'purchase_return') {
                $receivablePurchaseReturnIds[(int)$row['source_id']][] = (int)$id;
            }
        }

        $map = [];
        if (!empty($payableDirectAssetIds) || !empty($payableAssetIds) || !empty($payablePurchaseIds) || !empty($receivableDirectAssetIds)) {
            $query = ErpAsset::where([['site_id', '=', $this->site_id]]);
            $query->where(function ($q) use ($payableDirectAssetIds, $payableAssetIds, $payablePurchaseIds, $receivableDirectAssetIds) {
                $directIds = array_values(array_unique(array_merge(array_keys($payableDirectAssetIds), array_keys($payableAssetIds), array_keys($receivableDirectAssetIds))));
                if (!empty($directIds)) {
                    $q->whereIn('id', $directIds);
                }
                if (!empty($payablePurchaseIds)) {
                    !empty($directIds) ? $q->whereOr('purchase_order_id', 'in', array_keys($payablePurchaseIds)) : $q->whereIn('purchase_order_id', array_keys($payablePurchaseIds));
                }
            });
            $rows = $query->field('id,asset_no,imei,sn,model,spec,total_cost,sale_price,profit,warehouse_name,location_name,purchase_order_id')->select()->toArray();
            foreach ($rows as $row) {
                $device = $this->formatSettlementDevice($row, 'purchase');
                foreach ($payableDirectAssetIds[(int)$row['id']] ?? [] as $payableId) {
                    $map[ErpDict::TARGET_PAYABLE . '_' . $payableId][] = $device;
                }
                foreach ($payableAssetIds[(int)$row['id']] ?? [] as $payableId) {
                    $map[ErpDict::TARGET_PAYABLE . '_' . $payableId][] = $device;
                }
                foreach ($payablePurchaseIds[(int)$row['purchase_order_id']] ?? [] as $payableId) {
                    $map[ErpDict::TARGET_PAYABLE . '_' . $payableId][] = $device;
                }
                foreach ($receivableDirectAssetIds[(int)$row['id']] ?? [] as $receivableId) {
                    $map[ErpDict::TARGET_RECEIVABLE . '_' . $receivableId][] = $this->formatSettlementDevice($row, 'sale');
                }
            }
        }

        if (!empty($receivableSaleIds)) {
            $assetTable = (new ErpAsset())->getTable();
            $rows = ErpSaleItem::alias('i')
                ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
                ->where([['i.site_id', '=', $this->site_id]])
                ->whereIn('i.sale_order_id', array_keys($receivableSaleIds))
                ->field([
                    'i.sale_order_id',
                    'i.asset_id as id',
                    "COALESCE(NULLIF(i.imei,''), a.imei) as imei",
                    "COALESCE(NULLIF(i.model,''), a.model) as model",
                    'i.cost as total_cost',
                    'i.sale_price',
                    'i.profit',
                    'a.asset_no',
                    'a.sn',
                    'a.spec',
                    'a.warehouse_name',
                    'a.location_name',
                ])
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $device = $this->formatSettlementDevice($row, 'sale');
                foreach ($receivableSaleIds[(int)$row['sale_order_id']] ?? [] as $receivableId) {
                    $map[ErpDict::TARGET_RECEIVABLE . '_' . $receivableId][] = $device;
                }
            }
        }

        if (!empty($receivablePurchaseReturnIds)) {
            $assetTable = (new ErpAsset())->getTable();
            $rows = ErpPurchaseReturnItem::alias('i')
                ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
                ->where([['i.site_id', '=', $this->site_id]])
                ->whereIn('i.return_id', array_keys($receivablePurchaseReturnIds))
                ->field([
                    'i.return_id', 'i.asset_id as id',
                    "COALESCE(NULLIF(i.asset_no,''), a.asset_no) as asset_no",
                    "COALESCE(NULLIF(i.imei,''), a.imei) as imei",
                    "COALESCE(NULLIF(i.model,''), a.model) as model",
                    'i.return_cost as total_cost', 'a.sn', 'a.spec', 'a.warehouse_name', 'a.location_name',
                ])->select()->toArray();
            foreach ($rows as $row) {
                $device = $this->formatSettlementDevice($row, 'purchase_return');
                foreach ($receivablePurchaseReturnIds[(int)$row['return_id']] ?? [] as $receivableId) {
                    $map[ErpDict::TARGET_RECEIVABLE . '_' . $receivableId][] = $device;
                }
            }
        }

        return $map;
    }

    /** 给应收/应付候选一次性附加设备快照，供折账和结算界面准确展示型号与IMEI。 */
    private function appendFinanceDevices(array &$rows, string $targetType, string $idField = 'id'): void
    {
        if ($rows === []) return;
        $targets = [];
        foreach ($rows as $row) {
            $id = (int)($row[$idField] ?? 0);
            if ($id > 0) $targets[$id] = $row;
        }
        if ($targets === []) return;
        $deviceMap = $targetType === ErpDict::TARGET_PAYABLE
            ? $this->settlementTargetDeviceMap($targets, [])
            : $this->settlementTargetDeviceMap([], $targets);
        foreach ($rows as &$row) {
            $id = (int)($row[$idField] ?? 0);
            $row['devices'] = $deviceMap[$targetType . '_' . $id] ?? [];
        }
        unset($row);
    }

    private function formatSettlementDevice(array $row, string $scene): array
    {
        return [
            'scene' => $scene,
            'asset_id' => (int)($row['id'] ?? 0),
            'asset_no' => (string)($row['asset_no'] ?? ''),
            'imei' => (string)($row['imei'] ?? ''),
            'sn' => (string)($row['sn'] ?? ''),
            'model' => (string)($row['model'] ?? ''),
            'spec' => (string)($row['spec'] ?? ''),
            'warehouse_name' => (string)($row['warehouse_name'] ?? ''),
            'location_name' => (string)($row['location_name'] ?? ''),
            'cost' => (float)($row['total_cost'] ?? 0),
            'sale_price' => (float)($row['sale_price'] ?? 0),
            'profit' => (float)($row['profit'] ?? 0),
        ];
    }

    private function applyFinanceFilters($query, array $where, string $assetAlias, string $orderAlias, string $financeAlias): void
    {
        if (!empty($where['status'])) {
            $query->where($financeAlias . '.status', '=', (string)$where['status']);
        }
        if (!empty($where['start_at'])) {
            $query->where($financeAlias . '.occurred_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where($financeAlias . '.occurred_at', '<=', (int)$where['end_at']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike($assetAlias . '.asset_no|' . $assetAlias . '.imei|' . $assetAlias . '.sn|' . $assetAlias . '.model|' . $assetAlias . '.spec|' . $orderAlias . '.purchase_no', '%' . $kw . '%');
        }
    }

    private function allocatedAmount(float $itemCost, float $orderCost, float $paid): float
    {
        if ($itemCost <= 0 || $orderCost <= 0 || $paid <= 0) {
            return 0.0;
        }
        return round(min($itemCost, $itemCost * min($paid / $orderCost, 1)), 2);
    }

    private function createSettlement($target, string $type, float $amount, string $cashDirection, array $account, array $data): ErpSettlement
    {
        $confirmedAt = (int)($data['confirmed_at'] ?? 0);
        if ($confirmedAt <= 0) $confirmedAt = time();
        $requestId = ErpIdempotency::nullable($data['request_id'] ?? null);
        return ErpSettlement::create([
            'site_id' => $this->site_id,
            'request_id' => $requestId,
            'settlement_no' => ErpLedgerService::makeNo('ST'),
            'party_id' => (int)$target->party_id,
            'party_name' => (string)$target->party_name,
            'settlement_type' => $type,
            'amount' => $amount,
            'cash_direction' => $cashDirection,
            'capital_account_id' => (int)($account['id'] ?? 0),
            'capital_account_name' => (string)($account['name'] ?? ''),
            'status' => 'confirmed',
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'confirmed_at' => $confirmedAt,
            'voucher_urls' => is_array($data['voucher_urls'] ?? null) ? json_encode($data['voucher_urls'], JSON_UNESCAPED_UNICODE) : trim((string)($data['voucher_urls'] ?? '')),
            'remark' => (string)($data['remark'] ?? ''),
            'create_at' => time(),
        ]);
    }

    private function applyPayable(ErpPayable $payable, float $amount, int $settlementId): void
    {
        $amount = round($amount, 2);
        $remain = round((float)$payable->amount - (float)$payable->settled_amount, 2);
        if ($amount <= 0 || $amount > $remain + 0.0001) {
            throw new CommonException('应付款核销金额无效或已超过剩余应付');
        }
        $newSettled = round((float)$payable->settled_amount + $amount, 2);
        $payable->save([
            'settled_amount' => $newSettled,
            'status' => ErpDict::financeStatus((float)$payable->amount, $newSettled),
            'update_at' => time(),
        ]);
        ErpSettlementLink::create(array_merge([
            'site_id' => $this->site_id,
            'settlement_id' => $settlementId,
            'target_type' => ErpDict::TARGET_PAYABLE,
            'target_id' => (int)$payable->id,
            'applied_amount' => $amount,
            'create_at' => time(),
        ], $this->settlementLinkMeta($payable, 'payable')));
    }

    private function applyReceivable(ErpReceivable $receivable, float $amount, int $settlementId): void
    {
        $amount = round($amount, 2);
        $remain = round((float)$receivable->amount - (float)$receivable->settled_amount, 2);
        if ($amount <= 0 || $amount > $remain + 0.0001) {
            throw new CommonException('应收款核销金额无效或已超过剩余应收');
        }
        $newSettled = round((float)$receivable->settled_amount + $amount, 2);
        $receivable->save([
            'settled_amount' => $newSettled,
            'status' => ErpDict::financeStatus((float)$receivable->amount, $newSettled),
            'update_at' => time(),
        ]);
        ErpSettlementLink::create(array_merge([
            'site_id' => $this->site_id,
            'settlement_id' => $settlementId,
            'target_type' => ErpDict::TARGET_RECEIVABLE,
            'target_id' => (int)$receivable->id,
            'applied_amount' => $amount,
            'create_at' => time(),
        ], $this->settlementLinkMeta($receivable, 'receivable')));
    }

    private function targetCategoryMeta($target): array
    {
        $row = is_array($target) ? $target : $target->toArray();
        $side = $target instanceof ErpReceivable || (($row['receivable_no'] ?? '') !== '') ? 'receivable' : 'payable';
        $sourceMeta = (new ErpFinanceSourceService())->sourceMeta($row, $side);
        $categoryKey = trim((string)($row['category_key'] ?? '')) ?: (string)($sourceMeta['finance_type_key'] ?? '');
        return [
            'category_key' => $categoryKey,
            'category_name' => trim((string)($row['category_name'] ?? '')) ?: (string)($sourceMeta['finance_type_name'] ?? ''),
            'category_statement_group' => trim((string)($row['category_statement_group'] ?? '')) ?: (string)($sourceMeta['statement_group'] ?? ''),
            'category_source_plugin' => trim((string)($row['category_source_plugin'] ?? '')) ?: 'hsx_erp',
            'category_source_key' => trim((string)($row['category_source_key'] ?? '')) ?: $categoryKey,
        ];
    }

    private function combinedCategoryMeta(iterable $targets): array
    {
        $rows = [];
        $payableSide = false;
        foreach ($targets as $target) {
            if ($target instanceof ErpPayable) $payableSide = true;
            $meta = $this->targetCategoryMeta($target);
            $rows[(string)$meta['category_key']] = $meta;
        }
        if (count($rows) === 1) return array_values($rows)[0];
        return [
            'category_key' => 'mixed',
            'category_name' => $payableSide ? '混合支出' : '混合收入',
            'category_statement_group' => 'mixed',
            'category_source_plugin' => 'hsx_erp',
            'category_source_key' => 'mixed',
        ];
    }

    private function settlementLinkMeta($target, string $side): array
    {
        $row = is_array($target) ? $target : $target->toArray();
        $category = $this->targetCategoryMeta($target);
        $sourceMeta = (new ErpFinanceSourceService())->sourceMeta($row, $side);
        return array_merge($category, [
            'asset_id' => (int)($row['asset_id'] ?? 0),
            'biz_scene' => (string)($row['biz_scene'] ?? $sourceMeta['biz_scene'] ?? ''),
            'origin_plugin' => (string)($row['origin_plugin'] ?? $sourceMeta['source_plugin'] ?? ''),
            'origin_type' => (string)($row['origin_type'] ?? $sourceMeta['business_source_key'] ?? ''),
            'origin_id' => (string)($row['origin_id'] ?? ''),
        ]);
    }

    /** 在结算事务内固化完整来源/设备快照，事务提交后再派发给插件。 */
    private function queueSettlementCompletedEvent(int $settlementId): void
    {
        $settlement = ErpSettlement::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $settlementId],
        ])->findOrEmpty();
        if ($settlement->isEmpty()) {
            throw new CommonException('结算领域事件缺少结算单');
        }

        $links = ErpSettlementLink::where([
            ['site_id', '=', $this->site_id],
            ['settlement_id', '=', $settlementId],
        ])->order('id asc')->select()->toArray();
        $targets = [];
        foreach ($links as $link) {
            $side = (string)($link['target_type'] ?? '');
            $targetId = (int)($link['target_id'] ?? 0);
            $target = $side === ErpDict::TARGET_PAYABLE
                ? ErpPayable::where([['site_id', '=', $this->site_id], ['id', '=', $targetId]])->findOrEmpty()
                : ErpReceivable::where([['site_id', '=', $this->site_id], ['id', '=', $targetId]])->findOrEmpty();
            if ($target->isEmpty()) continue;

            $assets = $this->settlementTargetAssets($target, $side, (int)($link['asset_id'] ?? 0));
            $sourceMeta = (new ErpFinanceSourceService())->sourceMeta($target->toArray(), $side);
            $origin = $this->settlementTargetOrigin($target, $side);
            $targets[] = [
                'target_type' => $side,
                'target_id' => $targetId,
                'target_no' => $side === ErpDict::TARGET_PAYABLE ? (string)$target->payable_no : (string)$target->receivable_no,
                'applied_amount' => round((float)($link['applied_amount'] ?? 0), 2),
                'target_amount' => round((float)$target->amount, 2),
                'settled_amount' => round((float)$target->settled_amount, 2),
                'remaining_amount' => max(0, round((float)$target->amount - (float)$target->settled_amount, 2)),
                'finance_status' => (string)$target->status,
                'source_type' => (string)$target->source_type,
                'source_id' => (int)$target->source_id,
                'source_no' => (string)$target->source_no,
                'source_meta' => $sourceMeta,
                'origin' => $origin,
                // 同一商城订单可能同时含ERP设备和商城普通商品，状态回写必须按
                // 原订单聚合，而不是用当前一张应收的局部结算额覆盖商城状态。
                'origin_finance' => $side === ErpDict::TARGET_RECEIVABLE
                    ? $this->settlementOriginReceivableSummary($origin, $target)
                    : null,
                'asset' => $assets[0] ?? null,
                'assets' => $assets,
            ];
        }

        $requiredConsumers = [];
        foreach ($targets as $target) {
            if ((string)($target['origin']['plugin'] ?? '') === 'hsx_recycle') {
                $requiredConsumers[] = 'hsx_recycle';
            }
            if ((string)($target['origin']['plugin'] ?? '') === 'phone_shop') {
                $requiredConsumers[] = 'phone_shop.erp_credit_state';
            }
        }
        $requiredConsumers = array_values(array_unique($requiredConsumers));
        $queued = (new ErpIntegrationService())->enqueueDomainEvent(
            'erp.settlement.completed.v1',
            'settlement',
            $settlementId,
            [
                'settlement_id' => $settlementId,
                'settlement_no' => (string)$settlement->settlement_no,
                'settlement_type' => (string)$settlement->settlement_type,
                'amount' => round((float)$settlement->amount, 2),
                'cash_direction' => (string)$settlement->cash_direction,
                'capital_account_id' => (int)$settlement->capital_account_id,
                'capital_account_name' => (string)$settlement->capital_account_name,
                'party_id' => (int)$settlement->party_id,
                'party_name' => (string)$settlement->party_name,
                'confirmed_at' => (int)$settlement->confirmed_at,
                'targets' => $targets,
                'snapshot_at' => time(),
            ],
            [],
            $requiredConsumers
        );
        $this->settlementOutboxIds[] = (int)$queued['id'];
    }

    /** 历史应收可能未固化origin字段，使用其销售单快照补齐跨插件身份。 */
    private function settlementTargetOrigin($target, string $side): array
    {
        $origin = [
            'plugin' => (string)($target->origin_plugin ?? ''),
            'type' => (string)($target->origin_type ?? ''),
            'id' => (string)($target->origin_id ?? ''),
            'no' => (string)($target->origin_no ?? ''),
        ];
        if ($side === ErpDict::TARGET_RECEIVABLE
            && ((string)($target->source_type ?? '') === 'sale'
                || str_starts_with((string)($target->source_type ?? ''), 'phone_shop.'))) {
            $sale = ErpSaleOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)($target->source_id ?? 0)],
            ])->findOrEmpty();
            if (!$sale->isEmpty()) {
                $salePlugin = (string)($sale->origin_plugin ?? '');
                if ($salePlugin !== '' && ($origin['plugin'] === '' || $origin['plugin'] === 'hsx_erp' || $salePlugin === 'phone_shop')) {
                    $origin['plugin'] = $salePlugin;
                    $origin['type'] = (string)($sale->origin_type ?? $origin['type']);
                    $origin['id'] = (string)($sale->origin_id ?? $origin['id']);
                    $origin['no'] = (string)($sale->origin_no ?? $origin['no']);
                }
            }
        }
        if (($origin['plugin'] === '' || $origin['plugin'] === 'hsx_erp')
            && str_starts_with((string)($target->source_type ?? ''), 'phone_shop.')) {
            $origin['plugin'] = 'phone_shop';
        }
        return $origin;
    }

    /** @return array{target_amount:float,settled_amount:float,remaining_amount:float,finance_status:string} */
    private function settlementOriginReceivableSummary(array $origin, $target): array
    {
        $ids = [];
        $plugin = (string)($origin['plugin'] ?? '');
        $originId = (string)($origin['id'] ?? '');
        if ($plugin !== '' && $originId !== '') {
            $ids = array_map('intval', ErpReceivable::where([
                ['site_id', '=', $this->site_id],
                ['origin_plugin', '=', $plugin],
                ['origin_id', '=', $originId],
            ])->column('id'));
            $saleIds = array_map('intval', ErpSaleOrder::where([
                ['site_id', '=', $this->site_id],
                ['origin_plugin', '=', $plugin],
                ['origin_id', '=', $originId],
            ])->column('id'));
            if ($saleIds !== []) {
                $legacyRows = ErpReceivable::where([
                    ['site_id', '=', $this->site_id],
                ])->whereIn('source_id', $saleIds)->field('id,source_type,origin_plugin')->select()->toArray();
                foreach ($legacyRows as $legacyRow) {
                    $sourceType = (string)($legacyRow['source_type'] ?? '');
                    if ($sourceType === 'sale'
                        || str_starts_with($sourceType, 'phone_shop.')
                        || (string)($legacyRow['origin_plugin'] ?? '') === $plugin) {
                        $ids[] = (int)$legacyRow['id'];
                    }
                }
            }
        }
        $ids[] = (int)($target->id ?? 0);
        $ids = array_values(array_unique(array_filter($ids)));
        $rows = $ids === [] ? [] : ErpReceivable::where([
            ['site_id', '=', $this->site_id],
        ])->whereIn('id', $ids)->select()->toArray();
        $amount = 0.0;
        $settled = 0.0;
        foreach ($rows as $row) {
            if ((string)($row['status'] ?? '') === ErpDict::STATUS_VOID) continue;
            $amount = round($amount + (float)($row['amount'] ?? 0), 2);
            $settled = round($settled + min((float)($row['settled_amount'] ?? 0), (float)($row['amount'] ?? 0)), 2);
        }
        $remaining = max(0, round($amount - $settled, 2));
        return [
            'target_amount' => $amount,
            'settled_amount' => $settled,
            'remaining_amount' => $remaining,
            'finance_status' => ErpDict::financeStatus($amount, $settled),
        ];
    }

    /** @return array<int,array{id:int,asset_no:string,imei:string,sn:string,model:string,spec:string}> */
    private function settlementTargetAssets($target, string $side, int $linkAssetId = 0): array
    {
        $assetIds = [];
        if ($linkAssetId > 0) $assetIds[] = $linkAssetId;
        $targetAssetId = (int)($target->asset_id ?? 0);
        if ($targetAssetId > 0) $assetIds[] = $targetAssetId;

        $sourceType = (string)$target->source_type;
        $sourceId = (int)$target->source_id;
        if ($side === ErpDict::TARGET_PAYABLE && $sourceType === 'purchase_asset') {
            $assetIds[] = $sourceId;
        } elseif ($side === ErpDict::TARGET_PAYABLE && $sourceType === 'purchase' && $sourceId > 0) {
            $assetIds = array_merge($assetIds, array_map('intval', ErpAsset::where([
                ['site_id', '=', $this->site_id],
                ['purchase_order_id', '=', $sourceId],
            ])->column('id')));
        } elseif ($side === ErpDict::TARGET_RECEIVABLE && $sourceType === 'sale' && $sourceId > 0) {
            $assetIds = array_merge($assetIds, array_map('intval', ErpSaleItem::where([
                ['site_id', '=', $this->site_id],
                ['sale_order_id', '=', $sourceId],
            ])->column('asset_id')));
        } elseif ($side === ErpDict::TARGET_RECEIVABLE && $sourceType === 'purchase_return' && $sourceId > 0) {
            $assetIds = array_merge($assetIds, array_map('intval', ErpPurchaseReturnItem::where([
                ['site_id', '=', $this->site_id],
                ['return_id', '=', $sourceId],
            ])->column('asset_id')));
        }

        $assetIds = array_values(array_unique(array_filter($assetIds)));
        if ($assetIds === []) return [];
        $rows = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $assetIds)
            ->field('id,asset_no,imei,sn,model,spec,spec_json')
            ->select()->toArray();
        $map = [];
        foreach ($rows as $row) $map[(int)$row['id']] = $row;
        $result = [];
        foreach ($assetIds as $assetId) {
            if (!isset($map[$assetId])) continue;
            $row = $map[$assetId];
            $sourceSnapshot = json_decode((string)($row['spec_json'] ?? ''), true);
            if (!is_array($sourceSnapshot)) $sourceSnapshot = [];
            $result[] = [
                'id' => (int)$row['id'],
                'asset_no' => (string)$row['asset_no'],
                'imei' => (string)$row['imei'],
                'sn' => (string)$row['sn'],
                'model' => (string)$row['model'],
                'spec' => (string)$row['spec'],
                'source_plugin' => (string)($sourceSnapshot['source_plugin'] ?? ''),
                'source_device_id' => $sourceSnapshot['source_device_id'] ?? '',
                'source_order_no' => (string)($sourceSnapshot['source_order_no'] ?? ''),
            ];
        }
        return $result;
    }

    /** 只在外层事务提交后派发；失败由 outbox 重试任务补偿。 */
    private function flushSettlementDomainEvents(): void
    {
        try {
            if (Db::connect()->getPdo()->inTransaction()) return;
        } catch (\Throwable $e) {
            // 无活动连接时仍尝试派发，dispatch 会把失败保存在 outbox。
        }
        $ids = array_values(array_unique(array_filter($this->settlementOutboxIds)));
        $this->settlementOutboxIds = [];
        $integration = new ErpIntegrationService();
        foreach ($ids as $id) {
            $integration->dispatchDomainEvent((int)$id);
        }
    }

    /** 供采购现结等外层事务在成功提交后派发结算完成事件，避免事件早于数据库提交。 */
    public function flushPendingSettlementDomainEvents(): void
    {
        $this->flushSettlementDomainEvents();
    }

    private function settleOffsetDifference(array $payableIds, array $receivableIds, float $payableRemain, float $receivableRemain, float $offsetAmount, array $data): void
    {
        $payableLeft = max(0, round($payableRemain - $offsetAmount, 2));
        $receivableLeft = max(0, round($receivableRemain - $offsetAmount, 2));
        if ($payableLeft > 0 && $receivableLeft > 0) {
            throw new CommonException('部分抵扣后双方仍有余额，不能一次结清差额');
        }
        if ($payableLeft <= 0 && $receivableLeft <= 0) {
            return;
        }
        if ((int)($data['capital_account_id'] ?? 0) <= 0) {
            throw new CommonException('结清差额需要选择收付款账户');
        }

        if ($payableLeft > 0) {
            $payables = $this->openPayables($payableIds, false);
            $items = [];
            foreach ($payables as $payable) {
                $remain = max(0, round((float)$payable['amount'] - (float)$payable['settled_amount'], 2));
                if ($remain > 0) {
                    $items[] = ['payable_id' => (int)$payable['id'], 'amount' => $remain];
                }
            }
            if (!empty($items)) {
                $this->confirmPayableItemsInTransaction((int)$payables[0]['party_id'], $items, array_merge($data, [
                    'request_id' => ErpIdempotency::child((string)($data['request_id'] ?? ''), 'difference-payment') ?: null,
                    'remark' => (string)($data['remark'] ?? '折账后差额付款'),
                ]));
            }
            return;
        }

        $receivables = $this->openReceivables($receivableIds, false);
        $items = [];
        foreach ($receivables as $receivable) {
            $remain = max(0, round((float)$receivable['amount'] - (float)$receivable['settled_amount'], 2));
            if ($remain > 0) {
                $items[] = ['receivable_id' => (int)$receivable['id'], 'amount' => $remain];
            }
        }
        if (!empty($items)) {
            $this->confirmReceivableItemsInTransaction((int)$receivables[0]['party_id'], $items, array_merge($data, [
                'request_id' => ErpIdempotency::child((string)($data['request_id'] ?? ''), 'difference-receipt') ?: null,
                'remark' => (string)($data['remark'] ?? '折账后差额收款'),
            ]));
        }
    }

    private function runIdempotentSettlement(array $data, string $settlementType, callable $callback, array $expected = []): int
    {
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $existingId = $this->existingSettlementId($requestId, $settlementType, $expected);
        if ($existingId > 0) {
            return $existingId;
        }
        $data['request_id'] = $requestId !== '' ? $requestId : null;
        try {
            return (int)$callback($data);
        } catch (\Throwable $e) {
            $existingId = $this->existingSettlementId($requestId, $settlementType, $expected);
            if ($existingId > 0) {
                return $existingId;
            }
            throw $e;
        }
    }

    private function existingSettlementId(string $requestId, string $settlementType, array $expected = []): int
    {
        if ($requestId === '') {
            return 0;
        }
        $settlement = ErpSettlement::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
        ])->findOrEmpty();
        if ($settlement->isEmpty()) {
            return 0;
        }
        if ((string)$settlement->settlement_type !== $settlementType) {
            throw new CommonException('request_id已用于其他结算业务');
        }
        if (isset($expected['amount']) && abs((float)$settlement->amount - (float)$expected['amount']) > 0.0001) {
            throw new CommonException('request_id已用于不同金额的结算请求');
        }
        if (!empty($expected['party_id']) && (int)$settlement->party_id !== (int)$expected['party_id']) {
            throw new CommonException('request_id已用于其他往来主体的结算请求');
        }
        $targetType = trim((string)($expected['target_type'] ?? ''));
        $targetIds = array_values(array_unique(array_filter(array_map('intval', (array)($expected['target_ids'] ?? [])))));
        if ($targetType !== '' && $targetIds !== []) {
            $existingTargetIds = ErpSettlementLink::where([
                ['site_id', '=', $this->site_id],
                ['settlement_id', '=', (int)$settlement->id],
                ['target_type', '=', $targetType],
            ])->order('target_id asc')->column('target_id');
            $existingTargetIds = array_values(array_unique(array_map('intval', $existingTargetIds)));
            sort($existingTargetIds);
            sort($targetIds);
            $targetMatch = (string)($expected['target_match'] ?? 'exact');
            $mismatch = $targetMatch === 'subset'
                ? ($existingTargetIds === [] || array_diff($existingTargetIds, $targetIds) !== [])
                : $existingTargetIds !== $targetIds;
            if ($mismatch) {
                throw new CommonException('request_id已用于其他应收应付明细的结算请求');
            }
        }
        if (!empty($expected['source_type']) || !empty($expected['batch_id']) || !empty($expected['batch_no'])) {
            $payableIds = ErpSettlementLink::where([
                ['site_id', '=', $this->site_id],
                ['settlement_id', '=', (int)$settlement->id],
                ['target_type', '=', ErpDict::TARGET_PAYABLE],
            ])->column('target_id');
            $payables = $payableIds === [] ? [] : ErpPayable::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', array_values(array_unique(array_map('intval', $payableIds))))->select();
            if ($payables === [] || (is_object($payables) && $payables->isEmpty())) {
                throw new CommonException('request_id对应的应付款核销明细不存在');
            }
            $this->assertSinglePayableBatch(
                $payables,
                trim((string)($expected['source_type'] ?? '')),
                (int)($expected['batch_id'] ?? 0),
                trim((string)($expected['batch_no'] ?? ''))
            );
        }
        return (int)$settlement->id;
    }

    private function existingOffsetId(string $requestId): int
    {
        $settlementId = $this->existingSettlementId($requestId, ErpDict::SETTLEMENT_OFFSET);
        if ($settlementId <= 0) {
            return 0;
        }
        $offset = ErpOffset::where([
            ['site_id', '=', $this->site_id],
            ['settlement_id', '=', $settlementId],
        ])->findOrEmpty();
        return $offset->isEmpty() ? 0 : (int)$offset->id;
    }

    private function assertOffsetReplayMatches(int $offsetId, array $payableIds, array $receivableIds, float $amount, bool $settleDiff = false): void
    {
        $offset = ErpOffset::where([['site_id', '=', $this->site_id], ['id', '=', $offsetId]])->findOrEmpty();
        if ($offset->isEmpty()) throw new CommonException('折账幂等记录不存在');
        if (abs((float)$offset->amount - $amount) > 0.0001 && (!$settleDiff || $amount + 0.0001 < (float)$offset->amount)) {
            throw new CommonException('request_id已用于不同金额的折账请求');
        }
        $expected = [
            ErpDict::TARGET_PAYABLE => array_values(array_unique(array_filter(array_map('intval', $payableIds)))),
            ErpDict::TARGET_RECEIVABLE => array_values(array_unique(array_filter(array_map('intval', $receivableIds)))),
        ];
        foreach ($expected as $targetType => $targetIds) {
            sort($targetIds);
            $existing = ErpOffsetLink::where([
                ['site_id', '=', $this->site_id],
                ['offset_id', '=', $offsetId],
                ['target_type', '=', $targetType],
            ])->column('target_id');
            $existing = array_values(array_unique(array_map('intval', $existing)));
            sort($existing);
            // 折账金额可能在一侧提前耗尽，未实际核销的已选明细不会生成 link；
            // 重放至少必须包含原请求已实际核销的全部目标。
            if ($existing === [] || array_diff($existing, $targetIds) !== []) {
                throw new CommonException('request_id已用于其他应收应付明细的折账请求');
            }
        }
    }

    /**
     * 在调用方事务中确认多笔应收到账。
     *
     * 供采购退货现场收款等领域服务复用同一套结算、账户余额和资金流水规则；
     * 调用方事务提交后必须调用 flushPendingSettlementDomainEvents() 派发事件。
     */
    public function confirmReceivableItemsInTransaction(int $partyId, array $items, array $data): int
    {
        if ($partyId <= 0) {
            throw new CommonException('请选择收款对象');
        }
        $applyMap = [];
        foreach ($items as $item) {
            $receivableId = (int)($item['receivable_id'] ?? 0);
            $amount = round((float)($item['amount'] ?? 0), 2);
            if ($receivableId <= 0 || $amount <= 0) {
                continue;
            }
            $applyMap[$receivableId] = round(($applyMap[$receivableId] ?? 0) + $amount, 2);
        }
        if (empty($applyMap)) {
            throw new CommonException('请选择要收款的账目');
        }

        $receivables = ErpReceivable::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', array_keys($applyMap))
            ->order('id asc')
            ->lock(true)
            ->select();
        if ($receivables->count() !== count($applyMap)) {
            throw new CommonException('应收款不存在或已变化');
        }

        $totalAmount = 0.0;
        $firstReceivable = null;
        foreach ($receivables as $receivable) {
            if ($firstReceivable === null) {
                $firstReceivable = $receivable;
            }
            if ((int)$receivable->party_id !== $partyId) {
                throw new CommonException('只能处理同一个客户的应收款');
            }
            if (!in_array((string)$receivable->status, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)) {
                throw new CommonException('只能收款待收款或部分收款的账目');
            }
            $amount = (float)$applyMap[(int)$receivable->id];
            $remain = round((float)$receivable->amount - (float)$receivable->settled_amount, 2);
            if ($amount > $remain + 0.0001) {
                throw new CommonException('收款金额不能大于剩余应收');
            }
            $totalAmount = round($totalAmount + $amount, 2);
        }
        if ($totalAmount <= 0) {
            throw new CommonException('收款金额必须大于0');
        }

        $account = $this->resolveAccount((int)($data['capital_account_id'] ?? 0));
        $settlement = $this->createSettlement($firstReceivable, ErpDict::SETTLEMENT_RECEIPT, $totalAmount, 'in', $account, $data);
        $settlementId = (int)$settlement->id;
        foreach ($receivables as $receivable) {
            $amount = (float)$applyMap[(int)$receivable->id];
            $this->applyReceivable($receivable, $amount, $settlementId);
            (new ErpLedgerService())->account([
                'biz_type' => 'receipt',
                'direction' => 'decrease',
                'amount' => $amount,
                'party_id' => (int)$receivable->party_id,
                'party_name' => (string)$receivable->party_name,
                'asset_id' => (int)($receivable->asset_id ?? 0),
                'source_type' => 'receivable',
                'source_id' => (int)$receivable->id,
                'source_no' => (string)$receivable->receivable_no,
                'remark' => (string)($data['remark'] ?? '财务确认收款'),
            ]);
            if ((string)$receivable->source_type === 'sale') {
                $this->refreshSaleFinance((int)$receivable->source_id);
            }
        }
        $balanceAfter = $this->adjustCapitalAccount((int)($account['id'] ?? 0), 'in', $totalAmount);
        (new ErpLedgerService())->money(array_merge([
            'settlement_id' => $settlementId,
            'capital_account_id' => (int)($account['id'] ?? 0),
            'capital_account_name' => (string)($account['name'] ?? ''),
            'direction' => 'in',
            'amount' => $totalAmount,
            'balance_after' => $balanceAfter,
            'party_id' => $partyId,
            'party_name' => (string)$firstReceivable->party_name,
            'voucher_urls' => $data['voucher_urls'] ?? '',
            'remark' => (string)($data['remark'] ?? '确认收款'),
        ], $this->combinedCategoryMeta($receivables)));
        $this->queueSettlementCompletedEvent($settlementId);
        return $settlementId;
    }

    private function consumeOffsetTargets(array $rows, string $targetType, float $amount, int $settlementId, int $offsetId): void
    {
        $left = $amount;
        foreach ($rows as $row) {
            if ($left <= 0) {
                break;
            }
            $remain = round((float)$row['amount'] - (float)$row['settled_amount'], 2);
            $apply = min($left, $remain);
            if ($apply <= 0) {
                continue;
            }
            if ($targetType === ErpDict::TARGET_PAYABLE) {
                $target = $this->findPayable((int)$row['id'], true);
                $this->applyPayable($target, $apply, $settlementId);
                $this->refreshPurchaseByPayable($target);
            } else {
                $target = $this->findReceivable((int)$row['id'], true);
                $this->applyReceivable($target, $apply, $settlementId);
                if ((string)$target->source_type === 'sale') {
                    $this->refreshSaleFinance((int)$target->source_id);
                }
            }
            ErpOffsetLink::create([
                'site_id' => $this->site_id,
                'offset_id' => $offsetId,
                'target_type' => $targetType,
                'target_id' => (int)$row['id'],
                'applied_amount' => $apply,
                'create_at' => time(),
            ]);
            $left = round($left - $apply, 2);
        }
    }

    private function findPayable(int $id, bool $forUpdate = false): ErpPayable
    {
        $query = ErpPayable::where([['site_id', '=', $this->site_id], ['id', '=', $id]]);
        if ($forUpdate) {
            $query->lock(true);
        }
        $row = $query->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('应付款不存在');
        }
        return $row;
    }

    private function findReceivable(int $id, bool $forUpdate = false): ErpReceivable
    {
        $query = ErpReceivable::where([['site_id', '=', $this->site_id], ['id', '=', $id]]);
        if ($forUpdate) {
            $query->lock(true);
        }
        $row = $query->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('应收款不存在');
        }
        return $row;
    }

    private function openPayables(array $ids, bool $strict = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) return [];
        $rows = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $ids)->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->order('id asc')->lock(true)->select()->toArray();
        if ($strict && count($rows) !== count($ids)) {
            throw new CommonException('部分应付款不存在、已结清或已变化，请刷新后重试');
        }
        return $rows;
    }

    private function openReceivables(array $ids, bool $strict = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) return [];
        $rows = ErpReceivable::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $ids)->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->order('id asc')->lock(true)->select()->toArray();
        if ($strict && count($rows) !== count($ids)) {
            throw new CommonException('部分应收款不存在、已结清或已变化，请刷新后重试');
        }
        return $rows;
    }

    private function resolveAccount(int $id): array
    {
        if ($id <= 0) {
            throw new CommonException('实际收付款必须选择资金账户');
        }
        $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id], ['status', '=', 1]])->findOrEmpty();
        if ($account->isEmpty()) {
            throw new CommonException('资金账户不存在或已停用');
        }
        return ['id' => (int)$account->id, 'name' => (string)$account->account_name];
    }

    private function adjustCapitalAccount(int $accountId, string $direction, float $amount): string
    {
        if ($accountId <= 0) {
            return '0.00';
        }
        $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $accountId], ['status', '=', 1]])->lock(true)->findOrEmpty();
        if ($account->isEmpty()) {
            throw new CommonException('资金账户不存在或已停用');
        }
        $balanceAfter = $direction === 'in'
            ? ErpMoney::add($account->balance, $amount)
            : ErpMoney::subtract($account->balance, $amount);
        $account->save([
            'balance' => $balanceAfter,
            'update_at' => time(),
        ]);
        return $balanceAfter;
    }

    private function refreshPurchaseFinance(int $purchaseId): void
    {
        if ($purchaseId <= 0) {
            return;
        }
        $order = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['id', '=', $purchaseId]])->findOrEmpty();
        if ($order->isEmpty()) {
            return;
        }
        // 采购退货会把对应设备应付置为 void。付款、折账之后必须继续按
        // “仍生效的设备应付”计算，不能再拿原采购单总额判断是否结清。
        $totals = $this->effectivePurchasePayableTotals($purchaseId);
        $total = $totals['amount'];
        $paid = $totals['settled_amount'];
        $order->save([
            'paid_amount' => round($paid, 2),
            'payable_amount' => max(0, round($total - $paid, 2)),
            'finance_status' => ErpDict::financeStatus($total, $paid),
            'update_at' => time(),
        ]);
    }

    /**
     * 获取采购单当前有效应付口径。
     *
     * 新数据按设备生成 purchase_asset；兼容历史数据时，仅在没有设备级应付的情况下
     * 回退 purchase 批次级应付，避免两套迁移数据被重复汇总。
     * 已采购退货/作废的应付不再计入金额和结算状态。
     *
     * @return array{amount: float, settled_amount: float}
     */
    private function effectivePurchasePayableTotals(int $purchaseId): array
    {
        if ($purchaseId <= 0) {
            return ['amount' => 0.0, 'settled_amount' => 0.0];
        }
        $assetIds = array_map('intval', ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['purchase_order_id', '=', $purchaseId],
        ])->column('id'));
        $rows = [];
        if ($assetIds !== []) {
            $rows = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase_asset'],
            ])->whereIn('source_id', $assetIds)
                ->where('status', '<>', ErpDict::STATUS_VOID)
                ->field('amount,settled_amount')->select()->toArray();
        }
        if ($rows === []) {
            $rows = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase'],
                ['source_id', '=', $purchaseId],
            ])->where('status', '<>', ErpDict::STATUS_VOID)
                ->field('amount,settled_amount')->select()->toArray();
        }
        return [
            'amount' => round(array_sum(array_map(static fn(array $row): float => (float)$row['amount'], $rows)), 2),
            'settled_amount' => round(array_sum(array_map(static fn(array $row): float => (float)$row['settled_amount'], $rows)), 2),
        ];
    }

    private function refreshPurchaseByPayable(ErpPayable $payable): void
    {
        $purchaseId = $this->purchaseIdFromPayable($payable);
        if ($purchaseId > 0) {
            $this->refreshPurchaseFinance($purchaseId);
        }
    }

    private function purchaseIdFromPayable(ErpPayable $payable): int
    {
        if ((string)$payable->source_type === 'purchase') {
            return (int)$payable->source_id;
        }
        if ((string)$payable->source_type !== 'purchase_asset') {
            return 0;
        }
        $asset = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$payable->source_id],
        ])->findOrEmpty();
        return $asset->isEmpty() ? 0 : (int)$asset->purchase_order_id;
    }

    private function assetIdFromPayable(ErpPayable $payable): int
    {
        $assetId = (int)($payable->asset_id ?? 0);
        if ($assetId > 0) {
            return $assetId;
        }
        return (string)$payable->source_type === 'purchase_asset' ? (int)$payable->source_id : 0;
    }

    private function refreshSaleFinance(int $saleId): void
    {
        if ($saleId <= 0) {
            return;
        }
        $order = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $saleId]])->findOrEmpty();
        if ($order->isEmpty()) {
            return;
        }
        $received = (float)ErpReceivable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'sale'],
            ['source_id', '=', $saleId],
        ])->sum('settled_amount');
        $total = (float)$order->total_amount;
        $order->save([
            'received_amount' => round($received, 2),
            'receivable_amount' => max(0, round($total - $received, 2)),
            'finance_status' => ErpDict::financeStatus($total, $received),
            'update_at' => time(),
        ]);
    }
}
