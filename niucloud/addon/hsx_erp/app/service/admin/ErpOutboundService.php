<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetMoveLog;
use addon\hsx_erp\app\model\ErpOutboundItem;
use addon\hsx_erp\app\model\ErpOutboundOrder;
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

        if (empty($items)) {
            throw new CommonException('请选择要出库的设备');
        }
        if ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && $cpId <= 0) {
            throw new CommonException('同行销售出库必须选择往来单位(同行)');
        }
        // 报废无结算
        if ($type !== ErpDict::OUTBOUND_TYPE_PEER_SALE) {
            $settleMode = ErpDict::SETTLE_MODE_NONE;
        }
        // 现结必须每台有价
        if ($settleMode === ErpDict::SETTLE_MODE_NOW) {
            foreach ($items as $it) {
                if (round((float)($it['sale_price'] ?? 0), 2) <= 0) {
                    throw new CommonException('现结出库必须为每台填写出货价');
                }
            }
        }

        $assetIds = array_values(array_unique(array_map(static fn($it) => (int)($it['asset_id'] ?? 0), $items)));
        $assetIds = array_filter($assetIds);
        if (empty($assetIds)) {
            throw new CommonException('设备无效');
        }
        $priceMap = [];
        foreach ($items as $it) {
            $priceMap[(int)$it['asset_id']] = round((float)($it['sale_price'] ?? 0), 2);
        }

        $now = time();
        $no = 'CK' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $priceStatus = $settleMode === ErpDict::SETTLE_MODE_LATER ? ErpDict::OUTBOUND_PRICE_PENDING : ErpDict::OUTBOUND_PRICE_FILLED;
        $outboundId = 0;
        $emitItems = [];

        Db::transaction(function () use ($assetIds, $priceMap, $type, $settleMode, $priceStatus, $cpId, $p, $no, $now, &$outboundId, &$emitItems) {
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
                $asset->inventory_status = ErpDict::INVENTORY_OUTBOUND;
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
                // 同行销售 + 已有价 → 待发应收
                if ($type === ErpDict::OUTBOUND_TYPE_PEER_SALE && $row['sale_price'] > 0) {
                    $emitItems[] = ['item_id' => (int)$item->id, 'price' => $row['sale_price'], 'device_id' => $row['source_device_id']];
                }
            }
        });

        // 发应收(故障隔离)
        if (!empty($emitItems)) {
            $this->emitReceivables($outboundId, $cpId, (string)($p['counterparty_name'] ?? ''), $no, $emitItems);
        }

        return ['outbound_id' => $outboundId, 'outbound_no' => $no];
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
     * 调拨: 把设备移到另一个仓库/库位(如划拨到同行仓), 不出库不动钱
     * @param array $assetIds
     */
    public function transfer(array $assetIds, int $toWarehouseId, int $toLocationId, string $remark = ''): array
    {
        $assetIds = array_values(array_filter(array_map('intval', $assetIds)));
        if (empty($assetIds)) {
            throw new CommonException('请选择要调拨的设备');
        }
        if ($toWarehouseId <= 0) {
            throw new CommonException('请选择目标仓库');
        }
        $now = time();
        $moved = 0;
        Db::transaction(function () use ($assetIds, $toWarehouseId, $toLocationId, $remark, $now, &$moved) {
            $assets = ErpAsset::where([['site_id', '=', $this->site_id], ['id', 'in', $assetIds]])->select();
            foreach ($assets as $asset) {
                if ((string)$asset->inventory_status === ErpDict::INVENTORY_OUTBOUND) {
                    throw new CommonException('设备[' . $asset->asset_no . ']已出库, 不能调拨');
                }
                ErpAssetMoveLog::create([
                    'site_id'           => $this->site_id,
                    'asset_id'          => (int)$asset->id,
                    'from_warehouse_id' => (int)$asset->warehouse_id,
                    'from_location_id'  => (int)$asset->location_id,
                    'to_warehouse_id'   => $toWarehouseId,
                    'to_location_id'    => $toLocationId,
                    'operator_uid'      => (int)$this->uid,
                    'operator_name'     => (string)$this->username,
                    'remark'            => $remark,
                    'create_at'         => $now,
                ]);
                $asset->warehouse_id = $toWarehouseId;
                $asset->location_id = $toLocationId;
                $asset->version = (int)$asset->version + 1;
                $asset->update_at = $now;
                $asset->save();
                $moved++;
            }
        });
        return ['moved' => $moved];
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
        $typeMap = ErpDict::getOutboundTypeMap();
        $list = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page'      => (int)($where['page'] ?? 1),
        ]);
        $data = $list->toArray();
        foreach ($data['data'] as &$row) {
            $row['type_text'] = $typeMap[$row['outbound_type']] ?? $row['outbound_type'];
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
        $data['type_text'] = ErpDict::getOutboundTypeMap()[$data['outbound_type']] ?? $data['outbound_type'];
        $data['items'] = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['outbound_id', '=', $id]])->select()->toArray();
        return $data;
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
}
