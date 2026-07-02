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
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\model\ErpSettlementLink;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpFinanceService extends BaseAdminService
{
    public function payablePage(array $where): array
    {
        return $this->payablePartyPage($where);
    }

    public function receivablePage(array $where): array
    {
        return $this->financePage(ErpReceivable::class, $where);
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
        });
        return $settlementId;
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
        return $page;
    }

    public function confirmOffset(array $payableIds, array $receivableIds, float $amount, string $remark = ''): int
    {
        if ($amount <= 0) {
            throw new CommonException('折账金额必须大于0');
        }
        $offsetId = 0;
        Db::transaction(function () use ($payableIds, $receivableIds, $amount, $remark, &$offsetId) {
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
            $payableRemain = array_sum(array_map(fn($r) => round((float)$r['amount'] - (float)$r['settled_amount'], 2), $payables));
            $receivableRemain = array_sum(array_map(fn($r) => round((float)$r['amount'] - (float)$r['settled_amount'], 2), $receivables));
            if ($amount > min($payableRemain, $receivableRemain) + 0.0001) {
                throw new CommonException('折账金额不能大于可折账金额');
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
        return $query->field([
            'p.party_id',
            'MAX(p.party_name) as party_name',
            'MAX(party.contact_name) as contact_name',
            'MAX(party.contact_mobile) as contact_mobile',
            'COUNT(p.id) as payable_count',
            'SUM(p.amount) as amount',
            'SUM(p.settled_amount) as settled_amount',
            'MAX(p.occurred_at) as latest_at',
            'MIN(p.occurred_at) as first_at',
        ])->group('p.party_id')->order('latest_at desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
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
