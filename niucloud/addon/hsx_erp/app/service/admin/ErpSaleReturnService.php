<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleReturnOrder;
use addon\hsx_erp\app\model\ErpSaleReturnItem;
use addon\hsx_erp\app\model\ErpWarehouse;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 销售退货服务
 *
 * 设计原则：
 *  - create()：业务人员登记退货意向，不改资产状态，等财务确认。
 *  - confirm()：财务确认，设备回库，三分支处理应收/应付。
 *  - 三分支：① 未收款→减少应收；② 已收款→生成应付退款；③ 部分收款→混合。
 *  - 销售应收是按单维度，按台收款通过 account_ledger 追踪。
 */
class ErpSaleReturnService extends BaseAdminService
{
    // ─── 公开接口 ────────────────────────────────────────────────────────────

    /**
     * 创建销售退货单（业务人员操作）
     */
    public function create(array $data): int
    {
        $saleOrderId = (int)($data['sale_order_id'] ?? 0);
        if ($saleOrderId <= 0) {
            throw new CommonException('请选择原销售单');
        }
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请至少选择一台退货设备');
        }

        $returnId = 0;
        Db::transaction(function () use ($data, $saleOrderId, $items, &$returnId) {
            $now   = time();
            $order = $this->findSaleOrder($saleOrderId);

            // 获取该销售单的应收，用于计算各设备已收金额
            $receivable = ErpReceivable::where([
                ['site_id',     '=', $this->site_id],
                ['source_type', '=', 'sale'],
                ['source_id',   '=', $saleOrderId],
            ])->findOrEmpty();

            $itemSettledMap = $receivable->isEmpty()
                ? []
                : $this->receivableItemSettledMap((int)$receivable->id);

            $totalAmount = 0.0;
            $itemsData   = [];

            foreach ($items as $item) {
                $assetId = (int)($item['asset_id'] ?? 0);
                if ($assetId <= 0) {
                    throw new CommonException('退货设备ID不能为空');
                }
                $asset    = $this->findAssetInSaleOrder($assetId, $saleOrderId);
                $saleItem = $this->findSaleItem($assetId, $saleOrderId);

                if ((string)$asset->status !== ErpDict::ASSET_SOLD) {
                    throw new CommonException('设备【' . (string)$asset->imei . '】状态不是已售，无法退货');
                }
                $this->assertNoPendingSaleReturn($assetId);

                $returnPrice  = round((float)($item['return_price'] ?? (float)$saleItem->sale_price), 2);
                $receivedAmt  = round((float)($itemSettledMap[$assetId] ?? 0), 2);

                $totalAmount = round($totalAmount + $returnPrice, 2);
                $itemsData[] = [
                    'asset'          => $asset,
                    'sale_item'      => $saleItem,
                    'return_price'   => $returnPrice,
                    'received_amount'=> $receivedAmt,
                    'reason'         => trim((string)($item['reason'] ?? '')),
                ];
            }

            $returnNo   = ErpLedgerService::makeNo('SR');
            $warehouseId   = (int)($data['return_to_warehouse_id'] ?? (int)$order->warehouse_id);
            $locationId    = (int)($data['return_to_location_id'] ?? (int)$order->location_id);
            [$warehouse, $location] = (new ErpWarehouseService())->validateInboundLocation($warehouseId, $locationId);

            $return = ErpSaleReturnOrder::create([
                'site_id'                => $this->site_id,
                'return_no'              => $returnNo,
                'sale_order_id'          => $saleOrderId,
                'sale_no'                => (string)$order->sale_no,
                'party_id'               => (int)$order->party_id,
                'party_name'             => (string)$order->party_name,
                'operator_id'            => (int)$this->uid,
                'operator_name'          => (string)$this->username,
                'total_amount'           => $totalAmount,
                'settled_amount'         => 0,
                'status'                 => 'pending',
                'refund_mode'            => trim((string)($data['refund_mode'] ?? 'cash')),
                'capital_account_id'     => (int)($data['capital_account_id'] ?? 0),
                'return_to_warehouse_id' => (int)$warehouse->id,
                'return_to_location_id'  => (int)$location->id,
                'remark'                 => trim((string)($data['remark'] ?? '')),
                'occurred_at'            => $now,
                'create_at'              => $now,
                'update_at'              => $now,
            ]);
            $returnId = (int)$return->id;

            foreach ($itemsData as $row) {
                /** @var ErpAsset    $asset */
                /** @var ErpSaleItem $saleItem */
                $asset    = $row['asset'];
                $saleItem = $row['sale_item'];
                ErpSaleReturnItem::create([
                    'site_id'         => $this->site_id,
                    'return_id'       => $returnId,
                    'asset_id'        => (int)$asset->id,
                    'asset_no'        => (string)$asset->asset_no,
                    'imei'            => (string)$asset->imei,
                    'model'           => (string)$asset->model,
                    'sale_item_id'    => (int)$saleItem->id,
                    'sale_price'      => (float)$saleItem->sale_price,
                    'return_price'    => $row['return_price'],
                    'received_amount' => $row['received_amount'],
                    'reason'          => $row['reason'],
                    'create_at'       => $now,
                ]);
            }
        });

        return $returnId;
    }

    /**
     * 财务确认销售退货（三分支处理）
     */
    public function confirm(int $returnId, array $data = []): bool
    {
        Db::transaction(function () use ($returnId, $data) {
            $now    = time();
            $return = $this->findReturn($returnId);

            if ((string)$return->status !== 'pending') {
                throw new CommonException('只有待确认的退货单可以确认');
            }

            $items = ErpSaleReturnItem::where([
                ['site_id',   '=', $this->site_id],
                ['return_id', '=', $returnId],
            ])->order('id asc')->select();

            if ($items->isEmpty()) {
                throw new CommonException('退货单明细为空');
            }

            $remark = trim((string)($data['remark'] ?? '销售退货'));
            if ($remark === '') {
                $remark = '销售退货';
            }

            $returnWarehouseId   = (int)$return->return_to_warehouse_id;
            $returnLocationId    = (int)$return->return_to_location_id;
            [$retWarehouse, $retLocation] = (new ErpWarehouseService())->validateInboundLocation(
                $returnWarehouseId, $returnLocationId
            );
            $retWarehouseName = (string)$retWarehouse->warehouse_name;
            $retLocationName  = (string)$retLocation->location_name;

            $ledger = new ErpLedgerService();

            foreach ($items as $item) {
                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id',      '=', (int)$item->asset_id],
                ])->findOrEmpty();

                if ($asset->isEmpty()) {
                    throw new CommonException('退货设备不存在，asset_id=' . $item->asset_id);
                }
                if ((string)$asset->status !== ErpDict::ASSET_SOLD) {
                    throw new CommonException('设备【' . (string)$asset->imei . '】当前状态不是已售');
                }

                $saleOrderId  = (int)$asset->sale_order_id;
                $receivable   = $this->findSaleReceivable($saleOrderId);
                $receivedAmt  = round((float)$item->received_amount, 2);
                $returnPrice  = round((float)$item->return_price, 2);

                // ── 三分支退款逻辑 ─────────────────────────────────────────
                if ($receivedAmt <= 0) {
                    // 分支①：该设备未收款 → 减少应收金额，无现金流
                    $this->reduceReceivable($receivable, $returnPrice);

                } elseif ($receivedAmt >= $returnPrice - 0.0001) {
                    // 分支②：已收款 → 生成应付退款（我们欠客户）
                    $this->createReturnPayable($return, $item, $receivedAmt, $remark, $now);
                    $this->reduceReceivable($receivable, $returnPrice);

                } else {
                    // 分支③：部分收款 → 减少未收部分 + 生成已收部分的应付
                    $this->reduceReceivable($receivable, $returnPrice);
                    $this->createReturnPayable($return, $item, $receivedAmt, $remark, $now);
                }

                // ── 设备回库 ──────────────────────────────────────────────
                $beforeStatus       = (string)$asset->status;
                $beforeWarehouseId  = (int)$asset->warehouse_id;
                $beforeWarehouseName= (string)$asset->warehouse_name;
                $beforeLocationId   = (int)$asset->location_id;
                $beforeLocationName = (string)$asset->location_name;
                $totalCost          = round((float)$asset->total_cost, 2);

                $asset->save([
                    'status'         => ErpDict::ASSET_RETURNED,
                    'warehouse_id'   => (int)$retWarehouse->id,
                    'warehouse_name' => $retWarehouseName,
                    'location_id'    => (int)$retLocation->id,
                    'location_name'  => $retLocationName,
                    'sale_order_id'  => 0,
                    'sale_item_id'   => 0,
                    'sale_price'     => 0,
                    'profit'         => 0,
                    'update_at'      => $now,
                ]);

                // ── 库存流水 ──────────────────────────────────────────────
                $ledger->asset([
                    'asset_id'              => (int)$asset->id,
                    'action'                => 'sale_return',
                    'before_status'         => $beforeStatus,
                    'after_status'          => ErpDict::ASSET_RETURNED,
                    'before_warehouse_id'   => $beforeWarehouseId,
                    'before_warehouse_name' => $beforeWarehouseName,
                    'before_location_id'    => $beforeLocationId,
                    'before_location_name'  => $beforeLocationName,
                    'after_warehouse_id'    => (int)$retWarehouse->id,
                    'after_warehouse_name'  => $retWarehouseName,
                    'after_location_id'     => (int)$retLocation->id,
                    'after_location_name'   => $retLocationName,
                    'before_total_cost'     => $totalCost,
                    'after_total_cost'      => $totalCost,
                    'party_id'              => (int)$return->party_id,
                    'party_name'            => (string)$return->party_name,
                    'source_type'           => 'sale_return',
                    'source_id'             => $returnId,
                    'source_no'             => (string)$return->return_no,
                    'occurred_at'           => $now,
                    'remark'                => $remark,
                ]);

                // ── 账目流水（应收减少） ────────────────────────────────────
                $ledger->account([
                    'biz_type'    => 'sale_return',
                    'direction'   => 'decrease',
                    'amount'      => $returnPrice,
                    'party_id'    => (int)$return->party_id,
                    'party_name'  => (string)$return->party_name,
                    'asset_id'    => (int)$asset->id,
                    'source_type' => 'sale_return',
                    'source_id'   => $returnId,
                    'source_no'   => (string)$return->return_no,
                    'remark'      => $remark,
                ]);

                // 刷新销售单财务状态
                $this->refreshSaleFinance($saleOrderId);
            }

            $return->save([
                'status'    => 'confirmed',
                'update_at' => $now,
            ]);
        });

        return true;
    }

    /**
     * 撤销销售退货单（仅 pending 可撤）
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
        $query = ErpSaleReturnOrder::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }
        if (!empty($where['party_id'])) {
            $query->where('party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('return_no|sale_no|party_name|remark', '%' . $kw . '%');
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
        $return          = $this->findReturn($returnId)->toArray();
        $return['items'] = ErpSaleReturnItem::where([
            ['site_id',   '=', $this->site_id],
            ['return_id', '=', $returnId],
        ])->order('id asc')->select()->toArray();
        return $return;
    }

    // ─── 私有辅助 ────────────────────────────────────────────────────────────

    private function findReturn(int $id): ErpSaleReturnOrder
    {
        $row = ErpSaleReturnOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $id],
        ])->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('销售退货单不存在');
        }
        return $row;
    }

    private function findSaleOrder(int $id): ErpSaleOrder
    {
        $order = ErpSaleOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $id],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('销售单不存在');
        }
        return $order;
    }

    private function findAssetInSaleOrder(int $assetId, int $saleOrderId): ErpAsset
    {
        $asset = ErpAsset::where([
            ['site_id',        '=', $this->site_id],
            ['id',             '=', $assetId],
            ['sale_order_id',  '=', $saleOrderId],
        ])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('设备不存在或不属于该销售单，asset_id=' . $assetId);
        }
        return $asset;
    }

    private function findSaleItem(int $assetId, int $saleOrderId): ErpSaleItem
    {
        $item = ErpSaleItem::where([
            ['site_id',        '=', $this->site_id],
            ['asset_id',       '=', $assetId],
            ['sale_order_id',  '=', $saleOrderId],
        ])->findOrEmpty();
        if ($item->isEmpty()) {
            throw new CommonException('销售明细不存在，asset_id=' . $assetId);
        }
        return $item;
    }

    private function findSaleReceivable(int $saleOrderId): ?ErpReceivable
    {
        $row = ErpReceivable::where([
            ['site_id',     '=', $this->site_id],
            ['source_type', '=', 'sale'],
            ['source_id',   '=', $saleOrderId],
        ])->findOrEmpty();
        return $row->isEmpty() ? null : $row;
    }

    private function assertNoPendingSaleReturn(int $assetId): void
    {
        $returnTable = (new ErpSaleReturnOrder())->getTable();
        $exists = ErpSaleReturnItem::alias('ri')
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

    /**
     * 按设备查询该应收已收了多少（复用 ErpFinanceService 逻辑）
     */
    private function receivableItemSettledMap(int $receivableId): array
    {
        if ($receivableId <= 0) {
            return [];
        }
        $rows = ErpAccountLedger::where([
            ['site_id',     '=', $this->site_id],
            ['biz_type',    '=', 'receipt'],
            ['source_type', '=', 'receivable'],
            ['source_id',   '=', $receivableId],
        ])
            ->where('asset_id', '>', 0)
            ->field('asset_id, SUM(amount) as amount')
            ->group('asset_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['asset_id']] = round((float)$row['amount'], 2);
        }
        return $map;
    }

    /** 分支①&③辅助：减少应收金额（冲回未收部分） */
    private function reduceReceivable(?ErpReceivable $receivable, float $amount): void
    {
        if ($receivable === null) {
            return;
        }
        $newAmount = max(0, round((float)$receivable->amount - $amount, 2));
        $settled   = round((float)$receivable->settled_amount, 2);
        if ($newAmount < $settled - 0.0001) {
            // 退货金额超过了"未收部分"，不能静默把应收往上调，必须拒绝
            throw new CommonException(
                sprintf(
                    '退货金额(¥%s)超过该销售单剩余未收部分(¥%s)，请先处理收款再退货，或调整退货价格',
                    number_format($amount, 2),
                    number_format(max(0, (float)$receivable->amount - $settled), 2)
                )
            );
        }
        $receivable->save([
            'amount'    => $newAmount,
            'status'    => ErpDict::financeStatus($newAmount, $settled),
            'update_at' => time(),
        ]);
    }

    /** 分支②&③：生成应付退款（我们欠客户的钱） */
    private function createReturnPayable(
        ErpSaleReturnOrder $return,
        ErpSaleReturnItem  $item,
        float              $receivedAmount,
        string             $remark,
        int                $now
    ): void {
        ErpPayable::create([
            'site_id'        => $this->site_id,
            'payable_no'     => ErpLedgerService::makeNo('AP'),
            'party_id'       => (int)$return->party_id,
            'party_name'     => (string)$return->party_name,
            'source_type'    => 'sale_return',
            'source_id'      => (int)$return->id,
            'source_no'      => (string)$return->return_no,
            'amount'         => $receivedAmount,
            'settled_amount' => 0,
            'status'         => ErpDict::STATUS_PENDING,
            'occurred_at'    => $now,
            'remark'         => $remark ?: '销售退货应付',
            'create_at'      => $now,
            'update_at'      => $now,
        ]);
    }

    private function refreshSaleFinance(int $saleId): void
    {
        if ($saleId <= 0) {
            return;
        }
        $order = ErpSaleOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $saleId],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            return;
        }
        $received = (float)ErpReceivable::where([
            ['site_id',     '=', $this->site_id],
            ['source_type', '=', 'sale'],
            ['source_id',   '=', $saleId],
        ])->sum('settled_amount');
        $total = (float)$order->total_amount;
        $order->save([
            'received_amount'  => round($received, 2),
            'receivable_amount'=> max(0, round($total - $received, 2)),
            'finance_status'   => ErpDict::financeStatus($total, $received),
            'update_at'        => time(),
        ]);
    }
}
