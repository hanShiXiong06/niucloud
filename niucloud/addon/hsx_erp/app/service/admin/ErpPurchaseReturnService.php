<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpPurchaseReturnOrder;
use addon\hsx_erp\app\model\ErpPurchaseReturnItem;
use addon\hsx_erp\app\model\ErpReceivable;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 采购退货服务
 *
 * 设计原则：
 *  - create()：业务人员登记退货意向，仅落单、不改资产状态，等财务确认。
 *  - confirm()：财务确认，实际回退资产、处理应付/应收三分支。
 *  - 三分支：① 未付款→冲销应付；② 已付款→生成应收退款；③ 部分付款→混合。
 *  - 错账不涂改：已形成资金事实的部分通过应收退款走逆向流水。
 */
class ErpPurchaseReturnService extends BaseAdminService
{
    // ─── 公开接口 ────────────────────────────────────────────────────────────

    /**
     * 创建采购退货单（业务人员操作）
     * 仅登记意向，不改资产状态，status=pending
     */
    public function create(array $data): int
    {
        $purchaseOrderId = (int)($data['purchase_order_id'] ?? 0);
        if ($purchaseOrderId <= 0) {
            throw new CommonException('请选择原采购单');
        }
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请至少选择一台退货设备');
        }

        $returnId = 0;
        Db::transaction(function () use ($data, $purchaseOrderId, $items, &$returnId) {
            $now  = time();
            $order = $this->findPurchaseOrder($purchaseOrderId);

            $totalAmount = 0.0;
            $itemsData   = [];
            foreach ($items as $item) {
                $assetId = (int)($item['asset_id'] ?? 0);
                if ($assetId <= 0) {
                    throw new CommonException('退货设备ID不能为空');
                }
                $asset = $this->findAssetInOrder($assetId, $purchaseOrderId);
                if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
                    throw new CommonException('设备【' . (string)$asset->imei . '】不在库存中，无法退货');
                }
                // 禁止重复建待确认退货单
                $this->assertNoPendingReturn($assetId);

                $returnCost = round((float)($item['return_cost'] ?? (float)$asset->total_cost), 2);
                if ($returnCost < 0) {
                    throw new CommonException('退货成本不能小于0');
                }

                // 快照已付金额，供确认时决定分支
                $payable    = $this->findAssetPayable($assetId);
                $paidAmount = $payable ? round((float)$payable->settled_amount, 2) : 0.0;

                $totalAmount  = round($totalAmount + $returnCost, 2);
                $itemsData[] = [
                    'asset'       => $asset,
                    'return_cost' => $returnCost,
                    'paid_amount' => $paidAmount,
                    'reason'      => trim((string)($item['reason'] ?? '')),
                ];
            }

            $returnNo = ErpLedgerService::makeNo('PR');
            $return   = ErpPurchaseReturnOrder::create([
                'site_id'            => $this->site_id,
                'return_no'          => $returnNo,
                'purchase_order_id'  => $purchaseOrderId,
                'purchase_no'        => (string)$order->purchase_no,
                'party_id'           => (int)$order->party_id,
                'party_name'         => (string)$order->party_name,
                'operator_id'        => (int)$this->uid,
                'operator_name'      => (string)$this->username,
                'total_amount'       => $totalAmount,
                'settled_amount'     => 0,
                'status'             => 'pending',
                'refund_mode'        => trim((string)($data['refund_mode'] ?? 'cash')),
                'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
                'remark'             => trim((string)($data['remark'] ?? '')),
                'occurred_at'        => $now,
                'create_at'          => $now,
                'update_at'          => $now,
            ]);
            $returnId = (int)$return->id;

            foreach ($itemsData as $row) {
                /** @var ErpAsset $asset */
                $asset = $row['asset'];
                ErpPurchaseReturnItem::create([
                    'site_id'          => $this->site_id,
                    'return_id'        => $returnId,
                    'asset_id'         => (int)$asset->id,
                    'asset_no'         => (string)$asset->asset_no,
                    'imei'             => (string)$asset->imei,
                    'model'            => (string)$asset->model,
                    'purchase_item_id' => (int)$asset->purchase_item_id,
                    'return_cost'      => $row['return_cost'],
                    'paid_amount'      => $row['paid_amount'],
                    'reason'           => $row['reason'],
                    'create_at'        => $now,
                ]);
            }
        });

        return $returnId;
    }

    /**
     * 财务确认退货（三分支处理，实际改变资产与财务状态）
     */
    public function confirm(int $returnId, array $data = []): bool
    {
        Db::transaction(function () use ($returnId, $data) {
            $now    = time();
            $return = $this->findReturn($returnId);

            if ((string)$return->status !== 'pending') {
                throw new CommonException('只有待确认的退货单可以确认');
            }

            $items = ErpPurchaseReturnItem::where([
                ['site_id', '=', $this->site_id],
                ['return_id', '=', $returnId],
            ])->order('id asc')->select();

            if ($items->isEmpty()) {
                throw new CommonException('退货单明细为空');
            }

            $remark = trim((string)($data['remark'] ?? '采购退货'));
            if ($remark === '') {
                $remark = '采购退货';
            }

            $ledger = new ErpLedgerService();

            foreach ($items as $item) {
                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id',      '=', (int)$item->asset_id],
                ])->findOrEmpty();

                if ($asset->isEmpty()) {
                    throw new CommonException('退货设备不存在，asset_id=' . $item->asset_id);
                }
                if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
                    throw new CommonException('设备【' . (string)$asset->imei . '】已不在库存中，请检查退货单');
                }

                $payable       = $this->findAssetPayable((int)$asset->id);
                $payableAmount = $payable ? round((float)$payable->amount, 2) : 0.0;
                $paidAmount    = $payable ? round((float)$payable->settled_amount, 2) : 0.0;

                // ── 三分支退款逻辑 ─────────────────────────────────────────
                if ($paidAmount <= 0) {
                    // 分支①：全部未付 → 直接作废应付，无现金流
                    $this->voidPayable($payable);

                } elseif ($paidAmount >= $payableAmount - 0.0001) {
                    // 分支②：全部已付 → 生成"应收退款"（供应商欠我们）
                    $this->createReturnReceivable($return, $item, $paidAmount, $remark, $now);

                } else {
                    // 分支③：部分付款 → 作废未付部分 + 生成已付部分的应收
                    $unpaid = round($payableAmount - $paidAmount, 2);
                    $this->partialVoidPayable($payable, $unpaid);
                    $this->createReturnReceivable($return, $item, $paidAmount, $remark, $now);
                }

                // ── 资产状态 → 已退回 ─────────────────────────────────────
                $beforeStatus = (string)$asset->status;
                $totalCost    = round((float)$asset->total_cost, 2);
                $asset->save([
                    'status'    => ErpDict::ASSET_RETURNED,
                    'update_at' => $now,
                ]);

                // ── 库存流水 ──────────────────────────────────────────────
                $ledger->asset([
                    'asset_id'             => (int)$asset->id,
                    'action'               => 'purchase_return',
                    'before_status'        => $beforeStatus,
                    'after_status'         => ErpDict::ASSET_RETURNED,
                    'before_warehouse_id'  => (int)$asset->warehouse_id,
                    'before_warehouse_name'=> (string)$asset->warehouse_name,
                    'before_location_id'   => (int)$asset->location_id,
                    'before_location_name' => (string)$asset->location_name,
                    'before_total_cost'    => $totalCost,
                    'after_total_cost'     => 0.0,
                    'cost_delta'           => -$totalCost,
                    'party_id'             => (int)$return->party_id,
                    'party_name'           => (string)$return->party_name,
                    'source_type'          => 'purchase_return',
                    'source_id'            => $returnId,
                    'source_no'            => (string)$return->return_no,
                    'occurred_at'          => $now,
                    'remark'               => $remark,
                ]);

                // ── 账目流水（成本减少）────────────────────────────────────
                $ledger->account([
                    'biz_type'    => 'purchase_return',
                    'direction'   => 'decrease',
                    'amount'      => $totalCost,
                    'party_id'    => (int)$return->party_id,
                    'party_name'  => (string)$return->party_name,
                    'asset_id'    => (int)$asset->id,
                    'source_type' => 'purchase_return',
                    'source_id'   => $returnId,
                    'source_no'   => (string)$return->return_no,
                    'remark'      => $remark,
                ]);
            }

            // ── 刷新采购单财务状态（排除已作废的应付）────────────────────
            $this->refreshPurchaseOrderAfterReturn((int)$return->purchase_order_id);

            // ── 更新退货单状态 ────────────────────────────────────────────
            $return->save([
                'status'    => 'confirmed',
                'update_at' => $now,
            ]);
        });

        return true;
    }

    /**
     * 撤销退货单（仅 pending 状态可撤销，不改任何财务状态）
     */
    public function cancel(int $returnId, string $remark = ''): bool
    {
        $return = $this->findReturn($returnId);
        if ((string)$return->status !== 'pending') {
            throw new CommonException('只有待确认的退货单可以撤销');
        }
        $return->save([
            'status'    => 'cancelled',
            'remark'    => $remark !== '' ? ((string)$return->remark . ' / ' . $remark) : (string)$return->remark,
            'update_at' => time(),
        ]);
        return true;
    }

    /**
     * 退货单列表（分页）
     */
    public function lists(array $where): array
    {
        $query = ErpPurchaseReturnOrder::where([['site_id', '=', $this->site_id]]);

        if (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }
        if (!empty($where['party_id'])) {
            $query->where('party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('return_no|purchase_no|party_name|remark', '%' . $kw . '%');
        }
        if (!empty($where['start_at'])) {
            $query->where('occurred_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('occurred_at', '<=', (int)$where['end_at']);
        }

        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page'      => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    /**
     * 退货单详情（含明细）
     */
    public function info(int $returnId): array
    {
        $return        = $this->findReturn($returnId)->toArray();
        $return['items'] = ErpPurchaseReturnItem::where([
            ['site_id',   '=', $this->site_id],
            ['return_id', '=', $returnId],
        ])->order('id asc')->select()->toArray();
        return $return;
    }

    // ─── 私有辅助 ────────────────────────────────────────────────────────────

    private function findReturn(int $id): ErpPurchaseReturnOrder
    {
        $row = ErpPurchaseReturnOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $id],
        ])->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('采购退货单不存在');
        }
        return $row;
    }

    private function findPurchaseOrder(int $id): ErpPurchaseOrder
    {
        $order = ErpPurchaseOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $id],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('采购单不存在');
        }
        return $order;
    }

    private function findAssetInOrder(int $assetId, int $purchaseOrderId): ErpAsset
    {
        $asset = ErpAsset::where([
            ['site_id',           '=', $this->site_id],
            ['id',                '=', $assetId],
            ['purchase_order_id', '=', $purchaseOrderId],
        ])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('设备不存在或不属于该采购单，asset_id=' . $assetId);
        }
        return $asset;
    }

    /** 查找按台生成的应付（purchase_asset 类型） */
    private function findAssetPayable(int $assetId): ?ErpPayable
    {
        $payable = ErpPayable::where([
            ['site_id',      '=', $this->site_id],
            ['source_type',  '=', 'purchase_asset'],
            ['source_id',    '=', $assetId],
        ])->findOrEmpty();
        return $payable->isEmpty() ? null : $payable;
    }

    /** 断言：该设备没有尚未确认的退货单，防止重复提交 */
    private function assertNoPendingReturn(int $assetId): void
    {
        $returnTable = (new ErpPurchaseReturnOrder())->getTable();
        $exists = ErpPurchaseReturnItem::alias('ri')
            ->leftJoin($returnTable . ' r', 'r.id = ri.return_id AND r.site_id = ri.site_id')
            ->where([
                ['ri.site_id',  '=', $this->site_id],
                ['ri.asset_id', '=', $assetId],
                ['r.status',    '=', 'pending'],
            ])->count();
        if ($exists > 0) {
            throw new CommonException('该设备已有待确认的退货单，请先处理');
        }
    }

    /** 分支①：全部未付 → 作废应付 */
    private function voidPayable(?ErpPayable $payable): void
    {
        if ($payable === null) {
            return;
        }
        if ((float)$payable->settled_amount > 0.0001) {
            throw new CommonException('应付已有付款记录，不能直接作废，请走部分付款分支');
        }
        $payable->save([
            'status'    => ErpDict::STATUS_VOID,
            'update_at' => time(),
        ]);
    }

    /** 分支③辅助：减少应付金额（作废未付部分） */
    private function partialVoidPayable(ErpPayable $payable, float $unpaidAmount): void
    {
        $newAmount = max(0, round((float)$payable->amount - $unpaidAmount, 2));
        $payable->save([
            'amount'    => $newAmount,
            'status'    => ErpDict::financeStatus($newAmount, (float)$payable->settled_amount),
            'update_at' => time(),
        ]);
    }

    /** 分支②&③：生成应收退款（供应商欠我们的现金） */
    private function createReturnReceivable(
        ErpPurchaseReturnOrder $return,
        ErpPurchaseReturnItem  $item,
        float                  $paidAmount,
        string                 $remark,
        int                    $now
    ): void {
        ErpReceivable::create([
            'site_id'        => $this->site_id,
            'receivable_no'  => ErpLedgerService::makeNo('AR'),
            'party_id'       => (int)$return->party_id,
            'party_name'     => (string)$return->party_name,
            'source_type'    => 'purchase_return',
            'source_id'      => (int)$return->id,
            'source_no'      => (string)$return->return_no,
            'amount'         => $paidAmount,
            'settled_amount' => 0,
            'status'         => ErpDict::STATUS_PENDING,
            'occurred_at'    => $now,
            'remark'         => $remark ?: '采购退货应收',
            'create_at'      => $now,
            'update_at'      => $now,
        ]);
    }

    /**
     * 退货确认后刷新采购单财务状态。
     *
     * 核心逻辑：排除已作废(void)的应付，只汇总仍生效的应付金额，
     * 从而正确反映"退货后剩余欠款"。
     * 解决：未付款退货后采购单仍显示"待付款"的问题。
     */
    private function refreshPurchaseOrderAfterReturn(int $purchaseOrderId): void
    {
        if ($purchaseOrderId <= 0) {
            return;
        }
        $order = ErpPurchaseOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $purchaseOrderId],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            return;
        }

        // 只看该采购单下所有非作废应付的汇总
        $assetIds = ErpAsset::where([
            ['site_id',           '=', $this->site_id],
            ['purchase_order_id', '=', $purchaseOrderId],
        ])->column('id');

        // 汇总仍生效的应付（排除 void）
        $activePayables = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->where(function ($q) use ($purchaseOrderId, $assetIds) {
                $q->where(function ($qq) use ($purchaseOrderId) {
                    $qq->where('source_type', '=', 'purchase')
                       ->where('source_id',   '=', $purchaseOrderId);
                })->whereOr(function ($qq) use ($assetIds) {
                    if (!empty($assetIds)) {
                        $qq->where('source_type', '=', 'purchase_asset')
                           ->whereIn('source_id', $assetIds);
                    }
                });
            })
            ->whereNotIn('status', [ErpDict::STATUS_VOID])
            ->field('amount, settled_amount')
            ->select()
            ->toArray();

        $effectiveTotal  = 0.0;
        $effectivePaid   = 0.0;
        foreach ($activePayables as $row) {
            $effectiveTotal = round($effectiveTotal + (float)$row['amount'], 2);
            $effectivePaid  = round($effectivePaid  + (float)$row['settled_amount'], 2);
        }

        // 如果所有应付都已作废（全额未付退货），有效总额为 0 → 视为已结清
        $financeStatus = $effectiveTotal <= 0
            ? ErpDict::STATUS_SETTLED
            : ErpDict::financeStatus($effectiveTotal, $effectivePaid);

        $order->save([
            'payable_amount' => max(0, round($effectiveTotal - $effectivePaid, 2)),
            'paid_amount'    => round($effectivePaid, 2),
            'finance_status' => $financeStatus,
            'update_at'      => time(),
        ]);
    }
}
