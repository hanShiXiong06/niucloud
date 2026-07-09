<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use addon\hsx_erp\app\model\ErpOffset;
use addon\hsx_erp\app\model\ErpOffsetLink;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpPurchaseReturnItem;
use addon\hsx_erp\app\model\ErpPurchaseReturnOrder;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\model\ErpSettlementLink;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpFinanceService extends BaseAdminService
{
    public function dashboard(array $where = []): array
    {
        $range = $this->dashboardRange($where);
        $purchaseQuery = ErpPurchaseOrder::where([['site_id', '=', $this->site_id]]);
        $saleQuery = ErpSaleOrder::where([['site_id', '=', $this->site_id]]);
        $settlementQuery = ErpSettlement::where([['site_id', '=', $this->site_id]]);
        $stockQuery = ErpAsset::where([['site_id', '=', $this->site_id]]);

        if ($range['start_at'] > 0) {
            $purchaseQuery->where('purchase_at', '>=', $range['start_at']);
            $saleQuery->where('sale_at', '>=', $range['start_at']);
            $settlementQuery->where('confirmed_at', '>=', $range['start_at']);
        }
        if ($range['end_at'] > 0) {
            $purchaseQuery->where('purchase_at', '<=', $range['end_at']);
            $saleQuery->where('sale_at', '<=', $range['end_at']);
            $settlementQuery->where('confirmed_at', '<=', $range['end_at']);
        }

        $purchaseAmount = (float)(clone $purchaseQuery)->where('status', '<>', 'void')->sum('total_cost');
        $saleAmount = (float)(clone $saleQuery)->where('status', '<>', 'void')->sum('total_amount');
        $profitAmount = (float)(clone $saleQuery)->where('status', '<>', 'void')->sum('profit');
        $receiptAmount = (float)(clone $settlementQuery)->where('settlement_type', '=', ErpDict::SETTLEMENT_RECEIPT)->sum('amount');
        $paymentAmount = (float)(clone $settlementQuery)->where('settlement_type', '=', ErpDict::SETTLEMENT_PAYMENT)->sum('amount');
        $offsetAmount = (float)(clone $settlementQuery)->where('settlement_type', '=', ErpDict::SETTLEMENT_OFFSET)->sum('amount');

        $payableBase = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL]);
        $receivableBase = ErpReceivable::where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL]);
        $payableRemain = (float)(clone $payableBase)->sum('amount') - (float)(clone $payableBase)->sum('settled_amount');
        $receivableRemain = (float)(clone $receivableBase)->sum('amount') - (float)(clone $receivableBase)->sum('settled_amount');

        return [
            'range' => $range,
            'summary' => [
                'purchase_count' => (int)(clone $purchaseQuery)->where('status', '<>', 'void')->count(),
                'purchase_amount' => round($purchaseAmount, 2),
                'sale_count' => (int)(clone $saleQuery)->where('status', '<>', 'void')->count(),
                'sale_amount' => round($saleAmount, 2),
                'profit_amount' => round($profitAmount, 2),
                'receipt_amount' => round($receiptAmount, 2),
                'payment_amount' => round($paymentAmount, 2),
                'offset_amount' => round($offsetAmount, 2),
                'payable_remain' => round(max(0, $payableRemain), 2),
                'receivable_remain' => round(max(0, $receivableRemain), 2),
                'stock_count' => (int)(clone $stockQuery)->whereIn('status', ['in_stock', 'pending_sale'])->count(),
                'stock_cost' => round((float)(clone $stockQuery)->whereIn('status', ['in_stock', 'pending_sale'])->sum('total_cost'), 2),
            ],
            'todo' => [
                'payable_count' => (int)ErpPayable::where([['site_id', '=', $this->site_id]])->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])->count(),
                'receivable_count' => (int)ErpReceivable::where([['site_id', '=', $this->site_id]])->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])->count(),
                'offset_party_count' => $this->offsetPartyCount(),
            ],
            'recent' => [
                'purchases' => (clone $purchaseQuery)->field('id,purchase_no,party_name,total_cost,finance_status,purchase_at')->order('purchase_at desc,id desc')->limit(5)->select()->toArray(),
                'sales' => (clone $saleQuery)->field('id,sale_no,party_name,total_amount,profit,finance_status,sale_at')->order('sale_at desc,id desc')->limit(5)->select()->toArray(),
                'settlements' => (clone $settlementQuery)->field('id,settlement_no,party_name,settlement_type,amount,cash_direction,capital_account_name,confirmed_at')->order('confirmed_at desc,id desc')->limit(6)->select()->toArray(),
            ],
        ];
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
            return ['items' => [], 'settlements' => $settlements];
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
            ])->field('id,sale_no,sale_channel,settle_method,salesman_name,total_amount,received_amount,receivable_amount,status,finance_status,remark,sale_at,create_at,update_at')->find();
            if ($order) {
                $row['source_order'] = $order->toArray();
                $row['batch_no'] = (string)($row['source_order']['sale_no'] ?? $receivable->source_no);
            }
        }

        if ((string)$receivable->source_type === 'purchase_return' && (int)$receivable->source_id > 0) {
            $order = ErpPurchaseReturnOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$receivable->source_id],
            ])->field('id,return_no,purchase_order_id,purchase_no,refund_mode,total_amount,status,remark,occurred_at,create_at,update_at')->find();
            if ($order) {
                $row['source_order'] = $order->toArray();
                $row['batch_no'] = (string)($row['source_order']['return_no'] ?? $receivable->source_no);
                $row['purchase_no'] = (string)($row['source_order']['purchase_no'] ?? '');
                $row['return_remark'] = (string)($row['source_order']['remark'] ?? '');
            }
        }

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
            $refundAmount = round((float)($item['paid_amount'] ?? $returnCost), 2);
            $allocated = round((float)($itemSettledMap[(int)$item['asset_id']] ?? 0) + $this->allocatedAmount($refundAmount, $totalAmount, $fallbackSettled), 2);
            $item['id'] = (int)$item['asset_id'];
            $item['sale_price'] = $refundAmount;
            $item['return_cost'] = $returnCost;
            $item['refund_amount'] = $refundAmount;
            $item['allocated_settled'] = $allocated;
            $item['allocated_remain'] = max(0, round($refundAmount - $allocated, 2));
            $item['source_label'] = '采购退货退款';
        }
        unset($item);

        return $items;
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
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function moneyLedgerPage(array $where): array
    {
        $query = ErpMoneyLedger::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('ledger_no|party_name|capital_account_name|remark', '%' . $kw . '%');
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
        if (!empty($ids)) {
            $linksBySettlement = $this->settlementTargetDetails(array_map('intval', $ids));
        }
        foreach ($page['data'] as &$row) {
            $row['settlement_type_text'] = self::settlementTypeText((string)$row['settlement_type']);
            $row['pay_method_text'] = $this->payMethodText($row);
            $row['links'] = $linksBySettlement[(int)$row['id']] ?? [];
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
        $settlementId = 0;
        Db::transaction(function () use ($payableId, $amount, $data, &$settlementId) {
            $settlementId = $this->confirmPaymentInTransaction($payableId, $amount, $data);
        });
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
        $settlementIds = [];
        Db::transaction(function () use ($partyId, $amount, $data, &$settlementIds) {
            $left = round($amount, 2);
            $payables = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['party_id', '=', $partyId],
            ])->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
                ->order('occurred_at asc,id asc')
                ->select()
                ->toArray();
            foreach ($payables as $row) {
                if ($left <= 0) {
                    break;
                }
                $remain = round((float)$row['amount'] - (float)$row['settled_amount'], 2);
                if ($remain <= 0) {
                    continue;
                }
                $apply = min($left, $remain);
                $settlementIds[] = $this->confirmPaymentInTransaction((int)$row['id'], $apply, $data);
                $left = round($left - $apply, 2);
            }
            if ($left > 0.0001) {
                throw new CommonException('付款金额不能大于该供应商剩余应付');
            }
        });
        return $settlementIds;
    }

    public function confirmPayableItemsPayment(int $partyId, array $items, array $data): int
    {
        $settlementId = 0;
        Db::transaction(function () use ($partyId, $items, $data, &$settlementId) {
            $settlementId = $this->confirmPayableItemsInTransaction($partyId, $items, $data);
        });
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
            throw new CommonException('请选择要付款的设备');
        }
        $payables = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', array_keys($applyMap))
            ->order('id asc')
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
                throw new CommonException('只能处理同一个供应商的应付款');
            }
            if (!in_array((string)$payable->status, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)) {
                throw new CommonException('只能付款待付款或部分付款的设备');
            }
            $amount = (float)$applyMap[(int)$payable->id];
            $remain = round((float)$payable->amount - (float)$payable->settled_amount, 2);
            if ($amount > $remain + 0.0001) {
                throw new CommonException('付款金额不能大于设备剩余应付');
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
        (new ErpLedgerService())->money([
            'settlement_id' => $settlementId,
            'capital_account_id' => (int)($account['id'] ?? 0),
            'capital_account_name' => (string)($account['name'] ?? ''),
            'direction' => 'out',
            'amount' => $totalAmount,
            'balance_after' => $balanceAfter,
            'party_id' => $partyId,
            'party_name' => (string)$firstPayable->party_name,
            'remark' => (string)($data['remark'] ?? '确认付款'),
        ]);
        foreach ($purchaseIds as $purchaseId) {
            $this->refreshPurchaseFinance($purchaseId);
        }
        return $settlementId;
    }

    public function confirmPaymentInTransaction(int $payableId, float $amount, array $data): int
    {
        if ($amount <= 0) {
            throw new CommonException('付款金额必须大于0');
        }
        $payable = $this->findPayable($payableId);
        $remain = round((float)$payable->amount - (float)$payable->settled_amount, 2);
        if ($amount > $remain + 0.0001) {
            throw new CommonException('付款金额不能大于剩余应付');
        }
        $account = $this->resolveAccount((int)($data['capital_account_id'] ?? 0));
        $settlement = $this->createSettlement($payable, ErpDict::SETTLEMENT_PAYMENT, $amount, 'out', $account, $data);
        $settlementId = (int)$settlement->id;
        $this->applyPayable($payable, $amount, $settlementId);
        $balanceAfter = $this->adjustCapitalAccount((int)($account['id'] ?? 0), 'out', $amount);
        (new ErpLedgerService())->money([
            'settlement_id' => $settlementId,
            'capital_account_id' => (int)($account['id'] ?? 0),
            'capital_account_name' => (string)($account['name'] ?? ''),
            'direction' => 'out',
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'party_id' => (int)$payable->party_id,
            'party_name' => (string)$payable->party_name,
            'remark' => (string)($data['remark'] ?? '确认付款'),
        ]);
        (new ErpLedgerService())->account([
            'biz_type' => 'payment',
            'direction' => 'decrease',
            'amount' => $amount,
            'party_id' => (int)$payable->party_id,
            'party_name' => (string)$payable->party_name,
            'source_type' => 'payable',
            'source_id' => (int)$payable->id,
            'source_no' => (string)$payable->payable_no,
            'remark' => (string)($data['remark'] ?? '财务确认付款'),
        ]);
        $this->refreshPurchaseByPayable($payable);
        return $settlementId;
    }

    public function confirmReceipt(int $receivableId, float $amount, array $data): int
    {
        if ($amount <= 0) {
            throw new CommonException('收款金额必须大于0');
        }
        $settlementId = 0;
        Db::transaction(function () use ($receivableId, $amount, $data, &$settlementId) {
            $receivable = $this->findReceivable($receivableId);
            $receiptApply = $this->applyReceiptItems($receivable, (array)($data['items'] ?? []), $amount);
            $amount = (float)$receiptApply['amount'];
            $receiptItems = (array)$receiptApply['items'];
            $remain = round((float)$receivable->amount - (float)$receivable->settled_amount, 2);
            if ($amount > $remain + 0.0001) {
                throw new CommonException('收款金额不能大于剩余应收');
            }
            $account = $this->resolveAccount((int)($data['capital_account_id'] ?? 0));
            $settlement = $this->createSettlement($receivable, ErpDict::SETTLEMENT_RECEIPT, $amount, 'in', $account, $data);
            $settlementId = (int)$settlement->id;
            $this->applyReceivable($receivable, $amount, $settlementId);
            $balanceAfter = $this->adjustCapitalAccount((int)($account['id'] ?? 0), 'in', $amount);
            (new ErpLedgerService())->money([
                'settlement_id' => $settlementId,
                'capital_account_id' => (int)($account['id'] ?? 0),
                'capital_account_name' => (string)($account['name'] ?? ''),
                'direction' => 'in',
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'party_id' => (int)$receivable->party_id,
                'party_name' => (string)$receivable->party_name,
                'remark' => (string)($data['remark'] ?? '确认收款'),
            ]);
            $this->writeReceiptAccountLedgers($receivable, $amount, $receiptItems, (string)($data['remark'] ?? '财务确认收款'));
            if ((string)$receivable->source_type === 'sale') {
                $this->refreshSaleFinance((int)$receivable->source_id);
            }
        });
        return $settlementId;
    }

    private function applyReceiptItems(ErpReceivable $receivable, array $items, float $amount): array
    {
        if ((string)$receivable->source_type === 'purchase_return') {
            return $this->applyReceiptPurchaseReturnItems($receivable, $items, $amount);
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
            $refundAmount = round((float)$returnItem->paid_amount, 2);
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
        (new ErpWarehouseService())->ensureReady();
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
        $this->applyFinanceFilters($query, $where, 'a', 'o', 'p');
        $page = $query->field([
            'a.id',
            'a.asset_no',
            'a.imei',
            'a.sn',
            'a.model',
            'a.spec',
            'a.total_cost',
            'a.status',
            'a.warehouse_name',
            'a.location_name',
            'o.purchase_no',
            'o.purchase_at',
            'o.total_cost as order_total_cost',
            'IFNULL(p.id, po.id) as payable_id',
            'IFNULL(p.amount, a.total_cost) as payable_amount',
            'IFNULL(p.settled_amount, po.settled_amount) as settled_amount',
            'IFNULL(p.status, po.status) as payable_status',
            'p.id as asset_payable_id',
            'po.id as order_payable_id',
        ])->order('a.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            if (!empty($row['asset_payable_id'])) {
                $row['allocated_paid'] = round((float)$row['settled_amount'], 2);
                $row['allocated_remain'] = max(0, round((float)$row['payable_amount'] - (float)$row['allocated_paid'], 2));
            } else {
                $row['allocated_paid'] = $this->allocatedAmount((float)$row['total_cost'], (float)$row['order_total_cost'], (float)$row['settled_amount']);
                $row['allocated_remain'] = max(0, round((float)$row['total_cost'] - (float)$row['allocated_paid'], 2));
            }
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

    public function confirmOffset(array $payableIds, array $receivableIds, float $amount, string $remark = '', array $data = []): int
    {
        if ($amount <= 0) {
            throw new CommonException('折账金额必须大于0');
        }
        $offsetId = 0;
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
        });
        return $offsetId;
    }

    private function receivableBatchPage(array $where): array
    {
        $partyTable = (new ErpParty())->getTable();
        $orderTable = (new ErpSaleOrder())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $query = ErpReceivable::alias('r')
            ->leftJoin($partyTable . ' party', 'party.id = r.party_id AND party.site_id = r.site_id')
            ->leftJoin($orderTable . ' o', "r.source_type = 'sale' AND o.id = r.source_id AND o.site_id = r.site_id")
            ->where([['r.site_id', '=', $this->site_id]]);

        if (!empty($where['status'])) {
            $query->where('r.status', '=', (string)$where['status']);
        } else {
            $query->where('r.status', '<>', ErpDict::STATUS_VOID);
        }
        if (!empty($where['party_id'])) {
            $query->where('r.party_id', '=', (int)$where['party_id']);
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
            $query->whereLike('o.salesman_name', '%' . trim((string)$where['salesman_name']) . '%');
        }
        if (!empty($where['salesman_uid'])) {
            $query->where('o.salesman_uid', '=', (int)$where['salesman_uid']);
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
            'r.amount',
            'r.settled_amount',
            'r.status',
            'r.occurred_at',
            'r.remark',
            'o.sale_no',
            'o.sale_channel',
            'o.settle_method',
            'o.salesman_uid',
            'o.salesman_name',
            'o.sale_at',
            'o.total_amount as sale_total_amount',
            'o.received_amount',
            'o.receivable_amount',
            'o.finance_status',
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
            $row['batch_no'] = $row['source_no'] ?: ($row['sale_no'] ?? '');
            $row['source_label'] = $this->receivableSourceLabel((string)$row['source_type']);
            if ((string)$row['source_type'] === 'purchase_return') {
                $returnRow = $returnMap[(int)$row['source_id']] ?? [];
                $row['batch_no'] = (string)($returnRow['return_no'] ?? $row['source_no'] ?? '');
                $row['purchase_no'] = (string)($returnRow['purchase_no'] ?? '');
                $row['item_count'] = $itemCountMap['purchase_return_' . (int)$row['source_id']] ?? 0;
                $row['opening_settle_method'] = '采购退货退款';
                $row['return_remark'] = (string)($returnRow['remark'] ?? '');
            } else {
                $row['item_count'] = $itemCountMap[(int)$row['source_id']] ?? 0;
                $row['opening_settle_method'] = (string)($row['settle_method'] ?? '');
            }
            $row['remain_amount'] = max(0, round((float)$row['amount'] - (float)$row['settled_amount'], 2));
        }
        unset($row);
        $summaryMap = $this->settlementSummaryForTargets(ErpDict::TARGET_RECEIVABLE, array_column($page['data'], 'id'));
        foreach ($page['data'] as &$row) {
            $summary = $summaryMap[(int)$row['id']] ?? [];
            $row['settle_summary'] = $summary['text'] ?? $this->emptySettleSummary((float)$row['settled_amount']);
            $row['settle_summary_items'] = $summary['items'] ?? [];
        }
        unset($row);
        $this->fillOffsetState($page['data'], 'receivable');
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
        (new ErpWarehouseService())->ensureReady();
        $partyTable = (new ErpParty())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $orderTable = (new ErpPurchaseOrder())->getTable();
        $batchExpr = "IF(p.source_type = 'purchase_asset', a.purchase_order_id, p.source_id)";
        $query = ErpPayable::alias('p')
            ->leftJoin($partyTable . ' party', 'party.id = p.party_id AND party.site_id = p.site_id')
            ->leftJoin($assetTable . ' a', "p.source_type = 'purchase_asset' AND a.id = p.source_id AND a.site_id = p.site_id")
            ->leftJoin($orderTable . ' o', 'o.id = ' . $batchExpr . ' AND o.site_id = p.site_id')
            ->where([['p.site_id', '=', $this->site_id]]);

        if (!empty($where['status'])) {
            $query->where('p.status', '=', (string)$where['status']);
        } else {
            $query->where('p.status', '<>', ErpDict::STATUS_VOID);
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
            $query->whereLike('p.payable_no|p.party_name|p.source_no|p.remark|party.contact_name|party.contact_mobile|party.m_no|a.asset_no|a.imei|a.sn|a.model|a.spec|o.purchase_no|o.purchase_channel|o.purchaser_name', '%' . $kw . '%');
        }

        $page = $query->field([
            'p.party_id',
            $batchExpr . ' as purchase_order_id',
            'MAX(p.party_name) as party_name',
            'MAX(party.contact_name) as contact_name',
            'MAX(party.contact_mobile) as contact_mobile',
            'MAX(party.m_no) as m_no',
            'MAX(o.purchase_no) as purchase_no',
            'MAX(o.purchase_channel) as purchase_channel',
            'MAX(o.settle_method) as settle_method',
            'MAX(o.purchaser_name) as purchaser_name',
            'MAX(o.purchase_at) as purchase_at',
            'MAX(o.warehouse_name) as warehouse_name',
            'MAX(o.location_name) as location_name',
            'COUNT(p.id) as payable_count',
            'SUM(p.amount) as amount',
            'SUM(p.settled_amount) as settled_amount',
            'MAX(p.occurred_at) as latest_at',
            'MIN(p.occurred_at) as first_at',
        ])->group('p.party_id,' . $batchExpr)->order('latest_at desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();

        foreach ($page['data'] as &$row) {
            $row['batch_no'] = $row['purchase_no'] ?: ('采购批次#' . (int)$row['purchase_order_id']);
            $row['remain_amount'] = max(0, round((float)$row['amount'] - (float)$row['settled_amount'], 2));
            $row['finance_status'] = ErpDict::financeStatus((float)$row['amount'], (float)$row['settled_amount']);
            $row['opening_settle_method'] = (string)($row['settle_method'] ?? '');
        }
        unset($row);
        $this->fillPayableBatchSettleSummary($page['data']);
        $this->fillOffsetState($page['data'], 'payable');
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
            ->field('l.target_id,l.applied_amount,s.settlement_type,s.capital_account_name')
            ->order('s.confirmed_at asc,l.id asc')
            ->select()
            ->toArray();

        $itemsByTarget = [];
        foreach ($rows as $row) {
            $targetId = (int)$row['target_id'];
            $label = $this->settleSummaryLabel((string)($row['settlement_type'] ?? ''), (string)($row['capital_account_name'] ?? ''));
            if (!isset($itemsByTarget[$targetId][$label])) {
                $itemsByTarget[$targetId][$label] = ['label' => $label, 'amount' => 0.0];
            }
            $itemsByTarget[$targetId][$label]['amount'] = round((float)$itemsByTarget[$targetId][$label]['amount'] + (float)$row['applied_amount'], 2);
        }

        $map = [];
        foreach ($itemsByTarget as $targetId => $items) {
            $items = array_values($items);
            $map[$targetId] = [
                'text' => $this->formatSettleSummary($items),
                'items' => $items,
            ];
        }
        return $map;
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
        (new ErpWarehouseService())->ensureReady();
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
            ->field('settlement_id,ledger_no,direction,amount,capital_account_name,balance_after,occurred_at,remark')
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
            ->field('settlement_id,ledger_no,direction,amount,capital_account_name,balance_after,occurred_at,remark')
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
                ->field('id,payable_no,party_name,source_type,source_id,source_no,amount,settled_amount,status')
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
                ->field('id,receivable_no,party_name,source_type,source_id,source_no,amount,settled_amount,status')
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
                'devices' => $targetDeviceMap[$targetType . '_' . $targetId] ?? [],
            ];
        }
        return $map;
    }

    private function settlementTargetDeviceMap(array $payableMap, array $receivableMap): array
    {
        $payableAssetIds = [];
        $payablePurchaseIds = [];
        foreach ($payableMap as $id => $row) {
            if ((string)($row['source_type'] ?? '') === 'purchase_asset') {
                $payableAssetIds[(int)$row['source_id']][] = (int)$id;
            } elseif ((string)($row['source_type'] ?? '') === 'purchase') {
                $payablePurchaseIds[(int)$row['source_id']][] = (int)$id;
            }
        }

        $receivableSaleIds = [];
        foreach ($receivableMap as $id => $row) {
            if ((string)($row['source_type'] ?? '') === 'sale') {
                $receivableSaleIds[(int)$row['source_id']][] = (int)$id;
            }
        }

        $map = [];
        if (!empty($payableAssetIds) || !empty($payablePurchaseIds)) {
            $query = ErpAsset::where([['site_id', '=', $this->site_id]]);
            $query->where(function ($q) use ($payableAssetIds, $payablePurchaseIds) {
                if (!empty($payableAssetIds)) {
                    $q->whereIn('id', array_keys($payableAssetIds));
                }
                if (!empty($payablePurchaseIds)) {
                    !empty($payableAssetIds) ? $q->whereOr('purchase_order_id', 'in', array_keys($payablePurchaseIds)) : $q->whereIn('purchase_order_id', array_keys($payablePurchaseIds));
                }
            });
            $rows = $query->field('id,asset_no,imei,sn,model,spec,total_cost,sale_price,profit,warehouse_name,location_name,purchase_order_id')->select()->toArray();
            foreach ($rows as $row) {
                $device = $this->formatSettlementDevice($row, 'purchase');
                foreach ($payableAssetIds[(int)$row['id']] ?? [] as $payableId) {
                    $map[ErpDict::TARGET_PAYABLE . '_' . $payableId][] = $device;
                }
                foreach ($payablePurchaseIds[(int)$row['purchase_order_id']] ?? [] as $payableId) {
                    $map[ErpDict::TARGET_PAYABLE . '_' . $payableId][] = $device;
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
                    'i.imei',
                    'i.model',
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

        return $map;
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
        return ErpSettlement::create([
            'site_id' => $this->site_id,
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
            'confirmed_at' => (int)($data['confirmed_at'] ?? time()),
            'remark' => (string)($data['remark'] ?? ''),
            'create_at' => time(),
        ]);
    }

    private function applyPayable(ErpPayable $payable, float $amount, int $settlementId): void
    {
        $newSettled = round((float)$payable->settled_amount + $amount, 2);
        $payable->save([
            'settled_amount' => $newSettled,
            'status' => ErpDict::financeStatus((float)$payable->amount, $newSettled),
            'update_at' => time(),
        ]);
        ErpSettlementLink::create([
            'site_id' => $this->site_id,
            'settlement_id' => $settlementId,
            'target_type' => ErpDict::TARGET_PAYABLE,
            'target_id' => (int)$payable->id,
            'applied_amount' => $amount,
            'create_at' => time(),
        ]);
    }

    private function applyReceivable(ErpReceivable $receivable, float $amount, int $settlementId): void
    {
        $newSettled = round((float)$receivable->settled_amount + $amount, 2);
        $receivable->save([
            'settled_amount' => $newSettled,
            'status' => ErpDict::financeStatus((float)$receivable->amount, $newSettled),
            'update_at' => time(),
        ]);
        ErpSettlementLink::create([
            'site_id' => $this->site_id,
            'settlement_id' => $settlementId,
            'target_type' => ErpDict::TARGET_RECEIVABLE,
            'target_id' => (int)$receivable->id,
            'applied_amount' => $amount,
            'create_at' => time(),
        ]);
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
            $payables = $this->openPayables($payableIds);
            $items = [];
            foreach ($payables as $payable) {
                $remain = max(0, round((float)$payable['amount'] - (float)$payable['settled_amount'], 2));
                if ($remain > 0) {
                    $items[] = ['payable_id' => (int)$payable['id'], 'amount' => $remain];
                }
            }
            if (!empty($items)) {
                $this->confirmPayableItemsInTransaction((int)$payables[0]['party_id'], $items, array_merge($data, [
                    'remark' => (string)($data['remark'] ?? '折账后差额付款'),
                ]));
            }
            return;
        }

        $receivables = $this->openReceivables($receivableIds);
        $items = [];
        foreach ($receivables as $receivable) {
            $remain = max(0, round((float)$receivable['amount'] - (float)$receivable['settled_amount'], 2));
            if ($remain > 0) {
                $items[] = ['receivable_id' => (int)$receivable['id'], 'amount' => $remain];
            }
        }
        if (!empty($items)) {
            $this->confirmReceivableItemsInTransaction((int)$receivables[0]['party_id'], $items, array_merge($data, [
                'remark' => (string)($data['remark'] ?? '折账后差额收款'),
            ]));
        }
    }

    private function confirmReceivableItemsInTransaction(int $partyId, array $items, array $data): int
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
                'source_type' => 'receivable',
                'source_id' => (int)$receivable->id,
                'source_no' => (string)$receivable->receivable_no,
                'remark' => (string)($data['remark'] ?? '财务确认收款'),
            ]);
            $this->refreshSaleFinance((int)$receivable->source_id);
        }
        $balanceAfter = $this->adjustCapitalAccount((int)($account['id'] ?? 0), 'in', $totalAmount);
        (new ErpLedgerService())->money([
            'settlement_id' => $settlementId,
            'capital_account_id' => (int)($account['id'] ?? 0),
            'capital_account_name' => (string)($account['name'] ?? ''),
            'direction' => 'in',
            'amount' => $totalAmount,
            'balance_after' => $balanceAfter,
            'party_id' => $partyId,
            'party_name' => (string)$firstReceivable->party_name,
            'remark' => (string)($data['remark'] ?? '确认收款'),
        ]);
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
                $target = $this->findPayable((int)$row['id']);
                $this->applyPayable($target, $apply, $settlementId);
                $this->refreshPurchaseByPayable($target);
            } else {
                $target = $this->findReceivable((int)$row['id']);
                $this->applyReceivable($target, $apply, $settlementId);
                $this->refreshSaleFinance((int)$target->source_id);
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

    private function findPayable(int $id): ErpPayable
    {
        $row = ErpPayable::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('应付款不存在');
        }
        return $row;
    }

    private function findReceivable(int $id): ErpReceivable
    {
        $row = ErpReceivable::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('应收款不存在');
        }
        return $row;
    }

    private function openPayables(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        return empty($ids) ? [] : ErpPayable::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $ids)->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->order('id asc')->select()->toArray();
    }

    private function openReceivables(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        return empty($ids) ? [] : ErpReceivable::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $ids)->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->order('id asc')->select()->toArray();
    }

    private function resolveAccount(int $id): array
    {
        if ($id <= 0) {
            return ['id' => 0, 'name' => '未指定账户'];
        }
        $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($account->isEmpty()) {
            throw new CommonException('资金账户不存在');
        }
        return ['id' => (int)$account->id, 'name' => (string)$account->account_name];
    }

    private function adjustCapitalAccount(int $accountId, string $direction, float $amount): float
    {
        if ($accountId <= 0) {
            return 0.0;
        }
        $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $accountId]])->findOrEmpty();
        if ($account->isEmpty()) {
            throw new CommonException('资金账户不存在');
        }
        $delta = $direction === 'in' ? $amount : -$amount;
        $balanceAfter = round((float)$account->balance + $delta, 2);
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
        $assetIds = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['purchase_order_id', '=', $purchaseId],
        ])->column('id');
        $assetPaid = 0.0;
        if (!empty($assetIds)) {
            $assetPaid = (float)ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase_asset'],
            ])->whereIn('source_id', $assetIds)->sum('settled_amount');
        }
        $orderPaid = (float)ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'purchase'],
            ['source_id', '=', $purchaseId],
        ])->sum('settled_amount');
        $paid = $assetPaid > 0 ? $assetPaid : $orderPaid;
        $total = (float)$order->total_cost;
        $order->save([
            'paid_amount' => round($paid, 2),
            'payable_amount' => max(0, round($total - $paid, 2)),
            'finance_status' => ErpDict::financeStatus($total, $paid),
            'update_at' => time(),
        ]);
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
