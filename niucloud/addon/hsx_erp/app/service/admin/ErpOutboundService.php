<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetMoveLog;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\ErpOutboundItem;
use addon\hsx_erp\app\model\ErpOutboundOrder;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\FinanceReceivable;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * ERP 出库 / 调拨(同行出货)
 *
 * 同行出货 = 把在库设备卖给同行并出库。支持:
 *   - 现结(settle_mode=now): 出库即填价, 立即生成"同行欠我"的应收。
 *   - 价格回填(settle_mode=later): 先出库, 价格以后补; 回填后再生成应收。
 *   - 报废出库(type=scrap): 仅离库, 不产生应收。
 * 往来单位锚 = member_id(同行也在后台建 member + 关联企业主体), 与回收应付同锚, 可折账。
 * 应收通过标准事件 FinanceReceivableCreated 发给财务中心(幂等), 故障隔离。
 */
class ErpOutboundService extends BaseAdminService
{
    /** 物理在库、可出库的状态 */
    private function outboundableStatuses(): array
    {
        return [
            ErpDict::INVENTORY_IN_STOCK,
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
            ErpDict::INVENTORY_PENDING_PRICING,
        ];
    }

    /**
     * 创建出库单
     * @param array $p [outbound_type, counterparty_id, counterparty_name, counterparty_enterprise_id,
     *                  settle_mode, remark, items=[{asset_id, sale_price}]]
     */
    public function createOutbound(array $p): array
    {
        $type = (string)($p['outbound_type'] ?? ErpDict::OUTBOUND_TYPE_PEER_SALE);
        $settleMode = (string)($p['settle_mode'] ?? ErpDict::SETTLE_MODE_NOW);
        $items = is_array($p['items'] ?? null) ? $p['items'] : [];
        $cpId = (int)($p['counterparty_id'] ?? 0);
        $capitalAccountId = (int)($p['capital_account_id'] ?? 0);

        if (empty($items)) {
            throw new CommonException('请选择要出库的设备');
        }
        if ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && $cpId <= 0) {
            throw new CommonException('同行销售出库必须选择对接人(交易人)');
        }
        // 报废无结算
        if ($type !== ErpDict::OUTBOUND_TYPE_PEER_SALE) {
            $settleMode = ErpDict::SETTLE_MODE_NONE;
        }
        // 现结必须每台有价 + 必须选收款户头(出库即收款入账)
        if ($settleMode === ErpDict::SETTLE_MODE_NOW) {
            foreach ($items as $it) {
                if (round((float)($it['sale_price'] ?? 0), 2) <= 0) {
                    throw new CommonException('现结出库必须为每台填写出货价');
                }
            }
            if ($capitalAccountId <= 0) {
                throw new CommonException('现结出库请选择收款户头(款项即时入账)');
            }
        }
        // 设备目标状态: 现结=已售下架(outbound); 挂单/未定价=锁定可退(locked); 报废/其他=已出库
        $targetStatus = ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && $settleMode === ErpDict::SETTLE_MODE_LATER)
            ? ErpDict::INVENTORY_LOCKED
            : ErpDict::INVENTORY_OUTBOUND;

        $assetIds = array_values(array_unique(array_map(static fn($it) => (int)($it['asset_id'] ?? 0), $items)));
        $assetIds = array_filter($assetIds);
        if (empty($assetIds)) {
            throw new CommonException('设备无效');
        }
        $priceMap = [];
        $consignorMap = []; // 代卖设备卖出时应付寄卖人金额(人手填, 可选)
        foreach ($items as $it) {
            $priceMap[(int)$it['asset_id']] = round((float)($it['sale_price'] ?? 0), 2);
            $consignorMap[(int)$it['asset_id']] = round((float)($it['consignor_payable'] ?? 0), 2);
        }

        $now = time();
        $no = 'CK' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $priceStatus = $settleMode === ErpDict::SETTLE_MODE_LATER ? ErpDict::OUTBOUND_PRICE_PENDING : ErpDict::OUTBOUND_PRICE_FILLED;
        $outboundId = 0;
        $emitItems = [];
        $emitConsignPayables = []; // 代卖卖出 → 应付寄卖人
        $soldDevices = [];         // 同行销售已售设备(来源回收), 出库后通知回收"已售/下架"

        Db::transaction(function () use ($assetIds, $priceMap, $consignorMap, $type, $settleMode, $priceStatus, $targetStatus, $cpId, $p, $no, $now, &$outboundId, &$emitItems, &$emitConsignPayables, &$soldDevices) {
            // 锁定并校验资产
            $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['id', 'in', $assetIds]])->select();
            if (count($assets) !== count($assetIds)) {
                throw new CommonException('部分设备不存在');
            }
            $total = 0.0;
            $qty = 0;
            $itemRows = [];
            foreach ($assets as $asset) {
                if (!in_array((string)$asset->inventory_status, $this->outboundableStatuses(), true)) {
                    throw new CommonException('设备[' . $asset->asset_no . ']当前状态不可出库');
                }
                $price = $priceMap[(int)$asset->id] ?? 0.0;
                $asset->inventory_status = $targetStatus;
                $asset->stock_out_at = $now;
                if ($cpId > 0) {
                    $asset->counterparty_id = $cpId;
                }
                if ($price > 0) {
                    $asset->current_sale_price = $price;
                }
                $asset->version = (int)$asset->version + 1;
                $asset->update_at = $now;
                $asset->save();

                $itemRows[] = [
                    'asset_id'         => (int)$asset->id,
                    'source_device_id' => (int)$asset->source_device_id,
                    'imei'             => (string)$asset->imei,
                    'model'            => (string)$asset->model,
                    'cost'             => round((float)$asset->current_cost, 2),
                    'sale_price'       => $price,
                ];
                // 同行销售且来源回收 → 出库后通知回收"已售/下架"(无商城也能闭环)
                if ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && (int)$asset->source_device_id > 0) {
                    $soldDevices[] = ['asset_id' => (int)$asset->id, 'source_device_id' => (int)$asset->source_device_id];
                }
                // 代卖设备卖出 → 收集"应付寄卖人"(人手填金额, 锚定该设备的寄卖人 counterparty_id)
                if ((string)$asset->ownership_type === ErpDict::OWNERSHIP_CONSIGN) {
                    $cpAmount = $consignorMap[(int)$asset->id] ?? 0.0;
                    if ($cpAmount > 0 && (int)$asset->counterparty_id > 0) {
                        $emitConsignPayables[] = [
                            'asset_id'  => (int)$asset->id,
                            'cp_id'     => (int)$asset->counterparty_id,
                            'device_id' => (int)$asset->source_device_id,
                            'amount'    => $cpAmount,
                        ];
                    }
                }
                $total += $price;
                $qty++;
            }

            $order = ErpOutboundOrder::create([
                'site_id'                    => $this->site_id,
                'outbound_no'                => $no,
                'outbound_type'              => $type,
                'counterparty_id'            => $cpId,
                'counterparty_name'          => (string)($p['counterparty_name'] ?? ''),
                'counterparty_enterprise_id' => (int)($p['counterparty_enterprise_id'] ?? 0),
                'settle_mode'                => $settleMode,
                'price_status'               => $priceStatus,
                'total_amount'               => round($total, 2),
                'qty'                        => $qty,
                'status'                     => ErpDict::OUTBOUND_STATUS_COMPLETED,
                'operator_uid'               => (int)$this->uid,
                'operator_name'              => (string)$this->username,
                'remark'                     => (string)($p['remark'] ?? ''),
                'out_at'                     => $now,
                'create_at'                  => $now,
                'update_at'                  => $now,
            ]);
            $outboundId = (int)$order->id;

            foreach ($itemRows as $row) {
                $item = ErpOutboundItem::create(array_merge($row, [
                    'site_id'            => $this->site_id,
                    'outbound_id'        => $outboundId,
                    'receivable_emitted' => 0,
                    'create_at'          => $now,
                ]));
                // 同行销售且有价 → 待处理应收(现结=生成后立即结清; 挂单=生成待结应收)
                if ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && $row['sale_price'] > 0) {
                    $emitItems[] = ['item_id' => (int)$item->id, 'price' => $row['sale_price'], 'device_id' => $row['source_device_id']];
                }
            }
        });

        // 应收处理(故障隔离):
        //   现结(now): 生成应收并"立即结清", 钱进所选户头 → 应收明细见已结清记录 + 结算记录 + 资金流水, 对账完整
        //   挂单(later): 此处无价(价格回填时再生成待结应收)
        if (!empty($emitItems)) {
            if ($settleMode === ErpDict::SETTLE_MODE_NOW) {
                $this->emitAndSettleNow($outboundId, $cpId, (string)($p['counterparty_name'] ?? ''), $no, $emitItems, $capitalAccountId, $now);
            } else {
                $this->emitReceivables($outboundId, $cpId, (string)($p['counterparty_name'] ?? ''), $no, $emitItems);
            }
        }
        // 代卖卖出 → 发应付寄卖人(故障隔离)
        if (!empty($emitConsignPayables)) {
            $this->emitConsignorPayables($no, $emitConsignPayables);
        }

        // 同行销售出库 → 通知回收"已售/下架"(回收 downstream_stage 推进到 SOLD; 无商城也闭环)
        foreach ($soldDevices as $sd) {
            try {
                event('ErpDomainEvent', [
                    'event_name'   => 'erp.asset.sold.v1',
                    'event_id'     => 'erp_sold_' . (int)$sd['asset_id'] . '_' . $now,
                    'site_id'      => (int)$this->site_id,
                    'aggregate_id' => (int)$sd['asset_id'],
                    'payload'      => [
                        'asset_id'         => (int)$sd['asset_id'],
                        'source_device_id' => (int)$sd['source_device_id'],
                        'outbound_no'      => $no,
                        'reason'           => 'peer_sale',
                    ],
                    'operator'     => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 出库通知回收已售失败: ' . $e->getMessage());
            }
        }

        return ['outbound_id' => $outboundId, 'outbound_no' => $no];
    }

    /**
     * 退回/取消出库: 仅"挂单/未定价(锁定中且未收款)"的出库单可退。
     * 把锁定设备恢复在库、作废其应收、出库单置void。现结(已售)需走退货流程, 不在此处。
     */
    public function cancelOutbound(int $outboundId, string $reason = ''): array
    {
        $order = ErpOutboundOrder::where([['site_id', '=', $this->site_id], ['id', '=', $outboundId]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('出库单不存在');
        }
        if ((string)$order->status === ErpDict::OUTBOUND_STATUS_VOID) {
            throw new CommonException('该出库单已退回');
        }
        if ((string)$order->settle_mode === ErpDict::SETTLE_MODE_NOW) {
            throw new CommonException('现结(已收款已售)出库不能退回，请走退货流程');
        }
        // 应收若已部分/全部收款则禁止退回
        $receivables = FinanceReceivable::where([
            ['site_id', '=', $this->site_id], ['source_no', '=', (string)$order->outbound_no],
        ])->select();
        foreach ($receivables as $r) {
            if (round((float)$r->settled_amount, 2) > 0) {
                throw new CommonException('该出库已部分收款，不能直接退回，请走结算/退货');
            }
        }

        $now = time();
        $restored = 0;
        Db::transaction(function () use ($order, $receivables, $now, $reason, &$restored) {
            $items = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['outbound_id', '=', (int)$order->id]])->select();
            $assetIds = array_values(array_filter(array_map(static fn($it) => (int)$it->asset_id, $items->toArray())));
            if (!empty($assetIds)) {
                $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['id', 'in', $assetIds]])->select();
                foreach ($assets as $asset) {
                    // 仅恢复仍处于锁定态的设备(避免覆盖被其它流程改动的状态)
                    if ((string)$asset->inventory_status === ErpDict::INVENTORY_LOCKED) {
                        $asset->inventory_status = ErpDict::INVENTORY_IN_STOCK;
                        $asset->stock_out_at = 0;
                        $asset->version = (int)$asset->version + 1;
                        $asset->update_at = $now;
                        $asset->save();
                        $restored++;
                    }
                }
            }
            // 作废未收款的应收
            foreach ($receivables as $r) {
                $r->save(['status' => FinanceDict::STATUS_VOID, 'update_at' => $now]);
            }
            $order->status = ErpDict::OUTBOUND_STATUS_VOID;
            $order->remark = trim((string)$order->remark . ' [退回:' . ($reason ?: '无') . ']');
            $order->update_at = $now;
            $order->save();
        });

        return ['outbound_id' => $outboundId, 'restored' => $restored];
    }

    /**
     * 回填价格(settle_mode=later 的出库单, 补齐出货价后生成应收)
     * @param int $outboundId
     * @param array $itemPrices [{item_id, sale_price}]
     */
    public function fillPrice(int $outboundId, array $itemPrices): array
    {
        $order = ErpOutboundOrder::where([['site_id', '=', $this->site_id], ['id', '=', $outboundId]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('出库单不存在');
        }
        $priceMap = [];
        foreach ($itemPrices as $row) {
            $priceMap[(int)($row['item_id'] ?? 0)] = round((float)($row['sale_price'] ?? 0), 2);
        }
        if (empty($priceMap)) {
            throw new CommonException('请填写出货价');
        }

        $now = time();
        $emitItems = [];
        Db::transaction(function () use ($order, $priceMap, $now, &$emitItems) {
            $items = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['outbound_id', '=', (int)$order->id]])->select();
            $total = 0.0;
            foreach ($items as $item) {
                $price = $priceMap[(int)$item->id] ?? (float)$item->sale_price;
                if (isset($priceMap[(int)$item->id]) && $price > 0) {
                    $item->sale_price = $price;
                    $item->create_at = $item->create_at; // no-op keep
                    $item->save();
                    // 回填后, 同步资产售价
                    ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])
                        ->update(['current_sale_price' => $price, 'update_at' => $now]);
                    if ((int)$item->receivable_emitted === 0 && $price > 0) {
                        $emitItems[] = ['item_id' => (int)$item->id, 'price' => $price, 'device_id' => (int)$item->source_device_id];
                    }
                }
                $total += (float)$item->sale_price;
            }
            $order->total_amount = round($total, 2);
            $order->price_status = ErpDict::OUTBOUND_PRICE_FILLED;
            $order->update_at = $now;
            $order->save();
        });

        if (!empty($emitItems)) {
            $this->emitReceivables((int)$order->id, (int)$order->counterparty_id, (string)$order->counterparty_name, (string)$order->outbound_no, $emitItems);
        }
        return ['outbound_id' => (int)$order->id, 'emitted' => count($emitItems)];
    }

    /**
     * 调拨: 把设备移到另一个仓库/库位, 并按"目标仓类型"触发联动:
     *   - 进入 mall(二手机仓): 发 ready_for_photo 进中台(中台按 device_id 去重, 已拍过不重拍)
     *   - 离开 mall(原在 mall 现去他仓): 发 erp.asset.delisted.v1 通知商城下架
     *   - 代卖(consign)进 mall 且 consign_action=buyout: 我方买断 → 改 ownership=owned + 计成本 + 对寄卖人发应付(人手填买断价)
     *     consign_action=list(默认): 仅上架代卖, 不动产权/应付, 卖出时再结寄卖人
     * @param array $assetIds
     * @param array $options [consign_action=list|buyout, buyout_prices=[{asset_id,amount}]]
     */
    public function transfer(array $assetIds, int $toWarehouseId, int $toLocationId, string $remark = '', array $options = []): array
    {
        $assetIds = array_values(array_filter(array_map('intval', $assetIds)));
        if (empty($assetIds)) {
            throw new CommonException('请选择要调拨的设备');
        }
        if ($toWarehouseId <= 0) {
            throw new CommonException('请选择目标仓库');
        }
        $consignAction = (string)($options['consign_action'] ?? 'list');
        $buyoutMap = [];
        foreach ((array)($options['buyout_prices'] ?? []) as $row) {
            $buyoutMap[(int)($row['asset_id'] ?? 0)] = round((float)($row['amount'] ?? 0), 2);
        }

        // 目标仓 业务类型 + 是否允许调入（allow_inbound 由仓库管理配置）
        $toWarehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $toWarehouseId]])->findOrEmpty();
        $toType = (string)($toWarehouse->business_type ?? '');
        $toAllowInbound = $toWarehouse->isEmpty() ? 1 : (int)($toWarehouse->allow_inbound ?? 1);
        $now = time();
        $moved = 0;
        $linkages = ['photo' => [], 'delist' => [], 'payable' => [], 'consign_to_recycle' => []]; // 提交后再发事件

        Db::transaction(function () use ($assetIds, $toWarehouseId, $toLocationId, $toType, $toAllowInbound, $remark, $consignAction, $buyoutMap, $now, &$moved, &$linkages) {
            $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['id', 'in', $assetIds]])->lock(true)->select();
            foreach ($assets as $asset) {
                if ((string)$asset->inventory_status === ErpDict::INVENTORY_OUTBOUND) {
                    throw new CommonException('设备[' . $asset->asset_no . ']已出库, 不能调拨');
                }
                $fromWarehouseId = (int)$asset->warehouse_id;
                $fromType = (string)(ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $fromWarehouseId]])->value('business_type') ?: '');

                // 调拨规则：
                //  1) 目标仓「允许调入」开关关闭则禁止调入（仓库管理里配置；代卖仓默认关）；
                //  2) 代卖仓里的设备只能调去二手机仓（买断转回收），不能调往同行仓/暂存仓等其它仓。
                if ($toAllowInbound !== 1) {
                    throw new CommonException('目标仓库不允许调入：设备[' . $asset->asset_no . ']不能调入该仓库（可在仓库管理中开启「允许调入」）');
                }
                if ($fromType === ErpDict::SALE_DESTINATION_CONSIGNMENT
                    && $toType !== ErpDict::SALE_DESTINATION_MALL) {
                    throw new CommonException('设备[' . $asset->asset_no . ']在代卖仓，只能调拨到二手机仓（买断转回收），不能调往其它仓');
                }

                ErpAssetMoveLog::create([
                    'site_id'           => $this->site_id,
                    'asset_id'          => (int)$asset->id,
                    'from_warehouse_id' => $fromWarehouseId,
                    'from_location_id'  => (int)$asset->location_id,
                    'to_warehouse_id'   => $toWarehouseId,
                    'to_location_id'    => $toLocationId,
                    'operator_uid'      => (int)$this->uid,
                    'operator_name'     => (string)$this->username,
                    'remark'            => $remark,
                    'create_at'         => $now,
                ]);

                // 代卖进 mall 且选择买断: 改产权 + 计成本(买断价计入成本)
                $isConsign = (string)$asset->ownership_type === ErpDict::OWNERSHIP_CONSIGN;
                if ($isConsign && $toType === ErpDict::SALE_DESTINATION_MALL && $consignAction === 'buyout') {
                    $buyout = $buyoutMap[(int)$asset->id] ?? 0.0;
                    if ($buyout <= 0) {
                        throw new CommonException('代卖买断必须为设备[' . $asset->asset_no . ']填写买断价');
                    }
                    $beforeCost = round((float)$asset->current_cost, 2);
                    $afterCost = round($beforeCost + $buyout, 2);
                    ErpCostLedger::create([
                        'site_id'       => $this->site_id,
                        'ledger_no'     => 'CL' . date('YmdHis') . random_int(1000, 9999),
                        'asset_id'      => (int)$asset->id,
                        'cycle_id'      => (int)$asset->cycle_id,
                        'cost_type'     => 'buyout',
                        'amount_delta'  => $buyout,
                        'before_cost'   => $beforeCost,
                        'after_cost'    => $afterCost,
                        'counterparty_id' => (int)$asset->counterparty_id,
                        'operator_id'   => (int)$this->uid,
                        'operator_name' => (string)$this->username,
                        'occurred_at'   => $now,
                        'remark'        => '代卖买断, 买断价计入成本',
                    ]);
                    $asset->ownership_type = ErpDict::OWNERSHIP_OWNED;
                    $asset->purchase_cost = round((float)$asset->purchase_cost + $buyout, 2);
                    $asset->current_cost = $afterCost;
                    // 代卖转回收：原代卖参考价失效，清零等中台按回收成本重新拍照定价
                    $asset->current_sale_price = 0;
                    // 应付寄卖人(买断价) → 提交后发
                    $linkages['payable'][] = ['asset_id' => (int)$asset->id, 'cp_id' => (int)$asset->counterparty_id, 'device_id' => (int)$asset->source_device_id, 'amount' => $buyout, 'reason' => 'consign_buyout'];
                    // 通知回收侧：该设备由代卖转回收（成本转移到我方），由回收业务体现 → 提交后发
                    $linkages['consign_to_recycle'][] = ['asset_id' => (int)$asset->id, 'device_id' => (int)$asset->source_device_id, 'cp_id' => (int)$asset->counterparty_id, 'amount' => $buyout];
                }

                $asset->warehouse_id = $toWarehouseId;
                $asset->location_id = $toLocationId;
                $asset->version = (int)$asset->version + 1;
                $asset->update_at = $now;
                $asset->save();
                $moved++;

                // 进入 mall → 进中台拍照(中台去重, 已拍过不重拍)
                if ($toType === ErpDict::SALE_DESTINATION_MALL && $fromType !== ErpDict::SALE_DESTINATION_MALL) {
                    $linkages['photo'][] = (int)$asset->id;
                }
                // 离开 mall → 通知商城下架
                if ($fromType === ErpDict::SALE_DESTINATION_MALL && $toType !== ErpDict::SALE_DESTINATION_MALL) {
                    $linkages['delist'][] = (int)$asset->id;
                }
            }
        });

        $this->emitTransferLinkages($linkages, $now);
        return ['moved' => $moved];
    }

    /** 提交后发调拨联动事件(故障隔离, 不影响调拨本身) */
    private function emitTransferLinkages(array $linkages, int $now): void
    {
        foreach ($linkages['photo'] as $assetId) {
            try {
                event('ErpDomainEvent', [
                    'event_name'   => 'erp.asset.ready_for_photo.v1',
                    'event_id'     => 'erp_transfer_photo_' . $assetId . '_' . $now,
                    'site_id'      => (int)$this->site_id,
                    'aggregate_id' => $assetId,
                    'payload'      => ['asset_id' => $assetId, 'sale_destination' => ErpDict::SALE_DESTINATION_MALL, 'source' => 'transfer'],
                    'operator'     => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 调拨进中台发 ready_for_photo 失败: ' . $e->getMessage());
            }
        }
        foreach ($linkages['delist'] as $assetId) {
            try {
                event('ErpDomainEvent', [
                    'event_name'   => 'erp.asset.delisted.v1',
                    'event_id'     => 'erp_transfer_delist_' . $assetId . '_' . $now,
                    'site_id'      => (int)$this->site_id,
                    'aggregate_id' => $assetId,
                    'payload'      => ['asset_id' => $assetId, 'reason' => 'transfer_out_of_mall'],
                    'operator'     => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 离开mall发下架失败: ' . $e->getMessage());
            }
        }
        foreach ($linkages['payable'] as $p) {
            if ((int)$p['cp_id'] <= 0 || (float)$p['amount'] <= 0) {
                Log::warning('[erp] 代卖买断应付缺往来单位或金额, 跳过: asset=' . $p['asset_id']);
                continue;
            }
            try {
                $cpName = (string)(ErpCounterparty::where([['site_id', '=', $this->site_id], ['id', '=', (int)$p['cp_id']]])->value('name') ?: '');
                event('FinancePayableCreated', [
                    'event'             => 'finance.payable.created.v1',
                    'event_id'          => 'erp_consign_buyout_' . (int)$p['asset_id'],
                    'site_id'           => (int)$this->site_id,
                    'counterparty_id'   => (int)$p['cp_id'],
                    'counterparty_name' => $cpName,
                    'amount'            => round((float)$p['amount'], 2),
                    'source_type'       => 'erp_consign_buyout',
                    'source_no'         => 'BUYOUT' . (int)$p['asset_id'],
                    'source_device_id'  => (int)$p['device_id'],
                    'occurred_at'       => $now,
                    'remark'            => '代卖买断, 应付寄卖人',
                ]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 代卖买断发应付失败: ' . $e->getMessage());
            }
        }
        // 代卖转回收：通知回收侧把该设备由代卖标记为回收（成本转移到我方，回收业务体现）。
        // 解耦：回收未装则无人应答；失败只记日志，不影响调拨。
        foreach (($linkages['consign_to_recycle'] ?? []) as $c) {
            try {
                event('ErpConsignDeviceBoughtOut', [
                    'site_id'          => (int)$this->site_id,
                    'source_device_id' => (int)$c['device_id'],
                    'erp_asset_id'     => (int)$c['asset_id'],
                    'counterparty_id'  => (int)$c['cp_id'],
                    'buyout_amount'    => round((float)$c['amount'], 2),
                    'occurred_at'      => $now,
                    'operator'         => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 代卖转回收通知回收失败: ' . $e->getMessage());
            }
        }
    }

    public function getPage(array $where = []): array
    {
        $query = ErpOutboundOrder::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['outbound_type'])) {
            $query->where('outbound_type', '=', (string)$where['outbound_type']);
        }
        if (!empty($where['price_status'])) {
            $query->where('price_status', '=', (string)$where['price_status']);
        }
        if (!empty($where['keyword'])) {
            $query->where('outbound_no|counterparty_name', 'like', '%' . $where['keyword'] . '%');
        }
        if (!empty($where['start_time'])) {
            $query->where('out_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('out_at', '<=', (int)$where['end_time']);
        }
        if (($where['amount_min'] ?? '') !== '') {
            $query->where('total_amount', '>=', (float)$where['amount_min']);
        }
        if (($where['amount_max'] ?? '') !== '') {
            $query->where('total_amount', '<=', (float)$where['amount_max']);
        }
        $sortMap = ['id' => 'id', 'out_at' => 'out_at', 'total_amount' => 'total_amount', 'qty' => 'qty'];
        $sf = $sortMap[(string)($where['sort_field'] ?? '')] ?? 'id';
        $so = strtolower((string)($where['sort_order'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $typeMap = ErpDict::getOutboundTypeMap();
        $list = $query->order($sf, $so)->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page'      => (int)($where['page'] ?? 1),
        ]);
        $data = $list->toArray();
        $settleMap = ['now' => '现结(已收款)', 'later' => '挂单(待收款)', 'none' => '无结算'];
        $priceMap = ['pending' => '待回填价', 'filled' => '价格已定'];
        // 挂单收款情况: 按出库单号(=应收来源单)反查应收, 是否已结清(折账或现金收款都算)
        $recAgg = [];
        $orderNos = array_values(array_filter(array_column($data['data'], 'outbound_no')));
        if (!empty($orderNos)) {
            $recRows = FinanceReceivable::where([['site_id', '=', $this->site_id]])
                ->whereIn('source_no', $orderNos)
                ->field('source_no,amount,settled_amount')->select()->toArray();
            foreach ($recRows as $r) {
                $no = (string)$r['source_no'];
                $recAgg[$no]['cnt'] = ($recAgg[$no]['cnt'] ?? 0) + 1;
                $recAgg[$no]['open'] = round(($recAgg[$no]['open'] ?? 0) + ((float)$r['amount'] - (float)$r['settled_amount']), 2);
            }
        }
        foreach ($data['data'] as &$row) {
            $row['type_text'] = $typeMap[$row['outbound_type']] ?? $row['outbound_type'];
            $row['settle_mode_text'] = $settleMap[(string)($row['settle_mode'] ?? '')] ?? (string)($row['settle_mode'] ?? '');
            $row['price_status_text'] = $priceMap[(string)($row['price_status'] ?? '')] ?? (string)($row['price_status'] ?? '');
            $row['is_void'] = (string)($row['status'] ?? '') === ErpDict::OUTBOUND_STATUS_VOID;
            // 收款状态: 现结=已收款; 挂单=看应收是否结清(有应收且未结额<=0 即已收款)
            $agg = $recAgg[(string)($row['outbound_no'] ?? '')] ?? null;
            $hasRec = $agg && (int)($agg['cnt'] ?? 0) > 0;
            $row['collected'] = (string)($row['settle_mode'] ?? '') === ErpDict::SETTLE_MODE_NOW
                || ($hasRec && (float)($agg['open'] ?? 0) <= 0);
            // 可退回：挂单 + 未作废 + 未收款(已收款的不能退, 需走退货)
            $row['can_cancel'] = !$row['is_void']
                && (string)($row['settle_mode'] ?? '') !== ErpDict::SETTLE_MODE_NOW
                && !$row['collected'];
        }
        unset($row);
        return $data;
    }

    public function getInfo(int $id): array
    {
        $order = ErpOutboundOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('出库单不存在');
        }
        $data = $order->toArray();
        $no = (string)$data['outbound_no'];
        $data['type_text'] = ErpDict::getOutboundTypeMap()[$data['outbound_type']] ?? $data['outbound_type'];
        $settleMap = ['now' => '现结(已收款)', 'later' => '挂单(待收款)', 'none' => '无结算'];
        $data['settle_mode_text'] = $settleMap[(string)$data['settle_mode']] ?? (string)$data['settle_mode'];
        $data['is_void'] = (string)$data['status'] === ErpDict::OUTBOUND_STATUS_VOID;

        // 卖给了谁: 对接人本人 + 所属主体
        $bm = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, [(int)$data['counterparty_id']])[(int)$data['counterparty_id']] ?? null;
        $data['buyer_name'] = $bm ? (string)$bm['name'] : (string)$data['counterparty_name'];
        $data['buyer_mobile'] = $bm ? (string)$bm['mobile'] : '';
        $data['buyer_entity'] = $bm ? (string)$bm['entity_name'] : '';
        $data['buyer_entity_id'] = $bm ? (int)$bm['entity_id'] : 0;

        // 设备明细 + 资产号/库存状态/毛利
        $items = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['outbound_id', '=', $id]])->select()->toArray();
        $assetMap = [];
        $assetIds = array_values(array_filter(array_column($items, 'asset_id')));
        if (!empty($assetIds)) {
            foreach (ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('id', $assetIds)->field('id,asset_no,inventory_status,current_cost')->select()->toArray() as $a) {
                $assetMap[(int)$a['id']] = $a;
            }
        }
        $invMap = ['in_stock' => '在库', 'refurbishing' => '整备中', 'pending_pricing' => '待定价', 'available_for_sale' => '可售', 'locked' => '已售/锁定', 'outbound' => '已售/已出库'];
        $totalCost = 0.0;
        $totalSale = 0.0;
        foreach ($items as &$it) {
            $a = $assetMap[(int)$it['asset_id']] ?? [];
            $cost = round((float)($it['cost'] ?: ($a['current_cost'] ?? 0)), 2);
            $sale = round((float)$it['sale_price'], 2);
            $it['asset_no'] = (string)($a['asset_no'] ?? '');
            $it['inventory_status'] = (string)($a['inventory_status'] ?? '');
            $it['inventory_status_text'] = $invMap[(string)($a['inventory_status'] ?? '')] ?? (string)($a['inventory_status'] ?? '');
            $it['cost'] = $cost;
            $it['profit'] = round($sale - $cost, 2);
            $totalCost += $cost;
            $totalSale += $sale;
        }
        unset($it);
        $data['items'] = $items;
        $data['total_cost'] = round($totalCost, 2);
        $data['total_profit'] = round($totalSale - $totalCost, 2);

        // 财务关联: 应收(本单) + 结算/折账 + 收款流水
        $recs = FinanceReceivable::where([['site_id', '=', $this->site_id], ['source_no', '=', $no]])->select()->toArray();
        $received = 0.0;
        $unreceived = 0.0;
        $statusMap = FinanceDict::getStatusMap();
        foreach ($recs as &$r) {
            $r['status_text'] = $statusMap[$r['status']] ?? $r['status'];
            $r['outstanding'] = round((float)$r['amount'] - (float)$r['settled_amount'], 2);
            $received += (float)$r['settled_amount'];
            $unreceived += $r['outstanding'];
        }
        unset($r);
        $data['receivables'] = $recs;
        $data['received'] = round($received, 2);
        $data['unreceived'] = round(max(0, $unreceived), 2);
        $data['collected'] = (string)$data['settle_mode'] === ErpDict::SETTLE_MODE_NOW || (!empty($recs) && $data['unreceived'] <= 0);

        // 关联结算单(通过应收核销关联)
        $data['settlements'] = [];
        $recIds = array_column($recs, 'id');
        if (!empty($recIds)) {
            $sids = \addon\hsx_erp\app\model\FinanceSettlementLink::where([['site_id', '=', $this->site_id], ['target_type', '=', FinanceDict::TARGET_RECEIVABLE]])
                ->whereIn('target_id', $recIds)->column('settlement_id');
            $sids = array_values(array_unique(array_filter(array_map('intval', $sids))));
            if (!empty($sids)) {
                $data['settlements'] = \addon\hsx_erp\app\model\FinanceSettlement::where([['site_id', '=', $this->site_id]])->whereIn('id', $sids)
                    ->field('id,settlement_no,method,offset_amount,cash_amount,cash_direction,account_name,occurred_at')->select()->toArray();
            }
        }
        // 收款资金流水(本单)
        $data['capital_flows'] = \addon\hsx_erp\app\model\ErpCapitalLedger::where([['site_id', '=', $this->site_id], ['source_no', '=', $no]])
            ->field('ledger_no,direction,amount,account_name,occurred_at,remark')->order('id asc')->select()->toArray();

        return $data;
    }

    /**
     * 现结: 生成应收并立即结清(收款入所选户头)。
     * 直连核心账务服务拿应收ID(幂等键按出库明细), 再用结算服务一次性结清并记现金入账。
     */
    private function emitAndSettleNow(int $outboundId, int $cpId, string $cpName, string $outboundNo, array $emitItems, int $capitalAccountId, int $now): void
    {
        if ($cpId <= 0) {
            return;
        }
        try {
            $core = new \addon\hsx_erp\app\service\core\CoreFinanceLedgerService();
            $rids = [];
            foreach ($emitItems as $it) {
                $rid = (int)$core->recordReceivable([
                    'event_id'          => 'erp_outbound_item_' . (int)$it['item_id'],
                    'site_id'           => (int)$this->site_id,
                    'counterparty_id'   => $cpId,
                    'counterparty_name' => $cpName,
                    'amount'            => round((float)$it['price'], 2),
                    'source_type'       => 'erp_peer_sale',
                    'source_no'         => $outboundNo,
                    'source_device_id'  => (int)($it['device_id'] ?? 0),
                    'occurred_at'       => $now,
                    'remark'            => '同行现结销售',
                ]);
                if ($rid > 0) {
                    $rids[] = $rid;
                    ErpOutboundItem::where([['site_id', '=', $this->site_id], ['id', '=', (int)$it['item_id']]])
                        ->update(['receivable_emitted' => 1]);
                }
            }
            // 立即结清这些应收, 现金收入所选户头(settle 会记一笔 in 的资金流水)
            if (!empty($rids)) {
                (new FinanceSettlementService())->settle($cpId, [], $rids, [
                    'capital_account_id' => $capitalAccountId,
                    'record_cash'        => true,
                    'remark'             => '同行现结收款',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[erp] 现结生成应收并结清失败: ' . $e->getMessage());
        }
    }

    /** 发应收事件给财务中心(同行欠我), 幂等键按出库明细 */
    private function emitReceivables(int $outboundId, int $cpId, string $cpName, string $outboundNo, array $emitItems): void
    {
        if ($cpId <= 0) {
            return;
        }
        $now = time();
        foreach ($emitItems as $it) {
            try {
                event('FinanceReceivableCreated', [
                    'event'             => 'finance.receivable.created.v1',
                    'event_id'          => 'erp_outbound_item_' . (int)$it['item_id'],
                    'site_id'           => (int)$this->site_id,
                    'counterparty_id'   => $cpId,
                    'counterparty_name' => $cpName,
                    'amount'            => round((float)$it['price'], 2),
                    'source_type'       => 'erp_peer_sale',
                    'source_no'         => $outboundNo,
                    'source_device_id'  => (int)($it['device_id'] ?? 0),
                    'occurred_at'       => $now,
                    'remark'            => '同行出货生成应收',
                ]);
                ErpOutboundItem::where([['site_id', '=', $this->site_id], ['id', '=', (int)$it['item_id']]])
                    ->update(['receivable_emitted' => 1]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 同行出货发应收失败: ' . $e->getMessage());
            }
        }
    }

    /** 代卖卖出 → 发应付寄卖人(锚定设备的寄卖人 counterparty_id, 金额人手填), 幂等键按设备 */
    private function emitConsignorPayables(string $outboundNo, array $payables): void
    {
        $now = time();
        foreach ($payables as $p) {
            if ((int)$p['cp_id'] <= 0 || (float)$p['amount'] <= 0) {
                continue;
            }
            try {
                $cpName = (string)(ErpCounterparty::where([['site_id', '=', $this->site_id], ['id', '=', (int)$p['cp_id']]])->value('name') ?: '');
                event('FinancePayableCreated', [
                    'event'             => 'finance.payable.created.v1',
                    'event_id'          => 'erp_consign_sale_' . (int)$p['asset_id'],
                    'site_id'           => (int)$this->site_id,
                    'counterparty_id'   => (int)$p['cp_id'],
                    'counterparty_name' => $cpName,
                    'amount'            => round((float)$p['amount'], 2),
                    'source_type'       => 'erp_consign_sale',
                    'source_no'         => $outboundNo,
                    'source_device_id'  => (int)$p['device_id'],
                    'occurred_at'       => $now,
                    'remark'            => '代卖卖出, 应付寄卖人',
                ]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 代卖卖出发应付失败: ' . $e->getMessage());
            }
        }
    }
}
