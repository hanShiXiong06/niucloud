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
use addon\hsx_erp\app\support\ErpIdempotency;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 销售退货服务
 *
 * 设计原则：
 *  - create()：业务人员登记退货意向，不改资产状态，等待实际收货。
 *  - confirm()：业务/仓库确认收到设备，设备回库并自动处理应收/应付；财务只负责后续付款。
 *  - 三分支：① 未收款→减少应收；② 已收款→生成应付退款；③ 部分收款→混合。
 *  - 销售应收是按单维度，按台收款通过 account_ledger 追踪。
 */
class ErpSaleReturnService extends BaseAdminService
{
    /** 店内退货一站式处理：登记即代表设备已经交回，立即回库并完成账务分流。 */
    public function createAndConfirm(array $data): array
    {
        $ids = $this->createBatch($data);
        foreach ($ids as $id) {
            $this->confirm((int)$id, [
                'remark' => (string)($data['remark'] ?? ''),
                'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
                'voucher_urls' => $data['voucher_urls'] ?? '',
            ]);
        }
        return $ids;
    }

    public function createCompensation(array $data): int
    {
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $existingId = $this->existingReturnId($requestId);
        if ($existingId > 0) return $existingId;
        $items = (array)($data['items'] ?? []);
        if ($items === []) throw new CommonException('请选择需要售后补差的设备');
        $now = time(); $id = 0;
        Db::transaction(function () use ($data, $items, $now, $requestId, &$id) {
            $partyId = (int)($data['party_id'] ?? 0); $total = 0.0; $rows = [];
            foreach ($items as $itemData) {
                $assetId = (int)($itemData['asset_id'] ?? 0);
                $asset = ErpAsset::where([['site_id','=',$this->site_id],['id','=',$assetId]])->lock(true)->findOrEmpty();
                if ($asset->isEmpty() || (string)$asset->status !== ErpDict::ASSET_SOLD) throw new CommonException('只有已售设备可以售后补差');
                $saleItem = $this->findSaleItem($assetId, (int)$asset->sale_order_id);
                $order = $this->findSaleOrder((int)$asset->sale_order_id);
                if ($partyId <= 0) $partyId = (int)$order->party_id;
                if ((int)$order->party_id !== $partyId) throw new CommonException('只能处理同一个客户的设备');
                $amount = round((float)($itemData['amount'] ?? $itemData['return_price'] ?? 0), 2);
                $alreadyCompensated = $this->confirmedCompensationAmount((int)$saleItem->id);
                $compensableRemain = max(0, round((float)$saleItem->sale_price - $alreadyCompensated, 2));
                if ($amount <= 0 || $amount > $compensableRemain + 0.0001) {
                    throw new CommonException(sprintf(
                        '补差金额必须大于0且不能超过该设备剩余可补差金额 ¥%s',
                        number_format($compensableRemain, 2, '.', '')
                    ));
                }
                $total = round($total + $amount, 2); $rows[] = [$asset,$saleItem,$order,$amount,trim((string)($itemData['reason'] ?? ''))];
            }
            $firstOrder = $rows[0][2]; $no = ErpLedgerService::makeNo('SC');
            $record = ErpSaleReturnOrder::create(['site_id'=>$this->site_id,'request_id'=>$requestId !== '' ? $requestId : null,'business_type'=>'after_sale_compensation','return_no'=>$no,'sale_order_id'=>(int)$firstOrder->id,'sale_no'=>(string)$firstOrder->sale_no,'party_id'=>$partyId,'party_name'=>(string)$firstOrder->party_name,'operator_id'=>(int)$this->uid,'operator_name'=>(string)$this->username,'total_amount'=>$total,'settled_amount'=>0,'status'=>'confirmed','refund_mode'=>(string)($data['refund_mode'] ?? 'payable'),'capital_account_id'=>(int)($data['capital_account_id'] ?? 0),'remark'=>trim((string)($data['remark'] ?? '')),'occurred_at'=>$now,'create_at'=>$now,'update_at'=>$now]);
            $id = (int)$record->id; $payItems=[];
            foreach ($rows as [$asset,$saleItem,$order,$amount,$reason]) {
                $ri=ErpSaleReturnItem::create(['site_id'=>$this->site_id,'return_id'=>$id,'asset_id'=>(int)$asset->id,'asset_no'=>(string)$asset->asset_no,'imei'=>(string)$asset->imei,'model'=>(string)$asset->model,'sale_item_id'=>(int)$saleItem->id,'sale_price'=>(float)$saleItem->sale_price,'return_price'=>$amount,'received_amount'=>$amount,'reason'=>$reason,'create_at'=>$now]);
                $saleItem->save(['profit'=>round((float)$saleItem->profit-$amount,2),'update_at'=>$now]);
                $asset->save(['profit'=>round((float)$asset->profit-$amount,2),'update_at'=>$now]);
                ErpSaleOrder::where([['site_id','=',$this->site_id],['id','=',(int)$order->id]])->dec('profit',$amount)->update(['update_at'=>$now]);
                $pid=$this->createReturnPayable($record,$ri,$amount,$reason ?: '售后补差',$now); $payItems[]=['payable_id'=>$pid,'amount'=>$amount];
                $compensationRemark = '售后补差：公司应向客户【' . (string)$order->party_name . '】支付 ¥' . number_format($amount, 2, '.', '')
                    . '；设备【' . (string)($asset->model ?: $asset->asset_no) . ' / IMEI ' . (string)($asset->imei ?: '-') . '】继续由客户持有，销售毛利同步减少。'
                    . ($reason !== '' ? ' 原因：' . $reason : '');
                (new ErpLedgerService())->account(['biz_type'=>'sale_compensation','direction'=>'increase','amount'=>$amount,'party_id'=>$partyId,'party_name'=>(string)$order->party_name,'asset_id'=>(int)$asset->id,'source_type'=>'sale_compensation','source_id'=>$id,'source_no'=>$no,'remark'=>$compensationRemark]);
            }
            if ((string)$record->refund_mode === 'cash') {
                $accountId=(int)$record->capital_account_id; if($accountId<=0) throw new CommonException('现场补差必须选择付款账户');
                (new ErpFinanceService())->confirmPayableItemsInTransaction($partyId,$payItems,['capital_account_id'=>$accountId,'voucher_urls'=>$data['voucher_urls'] ?? '','confirmed_at'=>$now,'remark'=>'售后补差：公司现场向客户支付补差款','request_id'=>'']);
            }
        });
        return $id;
    }

    /** 已确认的售后补差累计额；用于阻止同一设备被重复补差到超过原成交价。 */
    private function confirmedCompensationAmount(int $saleItemId): float
    {
        if ($saleItemId <= 0) return 0.0;
        $returnIds = ErpSaleReturnOrder::where([
            ['site_id', '=', $this->site_id],
            ['business_type', '=', 'after_sale_compensation'],
            ['status', '<>', 'cancelled'],
        ])->column('id');
        if ($returnIds === []) return 0.0;
        return round((float)ErpSaleReturnItem::where([
            ['site_id', '=', $this->site_id],
            ['sale_item_id', '=', $saleItemId],
        ])->whereIn('return_id', array_map('intval', $returnIds))->sum('return_price'), 2);
    }
    /** 业务端按客户选设备，系统按原销售单自动拆单。 */
    public function createBatch(array $data): array
    {
        $items = (array)($data['items'] ?? []);
        if ($items === []) {
            throw new CommonException('请至少选择一台退货设备');
        }
        $groups = [];
        foreach ($items as $item) {
            $orderId = (int)($item['sale_order_id'] ?? ($data['sale_order_id'] ?? 0));
            if ($orderId <= 0) {
                throw new CommonException('退货设备缺少原销售关系，请刷新后重试');
            }
            $assetId = (int)($item['asset_id'] ?? 0);
            $asset = $this->findAssetInSaleOrder($assetId, $orderId);
            $warehouseId = (int)$asset->warehouse_id;
            $locationId = (int)$asset->location_id;
            if ($warehouseId <= 0 || $locationId <= 0) {
                throw new CommonException('设备【' . (string)($asset->imei ?: $asset->asset_no) . '】缺少原仓库或库位，无法自动回库');
            }
            // 一张退货单只记录一个回库位置；同一销售单跨仓设备自动拆成多张退货单。
            $item['sale_order_id'] = $orderId;
            $groups[$orderId . '-' . $warehouseId . '-' . $locationId][] = $item;
        }
        $baseRequestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $ids = [];
        Db::transaction(function () use ($data, $groups, $baseRequestId, &$ids) {
            foreach ($groups as $groupKey => $groupItems) {
                $orderId = (int)($groupItems[0]['sale_order_id'] ?? 0);
                $groupData = $data;
                $groupData['sale_order_id'] = (int)$orderId;
                $groupData['items'] = $groupItems;
                $groupData['request_id'] = ErpIdempotency::child($baseRequestId, 'return-' . $groupKey);
                $ids[] = $this->create($groupData);
            }
        });
        return $ids;
    }

    // ─── 公开接口 ────────────────────────────────────────────────────────────

    /**
     * 创建销售退货单（业务人员操作）
     */
    public function create(array $data): int
    {
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        $existingId = $this->existingReturnId($requestId);
        if ($existingId > 0) {
            return $existingId;
        }
        $data['request_id'] = $requestId !== '' ? $requestId : null;
        $saleOrderId = (int)($data['sale_order_id'] ?? 0);
        if ($saleOrderId <= 0) {
            throw new CommonException('请选择原销售单');
        }
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请至少选择一台退货设备');
        }

        $returnId = 0;
        try {
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
                : $this->saleItemSettledMap($receivable, $saleOrderId);

            $totalAmount = 0.0;
            $itemsData   = [];
            $originalWarehouseId = 0;
            $originalLocationId = 0;

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

                $assetWarehouseId = (int)$asset->warehouse_id;
                $assetLocationId = (int)$asset->location_id;
                if ($assetWarehouseId <= 0 || $assetLocationId <= 0) {
                    throw new CommonException('设备【' . (string)($asset->imei ?: $asset->asset_no) . '】缺少原仓库或库位，无法自动回库');
                }
                if ($originalWarehouseId === 0) {
                    $originalWarehouseId = $assetWarehouseId;
                    $originalLocationId = $assetLocationId;
                } elseif ($originalWarehouseId !== $assetWarehouseId || $originalLocationId !== $assetLocationId) {
                    throw new CommonException('所选设备原仓位不同，请由系统按原仓位自动拆分退货单');
                }

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
            // 回库位置属于设备事实，不接受前端人工指定，避免退错仓。
            [$warehouse, $location] = (new ErpWarehouseService())->validateInboundLocation(
                $originalWarehouseId,
                $originalLocationId
            );

            $return = ErpSaleReturnOrder::create([
                'site_id'                => $this->site_id,
                'request_id'             => $data['request_id'],
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
     * 财务确认销售退货（三分支处理）
     */
    public function confirm(int $returnId, array $data = []): bool
    {
        $outboxIds = [];
        $integration = new ErpIntegrationService();
        Db::transaction(function () use ($returnId, $data, $integration, &$outboxIds) {
            $now    = time();
            $return = $this->findReturn($returnId, true);

            // 移动端弱网环境下可能在服务端已成功后重试确认。已确认直接按幂等成功返回，
            // 禁止因为重复请求再次回库或重复生成客户退款应付。
            if ((string)$return->status === 'confirmed') {
                return;
            }
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

            $returnSaleOrderId = (int)$return->sale_order_id;
            $returnReceivable = $this->findSaleReceivable($returnSaleOrderId);
            $confirmedSettledMap = $returnReceivable === null
                ? []
                : $this->saleItemSettledMap($returnReceivable, $returnSaleOrderId);

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
            $cashPayableItems = [];

            foreach ($items as $item) {
                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id',      '=', (int)$item->asset_id],
                ])->lock(true)->findOrEmpty();

                if ($asset->isEmpty()) {
                    throw new CommonException('退货设备不存在，asset_id=' . $item->asset_id);
                }
                if ((string)$asset->status !== ErpDict::ASSET_SOLD) {
                    throw new CommonException('设备【' . (string)$asset->imei . '】当前状态不是已售');
                }

                $saleOrderId  = (int)$asset->sale_order_id;
                $receivable   = $this->findSaleReceivable($saleOrderId);
                $receivedAmt  = round((float)($confirmedSettledMap[(int)$asset->id] ?? $item->received_amount), 2);
                $returnPrice  = round((float)$item->return_price, 2);
                if (abs($receivedAmt - (float)$item->received_amount) > 0.0001) {
                    $item->save(['received_amount' => $receivedAmt]);
                }

                // 只冲销尚未收取的应收；已经收取的部分形成逐台退款应付。
                $refundPayable = min($receivedAmt, $returnPrice);
                $unreceivedOffset = max(0, round($returnPrice - $refundPayable, 2));
                if ($unreceivedOffset > 0.0001) {
                    $this->reduceReceivable($receivable, $unreceivedOffset);
                }
                if ($refundPayable > 0.0001) {
                    $payableId = $this->createReturnPayable($return, $item, $refundPayable, $remark, $now);
                    $cashPayableItems[] = ['payable_id' => $payableId, 'amount' => $refundPayable];
                }

                // ── 设备回库 ──────────────────────────────────────────────
                $beforeStatus       = (string)$asset->status;
                $beforeWarehouseId  = (int)$asset->warehouse_id;
                $beforeWarehouseName= (string)$asset->warehouse_name;
                $beforeLocationId   = (int)$asset->location_id;
                $beforeLocationName = (string)$asset->location_name;
                $totalCost          = round((float)$asset->total_cost, 2);

                $asset->save([
                    'status'         => ErpDict::ASSET_IN_STOCK,
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
                ErpSaleItem::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$item->sale_item_id],
                ])->update([
                    'status' => ErpDict::ASSET_RETURNED,
                    'update_at' => $now,
                ]);

                // ── 库存流水 ──────────────────────────────────────────────
                $ledger->asset([
                    'asset_id'              => (int)$asset->id,
                    'action'                => 'sale_return',
                    'before_status'         => $beforeStatus,
                    'after_status'          => ErpDict::ASSET_IN_STOCK,
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

                $requiredConsumers = (string)$asset->sale_target === 'mall'
                    ? ['phone_shop.erp_asset_state']
                    : [];
                $sourceDeviceId = (string)$asset->source_plugin === 'hsx_recycle' && is_numeric((string)$asset->source_id)
                    ? (int)$asset->source_id
                    : 0;
                $queued = $integration->enqueueDomainEvent(
                    'erp.asset.returned.v1',
                    'asset',
                    (int)$asset->id,
                    [
                        'asset_id' => (int)$asset->id,
                        'asset_no' => (string)$asset->asset_no,
                        'source_device_id' => $sourceDeviceId,
                        'imei' => (string)$asset->imei,
                        'model' => (string)$asset->model,
                        'spec' => (string)$asset->spec,
                        'outbound_no' => (string)$return->sale_no,
                        'return_no' => (string)$return->return_no,
                        'return_type' => 'sale_return',
                        'return_reason' => (string)($item->reason ?: $remark),
                        'party_id' => (int)$return->party_id,
                        'party_name' => (string)$return->party_name,
                        'warehouse_id' => (int)$retWarehouse->id,
                        'location_id' => (int)$retLocation->id,
                        'sale_target' => (string)$asset->sale_target,
                        'snapshot_at' => $now,
                    ],
                    [],
                    $requiredConsumers
                );
                $outboxIds[] = (int)$queued['id'];

                $activeSaleItemCount = (int)ErpSaleItem::where([
                    ['site_id', '=', $this->site_id],
                    ['sale_order_id', '=', $saleOrderId],
                    ['status', '=', ErpDict::ASSET_SOLD],
                ])->count();
                if ($activeSaleItemCount <= 0) {
                    ErpSaleOrder::where([
                        ['site_id', '=', $this->site_id],
                        ['id', '=', $saleOrderId],
                    ])->update([
                        'status' => ErpDict::ASSET_RETURNED,
                        'update_at' => $now,
                    ]);
                }

                // 刷新销售单财务状态
                $this->refreshSaleFinance($saleOrderId);
            }

            if ((string)$return->refund_mode === 'cash' && $cashPayableItems !== []) {
                $capitalAccountId = (int)($data['capital_account_id'] ?? $return->capital_account_id ?? 0);
                if ($capitalAccountId <= 0) {
                    throw new CommonException('现场退款必须选择退款账户');
                }
                (new ErpFinanceService())->confirmPayableItemsInTransaction(
                    (int)$return->party_id,
                    $cashPayableItems,
                    [
                        'capital_account_id' => $capitalAccountId,
                        'voucher_urls' => $data['voucher_urls'] ?? '',
                        'confirmed_at' => $now,
                        'remark' => $remark !== '' ? $remark . '（现场退款）' : '销售退货现场退款：公司向客户【' . (string)$return->party_name . '】退还货款',
                        'request_id' => '',
                    ]
                );
            }

            $return->save([
                'status'    => 'confirmed',
                'update_at' => $now,
            ]);
        });

        foreach (array_values(array_unique($outboxIds)) as $outboxId) {
            $integration->dispatchDomainEvent((int)$outboxId);
        }

        return true;
    }

    /** 撤销退货：待确认可直接取消；已回库且退款应付尚未结算时可恢复原销售关系。 */
    public function cancel(int $returnId, string $remark = ''): bool
    {
        Db::transaction(function () use ($returnId, $remark) {
            $return = $this->findReturn($returnId, true);
            if ((string)$return->status === 'pending') {
                $return->save([
                    'status' => 'cancelled',
                    'remark' => $remark !== '' ? ((string)$return->remark . ' / ' . $remark) : (string)$return->remark,
                    'update_at' => time(),
                ]);
                return;
            }

            if ((string)$return->status !== 'confirmed' || (string)$return->business_type === 'after_sale_compensation') {
                throw new CommonException('当前退货单不能撤销');
            }
            if ((string)$return->refund_mode === 'cash') {
                throw new CommonException('现场退款已经产生实际出款，不能普通撤销，请走财务冲正');
            }

            $payables = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'sale_return'],
                ['source_id', '=', $returnId],
            ])->lock(true)->select();
            $payableByAsset = [];
            foreach ($payables as $payable) {
                if ((float)$payable->settled_amount > 0.0001) {
                    throw new CommonException('退款应付已发生付款或折账，不能普通撤销，请先由财务冲正');
                }
                $payableByAsset[(int)$payable->asset_id] = $payable;
            }

            $items = ErpSaleReturnItem::where([
                ['site_id', '=', $this->site_id],
                ['return_id', '=', $returnId],
            ])->order('id asc')->select();
            $receivable = $this->findSaleReceivable((int)$return->sale_order_id);
            $restoreReceivable = 0.0;
            $ledger = new ErpLedgerService();
            $now = time();
            foreach ($items as $item) {
                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$item->asset_id],
                ])->lock(true)->findOrEmpty();
                if ($asset->isEmpty() || (string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
                    throw new CommonException('设备已发生新的库存或销售动作，不能撤销本次退货');
                }
                $saleItem = ErpSaleItem::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$item->sale_item_id],
                ])->lock(true)->findOrEmpty();
                if ($saleItem->isEmpty()) {
                    throw new CommonException('原销售设备明细不存在，不能撤销退货');
                }
                $payable = $payableByAsset[(int)$item->asset_id] ?? null;
                $refundPayable = $payable ? round((float)$payable->amount, 2) : 0.0;
                $restoreReceivable = round($restoreReceivable + max(0, (float)$item->return_price - $refundPayable), 2);
                if ($payable) {
                    $payable->save(['status' => ErpDict::STATUS_VOID, 'update_at' => $now]);
                }
                $asset->save([
                    'status' => ErpDict::ASSET_SOLD,
                    'sale_order_id' => (int)$return->sale_order_id,
                    'sale_item_id' => (int)$saleItem->id,
                    'sale_price' => (float)$saleItem->sale_price,
                    'profit' => (float)$saleItem->profit,
                    'update_at' => $now,
                ]);
                $saleItem->save(['status' => ErpDict::ASSET_SOLD, 'update_at' => $now]);
                $ledger->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'sale_return_cancel',
                    'before_status' => ErpDict::ASSET_IN_STOCK,
                    'after_status' => ErpDict::ASSET_SOLD,
                    'before_total_cost' => (float)$asset->total_cost,
                    'after_total_cost' => (float)$asset->total_cost,
                    'party_id' => (int)$return->party_id,
                    'party_name' => (string)$return->party_name,
                    'source_type' => 'sale_return_cancel',
                    'source_id' => $returnId,
                    'source_no' => (string)$return->return_no,
                    'occurred_at' => $now,
                    'remark' => $remark !== '' ? $remark : '客户取消退货，设备恢复原销售关系',
                ]);
                $ledger->account([
                    'biz_type' => 'sale_return_cancel',
                    'direction' => 'increase',
                    'amount' => (float)$item->return_price,
                    'party_id' => (int)$return->party_id,
                    'party_name' => (string)$return->party_name,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'sale_return_cancel',
                    'source_id' => $returnId,
                    'source_no' => (string)$return->return_no,
                    'remark' => $remark !== '' ? $remark : '销售退货撤销，恢复原销售账务关系',
                ]);
            }
            if ($receivable && $restoreReceivable > 0.0001) {
                $amount = round((float)$receivable->amount + $restoreReceivable, 2);
                $receivable->save([
                    'amount' => $amount,
                    'status' => ErpDict::financeStatus($amount, (float)$receivable->settled_amount),
                    'update_at' => $now,
                ]);
            }
            ErpSaleOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$return->sale_order_id],
            ])->update(['status' => ErpDict::STATUS_COMPLETED, 'update_at' => $now]);
            $return->save([
                'status' => 'cancelled',
                'remark' => $remark !== '' ? ((string)$return->remark . ' / ' . $remark) : (string)$return->remark,
                'update_at' => $now,
            ]);
            $this->refreshSaleFinance((int)$return->sale_order_id);
        });
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
        if (!empty($where['operator_id'])) {
            $query->where('operator_id', '=', (int)$where['operator_id']);
        }
        if (!empty($where['imei'])) {
            $returnIds = ErpSaleReturnItem::where('site_id', '=', $this->site_id)
                ->whereLike('imei|asset_no', '%' . trim((string)$where['imei']) . '%')
                ->column('return_id');
            $query->whereIn('id', array_map('intval', $returnIds ?: [0]));
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $returnIds = ErpSaleReturnItem::where('site_id', '=', $this->site_id)
                ->whereLike('imei|asset_no|model', '%' . $kw . '%')
                ->column('return_id');
            $query->where(function ($sub) use ($kw, $returnIds) {
                $sub->whereLike('return_no|sale_no|party_name|operator_name|remark', '%' . $kw . '%');
                if ($returnIds !== []) {
                    $sub->whereOr('id', 'in', array_map('intval', $returnIds));
                }
            });
        }
        if (!empty($where['start_at'])) {
            $query->where('occurred_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('occurred_at', '<=', (int)$where['end_at']);
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page'      => (int)($where['page'] ?? 1),
        ])->toArray();
        $ids = array_values(array_filter(array_map(static fn(array $row): int => (int)$row['id'], $page['data'] ?? [])));
        $itemMap = [];
        if ($ids !== []) {
            $items = ErpSaleReturnItem::where('site_id', '=', $this->site_id)->whereIn('return_id', $ids)
                ->field('id,return_id,asset_id,asset_no,imei,model,return_price,reason')->order('id asc')->select()->toArray();
            foreach ($items as $item) $itemMap[(int)$item['return_id']][] = $item;
        }
        $settledReturnIds = $ids === [] ? [] : ErpPayable::where([
            ['site_id', '=', $this->site_id], ['source_type', '=', 'sale_return'],
        ])->whereIn('source_id', $ids)->where('settled_amount', '>', 0)->column('source_id');
        $settledMap = array_fill_keys(array_map('intval', $settledReturnIds), true);
        foreach ($page['data'] as &$row) {
            $row['items'] = $itemMap[(int)$row['id']] ?? [];
            $row['item_count'] = count($row['items']);
            $row['first_item'] = $row['items'][0] ?? null;
            $row['can_cancel'] = (string)$row['status'] === 'pending'
                || ((string)$row['status'] === 'confirmed' && (string)$row['refund_mode'] === 'payable' && empty($settledMap[(int)$row['id']]));
        }
        unset($row);
        return $page;
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
        $receivable = ErpReceivable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'sale'],
            ['source_id', '=', (int)$return['sale_order_id']],
        ])->findOrEmpty();
        $settledMap = $receivable->isEmpty()
            ? []
            : $this->saleItemSettledMap($receivable, (int)$return['sale_order_id']);
        $payableMap = [];
        $payables = ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'sale_return'],
            ['source_id', '=', $returnId],
        ])->field('id,payable_no,asset_id,amount,settled_amount,status')->select()->toArray();
        foreach ($payables as $payable) {
            $payableMap[(int)$payable['asset_id']] = $payable;
        }
        $assetIds = array_values(array_filter(array_map(
            static fn(array $item): int => (int)($item['asset_id'] ?? 0),
            $return['items']
        )));
        $assetMap = [];
        if ($assetIds !== []) {
            $assets = ErpAsset::where('site_id', '=', $this->site_id)
                ->whereIn('id', $assetIds)
                ->field('id,asset_no,imei,sn,model,spec,warehouse_id,warehouse_name,location_id,location_name,status')
                ->select()
                ->toArray();
            foreach ($assets as $asset) {
                $assetMap[(int)$asset['id']] = $asset;
            }
        }
        foreach ($return['items'] as &$item) {
            $asset = $assetMap[(int)($item['asset_id'] ?? 0)] ?? [];
            $receivedAmount = round((float)($settledMap[(int)($item['asset_id'] ?? 0)] ?? $item['received_amount'] ?? 0), 2);
            $returnPrice = round((float)($item['return_price'] ?? 0), 2);
            $payable = $payableMap[(int)($item['asset_id'] ?? 0)] ?? [];
            $item['received_amount'] = $receivedAmount;
            $item['refund_payable_amount'] = $payable !== []
                ? round((float)$payable['amount'], 2)
                : min($receivedAmount, $returnPrice);
            $item['refund_payable_no'] = (string)($payable['payable_no'] ?? '');
            $item['refund_payable_status'] = (string)($payable['status'] ?? '');
            $item['refund_settled_amount'] = round((float)($payable['settled_amount'] ?? 0), 2);
            $item['refund_remain_amount'] = max(0, round($item['refund_payable_amount'] - $item['refund_settled_amount'], 2));
            $item['unreceived_offset_amount'] = max(0, round($returnPrice - $item['refund_payable_amount'], 2));
            $item['sn'] = (string)($asset['sn'] ?? '');
            $item['spec'] = (string)($asset['spec'] ?? '');
            $item['warehouse_name'] = (string)($asset['warehouse_name'] ?? '');
            $item['location_name'] = (string)($asset['location_name'] ?? '');
            $item['asset_status'] = (string)($asset['status'] ?? '');
        }
        unset($item);
        $hasSettledRefund = false;
        foreach ($payables as $payable) {
            if ((float)($payable['settled_amount'] ?? 0) > 0.0001) $hasSettledRefund = true;
        }
        $return['can_cancel'] = (string)$return['status'] === 'pending'
            || ((string)$return['status'] === 'confirmed' && (string)$return['business_type'] !== 'after_sale_compensation'
                && (string)$return['refund_mode'] === 'payable' && !$hasSettledRefund);
        return $return;
    }

    // ─── 私有辅助 ────────────────────────────────────────────────────────────

    private function findReturn(int $id, bool $forUpdate = false): ErpSaleReturnOrder
    {
        $query = ErpSaleReturnOrder::where([
            ['site_id', '=', $this->site_id],
            ['id',      '=', $id],
        ]);
        if ($forUpdate) {
            $query->lock(true);
        }
        $row = $query->findOrEmpty();
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
        ])->lock(true)->findOrEmpty();
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
        ])->lock(true)->findOrEmpty();
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

    /**
     * 设备级已收金额：优先使用逐台收款流水；历史整单收款按各设备尚未分摊金额比例补齐。
     */
    private function saleItemSettledMap(ErpReceivable $receivable, int $saleOrderId): array
    {
        $map = $this->receivableItemSettledMap((int)$receivable->id);
        $fallback = max(0, round((float)$receivable->settled_amount - array_sum($map), 2));
        if ($fallback <= 0.0001) {
            return $map;
        }
        $items = ErpSaleItem::where([
            ['site_id', '=', $this->site_id],
            ['sale_order_id', '=', $saleOrderId],
        ])->field('asset_id,sale_price')->order('id asc')->select()->toArray();
        $capacities = [];
        foreach ($items as $row) {
            $assetId = (int)$row['asset_id'];
            $capacities[$assetId] = max(0, round((float)$row['sale_price'] - (float)($map[$assetId] ?? 0), 2));
        }
        $capacityTotal = round(array_sum($capacities), 2);
        if ($capacityTotal <= 0.0001) {
            return $map;
        }
        $remaining = min($fallback, $capacityTotal);
        $remainingCapacity = $capacityTotal;
        foreach ($capacities as $assetId => $capacity) {
            if ($capacity <= 0.0001 || $remaining <= 0.0001) {
                continue;
            }
            $allocated = $remainingCapacity <= $capacity + 0.0001
                ? $remaining
                : round($remaining * $capacity / $remainingCapacity, 2);
            $allocated = min($capacity, $allocated, $remaining);
            $map[$assetId] = round((float)($map[$assetId] ?? 0) + $allocated, 2);
            $remaining = round($remaining - $allocated, 2);
            $remainingCapacity = round($remainingCapacity - $capacity, 2);
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
    ): int {
        $existing = ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'sale_return'],
            ['source_id', '=', (int)$return->id],
            ['asset_id', '=', (int)$item->asset_id],
        ])->lock(true)->findOrEmpty();
        if (!$existing->isEmpty()) {
            $existingAmount = round((float)$existing->amount, 2);
            if (abs($existingAmount - round($receivedAmount, 2)) > 0.0001
                || (int)$existing->party_id !== (int)$return->party_id) {
                throw new CommonException(sprintf(
                    '退货设备【%s】已存在金额不一致的退款应付，请财务核对后再处理',
                    (string)($item->imei ?: $item->asset_id)
                ));
            }
            return (int)$existing->id;
        }

        $sale = ErpSaleOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$return->sale_order_id],
        ])->field('sale_channel,sale_channel_key,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_id,origin_no')->find();
        $isCompensation = (string)$return->business_type === 'after_sale_compensation';
        $sourceService = new ErpFinanceSourceService();
        $source = $sourceService->saleReturn($isCompensation, [
            'origin_no' => (string)$return->return_no,
            'sale_channel_key' => (string)($sale?->sale_channel_key ?? ''),
            'sale_channel' => (string)($sale?->sale_channel ?? ''),
            'business_reason' => $isCompensation
                ? '售后补差形成公司对客户的应付；设备继续由客户持有，付款后冲减设备实际销售收入。'
                : '客户退回已售设备，已收金额 ¥' . number_format($receivedAmount, 2, '.', '') . ' 形成销售退款应付。',
        ]);
        $payable = ErpPayable::create(array_merge([
            'site_id'        => $this->site_id,
            'payable_no'     => ErpLedgerService::makeNo('AP'),
            'party_id'       => (int)$return->party_id,
            'party_name'     => (string)$return->party_name,
            'source_type'    => 'sale_return',
            'source_id'      => (int)$return->id,
            'source_no'      => (string)$return->return_no,
            'asset_id'       => (int)$item->asset_id,
            'amount'         => $receivedAmount,
            'settled_amount' => 0,
            'status'         => ErpDict::STATUS_PENDING,
            'occurred_at'    => $now,
            'remark'         => sprintf(
                '销售退货退款：%s（IMEI %s）%s',
                (string)$item->model,
                (string)$item->imei,
                $remark !== '' ? '；' . $remark : ''
            ),
            'create_at'      => $now,
            'update_at'      => $now,
        ], $sourceService->persistable($source)));
        return (int)$payable->id;
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

    private function existingReturnId(string $requestId): int
    {
        if ($requestId === '') {
            return 0;
        }
        $return = ErpSaleReturnOrder::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
        ])->findOrEmpty();
        return $return->isEmpty() ? 0 : (int)$return->id;
    }
}
