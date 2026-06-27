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
use addon\hsx_erp\app\service\admin\concern\SortableQuery;
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
    use SortableQuery;

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
        $payments = is_array($p['payments'] ?? null) ? $p['payments'] : []; // 多账户收款 [{account_id, method, amount}]
        // 是否在商城侧建订单：默认 true(兼容历史)。代下单收银台传 false → 纯 ERP 出库,
        // 商城只把对应商品下架(不建商城订单),ERP 为唯一事实源。
        $buildMallOrder = !array_key_exists('build_mall_order', $p) || (bool)$p['build_mall_order'];

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
        // 现结开关门控:后台关闭现结后,出库只能挂账(收款归财务中心)。
        if ($settleMode === ErpDict::SETTLE_MODE_NOW
            && !\addon\hsx_erp\app\service\core\ErpConfigService::instantSettleAllowed($this->site_id)) {
            throw new CommonException('现结已被关闭,请改用「挂账」出库,款项由财务中心收取');
        }
        // 现结必须每台有价 + 必须选收款户头(出库即收款入账)
        if ($settleMode === ErpDict::SETTLE_MODE_NOW) {
            foreach ($items as $it) {
                if (round((float)($it['sale_price'] ?? 0), 2) <= 0) {
                    throw new CommonException('现结出库必须为每台填写出货价');
                }
            }
            // 收款户头：单账户(capital_account_id) 或 多账户(payments[])，二选一
            if ($capitalAccountId <= 0 && empty($payments)) {
                throw new CommonException('现结出库请选择收款户头(款项即时入账)');
            }
            // 多账户：合计必须等于成交总额
            if (!empty($payments)) {
                $payTotal = 0.0;
                foreach ($payments as $pm) $payTotal += round((float)($pm['amount'] ?? 0), 2);
                $saleTotal = 0.0;
                foreach ($items as $it) $saleTotal += round((float)($it['sale_price'] ?? 0), 2);
                if (round($payTotal, 2) !== round($saleTotal, 2)) {
                    throw new CommonException(sprintf('多账户收款合计 %.2f 与成交总额 %.2f 不一致', $payTotal, $saleTotal));
                }
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
            $saleChannel = (string)($p['sale_channel'] ?? 'peer');
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
                // 同行/商城销售 → 出库后发成交事件；商城可仅凭 asset_id 找 SKU，不强依赖 source_device_id
                if ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && ((int)$asset->source_device_id > 0 || $saleChannel === 'mall')) {
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
                'sale_channel'               => (string)($p['sale_channel'] ?? 'peer'),
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
                'express_no'                 => trim((string)($p['express_no'] ?? '')),
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
                // 开单即建账:只要价格已知,当场就生成应收(现结→应收+立即结清;挂账→待结应收),
                // 财务中心立刻可见,无需再去出库管理"处理挂单"。仅同行无价单(price=0)延后,
                // 由订单管理回填价格时再生成待结应收。
                if ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && $row['sale_price'] > 0) {
                    $emitItems[] = ['item_id' => (int)$item->id, 'price' => $row['sale_price'], 'device_id' => $row['source_device_id']];
                }
            }
        });

        // 应收处理(故障隔离):
        //   现结(now): 生成应收并"立即结清", 钱进所选户头 → 应收明细见已结清记录 + 结算记录 + 资金流水, 对账完整
        //   挂账(later)有价: 开单当场生成"待结应收"(财务中心可见, 由财务收款); 无价(price=0)不在此处入账,留待订单管理回填
        if (!empty($emitItems)) {
            // 销售渠道：mall=商城销售 / peer=同行销售（默认同行）。只影响应收的来源类型与备注，不动出库/库存逻辑。
            $saleChannel = (string)($p['sale_channel'] ?? 'peer');
            $saleSourceType = $saleChannel === 'mall' ? 'erp_mall_sale' : 'erp_peer_sale';
            $saleLabel = $saleChannel === 'mall' ? '商城' : '同行';
            if ($settleMode === ErpDict::SETTLE_MODE_NOW) {
                $this->emitAndSettleNow($outboundId, $cpId, (string)($p['counterparty_name'] ?? ''), $no, $emitItems, $capitalAccountId, $payments, $now, $saleSourceType, $saleLabel);
            } else {
                $this->emitReceivables($outboundId, $cpId, (string)($p['counterparty_name'] ?? ''), $no, $emitItems, $saleSourceType, $saleLabel);
            }
        }
        // 代卖卖出 → 发应付寄卖人(故障隔离)
        if (!empty($emitConsignPayables)) {
            $this->emitConsignorPayables($no, $emitConsignPayables);
        }

        // 同行销售出库 → 通知回收"已售/下架"(回收 downstream_stage 推进到 SOLD; 无商城也闭环)
        // 买家 member_id：本系统"往来单位锚 = member_id"，出库传入的 counterparty_id 即买家 member_id
        $buyerMemberId = $cpId;
        // 结果状态：现结=已售(sold) / 挂单=锁定(locked)，商城据此置商品状态
        $resultStatus = $settleMode === ErpDict::SETTLE_MODE_NOW ? 'sold' : 'locked';

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
                        'reason'           => ((string)($p['sale_channel'] ?? 'peer') === 'mall') ? 'mall_sale' : 'peer_sale',
                        'member_id'        => $buyerMemberId,                       // 商城建订单的买家
                        'sale_price'       => (float)($priceMap[(int)$sd['asset_id']] ?? 0),
                        'settle_mode'      => $settleMode,
                        'result_status'    => $resultStatus,                        // sold / locked
                        'build_mall_order' => $buildMallOrder,                       // false=只下架不建商城订单
                    ],
                    'operator'     => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[erp] 出库通知回收已售失败: ' . $e->getMessage());
            }

            // 仅当 build_mall_order=true 时在商城建订单;false(代下单收银台)只靠 sold 事件下架商品。
            if ($buildMallOrder && (string)($p['sale_channel'] ?? 'peer') === 'mall') {
                $this->syncMallSaleOrder((int)$sd['asset_id'], (int)$sd['source_device_id'], $buyerMemberId, $no, (float)($priceMap[(int)$sd['asset_id']] ?? 0), $settleMode, $now);
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
        $saleChannel = (string)($order->sale_channel ?? 'peer');
        $returnedItems = [];
        Db::transaction(function () use ($order, $receivables, $now, $reason, $saleChannel, &$restored, &$returnedItems) {
            $items = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['outbound_id', '=', (int)$order->id]])->select()->toArray();
            $assetIds = array_values(array_filter(array_map(static fn($it) => (int)($it['asset_id'] ?? 0), $items)));
            if (!empty($assetIds)) {
                $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['id', 'in', $assetIds]])->select();
                foreach ($assets as $asset) {
                    // 仅恢复仍处于锁定态的设备(避免覆盖被其它流程改动的状态)
                    if ((string)$asset->inventory_status === ErpDict::INVENTORY_LOCKED) {
                        // 商城渠道→恢复"可售"(商城回在售); 同行→回在库。与 partialReturn 口径一致。
                        $asset->inventory_status = $saleChannel === 'mall'
                            ? ErpDict::INVENTORY_AVAILABLE_FOR_SALE
                            : ErpDict::INVENTORY_IN_STOCK;
                        $asset->stock_out_at = 0;
                        $asset->counterparty_id = 0;
                        $asset->version = (int)$asset->version + 1;
                        $asset->update_at = $now;
                        $asset->save();
                        $restored++;
                    }
                }
            }
            // 收集退回明细(商城渠道用于回源上架 + 关闭挂账订单)
            foreach ($items as $it) {
                $returnedItems[] = [
                    'asset_id'         => (int)($it['asset_id'] ?? 0),
                    'source_device_id' => (int)($it['source_device_id'] ?? 0),
                ];
            }
            // 作废未收款的应收
            foreach ($receivables as $r) {
                $r->save(['status' => FinanceDict::STATUS_VOID, 'update_at' => $now]);
            }
            $order->status = ErpDict::OUTBOUND_STATUS_VOID;
            $order->remark = trim((string)$order->remark . ' [退回:' . ($reason ?: '无') . ' · 操作人:' . ((string)$this->username ?: ('uid' . (int)$this->uid)) . ' · ' . date('Y-m-d H:i', $now) . ']');
            $order->update_at = $now;
            $order->save();
        });

        // 商城渠道: 通知商城回源上架 + 关闭/退空挂账订单(与 partialReturn 一致, 故障隔离)
        if ($saleChannel === 'mall') {
            foreach ($returnedItems as $ri) {
                if ((int)$ri['asset_id'] <= 0) {
                    continue;
                }
                try {
                    event('ErpDomainEvent', [
                        'event_name'   => 'erp.asset.returned.v1',
                        'event_id'     => 'erp_returned_' . (int)$ri['asset_id'] . '_' . $now,
                        'site_id'      => (int)$this->site_id,
                        'aggregate_id' => (int)$ri['asset_id'],
                        'payload'      => [
                            'asset_id'         => (int)$ri['asset_id'],
                            'source_device_id' => (int)$ri['source_device_id'],
                            'outbound_id'      => $outboundId,
                            'outbound_no'      => (string)$order->outbound_no,
                            'reason'           => 'cancel_outbound',
                        ],
                        'operator' => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[erp] 整单退回通知商城回源上架失败: ' . $e->getMessage());
                }
                $this->syncMallReturned((int)$ri['asset_id'], (string)$order->outbound_no);
            }
        }

        return ['outbound_id' => $outboundId, 'restored' => $restored];
    }

    /**
     * 部分退回: 挂账(settle_mode=later)出库单中, 退回指定明细设备。
     *
     * 每台被退回的设备:
     *   1. 库存状态恢复: 商城渠道→available_for_sale; 同行→in_stock
     *   2. 对应待结应收作废(已收款的拒绝退回, 需走退货流程)
     *   3. 商城渠道额外发 erp.asset.returned.v1 通知商城回源上架
     * 出库单 qty/total_amount 同步缩减; 全部退完则整单 void。
     *
     * @param int    $outboundId
     * @param array  $itemIds   要退回的明细 ID 列表
     * @param string $reason    退回原因
     */
    public function partialReturn(int $outboundId, array $itemIds, string $reason = ''): array
    {
        if (empty($itemIds)) {
            throw new CommonException('请选择要退回的设备');
        }
        $order = ErpOutboundOrder::where([['site_id', '=', $this->site_id], ['id', '=', $outboundId]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('出库单不存在');
        }
        if ((string)$order->status === ErpDict::OUTBOUND_STATUS_VOID) {
            throw new CommonException('该出库单已全部退回');
        }
        if ((string)$order->settle_mode === ErpDict::SETTLE_MODE_NOW) {
            throw new CommonException('现结(已收款已售)出库不支持退回，请走退货流程');
        }

        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

        // 查要退回的明细(必须未退回、属于本单)
        $items = ErpOutboundItem::where([
            ['site_id', '=', $this->site_id],
            ['outbound_id', '=', $outboundId],
            ['is_returned', '=', 0],
        ])->whereIn('id', $itemIds)->select()->toArray();

        if (empty($items)) {
            throw new CommonException('未找到可退回的设备，可能已退回或不属于本单');
        }

        // 应收已部分/全部收款的明细不允许直接退回
        foreach ($items as $it) {
            if ((int)$it['receivable_emitted'] === 1) {
                $recv = FinanceReceivable::where([
                    ['site_id', '=', $this->site_id],
                    ['event_id', '=', 'erp_outbound_item_' . (int)$it['id']],
                ])->field('settled_amount')->findOrEmpty();
                if (!$recv->isEmpty() && round((float)$recv->settled_amount, 2) > 0) {
                    throw new CommonException('设备[' . $it['imei'] . ']对应应收已收款，无法直接退回，请走退货流程');
                }
            }
        }

        $now = time();
        $saleChannel = (string)($order->sale_channel ?? 'peer');
        $returnedItems = [];

        Db::transaction(function () use ($order, $items, $now, $reason, $saleChannel, &$returnedItems) {
            $assetIds = array_values(array_filter(array_map(static fn($it) => (int)($it['asset_id'] ?? 0), $items)));
            $assetMap = [];
            if (!empty($assetIds)) {
                $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['id', 'in', $assetIds]])->lock(true)->select();
                foreach ($assets as $a) {
                    $assetMap[(int)$a->id] = $a;
                }
            }

            foreach ($items as $it) {
                $asset = $assetMap[(int)$it['asset_id']] ?? null;

                // 恢复库存: 仅锁定态的设备才恢复(避免覆盖已被其它流程变更的状态)
                if ($asset && (string)$asset->inventory_status === ErpDict::INVENTORY_LOCKED) {
                    $restoreStatus = $saleChannel === 'mall'
                        ? ErpDict::INVENTORY_AVAILABLE_FOR_SALE
                        : ErpDict::INVENTORY_IN_STOCK;
                    $asset->inventory_status = $restoreStatus;
                    $asset->stock_out_at     = 0;
                    $asset->counterparty_id  = 0;
                    $asset->version          = (int)$asset->version + 1;
                    $asset->update_at        = $now;
                    $asset->save();
                }

                // 标记明细已退回
                ErpOutboundItem::where([['site_id', '=', $this->site_id], ['id', '=', (int)$it['id']]])
                    ->update(['is_returned' => 1, 'returned_at' => $now]);

                // 作废该明细对应的待结应收
                if ((int)$it['receivable_emitted'] === 1) {
                    FinanceReceivable::where([
                        ['site_id', '=', $this->site_id],
                        ['event_id', '=', 'erp_outbound_item_' . (int)$it['id']],
                        ['status', '<>', FinanceDict::STATUS_SETTLED],
                    ])->update(['status' => FinanceDict::STATUS_VOID, 'update_time' => $now]);
                }

                $returnedItems[] = [
                    'asset_id'         => (int)$it['asset_id'],
                    'source_device_id' => (int)$it['source_device_id'],
                ];
            }

            // 重算出库单台数/金额(仅未退回明细)
            $activeItems = ErpOutboundItem::where([
                ['site_id', '=', $this->site_id],
                ['outbound_id', '=', (int)$order->id],
                ['is_returned', '=', 0],
            ])->field('sale_price')->select()->toArray();

            $activeQty   = count($activeItems);
            $activeTotal = round((float)array_sum(array_column($activeItems, 'sale_price')), 2);

            $remarkSuffix = sprintf(
                ' [部分退回%d台：%s · 操作人：%s · %s]',
                count($items),
                $reason ?: '无',
                $this->username ?: ('uid' . $this->uid),
                date('Y-m-d H:i', $now)
            );
            $newRemark = substr(trim((string)$order->remark . $remarkSuffix), 0, 500);

            $updateData = [
                'qty'          => $activeQty,
                'total_amount' => $activeTotal,
                'remark'       => $newRemark,
                'update_at'    => $now,
            ];
            if ($activeQty === 0) {
                $updateData['status'] = ErpDict::OUTBOUND_STATUS_VOID;
            }
            ErpOutboundOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$order->id]])->update($updateData);
        });

        // 商城渠道: 通知商城回源上架(故障隔离)
        if ($saleChannel === 'mall') {
            foreach ($returnedItems as $ri) {
                try {
                    event('ErpDomainEvent', [
                        'event_name'   => 'erp.asset.returned.v1',
                        'event_id'     => 'erp_returned_' . (int)$ri['asset_id'] . '_' . $now,
                        'site_id'      => (int)$this->site_id,
                        'aggregate_id' => (int)$ri['asset_id'],
                        'payload'      => [
                            'asset_id'         => (int)$ri['asset_id'],
                            'source_device_id' => (int)$ri['source_device_id'],
                            'outbound_id'      => $outboundId,
                            'outbound_no'      => (string)$order->outbound_no,
                            'reason'           => 'partial_return',
                        ],
                        'operator' => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[erp] 退回通知商城回源上架失败: ' . $e->getMessage());
                }
                $this->syncMallReturned((int)$ri['asset_id'], (string)$order->outbound_no);
            }
        }

        return ['outbound_id' => $outboundId, 'returned' => count($returnedItems)];
    }

    /**
     * 商城侧主动关闭挂账订单 → 反向回写 ERP。
     * 口径(已与业务确认): 仅"挂账(settle_mode=later)且未收款"的明细 → 作废未结应收 + 设备恢复"可售"。
     * 已收款明细不动(需走退货流程); 现结单不处理。不回发商城事件(商城已关单, 防回环)。
     * @param int    $siteId
     * @param string $outboundNo 出库单号(=商城订单 relate_source)
     * @param array  $assetIds   限定要退的设备(空=该出库单全部未退明细)
     * @param string $reason
     */
    public function returnByMallClose(int $siteId, string $outboundNo, array $assetIds = [], string $reason = '商城关单'): array
    {
        if ($siteId <= 0 || $outboundNo === '') {
            return ['skipped' => true, 'reason' => 'missing_key'];
        }
        $order = ErpOutboundOrder::where([['site_id', '=', $siteId], ['outbound_no', '=', $outboundNo]])->findOrEmpty();
        if ($order->isEmpty()) {
            return ['skipped' => true, 'reason' => 'outbound_not_found'];
        }
        if ((string)$order->status === ErpDict::OUTBOUND_STATUS_VOID) {
            return ['skipped' => true, 'reason' => 'already_void'];
        }
        // 仅挂账单可由商城关单反向触发; 现结(已收款已售)不动
        if ((string)$order->settle_mode === ErpDict::SETTLE_MODE_NOW) {
            return ['skipped' => true, 'reason' => 'settle_now_need_refund'];
        }

        $assetIds = array_values(array_filter(array_map('intval', $assetIds)));
        $itemQuery = ErpOutboundItem::where([
            ['site_id', '=', $siteId],
            ['outbound_id', '=', (int)$order->id],
            ['is_returned', '=', 0],
        ]);
        if (!empty($assetIds)) {
            $itemQuery->whereIn('asset_id', $assetIds);
        }
        $items = $itemQuery->select()->toArray();
        if (empty($items)) {
            return ['skipped' => true, 'reason' => 'no_returnable_item'];
        }

        $now = time();
        $returned = 0;
        $skipped = [];
        $returnedAssetIds = [];
        Db::transaction(function () use ($siteId, $order, $items, $now, $reason, &$returned, &$skipped, &$returnedAssetIds) {
            foreach ($items as $it) {
                // 仅未收款: 已收款明细跳过(需走退货流程)
                if ((int)$it['receivable_emitted'] === 1) {
                    $recv = FinanceReceivable::where([
                        ['site_id', '=', $siteId],
                        ['event_id', '=', 'erp_outbound_item_' . (int)$it['id']],
                    ])->field('settled_amount')->findOrEmpty();
                    if (!$recv->isEmpty() && round((float)$recv->settled_amount, 2) > 0) {
                        $skipped[] = (int)$it['id'];
                        continue;
                    }
                }
                // 设备回"可售"(仅锁定态才恢复, 避免覆盖其它流程)
                $asset = ErpAsset::where([['site_id', '=', $siteId], ['id', '=', (int)$it['asset_id']]])->lock(true)->findOrEmpty();
                if (!$asset->isEmpty() && (string)$asset->inventory_status === ErpDict::INVENTORY_LOCKED) {
                    $asset->inventory_status = ErpDict::INVENTORY_AVAILABLE_FOR_SALE;
                    $asset->stock_out_at     = 0;
                    $asset->counterparty_id  = 0;
                    $asset->version          = (int)$asset->version + 1;
                    $asset->update_at        = $now;
                    $asset->save();
                }
                // 明细标记已退回
                ErpOutboundItem::where([['site_id', '=', $siteId], ['id', '=', (int)$it['id']]])
                    ->update(['is_returned' => 1, 'returned_at' => $now]);
                // 作废未结应收
                if ((int)$it['receivable_emitted'] === 1) {
                    FinanceReceivable::where([
                        ['site_id', '=', $siteId],
                        ['event_id', '=', 'erp_outbound_item_' . (int)$it['id']],
                        ['status', '<>', FinanceDict::STATUS_SETTLED],
                    ])->update(['status' => FinanceDict::STATUS_VOID, 'update_time' => $now]);
                }
                $returnedAssetIds[] = (int)$it['asset_id'];
                $returned++;
            }

            // 重算出库单台数/金额(仅未退回明细); 全退则整单 void
            $activeItems = ErpOutboundItem::where([
                ['site_id', '=', $siteId],
                ['outbound_id', '=', (int)$order->id],
                ['is_returned', '=', 0],
            ])->field('sale_price')->select()->toArray();
            $updateData = [
                'qty'          => count($activeItems),
                'total_amount' => round((float)array_sum(array_column($activeItems, 'sale_price')), 2),
                'remark'       => substr(trim((string)$order->remark . sprintf(' [商城关单回写：退%d台 · %s · %s]', $returned, $reason, date('Y-m-d H:i', $now))), 0, 500),
                'update_at'    => $now,
            ];
            if (count($activeItems) === 0) {
                $updateData['status'] = ErpDict::OUTBOUND_STATUS_VOID;
            }
            ErpOutboundOrder::where([['site_id', '=', $siteId], ['id', '=', (int)$order->id]])->update($updateData);
        });

        // 商城商品回在售上架(直接 update, 不发事件、商城订单已关则跳过, 无回环)。
        // 不发 erp.asset.returned.v1(商城已主动关单), 仅同步商品可售状态使 ERP/商城一致。
        foreach (array_unique($returnedAssetIds) as $aid) {
            if ((int)$aid > 0) {
                $this->syncMallReturned((int)$aid, (string)$order->outbound_no, $siteId);
            }
        }

        return ['outbound_id' => (int)$order->id, 'returned' => $returned, 'skipped' => $skipped];
    }

    /**
     * 回填价格(settle_mode=later 的出库单, 补齐出货价后生成应收)。
     * 可选「立即收款」: 生成应收并立即从指定户头结清(钱入账), 同时把锁定设备转「已售/下架」。
     * @param int   $outboundId
     * @param array $itemPrices [{item_id, sale_price}]
     * @param array $options    [collect_now=bool, capital_account_id=int]
     */
    public function fillPrice(int $outboundId, array $itemPrices, array $options = []): array
    {
        $order = ErpOutboundOrder::where([['site_id', '=', $this->site_id], ['id', '=', $outboundId]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('出库单不存在');
        }
        $collectNow = !empty($options['collect_now']);
        $capitalAccountId = (int)($options['capital_account_id'] ?? 0);
        // 多账户分笔收款(微信一笔/支付宝一笔…),账户本身已区分收款方式
        $payments = [];
        foreach (is_array($options['payments'] ?? null) ? $options['payments'] : [] as $pm) {
            $accId = (int)($pm['account_id'] ?? 0);
            $amt   = round((float)($pm['amount'] ?? 0), 2);
            if ($accId > 0 && $amt > 0) {
                $payments[] = ['account_id' => $accId, 'amount' => $amt];
            }
        }
        if ($collectNow && $capitalAccountId <= 0 && empty($payments)) {
            throw new CommonException('选择了「已收款」请指定收款账户');
        }
        // 防重复收款: 该单已生成应收且已全部结清(无未结额) → 不再二次收款
        if ($collectNow) {
            $recvRows = FinanceReceivable::where([['site_id', '=', $this->site_id], ['source_no', '=', (string)$order->outbound_no]])
                ->field('amount, settled_amount')->select()->toArray();
            if (!empty($recvRows)) {
                $outstanding = 0.0;
                foreach ($recvRows as $r) {
                    $outstanding += max(0, round((float)$r['amount'] - (float)$r['settled_amount'], 2));
                }
                if ($outstanding <= 0.001) {
                    throw new CommonException('本单应收已收齐，无需重复收款');
                }
            }
        }
        $priceMap = [];
        foreach ($itemPrices as $row) {
            $priceMap[(int)($row['item_id'] ?? 0)] = round((float)($row['sale_price'] ?? 0), 2);
        }
        if (empty($priceMap)) {
            throw new CommonException('请填写出货价');
        }

        $now = time();
        $emitItems = [];      // 未收款路径: 仅未发过应收的明细
        $collectItems = [];   // 收款路径: 所有有价明细(emitAndSettleNow 幂等, 已发过的也能一起结)
        $soldAssetIds = [];
        Db::transaction(function () use ($order, $priceMap, $now, $collectNow, &$emitItems, &$collectItems, &$soldAssetIds) {
            $items = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['outbound_id', '=', (int)$order->id]])->select();
            $total = 0.0;
            foreach ($items as $item) {
                // 已退回的设备不计价、不收款、不计入总额(支持"退N台留M台并对留下的收款"一次性处理)
                if ((int)$item->is_returned === 1) {
                    continue;
                }
                $price = $priceMap[(int)$item->id] ?? (float)$item->sale_price;
                if (isset($priceMap[(int)$item->id]) && $price > 0) {
                    $item->sale_price = $price;
                    $item->save();
                    ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])
                        ->update(['current_sale_price' => $price, 'update_at' => $now]);
                }
                $finalPrice = (float)$item->sale_price;
                if ($finalPrice > 0) {
                    $row = ['item_id' => (int)$item->id, 'price' => $finalPrice, 'device_id' => (int)$item->source_device_id, 'asset_id' => (int)$item->asset_id];
                    $collectItems[] = $row;
                    if ((int)$item->receivable_emitted === 0) {
                        $emitItems[] = $row;
                    }
                    $soldAssetIds[] = (int)$item->asset_id;
                }
                $total += (float)$item->sale_price;
            }
            $order->total_amount = round($total, 2);
            $order->price_status = ErpDict::OUTBOUND_PRICE_FILLED;
            $order->update_at = $now;
            $order->save();
        });

        if ($collectNow) {
            // 多账户:本次收款合计须等于待结明细总额(进财务流水,逐笔进对应户头,可追溯)
            if (!empty($payments)) {
                $collectTotal = 0.0;
                foreach ($collectItems as $ci) {
                    $collectTotal += round((float)($ci['price'] ?? 0), 2);
                }
                $payTotal = 0.0;
                foreach ($payments as $pm) {
                    $payTotal += round((float)$pm['amount'], 2);
                }
                if (round($payTotal, 2) !== round($collectTotal, 2)) {
                    throw new CommonException(sprintf('多账户收款合计 %.2f 与本单待收 %.2f 不一致', $payTotal, $collectTotal));
                }
            }
            // 生成应收并立即收款入账(幂等); 收款即下架: 锁定设备转「已售」
            if (!empty($collectItems)) {
                $this->emitAndSettleNow((int)$order->id, (int)$order->counterparty_id, (string)$order->counterparty_name, (string)$order->outbound_no, $collectItems, $capitalAccountId, $payments, $now);
            }
            $soldAssetIds = array_values(array_unique(array_filter($soldAssetIds)));
            if (!empty($soldAssetIds)) {
                ErpAsset::where([['site_id', '=', $this->site_id], ['inventory_status', '=', ErpDict::INVENTORY_LOCKED]])
                    ->whereIn('id', $soldAssetIds)
                    ->update(['inventory_status' => ErpDict::INVENTORY_OUTBOUND, 'stock_out_at' => $now, 'update_at' => $now]);
            }
            // 挂单收款成交后通知商城: 把原先「锁定(locked)」的商品真正下架(sold)。
            // 仅下架不重复建单(build_mall_order=false), 退回时另由 returned 事件回在售。
            foreach ($collectItems as $ci) {
                try {
                    event('ErpDomainEvent', [
                        'event_name'   => 'erp.asset.sold.v1',
                        'event_id'     => 'erp_sold_fill_' . (int)$ci['asset_id'] . '_' . $now,
                        'site_id'      => (int)$this->site_id,
                        'aggregate_id' => (int)$ci['asset_id'],
                        'payload'      => [
                            'asset_id'         => (int)$ci['asset_id'],
                            'source_device_id' => (int)$ci['device_id'],
                            'outbound_no'      => (string)$order->outbound_no,
                            'reason'           => 'peer_sale',
                            'member_id'        => (int)$order->counterparty_id,
                            'sale_price'       => (float)$ci['price'],
                            'settle_mode'      => 'now',
                            'result_status'    => 'sold',   // 收款成交 → 商城下架
                            'build_mall_order' => false,     // 只下架, 不重复建单
                        ],
                        'operator'     => ['id' => (int)$this->uid, 'name' => (string)$this->username],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[erp] 收款后通知商城下架失败: ' . $e->getMessage());
                }
            }
            return ['outbound_id' => (int)$order->id, 'collected' => true, 'count' => count($collectItems)];
        }

        if (!empty($emitItems)) {
            $this->emitReceivables((int)$order->id, (int)$order->counterparty_id, (string)$order->counterparty_name, (string)$order->outbound_no, $emitItems);
        }
        return ['outbound_id' => (int)$order->id, 'collected' => false, 'emitted' => count($emitItems)];
    }

    /**
     * 「卖同行待办」: 列出同行销售、挂单(未现结)、未作废, 且 待回填价 或 已回填未收齐 的出库单。
     * 给设备中心的专门处理页用。返回每单含明细(设备/型号/IMEI/成本/出货价/应收已收)。
     */
    public function peerSaleTodo(array $where = []): array
    {
        $query = ErpOutboundOrder::where([
            ['site_id', '=', $this->site_id],
            ['outbound_type', '=', ErpDict::OUTBOUND_TYPE_PEER_SALE],
            ['settle_mode', '=', ErpDict::SETTLE_MODE_LATER],
            ['status', '<>', ErpDict::OUTBOUND_STATUS_VOID],
        ])->order('id desc');
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->where(function ($q) use ($kw) {
                $q->whereLike('outbound_no', '%' . $kw . '%')
                    ->whereOr('counterparty_name', 'like', '%' . $kw . '%')
                    ->whereOr('express_no', 'like', '%' . $kw . '%');
            });
        }
        // 快递单号(独立筛选)
        if (!empty($where['express_no'])) {
            $query->whereLike('express_no', '%' . trim((string)$where['express_no']) . '%');
        }
        // 操作人(姓名模糊)
        if (!empty($where['operator'])) {
            $query->whereLike('operator_name', '%' . trim((string)$where['operator']) . '%');
        }
        // 出库时间区间
        if (!empty($where['start_time'])) {
            $query->where('out_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('out_at', '<=', (int)$where['end_time']);
        }
        // 台数区间
        if (($where['qty_min'] ?? '') !== '') {
            $query->where('qty', '>=', (int)$where['qty_min']);
        }
        if (($where['qty_max'] ?? '') !== '') {
            $query->where('qty', '<=', (int)$where['qty_max']);
        }
        // 按 IMEI 找含该机的出库单
        if (!empty($where['imei'])) {
            $oids = ErpOutboundItem::where([['site_id', '=', $this->site_id]])
                ->whereLike('imei', '%' . trim((string)$where['imei']) . '%')
                ->column('outbound_id');
            $query->whereIn('id', !empty($oids) ? array_values(array_unique(array_map('intval', $oids))) : [0]);
        }
        // 状态依赖应收(回填/收款后才算闭环), 故先全量取候选(封顶1000)→聚合算状态→按状态筛→再分页
        $page = max(1, (int)($where['page'] ?? 1));
        $limit = max(1, (int)($where['limit'] ?? 15));
        $stateFilter = (string)($where['state'] ?? ''); // ''全部 / pending_fill待回填 / pending_collect待收款 / done已完成 / todo需处理
        $orders = $query->limit(1000)->select()->toArray();
        if (empty($orders)) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
        }
        $orderIds = array_column($orders, 'id');
        // 明细
        $itemsByOrder = [];
        $items = ErpOutboundItem::where([['site_id', '=', $this->site_id]])->whereIn('outbound_id', $orderIds)->select()->toArray();
        foreach ($items as $it) {
            $itemsByOrder[(int)$it['outbound_id']][] = [
                'item_id'     => (int)$it['id'],
                'asset_id'    => (int)$it['asset_id'],
                'imei'        => (string)$it['imei'],
                'model'       => (string)$it['model'],
                'cost'        => round((float)$it['cost'], 2),
                'sale_price'  => round((float)$it['sale_price'], 2),
            ];
        }
        // 每单已收款(按 source_no 汇总应收的 settled_amount)
        $recvByNo = [];
        $nos = array_column($orders, 'outbound_no');
        $recvRows = FinanceReceivable::where([['site_id', '=', $this->site_id]])->whereIn('source_no', $nos)
            ->field('source_no, sum(amount) as amt, sum(settled_amount) as paid')->group('source_no')->select()->toArray();
        foreach ($recvRows as $r) {
            $recvByNo[(string)$r['source_no']] = ['amt' => round((float)$r['amt'], 2), 'paid' => round((float)$r['paid'], 2)];
        }
        $enriched = [];
        foreach ($orders as $o) {
            $no = (string)$o['outbound_no'];
            $o['items'] = $itemsByOrder[(int)$o['id']] ?? [];
            $pricePending = (string)$o['price_status'] !== ErpDict::OUTBOUND_PRICE_FILLED;
            $recv = $recvByNo[$no] ?? ['amt' => 0, 'paid' => 0];
            $o['price_pending'] = $pricePending;
            $o['receivable_total'] = $recv['amt'];
            $o['received'] = $recv['paid'];
            $o['unreceived'] = round($recv['amt'] - $recv['paid'], 2);
            // 状态: 待回填 / 待收款 / 已完成
            if ($pricePending) {
                $state = 'pending_fill';
            } elseif ($o['unreceived'] > 0.001 || $recv['amt'] <= 0) {
                $state = 'pending_collect';
            } else {
                $state = 'done';
            }
            $o['state'] = $state;
            $o['todo'] = $state !== 'done';
            // 按状态筛选
            if ($stateFilter === 'todo') {
                if ($state === 'done') {
                    continue;
                }
            } elseif ($stateFilter !== '' && $state !== $stateFilter) {
                continue;
            }
            $enriched[] = $o;
        }
        $total = count($enriched);
        $data = array_slice($enriched, ($page - 1) * $limit, $limit);
        return ['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit];
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
            $ot = (string)$where['outbound_type'];
            if ($ot === ErpDict::OUTBOUND_TYPE_MALL_SALE) {
                // 商城销售 = 同行销售出库 + 商城渠道
                $query->where('outbound_type', '=', ErpDict::OUTBOUND_TYPE_PEER_SALE)->where('sale_channel', '=', 'mall');
            } elseif ($ot === ErpDict::OUTBOUND_TYPE_PEER_SALE) {
                // 同行销售 = 同行销售出库 且 非商城渠道
                $query->where('outbound_type', '=', ErpDict::OUTBOUND_TYPE_PEER_SALE)->where('sale_channel', '<>', 'mall');
            } else {
                $query->where('outbound_type', '=', $ot);
            }
        }
        // 单据状态筛选(待回填 / 已定价 / 已退回)。
        // price_status(待回填/已定价) 与 退回(status=void) 是两个正交维度: 一张退回单的
        // price_status 仍可能是 pending, 若只 where price_status 会把"已退回"单错误带入"待回填"结果。
        // 故: 待回填/已定价 一律排除已退回; 已退回 单独按 status=void 过滤。
        $bizStatus = (string)($where['biz_status'] ?? $where['price_status'] ?? '');
        if ($bizStatus !== '') {
            if ($bizStatus === ErpDict::OUTBOUND_STATUS_VOID) {
                $query->where('status', '=', ErpDict::OUTBOUND_STATUS_VOID);
            } elseif (in_array($bizStatus, [ErpDict::OUTBOUND_PRICE_PENDING, ErpDict::OUTBOUND_PRICE_FILLED], true)) {
                $query->where('price_status', '=', $bizStatus)
                      ->where('status', '<>', ErpDict::OUTBOUND_STATUS_VOID);
            }
        }
        if (!empty($where['keyword'])) {
            $query->where('outbound_no|counterparty_name', 'like', '%' . $where['keyword'] . '%');
        }
        // IMEI 精确检索(独立条件):经出库明细按串号精确反查出库单
        if (!empty($where['imei'])) {
            $obIds = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['imei', '=', trim((string)$where['imei'])]])
                ->column('outbound_id');
            $obIds = array_values(array_unique(array_map('intval', $obIds)));
            $query->whereIn('id', !empty($obIds) ? $obIds : [-1]);
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
        $this->applySort($query, $where, ['id', 'out_at', 'total_amount', 'qty']);
        $typeMap = ErpDict::getOutboundTypeMap();
        $list = $query->paginate([
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
        // 往来单位富化(同财务中心口径): 对接人姓名/电话 + 所属主体(对接单位)
        $memberMap = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, array_column($data['data'], 'counterparty_id'));
        foreach ($data['data'] as &$row) {
            $viewType = ErpDict::resolveOutboundViewType((string)$row['outbound_type'], (string)($row['sale_channel'] ?? 'peer'));
            $row['outbound_type_view'] = $viewType;
            $row['type_text'] = $typeMap[$viewType] ?? $viewType;
            $m = $memberMap[(int)($row['counterparty_id'] ?? 0)] ?? null;
            if ($m) {
                if ((string)($row['counterparty_name'] ?? '') === '') {
                    $row['counterparty_name'] = $m['name'];
                }
                $row['counterparty_mobile'] = $m['mobile'];
                $row['entity_id'] = $m['entity_id'];
                $row['entity_name'] = $m['entity_name'];
            }
            $row['settle_mode_text'] = $settleMap[(string)($row['settle_mode'] ?? '')] ?? (string)($row['settle_mode'] ?? '');
            $row['price_status_text'] = $priceMap[(string)($row['price_status'] ?? '')] ?? (string)($row['price_status'] ?? '');
            $row['is_void'] = (string)($row['status'] ?? '') === ErpDict::OUTBOUND_STATUS_VOID;
            // 收款状态: 现结=已收款; 挂单=看应收是否结清(有应收且未结额<=0 即已收款)
            $agg = $recAgg[(string)($row['outbound_no'] ?? '')] ?? null;
            $hasRec = $agg && (int)($agg['cnt'] ?? 0) > 0;
            $row['collected'] = (string)($row['settle_mode'] ?? '') === ErpDict::SETTLE_MODE_NOW
                || ($hasRec && (float)($agg['open'] ?? 0) <= 0);
            // 整单退回：挂单 + 未作废 + 完全未收款
            $row['can_cancel'] = !$row['is_void']
                && (string)($row['settle_mode'] ?? '') !== ErpDict::SETTLE_MODE_NOW
                && !$row['collected'];
            // 部分退回：挂单 + 未作废(整单退回后不再显示)
            $row['can_partial_return'] = !$row['is_void']
                && (string)($row['settle_mode'] ?? '') === ErpDict::SETTLE_MODE_LATER;
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
        $viewType = ErpDict::resolveOutboundViewType((string)$data['outbound_type'], (string)($data['sale_channel'] ?? 'peer'));
        $data['outbound_type_view'] = $viewType;
        $data['type_text'] = ErpDict::getOutboundTypeMap()[$viewType] ?? $viewType;
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
        $hasReturnableItem = false; // 是否有可退回(locked)的未退回明细
        foreach ($items as &$it) {
            $a = $assetMap[(int)$it['asset_id']] ?? [];
            $cost = round((float)($it['cost'] ?: ($a['current_cost'] ?? 0)), 2);
            $sale = round((float)$it['sale_price'], 2);
            $it['asset_no'] = (string)($a['asset_no'] ?? '');
            $it['inventory_status'] = (string)($a['inventory_status'] ?? '');
            $it['inventory_status_text'] = $invMap[(string)($a['inventory_status'] ?? '')] ?? (string)($a['inventory_status'] ?? '');
            $it['cost'] = $cost;
            $it['profit'] = round($sale - $cost, 2);
            $it['is_returned'] = (int)($it['is_returned'] ?? 0) === 1;
            // 可退回条件：未退回 + 设备仍锁定
            $it['can_return'] = !$it['is_returned'] && (string)($a['inventory_status'] ?? '') === ErpDict::INVENTORY_LOCKED;
            if ($it['can_return']) {
                $hasReturnableItem = true;
            }
            if (!$it['is_returned']) {
                $totalCost += $cost;
                $totalSale += $sale;
            }
        }
        unset($it);
        $data['items'] = $items;
        // 可部分退回: 挂单 + 未全部作废 + 至少一台可退回的锁定设备
        $data['can_partial_return'] = !$data['is_void']
            && (string)$data['settle_mode'] === ErpDict::SETTLE_MODE_LATER
            && $hasReturnableItem;
        $data['total_cost'] = round($totalCost, 2);
        $data['total_profit'] = round($totalSale - $totalCost, 2);

        // 财务关联: 应收(本单) + 结算/折账 + 收款流水
        $recs = FinanceReceivable::where([['site_id', '=', $this->site_id], ['source_no', '=', $no]])->select()->toArray();
        $received = 0.0;
        $unreceived = 0.0;
        $statusMap = FinanceDict::getStatusMap();
        foreach ($recs as &$r) {
            $r['status_text'] = $statusMap[$r['status']] ?? $r['status'];
            $isVoid = (string)$r['status'] === FinanceDict::STATUS_VOID;
            // 已作废应收(退货/取消)不计入已收/未收, 但仍在明细中以"已作废"展示
            $r['outstanding'] = $isVoid ? 0.0 : round((float)$r['amount'] - (float)$r['settled_amount'], 2);
            if ($isVoid) {
                continue;
            }
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
    private function emitAndSettleNow(int $outboundId, int $cpId, string $cpName, string $outboundNo, array $emitItems, int $capitalAccountId, array $payments, int $now, string $sourceType = 'erp_peer_sale', string $saleLabel = '同行'): void
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
                    'source_type'       => $sourceType,
                    'source_no'         => $outboundNo,
                    'source_device_id'  => (int)($it['device_id'] ?? 0),
                    'occurred_at'       => $now,
                    'operator_uid'      => (int)$this->uid,
                    'operator_name'     => (string)$this->username,
                    'remark'            => $saleLabel . '现结销售',
                ]);
                if ($rid > 0) {
                    $rids[] = $rid;
                    ErpOutboundItem::where([['site_id', '=', $this->site_id], ['id', '=', (int)$it['item_id']]])
                        ->update(['receivable_emitted' => 1]);
                }
            }
            // 立即结清这些应收
            if (!empty($rids)) {
                if (!empty($payments)) {
                    // 多账户：先只核销应收(不二次记现金)，再按每笔 payment 记入对应账户
                    (new FinanceSettlementService())->settle($cpId, [], $rids, [
                        'record_cash' => false,
                        'remark'      => $saleLabel . '现结收款(多账户)',
                    ]);
                    $capSvc = new \addon\hsx_erp\app\service\admin\ErpCapitalAccountService();
                    foreach ($payments as $pm) {
                        $accId = (int)($pm['account_id'] ?? 0);
                        $amt = round((float)($pm['amount'] ?? 0), 2);
                        if ($accId <= 0 || $amt <= 0) continue;
                        $capSvc->recordEntry([
                            'account_id'        => $accId,
                            'direction'         => 'in',
                            'amount'            => $amt,
                            'biz_type'          => 'sale_income',
                            'counterparty_id'   => $cpId,
                            'counterparty_name' => $cpName,
                            'source_type'       => $sourceType,
                            'source_no'         => $outboundNo,
                            'remark'            => $saleLabel . '现结收款' . (((string)($pm['method'] ?? '') !== '') ? '·' . (string)$pm['method'] : ''),
                            'occurred_at'       => $now,
                        ]);
                    }
                } else {
                    // 单账户：现金收入所选户头(settle 记一笔 in 流水)
                    (new FinanceSettlementService())->settle($cpId, [], $rids, [
                        'capital_account_id' => $capitalAccountId,
                        'record_cash'        => true,
                        'remark'             => $saleLabel . '现结收款',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[erp] 现结生成应收并结清失败: ' . $e->getMessage());
        }
    }

    /** 发应收事件给财务中心(同行欠我), 幂等键按出库明细 */
    private function emitReceivables(int $outboundId, int $cpId, string $cpName, string $outboundNo, array $emitItems, string $sourceType = 'erp_peer_sale', string $saleLabel = '同行'): void
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
                    'source_type'       => $sourceType,
                    'source_no'         => $outboundNo,
                    'source_device_id'  => (int)($it['device_id'] ?? 0),
                    'occurred_at'       => $now,
                    'operator_uid'      => (int)$this->uid,
                    'operator_name'     => (string)$this->username,
                    'remark'            => $saleLabel . '出货生成应收',
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

    private function syncMallSaleOrder(int $assetId, int $sourceDeviceId, int $memberId, string $outboundNo, float $salePrice, string $settleMode, int $now): void
    {
        if ($assetId <= 0 || $memberId <= 0) {
            return;
        }
        $svcClass = '\\addon\\phone_shop\\app\\service\\core\\order\\CoreOfflineSaleService';
        if (!class_exists($svcClass)) {
            return;
        }
        try {
            $res = (new $svcClass())->createSaleOrder([
                'site_id'          => (int)$this->site_id,
                'member_id'        => $memberId,
                'sku_id'           => (int)(\addon\phone_shop\app\model\goods\GoodsSku::where('erp_asset_id', $assetId)->value('sku_id') ?: 0),
                'sale_price'       => $salePrice,
                'payment_mode'     => $settleMode === ErpDict::SETTLE_MODE_NOW ? 'offline_cash' : 'offline_credit',
                'buyer_type'       => 'b',
                'source_device_id' => $sourceDeviceId,
                'staff_id'         => (int)$this->uid,
                'outbound_no'      => $outboundNo,
                'result_status'    => $settleMode === ErpDict::SETTLE_MODE_NOW ? 'sold' : 'locked',
            ]);
            Log::write('[erp] 商城出库同步建单 asset_id=' . $assetId . ' result=' . json_encode($res, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('[erp] 商城出库同步建单失败: ' . $e->getMessage());
        }
    }

    private function syncMallReturned(int $assetId, string $outboundNo, int $siteId = 0): void
    {
        if ($assetId <= 0) {
            return;
        }
        $siteId = $siteId > 0 ? $siteId : (int)$this->site_id;
        $svcClass = '\\addon\\phone_shop\\app\\service\\core\\order\\CoreOfflineSaleService';
        if (!class_exists($svcClass)) {
            return;
        }
        try {
            $res = (new $svcClass())->returnSaleItemByAsset($siteId, $assetId, $outboundNo);
            Log::write('[erp] 商城退回同步 asset_id=' . $assetId . ' result=' . json_encode($res, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('[erp] 商城退回同步失败: ' . $e->getMessage());
        }
    }
}
