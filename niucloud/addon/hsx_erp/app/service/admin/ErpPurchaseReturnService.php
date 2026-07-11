<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpPurchaseReturnOrder;
use addon\hsx_erp\app\model\ErpPurchaseReturnItem;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpPurchaseReturnPolicy;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 采购退货服务
 *
 * 设计原则：
 *  - create()：业务人员确认退货，立即回退资产、处理应付/应收三分支。
 *  - confirm()：保留兼容旧待确认退货单，新流程不再依赖二次确认。
 *  - 三分支：① 未付款→冲销应付；② 已付款→生成应收退款；③ 部分付款→混合。
 *  - 错账不涂改：已形成资金事实的部分通过应收退款走逆向流水。
 */
class ErpPurchaseReturnService extends BaseAdminService
{
    /** 业务端按供货方选设备，系统按原采购单自动拆单。 */
    public function createBatch(array $data): array
    {
        $items = (array)($data['items'] ?? []);
        if ($items === []) {
            throw new CommonException('请至少选择一台退货设备');
        }
        $groups = [];
        foreach ($items as $item) {
            $orderId = (int)($item['purchase_order_id'] ?? 0);
            if ($orderId <= 0) {
                throw new CommonException('退货设备缺少原采购关系，请刷新后重试');
            }
            $groups[$orderId][] = $item;
        }
        $baseRequestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $ids = [];
        Db::transaction(function () use ($data, $groups, $baseRequestId, &$ids) {
            foreach ($groups as $orderId => $groupItems) {
                $groupData = $data;
                $groupData['purchase_order_id'] = (int)$orderId;
                $groupData['items'] = $groupItems;
                $groupData['request_id'] = ErpIdempotency::child($baseRequestId, 'po-' . $orderId);
                $ids[] = $this->create($groupData);
            }
        });
        return $ids;
    }

    // ─── 公开接口 ────────────────────────────────────────────────────────────

    /**
     * 创建采购退货单（业务人员操作）
     * 提交即完成业务退货；财务后续只在应收中确认供应商退款。
     */
    public function create(array $data): int
    {
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $existingId = $this->existingReturnId($requestId);
        if ($existingId > 0) {
            return $existingId;
        }
        $data['request_id'] = $requestId !== '' ? $requestId : null;
        $purchaseOrderId = (int)($data['purchase_order_id'] ?? 0);
        if ($purchaseOrderId <= 0) {
            throw new CommonException('请选择原采购单');
        }
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请至少选择一台退货设备');
        }

        $returnId = 0;
        try {
            Db::transaction(function () use ($data, $purchaseOrderId, $items, &$returnId) {
            $now  = time();
            $order = $this->findPurchaseOrder($purchaseOrderId);

            $totalAmount = 0.0;
            $itemsData   = [];
            $requiresRefund = false;
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

                $payable    = $this->findAssetPayable($assetId);
                $supplierAmount = $payable
                    ? round((float)$payable->amount, 2)
                    : round((float)$asset->purchase_cost, 2);
                $paidAmount = $payable ? round((float)$payable->settled_amount, 2) : 0.0;
                // 未形成付款/折账事实时，不存在“供应商退多少钱”的问题，整台直接取消。
                $returnCost = $paidAmount <= 0.0001
                    ? $supplierAmount
                    : round((float)($item['return_cost'] ?? $supplierAmount), 2);
                if ($returnCost <= 0) {
                    throw new CommonException('供应商确认退回金额必须大于0');
                }
                if ($returnCost > $supplierAmount + 0.0001) {
                    throw new CommonException(sprintf('供应商确认退回金额不能超过采购结算本金 ¥%.2f；额外补偿请走财务调账', $supplierAmount));
                }
                $returnPolicy = ErpPurchaseReturnPolicy::assess($asset->toArray(), $supplierAmount, $paidAmount, $returnCost);
                if (!$returnPolicy['returnable']) {
                    throw new CommonException((string)$returnPolicy['block_reason']);
                }
                if ($returnPolicy['requires_refund']) {
                    $requiresRefund = true;
                }

                $totalAmount  = round($totalAmount + $returnCost, 2);
                $itemsData[] = [
                    'asset'       => $asset,
                    'return_cost' => $returnCost,
                    'paid_amount' => $paidAmount,
                    'policy' => $returnPolicy,
                    'reason'      => trim((string)($item['reason'] ?? '')),
                ];
            }

            $returnNo = ErpLedgerService::makeNo('PR');
            $requestedRefundMode = trim((string)($data['refund_mode'] ?? 'cash'));
            $refundMode = $requiresRefund && in_array($requestedRefundMode, ['cash', 'offset'], true)
                ? $requestedRefundMode
                : ($requiresRefund ? 'cash' : 'none');
            $return   = ErpPurchaseReturnOrder::create([
                'site_id'            => $this->site_id,
                'request_id'         => $data['request_id'],
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
                'refund_mode'        => $refundMode,
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
                    'supplier_amount' => $row['policy']['supplier_amount'],
                    'unpaid_offset_amount' => $row['policy']['offset_amount'],
                    'refund_receivable_amount' => $row['policy']['refund_amount'],
                    'retained_payable_amount' => $row['policy']['retained_payable_amount'],
                    'internal_cost' => round((float)$row['policy']['refurbish_cost'] + abs((float)$row['policy']['unclassified_cost']), 2),
                    'policy_json' => json_encode($row['policy'], JSON_UNESCAPED_UNICODE),
                    'reason'           => $row['reason'],
                    'create_at'        => $now,
                ]);
            }

            $this->completeReturn($return, ['remark' => trim((string)($data['remark'] ?? ''))], $now);
            });
        } catch (\Throwable $e) {
            $existingId = $this->existingReturnId($requestId);
            if ($existingId > 0) {
                return $existingId;
            }
            throw $e;
        }

        return $returnId;
    }

    /**
     * 财务确认退货（三分支处理，实际改变资产与财务状态）
     */
    public function confirm(int $returnId, array $data = []): bool
    {
        Db::transaction(function () use ($returnId, $data) {
            $now    = time();
            $return = $this->findReturn($returnId, true);

            if ((string)$return->status !== 'pending') {
                throw new CommonException('只有待确认的退货单可以确认');
            }
            $this->completeReturn($return, $data, $now);
        });

        return true;
    }

    private function completeReturn(ErpPurchaseReturnOrder $return, array $data, int $now): void
    {
        if ((string)$return->status !== 'pending') {
            throw new CommonException('只有待确认的退货单可以确认');
        }

        $returnId = (int)$return->id;
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
        $receivableAmount = 0.0;

        foreach ($items as $item) {
            $asset = ErpAsset::where([
                ['site_id', '=', $this->site_id],
                ['id',      '=', (int)$item->asset_id],
            ])->lock(true)->findOrEmpty();

            if ($asset->isEmpty()) {
                throw new CommonException('退货设备不存在，asset_id=' . $item->asset_id);
            }
            if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
                throw new CommonException('设备【' . (string)$asset->imei . '】已不在库存中，请检查退货单');
            }

            $payable       = $this->findAssetPayable((int)$asset->id);
            $payableAmount = $payable ? round((float)$payable->amount, 2) : round((float)$asset->purchase_cost, 2);
            $paidAmount    = $payable ? round((float)$payable->settled_amount, 2) : 0.0;

            $returnFlow = ErpPurchaseReturnPolicy::assess(
                $asset->toArray(),
                $payableAmount,
                $paidAmount,
                (float)$item->return_cost
            );
            if (!$returnFlow['returnable']) {
                throw new CommonException((string)$returnFlow['block_reason']);
            }
            $this->reducePayableForReturn($payable, (float)$returnFlow['offset_amount']);
            $receivableAmount = round($receivableAmount + (float)$returnFlow['refund_amount'], 2);
            $item->save([
                'supplier_amount' => $returnFlow['supplier_amount'],
                'unpaid_offset_amount' => $returnFlow['offset_amount'],
                'refund_receivable_amount' => $returnFlow['refund_amount'],
                'retained_payable_amount' => $returnFlow['retained_payable_amount'],
                'internal_cost' => round((float)$returnFlow['refurbish_cost'] + abs((float)$returnFlow['unclassified_cost']), 2),
                'policy_json' => json_encode($returnFlow, JSON_UNESCAPED_UNICODE),
            ]);

            // ── 资产状态 → 已退回 ─────────────────────────────────────
            $beforeStatus = (string)$asset->status;
            $totalCost    = round((float)$asset->total_cost, 2);
            $asset->save([
                'status'    => ErpDict::ASSET_RETURNED,
                'update_at' => $now,
            ]);
            ErpPurchaseItem::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$asset->purchase_item_id],
            ])->update([
                'status' => ErpDict::ASSET_RETURNED,
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
                'remark'               => $remark . sprintf('；供应商退回 ¥%.2f', (float)$item->return_cost),
                'extra'                => $returnFlow,
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
            $returnLoss = max(0, round($payableAmount - (float)$item->return_cost, 2));
            if ($returnLoss > 0.0001) {
                $ledger->account([
                    'biz_type' => 'purchase_return_loss',
                    'direction' => 'increase',
                    'amount' => $returnLoss,
                    'party_id' => (int)$return->party_id,
                    'party_name' => (string)$return->party_name,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'purchase_return',
                    'source_id' => $returnId,
                    'source_no' => (string)$return->return_no,
                    'remark' => sprintf('供应商折价退货损失：结算本金 ¥%.2f，确认退回 ¥%.2f', $payableAmount, (float)$item->return_cost),
                ]);
            }
        }

        if ($receivableAmount > 0) {
            $this->createReturnReceivable($return, $receivableAmount, $remark, $now);
        }

        // ── 刷新采购单财务状态（排除已作废的应付）────────────────────
        $this->refreshPurchaseOrderAfterReturn((int)$return->purchase_order_id);

        // ── 更新退货单状态 ────────────────────────────────────────────
        $return->save([
            'status'         => 'confirmed',
            'settled_amount' => 0,
            'update_at'      => $now,
        ]);
    }

    /**
     * 撤销退货单（仅 pending 状态可撤销，不改任何财务状态）
     */
    public function cancel(int $returnId, string $remark = ''): bool
    {
        Db::transaction(function () use ($returnId, $remark) {
            $return = $this->findReturn($returnId, true);
            if ((string)$return->status !== 'pending') {
                throw new CommonException('只有待确认的退货单可以撤销');
            }
            $return->save([
                'status'    => 'cancelled',
                'remark'    => $remark !== '' ? ((string)$return->remark . ' / ' . $remark) : (string)$return->remark,
                'update_at' => time(),
            ]);
        });
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

        $result = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page'      => (int)($where['page'] ?? 1),
        ])->toArray();
        $returnIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            (array)($result['data'] ?? [])
        )));
        $receivables = [];
        if (!empty($returnIds)) {
            foreach (ErpReceivable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase_return'],
            ])->whereIn('source_id', $returnIds)->field('source_id,amount,settled_amount,status')->select()->toArray() as $row) {
                $receivables[(int)$row['source_id']] = $row;
            }
        }
        foreach ($result['data'] as &$row) {
            $row = $this->appendProcessStatus($row, $receivables[(int)$row['id']] ?? null);
        }
        unset($row);
        return $result;
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
        $receivable = ErpReceivable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'purchase_return'],
            ['source_id', '=', $returnId],
        ])->field('id,receivable_no,amount,settled_amount,status')->findOrEmpty();
        $return['refund_receivable'] = $receivable->isEmpty() ? null : $receivable->toArray();
        $return = $this->appendProcessStatus($return, $return['refund_receivable']);
        return $return;
    }

    /** 面向业务人员只暴露一个主状态：退机已确认后，由退款进度接管状态表达。 */
    private function appendProcessStatus(array $return, ?array $receivable): array
    {
        $status = (string)($return['status'] ?? '');
        if ($status === 'pending') {
            $process = ['value' => 'legacy_pending', 'label' => '旧单待确认', 'type' => 'warning'];
        } elseif ($status === 'cancelled') {
            $process = ['value' => 'cancelled', 'label' => '已撤销', 'type' => 'info'];
        } elseif ($receivable === null) {
            $process = ['value' => 'returned', 'label' => '退货完成', 'type' => 'success'];
        } else {
            $amount = round((float)($receivable['amount'] ?? 0), 2);
            $settled = round((float)($receivable['settled_amount'] ?? 0), 2);
            if ($amount > 0 && $settled + 0.0001 >= $amount) {
                $process = ['value' => 'refunded', 'label' => '已退款', 'type' => 'success'];
            } elseif ($settled > 0.0001) {
                $process = ['value' => 'partial_refund', 'label' => '部分退款', 'type' => 'warning'];
            } else {
                $process = ['value' => 'awaiting_refund', 'label' => '待退款', 'type' => 'warning'];
            }
        }
        $return['process_status'] = $process['value'];
        $return['process_status_label'] = $process['label'];
        $return['process_status_type'] = $process['type'];
        return $return;
    }

    // ─── 私有辅助 ────────────────────────────────────────────────────────────

    private function findReturn(int $id, bool $forUpdate = false): ErpPurchaseReturnOrder
    {
        $query = ErpPurchaseReturnOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $id],
        ]);
        if ($forUpdate) {
            $query->lock(true);
        }
        $row = $query->findOrEmpty();
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
        ])->lock(true)->findOrEmpty();
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
        ])->lock(true)->findOrEmpty();
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

    /** 退货金额优先冲销未付款；不得修改已经形成的付款事实。 */
    private function reducePayableForReturn(?ErpPayable $payable, float $offsetAmount): void
    {
        $offsetAmount = max(0, round($offsetAmount, 2));
        if ($payable === null || $offsetAmount <= 0.0001) {
            return;
        }
        $amount = round((float)$payable->amount, 2);
        $settled = round((float)$payable->settled_amount, 2);
        $remaining = max(0, round($amount - $settled, 2));
        if ($offsetAmount > $remaining + 0.0001) {
            throw new CommonException('退货冲销金额超过剩余应付，请刷新后重试');
        }
        $newAmount = max($settled, round($amount - $offsetAmount, 2));
        $status = $newAmount <= 0.0001 && $settled <= 0.0001
            ? ErpDict::STATUS_VOID
            : ErpDict::financeStatus($newAmount, $settled);
        $payable->save([
            'amount' => $newAmount,
            'status' => $status,
            'update_at' => time(),
        ]);
    }

    /** 分支②&③：生成应收退款（供应商欠我们的现金） */
    private function createReturnReceivable(
        ErpPurchaseReturnOrder $return,
        float                  $amount,
        string                 $remark,
        int                    $now
    ): void {
        $purchase = ErpPurchaseOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$return->purchase_order_id],
        ])->field('purchase_channel,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_id,origin_no')->find();
        $sourceService = new ErpFinanceSourceService();
        $source = $sourceService->purchaseReturn([
            'origin_no' => (string)$return->return_no,
            'channel_code' => 'purchase_return',
            'channel_name' => (string)($purchase?->purchase_channel ?? ''),
            'business_reason' => '采购退货已完成，已付款部分 ¥' . number_format($amount, 2, '.', '')
                . ' 形成供货商退款应收，财务需确认实际到账。',
        ]);
        ErpReceivable::create(array_merge([
            'site_id'        => $this->site_id,
            'receivable_no'  => ErpLedgerService::makeNo('AR'),
            'party_id'       => (int)$return->party_id,
            'party_name'     => (string)$return->party_name,
            'source_type'    => 'purchase_return',
            'source_id'      => (int)$return->id,
            'source_no'      => (string)$return->return_no,
            'amount'         => $amount,
            'settled_amount' => 0,
            'status'         => ErpDict::STATUS_PENDING,
            'occurred_at'    => $now,
            'remark'         => $remark ?: '采购退货应收',
            'create_at'      => $now,
            'update_at'      => $now,
        ], $sourceService->persistable($source)));
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

    private function existingReturnId(string $requestId): int
    {
        if ($requestId === '') {
            return 0;
        }
        $return = ErpPurchaseReturnOrder::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
        ])->findOrEmpty();
        return $return->isEmpty() ? 0 : (int)$return->id;
    }
}
