<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\model\ErpStocktakeItem;
use addon\hsx_erp\app\model\ErpStocktakeOrder;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 库存盘点：选仓库/库位 → 快照应在库清单 → 录入实物 → 盘盈/盘亏 → 据实核销盘亏并写库存流水。
 *
 * 与"账实校验(Reconciliation)"互补：
 *   - 账实校验：查"数据"是否自洽(流水累计 vs 资产快照)，不动库存。
 *   - 盘点：查"实物 vs 账面"的差，盘亏据实核销离库(写流水)。
 */
class ErpStocktakeService extends BaseAdminService
{
    /** 物理在库(应被盘到)的状态 */
    private function onHandStatuses(): array
    {
        return [
            ErpDict::INVENTORY_IN_STOCK,
            ErpDict::INVENTORY_REFURBISHING,
            ErpDict::INVENTORY_PENDING_PRICING,
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
            ErpDict::INVENTORY_LOCKED,
        ];
    }

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 创建盘点单：按仓库(可选库位)快照应在库清单为盘点明细
     */
    public function createStocktake(int $warehouseId, int $locationId = 0, string $remark = ''): array
    {
        if ($warehouseId <= 0) {
            throw new CommonException('请选择盘点仓库');
        }
        $warehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $warehouseId]])->findOrEmpty();
        if ($warehouse->isEmpty()) {
            throw new CommonException('仓库不存在');
        }
        $locationName = '';
        if ($locationId > 0) {
            $loc = ErpWarehouseLocation::where([['site_id', '=', $this->site_id], ['id', '=', $locationId]])->findOrEmpty();
            if ($loc->isEmpty()) {
                throw new CommonException('库位不存在');
            }
            $locationName = (string)$loc->location_name;
        }

        $where = [['site_id', '=', $this->site_id], ['warehouse_id', '=', $warehouseId], ['inventory_status', 'in', $this->onHandStatuses()]];
        if ($locationId > 0) {
            $where[] = ['location_id', '=', $locationId];
        }
        $assets = ErpAsset::where($where)->select();

        $now = time();
        $no = $this->makeNo('PD');
        $orderId = 0;
        Db::transaction(function () use ($assets, $warehouse, $warehouseId, $locationId, $locationName, $remark, $now, $no, &$orderId) {
            $order = ErpStocktakeOrder::create([
                'site_id'        => $this->site_id,
                'stocktake_no'   => $no,
                'warehouse_id'   => $warehouseId,
                'warehouse_name' => (string)$warehouse->warehouse_name,
                'location_id'    => $locationId,
                'location_name'  => $locationName,
                'status'         => ErpDict::STOCKTAKE_STATUS_COUNTING,
                'system_count'   => count($assets),
                'operator_uid'   => (int)$this->uid,
                'operator_name'  => (string)$this->username,
                'remark'         => $remark,
                'started_at'     => $now,
                'create_at'      => $now,
                'update_at'      => $now,
            ]);
            $orderId = (int)$order->id;
            foreach ($assets as $a) {
                ErpStocktakeItem::create([
                    'site_id'          => $this->site_id,
                    'stocktake_id'     => $orderId,
                    'asset_id'         => (int)$a->id,
                    'source_device_id' => (int)$a->source_device_id,
                    'imei'             => (string)$a->imei,
                    'model'            => (string)$a->model,
                    'system_status'    => (string)$a->inventory_status,
                    'result'           => ErpDict::STOCKTAKE_RESULT_UNCOUNTED,
                    'counted'          => 0,
                    'create_at'        => $now,
                    'update_at'        => $now,
                ]);
            }
        });
        return ['stocktake_id' => $orderId, 'stocktake_no' => $no, 'system_count' => count($assets)];
    }

    /**
     * 录入实物：传一批 IMEI(或资产号)，匹配清单标记已盘到；清单外的记为盘盈
     * @param array $codes IMEI 列表
     */
    public function scan(int $stocktakeId, array $codes): array
    {
        $order = $this->mustCounting($stocktakeId);
        $codes = array_values(array_unique(array_filter(array_map(static fn($c) => trim((string)$c), $codes))));
        if (empty($codes)) {
            throw new CommonException('请录入要盘点的 IMEI');
        }
        $now = time();
        $matched = 0;
        $profit = 0;
        Db::transaction(function () use ($order, $codes, $now, &$matched, &$profit) {
            foreach ($codes as $code) {
                $item = ErpStocktakeItem::where([
                    ['site_id', '=', $this->site_id], ['stocktake_id', '=', (int)$order->id], ['imei', '=', $code],
                ])->findOrEmpty();
                if (!$item->isEmpty()) {
                    if ((int)$item->counted === 0) {
                        $item->save(['counted' => 1, 'result' => ErpDict::STOCKTAKE_RESULT_MATCHED, 'update_at' => $now]);
                        $matched++;
                    }
                    continue;
                }
                // 清单外：盘盈(系统该仓库位无此机的在库记录)
                $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['imei', '=', $code]])->findOrEmpty();
                ErpStocktakeItem::create([
                    'site_id'          => $this->site_id,
                    'stocktake_id'     => (int)$order->id,
                    'asset_id'         => $asset->isEmpty() ? 0 : (int)$asset->id,
                    'source_device_id' => $asset->isEmpty() ? 0 : (int)$asset->source_device_id,
                    'imei'             => $code,
                    'model'            => $asset->isEmpty() ? '' : (string)$asset->model,
                    'system_status'    => $asset->isEmpty() ? '' : (string)$asset->inventory_status,
                    'result'           => ErpDict::STOCKTAKE_RESULT_PROFIT,
                    'counted'          => 1,
                    'remark'           => $asset->isEmpty() ? '系统无此机' : '不在本仓库位应盘清单',
                    'create_at'        => $now,
                    'update_at'        => $now,
                ]);
                $profit++;
            }
        });
        return ['matched_added' => $matched, 'profit_added' => $profit];
    }

    /**
     * 完成盘点：未盘到的判为盘亏；可据实核销盘亏(标记离库 lost 并写库存流水)
     */
    public function finish(int $stocktakeId, bool $adjustLoss = true): array
    {
        $order = $this->mustCounting($stocktakeId);
        $now = time();
        $stat = ['match' => 0, 'loss' => 0, 'profit' => 0, 'counted' => 0];
        Db::transaction(function () use ($order, $adjustLoss, $now, &$stat) {
            $items = ErpStocktakeItem::where([['site_id', '=', $this->site_id], ['stocktake_id', '=', (int)$order->id]])->select();
            foreach ($items as $item) {
                if ((string)$item->result === ErpDict::STOCKTAKE_RESULT_PROFIT) {
                    $stat['profit']++;
                    $stat['counted']++;
                    continue;
                }
                if ((int)$item->counted === 1) {
                    $stat['match']++;
                    $stat['counted']++;
                    continue;
                }
                // 未盘到 → 盘亏
                $item->save(['result' => ErpDict::STOCKTAKE_RESULT_LOSS, 'update_at' => $now]);
                $stat['loss']++;
                if ($adjustLoss && (int)$item->asset_id > 0) {
                    $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
                    if (!$asset->isEmpty() && in_array((string)$asset->inventory_status, $this->onHandStatuses(), true)) {
                        $before = (string)$asset->inventory_status;
                        $asset->save([
                            'inventory_status' => ErpDict::INVENTORY_LOST,
                            'stock_out_at'     => $now,
                            'version'          => (int)$asset->version + 1,
                            'update_at'        => $now,
                        ]);
                        ErpStockLedger::create([
                            'site_id'       => $this->site_id,
                            'ledger_no'     => $this->makeNo('SL'),
                            'asset_id'      => (int)$asset->id,
                            'cycle_id'      => (int)$asset->cycle_id,
                            'action'        => 'stocktake_loss',
                            'before_status' => $before,
                            'after_status'  => ErpDict::INVENTORY_LOST,
                            'warehouse_id'  => (int)$asset->warehouse_id,
                            'location_id'   => (int)$asset->location_id,
                            'operator_id'   => $this->uid,
                            'operator_name' => $this->username ?: '',
                            'occurred_at'   => $now,
                            'payload'       => ['stocktake_no' => (string)$order->stocktake_no],
                        ]);
                    }
                }
            }
            $order->save([
                'status'        => ErpDict::STOCKTAKE_STATUS_FINISHED,
                'counted_count' => $stat['counted'],
                'match_count'   => $stat['match'],
                'loss_count'    => $stat['loss'],
                'profit_count'  => $stat['profit'],
                'finished_at'   => $now,
                'update_at'     => $now,
            ]);
        });
        return $stat;
    }

    public function getPage(array $where = []): array
    {
        $query = ErpStocktakeOrder::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['warehouse_id'])) {
            $query->where('warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }
        if (!empty($where['keyword'])) {
            $query->whereLike('stocktake_no|warehouse_name|location_name', '%' . trim((string)$where['keyword']) . '%');
        }
        $statusMap = [
            ErpDict::STOCKTAKE_STATUS_COUNTING => '盘点中',
            ErpDict::STOCKTAKE_STATUS_FINISHED => '已完成',
            ErpDict::STOCKTAKE_STATUS_VOID     => '已作废',
        ];
        $list = $query->order('id desc')->paginate(['list_rows' => (int)($where['limit'] ?? 15), 'page' => (int)($where['page'] ?? 1)]);
        $data = $list->toArray();
        foreach ($data['data'] as &$row) {
            $row['status_text'] = $statusMap[$row['status']] ?? $row['status'];
        }
        unset($row);
        return $data;
    }

    public function getInfo(int $id): array
    {
        $order = ErpStocktakeOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('盘点单不存在');
        }
        $data = $order->toArray();
        $resultMap = ['uncounted' => '待盘', 'matched' => '相符', 'loss' => '盘亏', 'profit' => '盘盈'];
        $items = ErpStocktakeItem::where([['site_id', '=', $this->site_id], ['stocktake_id', '=', $id]])->order('result asc,id asc')->select()->toArray();
        foreach ($items as &$it) {
            $it['result_text'] = $resultMap[$it['result']] ?? $it['result'];
        }
        unset($it);
        $data['items'] = $items;
        return $data;
    }

    /**
     * 找回(误判盘亏纠正)：把盘亏核销的设备从"丢失"恢复到盘点前状态，写反向库存流水。
     * 不撤销整张盘点单(账务可追溯)，只对个别误判设备做反向纠正。
     * @param int $itemId 盘点明细ID(result=loss)
     */
    public function restoreLoss(int $itemId): array
    {
        $item = ErpStocktakeItem::where([['site_id', '=', $this->site_id], ['id', '=', $itemId]])->findOrEmpty();
        if ($item->isEmpty()) {
            throw new CommonException('盘点明细不存在');
        }
        if ((string)$item->result !== ErpDict::STOCKTAKE_RESULT_LOSS) {
            throw new CommonException('只有盘亏(丢失)的设备可以找回');
        }
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('设备不存在');
        }
        if ((string)$asset->inventory_status !== ErpDict::INVENTORY_LOST) {
            throw new CommonException('该设备当前不是丢失状态，无需找回');
        }
        // 恢复到盘点前的状态(快照)，无快照则回到在库
        $restoreStatus = (string)$item->system_status;
        if ($restoreStatus === '' || $restoreStatus === ErpDict::INVENTORY_LOST || $restoreStatus === ErpDict::INVENTORY_OUTBOUND) {
            $restoreStatus = ErpDict::INVENTORY_IN_STOCK;
        }
        $now = time();
        Db::transaction(function () use ($item, $asset, $restoreStatus, $now) {
            $asset->save([
                'inventory_status' => $restoreStatus,
                'stock_out_at'     => 0,
                'version'          => (int)$asset->version + 1,
                'update_at'        => $now,
            ]);
            ErpStockLedger::create([
                'site_id'       => $this->site_id,
                'ledger_no'     => $this->makeNo('SL'),
                'asset_id'      => (int)$asset->id,
                'cycle_id'      => (int)$asset->cycle_id,
                'action'        => 'stocktake_restore',
                'before_status' => ErpDict::INVENTORY_LOST,
                'after_status'  => $restoreStatus,
                'warehouse_id'  => (int)$asset->warehouse_id,
                'location_id'   => (int)$asset->location_id,
                'operator_id'   => $this->uid,
                'operator_name' => $this->username ?: '',
                'occurred_at'   => $now,
                'payload'       => ['stocktake_item_id' => (int)$item->id, 'reason' => '盘亏误判找回'],
            ]);
            $item->save(['result' => ErpDict::STOCKTAKE_RESULT_MATCHED, 'counted' => 1, 'remark' => '盘亏找回(已恢复在库)', 'update_at' => $now]);
            // 回写盘点单计数
            $order = ErpStocktakeOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->stocktake_id]])->findOrEmpty();
            if (!$order->isEmpty()) {
                $order->save([
                    'loss_count'  => max(0, (int)$order->loss_count - 1),
                    'match_count' => (int)$order->match_count + 1,
                    'update_at'   => $now,
                ]);
            }
        });
        return ['restored_status' => $restoreStatus];
    }

    private function mustCounting(int $stocktakeId): ErpStocktakeOrder
    {
        $order = ErpStocktakeOrder::where([['site_id', '=', $this->site_id], ['id', '=', $stocktakeId]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('盘点单不存在');
        }
        if ((string)$order->status !== ErpDict::STOCKTAKE_STATUS_COUNTING) {
            throw new CommonException('该盘点单已结束，不能再操作');
        }
        return $order;
    }
}
