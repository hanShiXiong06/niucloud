<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\job\PublishOutboxEvent;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\ErpLocationAssign;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpOutboundItem;
use addon\hsx_erp\app\model\ErpOutboundOrder;
use addon\hsx_erp\app\model\ErpRefurbishItem;
use addon\hsx_erp\app\model\ErpRefurbishOrder;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\model\ErpStockOrder;
use addon\hsx_erp\app\model\ErpStockOrderItem;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\service\core\CoreFinanceLedgerService;
use addon\hsx_erp\app\support\ErpDomainEvent;
use addon\hsx_erp\app\support\ErpMoney;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpAssetService extends BaseAdminService
{
    /**
     * 是否可查看全部（管理员 is_admin 组看全部，其余员工只看自己负责库位）。
     */
    protected function canViewAll(): bool
    {
        return SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['is_admin', '=', 1],
        ])->count() > 0;
    }

    /**
     * 当前用户的库位过滤范围。
     * null = 不限制（管理员）；[-1] = 无任何负责库位（看不到任何设备）；否则为负责的库位ID集合。
     */
    protected function scopedLocationIds(): ?array
    {
        if ($this->canViewAll()) {
            return null;
        }
        $ids = ErpLocationAssign::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
        ])->column('location_id');
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return empty($ids) ? [-1] : $ids;
    }

    public function getPage(array $where = []): array
    {
        $query = ErpAsset::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            // 支持多设备检索：空格/逗号/分号/换行分隔多个 IMEI/资产号/SN，命中任意一个即返回
            $terms = array_values(array_filter(array_map('trim', preg_split('/[\s,，;；\r\n]+/u', trim((string)$where['keyword'])))));
            if (!empty($terms)) {
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $t) {
                        $q->whereOr(function ($w) use ($t) {
                            $w->whereLike('asset_no|imei|imei2|sn|model', '%' . $t . '%');
                            if (is_numeric($t)) {
                                $w->whereOr('source_device_id', '=', (int)$t);
                            }
                        });
                    }
                });
            }
        }
        if (!empty($where['inventory_status'])) {
            if ((string)$where['inventory_status'] === 'sold') {
                // 已售/下架 = 已锁定(挂单) + 已出库
                $query->whereIn('inventory_status', [ErpDict::INVENTORY_LOCKED, ErpDict::INVENTORY_OUTBOUND]);
            } elseif ((string)$where['inventory_status'] === 'onhand') {
                // 在手库存 = 在库 + 整备中 + 待定价 + 可售（未卖掉的货）
                $query->whereIn('inventory_status', [
                    ErpDict::INVENTORY_IN_STOCK,
                    ErpDict::INVENTORY_REFURBISHING,
                    ErpDict::INVENTORY_PENDING_PRICING,
                    ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
                ]);
            } else {
                $query->where('inventory_status', '=', (string)$where['inventory_status']);
            }
        }
        // 可出库设备(在库/待定价/可售)，供出库选择用
        if (!empty($where['sellable'])) {
            $query->whereIn('inventory_status', [
                ErpDict::INVENTORY_IN_STOCK,
                ErpDict::INVENTORY_PENDING_PRICING,
                ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
            ]);
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('location_id', '=', (int)$where['location_id']);
        }
        if (($where['ownership_type'] ?? '') !== '') {
            $query->where('ownership_type', '=', (string)$where['ownership_type']);
        }
        if (($where['cost_min'] ?? '') !== '') {
            $query->where('current_cost', '>=', (float)$where['cost_min']);
        }
        if (($where['cost_max'] ?? '') !== '') {
            $query->where('current_cost', '<=', (float)$where['cost_max']);
        }
        if (!empty($where['stock_in_start'])) {
            $query->where('stock_in_at', '>=', (int)$where['stock_in_start']);
        }
        if (!empty($where['stock_in_end'])) {
            $query->where('stock_in_at', '<=', (int)$where['stock_in_end']);
        }
        // 员工只看自己负责库位的设备；管理员看全部
        $scope = $this->scopedLocationIds();
        if ($scope !== null) {
            $query->whereIn('location_id', $scope);
        }
        // 合计(对当前筛选的全集, 非仅当前页)
        $aggQuery = clone $query;
        $totalCount = (clone $aggQuery)->count();
        $totalCost = round((float)(clone $aggQuery)->sum('current_cost'), 2);
        $totalSale = round((float)(clone $aggQuery)->sum('current_sale_price'), 2);

        // 排序(白名单字段, 防注入)
        $sortMap = [
            'current_cost' => 'current_cost', 'current_sale_price' => 'current_sale_price',
            'stock_in_at' => 'stock_in_at', 'stock_out_at' => 'stock_out_at', 'id' => 'id',
        ];
        $sortField = $sortMap[(string)($where['sort_field'] ?? '')] ?? 'id';
        $sortOrder = strtolower((string)($where['sort_order'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $query->order($sortField, $sortOrder);

        // 流速：当前筛选集里"在库"设备的平均库龄(天)
        $onHand = [ErpDict::INVENTORY_IN_STOCK, ErpDict::INVENTORY_REFURBISHING, ErpDict::INVENTORY_PENDING_PRICING, ErpDict::INVENTORY_AVAILABLE_FOR_SALE, ErpDict::INVENTORY_LOCKED];
        $inStockCount = (clone $aggQuery)->whereIn('inventory_status', $onHand)->where('stock_in_at', '>', 0)->count();
        $avgStockIn = (float)(clone $aggQuery)->whereIn('inventory_status', $onHand)->where('stock_in_at', '>', 0)->avg('stock_in_at');
        $avgAgeDays = $avgStockIn > 0 ? round((time() - $avgStockIn) / 86400, 1) : 0;

        $result = $this->pageQuery($query);
        $this->appendCounterparties($result['data']);
        $this->appendWarehouseNames($result['data']);
        $this->appendStatusLabels($result['data']);
        $this->appendStockAge($result['data']);
        $this->appendMidDelegation($result['data']);
        $this->appendMidPresence($result['data']);
        $result['summary'] = [
            'count'          => (int)$totalCount,
            'total_cost'     => $totalCost,
            'total_sale'     => $totalSale,
            'in_stock_count' => (int)$inStockCount,
            'avg_age_days'   => $avgAgeDays,
        ];
        return $result;
    }

    /**
     * 细化状态展示：给每行补 status_text(明确中文) + status_type(标签色)。
     * 出库要 join 出库单区分 已售(同行)/报废/其他出库；盘亏→盘亏丢失。
     */
    /**
     * 交数据中台拍照定价:标记已交中台(用于列表显示/定价归属)并发 ready_for_photo 事件(中台据此建拍照任务)。
     * @return int[] outbox id 列表
     */
    private function handToMid(ErpAsset $asset, int $stockOrderId, int $now): array
    {
        $snap = (array)$asset->source_snapshot;
        $snap['delegated_mid'] = true;
        $asset->save(['source_snapshot' => $snap, 'update_at' => $now]);
        $this->writeOperation((int)$asset->id, (int)$asset->cycle_id, 'erp.asset.ready_for_photo.v1', 'hand_to_mid', $stockOrderId, ['require_photo' => true]);
        return [
            $this->writeDecisionEvent($asset, 'erp.asset.ready_for_photo.v1', $stockOrderId, [
                'source_device_id' => (int)$asset->source_device_id,
                'require_photo' => true,
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ], $now),
        ];
    }

    /**
     * 手动「推入拍照/中台」:把在库/待定价/可售的设备推进拍照流程(安全网,绕过仓库必拍照设置)。
     * 中台已接入 → 交中台(待定价 + ready_for_photo);未接入 → ERP 自己的「待拍照」。
     * 已售/已出库/整备中/待入库 等状态不可推,跳过。
     */
    public function pushToPhoto(array $assetIds): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));
        if (empty($assetIds)) {
            throw new CommonException('请选择要推入拍照的设备');
        }
        $midConnected = class_exists('\\addon\\hsx_device_asset\\app\\service\\admin\\DeviceAssetService');
        $allowed = [ErpDict::INVENTORY_IN_STOCK, ErpDict::INVENTORY_PENDING_PRICING, ErpDict::INVENTORY_AVAILABLE_FOR_SALE];
        $now = time();
        $ok = 0;
        $skip = 0;
        $outboxIds = [];
        Db::transaction(function () use ($assetIds, $midConnected, $allowed, $now, &$ok, &$skip, &$outboxIds) {
            foreach ($assetIds as $aid) {
                $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $aid]])->lock(true)->findOrEmpty();
                if ($asset->isEmpty() || !in_array((string)$asset->inventory_status, $allowed, true)) {
                    $skip++;
                    continue;
                }
                if ($midConnected) {
                    $asset->save(['inventory_status' => ErpDict::INVENTORY_PENDING_PRICING, 'update_at' => $now]);
                    $outboxIds = array_merge($outboxIds, $this->handToMid($asset, 0, $now));
                } else {
                    $asset->save(['inventory_status' => ErpDict::INVENTORY_PENDING_PHOTO, 'stock_in_at' => 0, 'update_at' => $now]);
                }
                ErpAssetCycle::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset->cycle_id]])
                    ->update(['status' => (string)$asset->inventory_status, 'update_at' => $now]);
                $ok++;
            }
        });
        $this->publish($outboxIds);
        return ['ok' => $ok, 'skipped' => $skip, 'to_mid' => $midConnected];
    }

    /**
     * 同步派发 outbox 事件:直接执行 PublishOutboxEvent(不入队列),保证中台等监听方当场收到
     * (dev/未跑队列 worker 时,异步 dispatch 会卡在 outbox 表里送不出去)。已发布的会被 job 自身跳过。
     */
    private function publish(array $outboxIds): void
    {
        foreach ($outboxIds as $outboxId) {
            $id = (int)$outboxId;
            if ($id <= 0) {
                continue;
            }
            try {
                (new \addon\hsx_erp\app\job\PublishOutboxEvent())->doJob($id);
            } catch (\Throwable $e) {
                // 单条派发失败已被 job 标记 failed,不影响其余,不打断本次动作
            }
        }
    }

    /**
     * 标记"是否真的交给了中台处理"(delegated_to_mid)：只有商城销路(sale_destination=mall)的待定价设备
     * 才是真正委托中台拍照/定价的;本地/手工入库的待定价是 ERP 自己待标价, 不应显示"已交中台", ERP 可自行定价。
     */
    private function appendMidDelegation(array &$rows): void
    {
        foreach ($rows as &$r) {
            $snap = $r['source_snapshot'] ?? null;
            if (is_string($snap)) {
                $snap = (array)json_decode($snap, true);
            } elseif (is_object($snap)) {
                $snap = (array)$snap;
            } elseif (!is_array($snap)) {
                $snap = [];
            }
            $dest = $snap['sale_destination'] ?? '';
            $dest = is_scalar($dest) ? (string)$dest : '';
            $r['delegated_to_mid'] = (string)($r['inventory_status'] ?? '') === ErpDict::INVENTORY_PENDING_PRICING
                && ($dest === ErpDict::SALE_DESTINATION_MALL || !empty($snap['delegated_mid']));
        }
        unset($r);
    }

    /**
     * 标记"中台是否真的有这台设备"(in_mid):查 device_asset_item 是否存在该 source_device_id。
     * 这是"是否已进拍照中台"的权威信号——用于前端判断"推入拍照(中台)"按钮显隐:
     * 已在中台 → 不显示;未在中台(在库未交 / 交了但卡住没落库)→ 显示,允许手动补推。
     */
    private function appendMidPresence(array &$rows): void
    {
        foreach ($rows as &$r) {
            $r['in_mid'] = false;
        }
        unset($r);
        if (!class_exists('\addon\hsx_device_asset\app\model\DeviceAssetItem')) {
            return;
        }
        $devIds = array_values(array_unique(array_filter(array_map(
            static fn ($r) => (int)($r['source_device_id'] ?? 0),
            $rows
        ))));
        if (empty($devIds)) {
            return;
        }
        try {
            $present = \addon\hsx_device_asset\app\model\DeviceAssetItem::whereIn('site_id', [$this->site_id, 0])
                ->whereIn('device_id', $devIds)
                ->column('device_id');
            $set = array_flip(array_map('intval', $present));
            foreach ($rows as &$r) {
                $r['in_mid'] = isset($set[(int)($r['source_device_id'] ?? 0)]);
            }
            unset($r);
        } catch (\Throwable $e) {
            // 查询失败时保持 in_mid=false(宁可多显示按钮,也不误判已入中台)
        }
    }

    private function appendStatusLabels(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        // 出库的资产，查它最近一张出库明细对应的出库类型，区分已售/报废
        $outAssetIds = [];
        foreach ($rows as $r) {
            if ((string)($r['inventory_status'] ?? '') === ErpDict::INVENTORY_OUTBOUND) {
                $outAssetIds[] = (int)$r['id'];
            }
        }
        $dispMap = [];
        if (!empty($outAssetIds)) {
            $items = ErpOutboundItem::where([['site_id', '=', $this->site_id]])
                ->whereIn('asset_id', array_values(array_unique($outAssetIds)))
                ->order('id desc')->field('asset_id,outbound_id')->select()->toArray();
            $obIds = array_values(array_unique(array_filter(array_column($items, 'outbound_id'))));
            $typeMap = [];
            if (!empty($obIds)) {
                foreach (ErpOutboundOrder::where([['site_id', '=', $this->site_id]])->whereIn('id', $obIds)->field('id,outbound_type')->select()->toArray() as $o) {
                    $typeMap[(int)$o['id']] = (string)$o['outbound_type'];
                }
            }
            foreach ($items as $it) {
                $aid = (int)$it['asset_id'];
                if (!isset($dispMap[$aid])) { // 取最近一条(已按 id desc)
                    $dispMap[$aid] = $typeMap[(int)$it['outbound_id']] ?? '';
                }
            }
        }

        $textMap = [
            ErpDict::INVENTORY_PENDING_IN          => '待入库',
            ErpDict::INVENTORY_PENDING_PHOTO       => '待拍照',
            ErpDict::INVENTORY_INBOUND_REJECTED    => '入库驳回',
            ErpDict::INVENTORY_IN_STOCK            => '在库',
            ErpDict::INVENTORY_REFURBISHING        => '整备中',
            ErpDict::INVENTORY_PENDING_PRICING     => '待销售定价',
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE  => '在售',
            ErpDict::INVENTORY_LOCKED              => '销售锁定',
            ErpDict::INVENTORY_LOST                => '丢失',
        ];
        $typeTag = [
            ErpDict::INVENTORY_PENDING_IN          => 'warning',
            ErpDict::INVENTORY_PENDING_PHOTO       => 'warning',
            ErpDict::INVENTORY_INBOUND_REJECTED    => 'danger',
            ErpDict::INVENTORY_IN_STOCK            => 'success',
            ErpDict::INVENTORY_REFURBISHING        => 'warning',
            ErpDict::INVENTORY_PENDING_PRICING     => 'primary',
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE  => 'success',
            ErpDict::INVENTORY_LOCKED              => 'info',
            ErpDict::INVENTORY_LOST                => 'danger',
        ];
        foreach ($rows as &$row) {
            // 是否走过拍照(有图片)：供前端判断 ERP 定价员可定价(即使全局接了中台)
            $row['has_photo'] = !empty(((array)($row['source_snapshot'] ?? []))['images']);
            $st = (string)($row['inventory_status'] ?? '');
            if ($st === ErpDict::INVENTORY_OUTBOUND) {
                $disp = $dispMap[(int)$row['id']] ?? '';
                if ($disp === ErpDict::OUTBOUND_TYPE_PEER_SALE) {
                    $row['status_text'] = '已售(同行)';
                    $row['status_type'] = 'info';
                } elseif ($disp === ErpDict::OUTBOUND_TYPE_SCRAP) {
                    $row['status_text'] = '已报废';
                    $row['status_type'] = 'danger';
                } else {
                    $row['status_text'] = '已出库';
                    $row['status_type'] = 'info';
                }
            } else {
                $row['status_text'] = $textMap[$st] ?? ($st ?: '-');
                $row['status_type'] = $typeTag[$st] ?? 'info';
            }
        }
        unset($row);
    }

    /**
     * 周转/库龄：在库设备=库龄(至今多少天)；已出库/丢失=周转天数(入库到离库用了多少天)。
     */
    private function appendStockAge(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        $now = time();
        $left = [ErpDict::INVENTORY_OUTBOUND, ErpDict::INVENTORY_LOST];
        foreach ($rows as &$r) {
            $in = (int)($r['stock_in_at'] ?? 0);
            $out = (int)($r['stock_out_at'] ?? 0);
            if ($in <= 0) {
                $r['age_days'] = null;
                $r['age_type'] = '';
                continue;
            }
            if (in_array((string)($r['inventory_status'] ?? ''), $left, true) && $out > 0) {
                $r['age_days'] = round(($out - $in) / 86400, 1);  // 周转：收到离库
                $r['age_type'] = 'turnover';
            } else {
                $r['age_days'] = round(($now - $in) / 86400, 1);  // 库龄：在库时长
                $r['age_type'] = 'in_stock';
            }
        }
        unset($r);
    }

    /**
     * 给资产列表行补上仓库/库位名称（资产表只存 id），供前端调拨弹框等反显当前库位。
     */
    private function appendWarehouseNames(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        $whIds = array_values(array_unique(array_filter(array_map(fn($r) => (int)($r['warehouse_id'] ?? 0), $rows))));
        $locIds = array_values(array_unique(array_filter(array_map(fn($r) => (int)($r['location_id'] ?? 0), $rows))));
        $whMap = [];
        if (!empty($whIds)) {
            foreach (ErpWarehouse::where([['site_id', '=', $this->site_id]])->whereIn('id', $whIds)->field('id,warehouse_name')->select()->toArray() as $w) {
                $whMap[(int)$w['id']] = (string)($w['warehouse_name'] ?? '');
            }
        }
        $locMap = [];
        if (!empty($locIds)) {
            foreach (ErpWarehouseLocation::where([['site_id', '=', $this->site_id]])->whereIn('id', $locIds)->field('id,location_name')->select()->toArray() as $l) {
                $locMap[(int)$l['id']] = (string)($l['location_name'] ?? '');
            }
        }
        foreach ($rows as &$row) {
            $row['warehouse_name'] = $whMap[(int)($row['warehouse_id'] ?? 0)] ?? '';
            $row['location_name'] = $locMap[(int)($row['location_id'] ?? 0)] ?? '';
        }
        unset($row);
    }

    /**
     * 实时调整在库设备成本（写成本流水留痕，不改已出库/已售/盘亏的）。
     * @param int $assetId 资产ID
     * @param float $newCost 新成本(>=0)
     * @param string $reason 调整原因
     */
    public function adjustCost(int $assetId, float $newCost, string $reason = '', bool $syncPayable = false): array
    {
        if ($newCost < 0) {
            throw new CommonException('成本不能为负');
        }
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('ERP资产不存在');
        }
        // 已售/已出库(OUTBOUND)允许财务订正成本: 常见于"卖后才发现成本=0/填错"。
        // 此情形按既定口径只改成本+记成本流水, 不联动应付/应收(下方强制 syncPayable=false)。
        // 仍禁止真正无意义的状态: 盘亏 / 待入库 / 入库被拒。
        $blocked = [ErpDict::INVENTORY_LOST, ErpDict::INVENTORY_PENDING_IN, ErpDict::INVENTORY_INBOUND_REJECTED];
        if (in_array((string)$asset->inventory_status, $blocked, true)) {
            throw new CommonException('该设备当前状态不可调成本');
        }
        // 已售设备订正: 仅改成本与毛利口径, 不动应付/应收
        if ((string)$asset->inventory_status === ErpDict::INVENTORY_OUTBOUND) {
            $syncPayable = false;
        }
        $before = round((float)$asset->current_cost, 2);
        $newCost = round($newCost, 2);
        if (abs($newCost - $before) < 0.001) {
            throw new CommonException('成本未变化');
        }
        $delta = round($newCost - $before, 2);

        // 选择"差额计入应付"时,先在事务外预校验该设备的入库应付(失败更干净)
        $payableToSync = null;
        if ($syncPayable) {
            if ((int)$asset->source_device_id <= 0) {
                throw new CommonException('该设备无来源单号,无法同步应付');
            }
            $payableToSync = FinancePayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'erp_inbound'],
                ['source_device_id', '=', (int)$asset->source_device_id],
            ])->order('id desc')->findOrEmpty();
            if ($payableToSync->isEmpty()) {
                throw new CommonException('未找到该设备的入库应付(可能来自回收来源,成本调整已另行通知回收);如只调库存成本请取消勾选');
            }
        }

        $now = time();
        Db::transaction(function () use ($asset, $before, $newCost, $delta, $reason, $now, $syncPayable, $payableToSync) {
            $asset->save([
                'current_cost' => $newCost,
                'version'      => (int)$asset->version + 1,
                'update_at'    => $now,
            ]);
            // 差额计入对供应商的应付:加/减应付额并重算结算状态
            if ($syncPayable && $payableToSync && !$payableToSync->isEmpty()) {
                $newAmount = round((float)$payableToSync->amount + $delta, 2);
                $settled = round((float)$payableToSync->settled_amount, 2);
                if ($newAmount >= $settled - 0.001) {
                    // 正常:已付不变,按新应付额重算状态
                    $status = ($settled + 0.001 >= $newAmount)
                        ? FinanceDict::STATUS_SETTLED
                        : ($settled > 0 ? FinanceDict::STATUS_PARTIAL : FinanceDict::STATUS_PENDING);
                    FinancePayable::where([['site_id', '=', $this->site_id], ['id', '=', (int)$payableToSync->id]])->update([
                        'amount'      => $newAmount,
                        'status'      => $status,
                        'update_time' => $now,
                    ]);
                } else {
                    // 下调到低于已付:应付封到新额并结清, 多付的部分转应收(供应商欠我, 等退款或下次抵)
                    $overpay = round($settled - $newAmount, 2);
                    FinancePayable::where([['site_id', '=', $this->site_id], ['id', '=', (int)$payableToSync->id]])->update([
                        'amount'         => $newAmount,
                        'settled_amount' => $newAmount,
                        'status'         => FinanceDict::STATUS_SETTLED,
                        'update_time'    => $now,
                    ]);
                    (new CoreFinanceLedgerService())->recordReceivable([
                        'site_id'           => $this->site_id,
                        'event_id'          => 'erp_cost_adjust_refund_' . (int)$payableToSync->id . '_' . $now,
                        'amount'            => $overpay,
                        'counterparty_id'   => (int)$payableToSync->counterparty_id,
                        'counterparty_name' => (string)$payableToSync->counterparty_name,
                        'source_type'       => 'cost_adjust',
                        'source_no'         => 'ADJ' . (int)$asset->id,
                        'source_device_id'  => (int)$asset->source_device_id,
                        'occurred_at'       => $now,
                        'remark'            => '成本下调 ¥' . number_format($overpay, 2) . ' 多付转应收' . ($reason !== '' ? '：' . $reason : ''),
                    ]);
                }
            }
            ErpCostLedger::create([
                'site_id'         => $this->site_id,
                'ledger_no'       => $this->makeNo('CL'),
                'asset_id'        => (int)$asset->id,
                'cycle_id'        => (int)$asset->cycle_id,
                'cost_type'       => 'manual_adjust',
                'amount_delta'    => round($newCost - $before, 2),
                'before_cost'     => $before,
                'after_cost'      => $newCost,
                'source_type'     => 'manual',
                'source_id'       => 0,
                'counterparty_id' => (int)$asset->counterparty_id,
                'operator_id'     => $this->uid,
                'operator_name'   => $this->username ?: '',
                'occurred_at'     => $now,
                'remark'          => '手动调成本' . ($reason !== '' ? '：' . $reason : ''),
            ]);
        });

        // 发"成本已调整"事件给来源插件(回收)承接留痕；故障隔离，不影响 ERP 调成本主流程
        if ((int)$asset->source_device_id > 0) {
            try {
                event('ErpAssetCostAdjusted', [
                    'site_id'          => (int)$this->site_id,
                    'source_device_id' => (int)$asset->source_device_id,
                    'asset_no'         => (string)$asset->asset_no,
                    'imei'             => (string)$asset->imei,
                    'model'            => (string)$asset->model,
                    'before_cost'      => $before,
                    'after_cost'       => $newCost,
                    'delta'            => round($newCost - $before, 2),
                    'reason'           => $reason,
                    'operator'         => (string)($this->username ?: ''),
                    'occurred_at'      => $now,
                ]);
            } catch (\Throwable $e) {
                \think\facade\Log::warning('[erp] 发成本调整事件失败: ' . $e->getMessage());
            }
        }
        return ['asset_id' => (int)$asset->id, 'before_cost' => $before, 'after_cost' => $newCost];
    }

    /**
     * 承接"回收侧成本调整"事件: 按增量同步 ERP 资产成本 + 记成本流水(操作人=回收操作人)。
     * 只改数据、不再回发事件(防与 ERP→回收 形成死循环)。
     */
    public function applyRecycleCostDelta(int $sourceDeviceId, float $delta, string $reason, string $operator): void
    {
        $delta = round($delta, 2);
        if ($sourceDeviceId <= 0 || abs($delta) < 0.001) {
            return;
        }
        $asset = ErpAsset::where([['source_device_id', '=', $sourceDeviceId]])->order('id desc')->findOrEmpty();
        if ($asset->isEmpty()) {
            return;
        }
        $before = round((float)$asset->current_cost, 2);
        $after = round($before + $delta, 2);
        $now = time();
        Db::transaction(function () use ($asset, $before, $after, $delta, $reason, $operator, $now, $sourceDeviceId) {
            $asset->save(['current_cost' => $after, 'version' => (int)$asset->version + 1, 'update_at' => $now]);
            ErpCostLedger::create([
                'site_id'         => (int)$asset->site_id ?: $this->site_id,
                'ledger_no'       => $this->makeNo('CL'),
                'asset_id'        => (int)$asset->id,
                'cycle_id'        => (int)$asset->cycle_id,
                'cost_type'       => 'recycle_cost_sync',
                'amount_delta'    => $delta,
                'before_cost'     => $before,
                'after_cost'      => $after,
                'source_type'     => 'recycle',
                'source_id'       => $sourceDeviceId,
                'counterparty_id' => (int)$asset->counterparty_id,
                'operator_id'     => 0,
                'operator_name'   => $operator !== '' ? $operator : '回收同步',
                'occurred_at'     => $now,
                'remark'          => '回收侧调成本同步' . ($reason !== '' ? '：' . $reason : ''),
            ]);
        });
    }

    public function getInfo(int $id): array
    {
        $asset = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('ERP资产不存在');
        }

        $counterparty = (int)$asset->counterparty_id > 0
            ? ErpCounterparty::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$asset->counterparty_id],
            ])->findOrEmpty()->toArray()
            : [];

        // 联系人：主体(往来单位)+主体电话 + 关联人(source_member)+关联人电话(两个电话区分)
        $memberMap = (int)$asset->source_member_id > 0
            ? FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, [(int)$asset->source_member_id])
            : [];
        $person = $memberMap[(int)$asset->source_member_id] ?? null;
        $contact = [
            'unit_name'     => (string)($counterparty['name'] ?? ($person['entity_name'] ?? '')),
            'unit_mobile'   => (string)($counterparty['mobile'] ?? ''),
            'unit_contact'  => (string)($counterparty['contact_name'] ?? ''),
            'person_name'   => (string)($person['name'] ?? ''),
            'person_mobile' => (string)($person['mobile'] ?? ''),
        ];

        $statusMap = ErpDict::getInventoryStatusMap();
        $actionMap = ErpDict::getLedgerActionMap();
        $costMap   = ErpDict::getCostTypeMap();

        $stockLedger = ErpStockLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])->order('id desc')->select()->toArray();
        foreach ($stockLedger as &$r) {
            $r['action_text'] = $actionMap[(string)($r['action'] ?? '')] ?? (string)($r['action'] ?? '');
            $r['before_status_text'] = $statusMap[(string)($r['before_status'] ?? '')] ?? (string)($r['before_status'] ?? '');
            $r['after_status_text'] = $statusMap[(string)($r['after_status'] ?? '')] ?? (string)($r['after_status'] ?? '');
        }
        unset($r);
        $costLedger = ErpCostLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])->order('id desc')->select()->toArray();
        foreach ($costLedger as &$r) {
            $r['cost_type_text'] = $costMap[(string)($r['cost_type'] ?? '')] ?? (string)($r['cost_type'] ?? '');
        }
        unset($r);
        $timeline = ErpOperationEvent::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])->order('occurred_at desc,id desc')->select()->toArray();
        foreach ($timeline as &$r) {
            $r['action_text'] = $actionMap[(string)($r['action'] ?? '')] ?? (string)($r['action'] ?? '');
        }
        unset($r);

        // 已出库/已售：出库人、时间、卖给了谁(买家主体/电话)
        $outboundInfo = null;
        if ((string)$asset->inventory_status === ErpDict::INVENTORY_OUTBOUND) {
            $item = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])->order('id desc')->findOrEmpty();
            if (!$item->isEmpty()) {
                $order = ErpOutboundOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->outbound_id]])->findOrEmpty();
                if (!$order->isEmpty()) {
                    $outboundInfo = [
                        'outbound_no'   => (string)$order->outbound_no,
                        'type_text'     => ErpDict::getOutboundTypeMap()[(string)$order->outbound_type] ?? (string)$order->outbound_type,
                        'operator_name' => (string)$order->operator_name,
                        'out_at'        => (int)$order->out_at,
                        'buyer_name'    => (string)$order->counterparty_name,
                        'sale_price'    => (float)$item->sale_price,
                    ];
                }
            }
        }

        return [
            'asset'         => $asset->toArray(),
            'counterparty'  => $counterparty,
            'contact'       => $contact,
            'outbound_info' => $outboundInfo,
            'stock_ledger'  => $stockLedger,
            'cost_ledger'   => $costLedger,
            'timeline'      => $timeline,
        ];
    }

    public function confirmInbound(int $stockOrderId, array $data = []): array
    {
        $order = ErpStockOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $stockOrderId],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('入库单不存在');
        }

        $assetIds = ErpStockOrderItem::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $stockOrderId],
            ['status', '=', 'pending'],
        ])->column('asset_id');
        if (empty($assetIds)) {
            if ((string)$order->status === ErpDict::STOCK_ORDER_CONFIRMED) {
                return [
                    'confirmed_count' => 0,
                    'existing_count' => (int)$order->device_count,
                    'order' => $order->toArray(),
                ];
            }
            throw new CommonException('入库单没有可确认设备');
        }

        $result = $this->confirmAssetsInbound($assetIds, $data);
        $result['order'] = ErpStockOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $stockOrderId],
        ])->findOrEmpty()->toArray();
        return $result;
    }

    public function confirmInboundByAsset(int $assetId, array $data = []): array
    {
        return $this->confirmAssetsInbound([$assetId], $data);
    }

    public function confirmAssetsInbound(array $assetIds, array $data = []): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));
        if (empty($assetIds)) {
            throw new CommonException('请选择需要确认入库的设备');
        }

        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $locationId = (int)($data['location_id'] ?? 0);
        (new ErpWarehouseService())->validateInboundLocation($warehouseId, $locationId);
        // 进仓门槛:目的仓"必须拍照"则不进在库、不走定价决策,拍完才入库。由仓库设置决定。
        // 要求拍照 + 数据中台已接入 → 交中台拍照定价(置「已交中台·处理中」=待定价并发 ready_for_photo);
        // 中台未接入 → ERP 自己走「待拍照」(completePhoto)。
        $requirePhoto = $warehouseId > 0 && (new ErpWarehouseService())->requiresPhoto($warehouseId);
        $midConnected = class_exists('\\addon\\hsx_device_asset\\app\\service\\admin\\DeviceAssetService');
        $delegateToMid = $requirePhoto && $midConnected;
        $targetStatus = $requirePhoto
            ? ($delegateToMid ? ErpDict::INVENTORY_PENDING_PRICING : ErpDict::INVENTORY_PENDING_PHOTO)
            : ErpDict::INVENTORY_IN_STOCK;
        $remark = trim((string)($data['remark'] ?? ''));
        $now = time();
        $outboxIds = [];
        $confirmedAssetIds = [];
        $existingAssetIds = [];
        $orderIds = [];

        Db::startTrans();
        try {
            $items = ErpStockOrderItem::where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 'pending'],
            ])->whereIn('asset_id', $assetIds)->lock(true)->select();
            $pendingItemMap = [];
            foreach ($items as $item) {
                $pendingItemMap[(int)$item->asset_id] = $item;
            }

            $missingAssetIds = array_values(array_diff($assetIds, array_keys($pendingItemMap)));
            if (!empty($missingAssetIds)) {
                $existingAssetIds = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['inventory_status', '=', ErpDict::INVENTORY_IN_STOCK],
                ])->whereIn('id', $missingAssetIds)->column('id');
                $invalidAssetIds = array_diff($missingAssetIds, array_map('intval', $existingAssetIds));
                if (!empty($invalidAssetIds)) {
                    throw new CommonException('部分设备不存在待入库明细，请刷新列表后重试');
                }
            }

            foreach ($pendingItemMap as $item) {
                $stockOrderId = (int)$item->order_id;
                $order = ErpStockOrder::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $stockOrderId],
                ])->lock(true)->findOrEmpty();
                if ($order->isEmpty()) {
                    throw new CommonException('设备所属入库单不存在');
                }
                $orderIds[$stockOrderId] = $stockOrderId;

                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$item->asset_id],
                ])->lock(true)->findOrEmpty();
                if ($asset->isEmpty()) {
                    throw new CommonException('入库设备不存在');
                }
                if ((string)$asset->inventory_status !== ErpDict::INVENTORY_PENDING_IN) {
                    throw new CommonException('设备状态不允许入库：' . ($asset->imei ?: $asset->asset_no));
                }

                $beforeStatus = (string)$asset->inventory_status;
                $purchaseCost = ErpMoney::normalize($asset->purchase_cost);
                $asset->save([
                    'inventory_status' => $targetStatus,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'current_cost' => $purchaseCost,
                    // ERP 待拍照阶段逻辑未在库, 库龄从真正入库(拍完)起算; 交中台/不要求拍照的已在库
                    'stock_in_at' => ($requirePhoto && !$delegateToMid) ? 0 : $now,
                    'version' => (int)$asset->version + 1,
                    'update_at' => $now,
                ]);
                ErpAssetCycle::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$asset->cycle_id],
                ])->update([
                    'status' => $targetStatus,
                    'update_at' => $now,
                ]);
                $item->save(['status' => 'confirmed', 'update_at' => $now]);
                $confirmedAssetIds[] = (int)$asset->id;

                ErpStockLedger::create([
                    'site_id' => $this->site_id,
                    'ledger_no' => $this->makeNo('SL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'stock_order_id' => $stockOrderId,
                    'action' => 'stock_in',
                    'before_status' => $beforeStatus,
                    'after_status' => $targetStatus,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'occurred_at' => $now,
                    'payload' => ['remark' => $remark],
                ]);
                ErpCostLedger::create([
                    'site_id' => $this->site_id,
                    'ledger_no' => $this->makeNo('CL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'cost_type' => 'purchase',
                    'amount_delta' => $purchaseCost,
                    'before_cost' => '0.00',
                    'after_cost' => $purchaseCost,
                    'source_plugin' => (string)$order->source_plugin,
                    'source_type' => (string)$order->source_type,
                    'source_id' => (int)$item->source_device_id,
                    'counterparty_id' => (int)$asset->counterparty_id,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'occurred_at' => $now,
                    'remark' => '确认入库生成初始采购成本',
                ]);
                $this->writeOperation(
                    (int)$asset->id,
                    (int)$asset->cycle_id,
                    'erp.asset.stocked.v1',
                    'confirm_stock_in',
                    $stockOrderId,
                    ['warehouse_id' => $warehouseId, 'location_id' => $locationId]
                );
                $sourceSnapshot = (array)$asset->source_snapshot;
                $payableAmount = ErpMoney::normalize($sourceSnapshot['payable_amount'] ?? $purchaseCost);
                $paidAmount = ErpMoney::normalize($sourceSnapshot['paid_amount'] ?? 0);
                $settlementStatus = array_key_exists('settlement_status', $sourceSnapshot)
                    ? (string)$sourceSnapshot['settlement_status']
                    : (ErpMoney::compare($payableAmount, '0.00') === 0 ? 'not_applicable' : 'unknown');
                $eventId = $this->makeEventId('stocked', (int)$asset->id);
                $domainEvent = ErpDomainEvent::create(
                    $this->site_id,
                    'erp.asset.stocked.v1',
                    $eventId,
                    'asset',
                    (int)$asset->id,
                    ['type' => 'staff', 'id' => $this->uid, 'name' => $this->username ?: ''],
                    [
                        'plugin' => (string)$order->source_plugin,
                        'type' => (string)$order->source_type,
                        'id' => (int)$item->source_device_id,
                    ],
                    [
                        'asset_id' => (int)$asset->id,
                        'cycle_id' => (int)$asset->cycle_id,
                        'stock_order_id' => $stockOrderId,
                        'source_device_id' => (int)$asset->source_device_id,
                        'counterparty_id' => (int)$asset->counterparty_id,
                        'source_member_id' => (int)$asset->source_member_id,
                        'ownership_type' => (string)$asset->ownership_type,
                        'payable_amount' => $payableAmount,
                        'paid_amount' => $paidAmount,
                        'settlement_status' => $settlementStatus,
                    ],
                    $now
                );
                $outbox = ErpDomainEvent::writeOutbox($domainEvent, $now);
                $outboxIds[] = (int)$outbox->id;
                // 待拍照:暂不走整备/定价决策,等拍完照确认入库后再决策。
                // 例外:需整备的机器要"先整备再拍照"——即使仓库要求拍照, 也先走整备决策(置整备中),
                // 整备完成后再转「待拍照」, 避免带病/未修的机器先被拍照。
                $needRefurb = (bool)($this->normalizeRefurbishmentPlan((array)($sourceSnapshot['refurbishment'] ?? []))['required'] ?? false);
                if (!$requirePhoto || $needRefurb) {
                    $outboxIds = array_merge(
                        $outboxIds,
                        $this->applyPostInboundRefurbishmentDecision($asset, $stockOrderId, $now)
                    );
                } elseif ($delegateToMid) {
                    // 要求拍照 + 中台接入 + 无需整备 → 交中台拍照定价
                    $outboxIds = array_merge($outboxIds, $this->handToMid($asset, $stockOrderId, $now));
                }
            }

            foreach ($orderIds as $orderId) {
                $order = ErpStockOrder::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $orderId],
                ])->lock(true)->findOrEmpty();
                $pendingCount = ErpStockOrderItem::where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', $orderId],
                    ['status', '=', 'pending'],
                ])->count();
                $confirmedCount = ErpStockOrderItem::where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', $orderId],
                    ['status', '=', ErpDict::STOCK_ITEM_CONFIRMED],
                ])->count();
                $rejectedCount = ErpStockOrderItem::where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', $orderId],
                    ['status', '=', ErpDict::STOCK_ITEM_REJECTED],
                ])->count();
                $orderStatus = ErpDict::STOCK_ORDER_DRAFT;
                if ($pendingCount === 0 && $rejectedCount === 0) {
                    $orderStatus = ErpDict::STOCK_ORDER_CONFIRMED;
                } elseif ($confirmedCount > 0) {
                    $orderStatus = ErpDict::STOCK_ORDER_PARTIAL_CONFIRMED;
                } elseif ($pendingCount === 0 && $rejectedCount > 0) {
                    $orderStatus = ErpDict::STOCK_ORDER_REJECTED;
                }
                $orderData = [
                    'status' => $orderStatus,
                    'warehouse_id' => $warehouseId,
                    'remark' => $remark ?: (string)$order->remark,
                    'update_at' => $now,
                ];
                if ($orderStatus === ErpDict::STOCK_ORDER_CONFIRMED) {
                    $orderData['confirmed_by'] = $this->uid;
                    $orderData['confirmed_at'] = $now;
                }
                $order->save($orderData);
            }

            Db::commit();
            foreach ($outboxIds as $outboxId) {
                $this->publish([(int)$outboxId]); // 同步派发,保证中台当场收到(不依赖队列 worker)
            }
            return [
                'confirmed_count' => count($confirmedAssetIds),
                'existing_count' => count($existingAssetIds),
                'asset_ids' => $confirmedAssetIds,
                'order_ids' => array_values($orderIds),
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 完成拍照:待拍照 → 真正入库在库,存图片,并走整备/定价决策(图片已存→抑制 ready_for_photo,直接进待定价/可售)。
     */
    public function completePhoto(int $assetId, array $images): array
    {
        $images = array_values(array_filter(array_map('strval', $images), static fn($u) => trim($u) !== ''));
        if (empty($images)) {
            throw new CommonException('请至少上传一张照片');
        }
        $now = time();
        $outboxIds = [];
        Db::startTrans();
        try {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty()) {
                throw new CommonException('设备不存在');
            }
            if ((string)$asset->inventory_status !== ErpDict::INVENTORY_PENDING_PHOTO) {
                throw new CommonException('该设备不在待拍照状态');
            }
            $snapshot = (array)$asset->source_snapshot;
            $snapshot['images'] = $images;
            $beforeStatus = (string)$asset->inventory_status;
            $asset->save([
                'inventory_status' => ErpDict::INVENTORY_IN_STOCK,
                'source_snapshot' => $snapshot,
                'stock_in_at' => $now,
                'version' => (int)$asset->version + 1,
                'update_at' => $now,
            ]);
            ErpAssetCycle::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset->cycle_id]])->update([
                'status' => ErpDict::INVENTORY_IN_STOCK,
                'update_at' => $now,
            ]);
            ErpStockLedger::create([
                'site_id' => $this->site_id,
                'ledger_no' => $this->makeNo('SL'),
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'stock_order_id' => 0,
                'action' => 'photo_completed',
                'before_status' => $beforeStatus,
                'after_status' => ErpDict::INVENTORY_IN_STOCK,
                'warehouse_id' => (int)$asset->warehouse_id,
                'location_id' => (int)$asset->location_id,
                'operator_id' => $this->uid,
                'operator_name' => $this->username ?: '',
                'occurred_at' => $now,
                'payload' => ['image_count' => count($images)],
            ]);
            $this->writeOperation((int)$asset->id, (int)$asset->cycle_id, 'erp.asset.photographed.v1', 'complete_photo', 0, ['image_count' => count($images)]);
            $outboxIds = $this->applyPostInboundRefurbishmentDecision($asset, 0, $now);
            Db::commit();
            foreach ($outboxIds as $outboxId) {
                $this->publish([(int)$outboxId]); // 同步派发,保证中台当场收到(不依赖队列 worker)
            }
            return ['asset_id' => $assetId, 'status' => ErpDict::INVENTORY_IN_STOCK, 'image_count' => count($images)];
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    private function writeOperation(
        int $assetId,
        int $cycleId,
        string $eventName,
        string $action,
        int $documentId,
        array $payload
    ): void {
        ErpOperationEvent::create([
            'site_id' => $this->site_id,
            'event_id' => $this->makeEventId($action, $assetId),
            'event_name' => $eventName,
            'asset_id' => $assetId,
            'cycle_id' => $cycleId,
            'document_type' => 'stock_in',
            'document_id' => $documentId,
            'stage' => 'inbound',
            'action' => $action,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'payload' => $payload,
            'occurred_at' => time(),
        ]);
    }

    private function applyPostInboundRefurbishmentDecision(ErpAsset $asset, int $stockOrderId, int $now): array
    {
        $sourceSnapshot = (array)$asset->source_snapshot;
        $plan = $this->normalizeRefurbishmentPlan((array)($sourceSnapshot['refurbishment'] ?? []));
        // 防二次整备:该设备已建过整备单(先整备再拍照场景, 拍完照又回到这里)则不再重复建单, 直接走定价决策
        $alreadyRefurbished = ErpRefurbishOrder::where([['site_id', '=', $this->site_id], ['asset_id', '=', (int)$asset->id]])->count() > 0;
        if ($plan['required'] && !$alreadyRefurbished) {
            $refurbishOrder = $this->createRefurbishmentOrderFromDecision($asset, $plan, $stockOrderId, $now);
            $this->writeOperation(
                (int)$asset->id,
                (int)$asset->cycle_id,
                'erp.refurbishment.required.v1',
                'refurbishment_required',
                $stockOrderId,
                [
                    'stock_order_id' => $stockOrderId,
                    'required' => true,
                    'decision_source' => $plan['decision_source'],
                    'reason' => $plan['reason'],
                    'suggested_items' => $plan['suggested_items'],
                    'estimated_cost' => $plan['estimated_cost'],
                    'assignee' => $plan['assignee'],
                    'refurbish_order_id' => (int)$refurbishOrder->id,
                    'refurbish_order_no' => (string)$refurbishOrder->order_no,
                    'next_status' => ErpDict::INVENTORY_REFURBISHING,
                ]
            );
            return [
                $this->writeDecisionEvent($asset, 'erp.refurbishment.required.v1', $stockOrderId, [
                    'required' => true,
                    'decision_source' => $plan['decision_source'],
                    'reason' => $plan['reason'],
                    'suggested_items' => $plan['suggested_items'],
                    'estimated_cost' => $plan['estimated_cost'],
                    'decided_by' => $plan['decided_by'],
                    'assignee' => $plan['assignee'],
                    'decided_at' => $plan['decided_at'],
                    'refurbish_order_id' => (int)$refurbishOrder->id,
                    'refurbish_order_no' => (string)$refurbishOrder->order_no,
                    'next_status' => ErpDict::INVENTORY_REFURBISHING,
                ], $now),
            ];
        }

        $beforeStatus = (string)$asset->inventory_status;

        // 定价归属（避免多处重复定价）：
        //  - 商城销路 → 委托中台拍照定价，仍置待定价，由中台定价回流后转可售；
        //  - 非商城销路且已带售价（回收侧已定价 / 手工建档已敲价）→ 入库即可售，直接用该售价，不再走 ERP 定价。
        $sourceSnapshot = (array)$asset->source_snapshot;
        $saleDestination = (string)($sourceSnapshot['sale_destination'] ?? '');
        $suggestedPrice = round((float)($sourceSnapshot['suggested_sale_price'] ?? $asset->current_sale_price ?? 0), 2);
        $isMall = $saleDestination === ErpDict::SALE_DESTINATION_MALL;
        $directSellable = !$isMall && $suggestedPrice > 0;
        $nextStatus = $directSellable ? ErpDict::INVENTORY_AVAILABLE_FOR_SALE : ErpDict::INVENTORY_PENDING_PRICING;
        $decisionReason = $plan['reason'] ?: ($directSellable
            ? '默认无需整备，已带售价，入库即可售'
            : '默认无需整备，入库后进入待销售定价');

        $assetSave = [
            'inventory_status' => $nextStatus,
            'version' => (int)$asset->version + 1,
            'update_at' => $now,
        ];
        if ($directSellable) {
            $assetSave['current_sale_price'] = $suggestedPrice;
        }
        $asset->save($assetSave);
        ErpAssetCycle::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->cycle_id],
        ])->update([
            'status' => $nextStatus,
            'update_at' => $now,
        ]);
        ErpStockLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => $this->makeNo('SL'),
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'stock_order_id' => $stockOrderId,
            'action' => 'skip_refurbishment',
            'before_status' => $beforeStatus,
            'after_status' => $nextStatus,
            'warehouse_id' => (int)$asset->warehouse_id,
            'location_id' => (int)$asset->location_id,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'occurred_at' => $now,
            'payload' => [
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $decisionReason,
                'sale_price' => $directSellable ? $suggestedPrice : null,
            ],
        ]);
        $this->writeOperation(
            (int)$asset->id,
            (int)$asset->cycle_id,
            'erp.refurbishment.skipped.v1',
            'skip_refurbishment',
            $stockOrderId,
            [
                'stock_order_id' => $stockOrderId,
                'required' => false,
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $decisionReason,
                'next_status' => $nextStatus,
            ]
        );

        return array_merge([
            $this->writeDecisionEvent($asset, 'erp.refurbishment.skipped.v1', $stockOrderId, [
                'required' => false,
                'decision_source' => $plan['decision_source'] ?: 'default',
                'reason' => $decisionReason,
                'next_status' => $nextStatus,
            ], $now),
        ], $this->writeReadyForPhotoEvents($asset, $stockOrderId, $now));
    }

    private function writeReadyForPhotoEvents(ErpAsset $asset, int $documentId, int $now): array
    {
        $sourceSnapshot = (array)$asset->source_snapshot;
        if ((string)($sourceSnapshot['sale_destination'] ?? '') !== ErpDict::SALE_DESTINATION_MALL) {
            return [];
        }
        // 轻量路:手工入库已内联补了图片(+价格)→ 走直推商城,不再生成拍照工单,避免重复
        if (!empty($sourceSnapshot['images'])) {
            return [];
        }
        // 进仓未要求拍照 → 不交中台,直接可售/可打包卖同行(require_photo 是"交不交中台"的唯一开关)
        if (!(new ErpWarehouseService())->requiresPhoto((int)$asset->warehouse_id)) {
            return [];
        }
        return [
            $this->writeDecisionEvent($asset, 'erp.asset.ready_for_photo.v1', $documentId, [
                'source_device_id' => (int)$asset->source_device_id,
                'sale_destination' => ErpDict::SALE_DESTINATION_MALL,
                'next_status' => ErpDict::INVENTORY_PENDING_PRICING,
            ], $now),
        ];
    }

    private function normalizeRefurbishmentPlan(array $plan): array
    {
        $items = $plan['suggested_items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }
        return [
            'required' => (bool)($plan['required'] ?? false),
            'decision_source' => (string)($plan['decision_source'] ?? 'default'),
            'reason' => trim((string)($plan['reason'] ?? '')),
            'suggested_items' => array_values($items),
            'estimated_cost' => ErpMoney::normalize($plan['estimated_cost'] ?? 0),
            'decided_by' => (array)($plan['decided_by'] ?? []),
            'assignee' => (array)($plan['assignee'] ?? []),
            'decided_at' => (int)($plan['decided_at'] ?? 0),
        ];
    }

    private function createRefurbishmentOrderFromDecision(ErpAsset $asset, array $plan, int $stockOrderId, int $now): ErpRefurbishOrder
    {
        $assignee = (array)$plan['assignee'];
        $assignedUid = (int)($assignee['id'] ?? 0);
        $assignedName = trim((string)($assignee['name'] ?? ''));
        if ($assignedUid <= 0) {
            $assignedUid = $this->uid;
            $assignedName = $this->username ?: '';
        }
        if ($assignedName === '') {
            $assignedName = $assignedUid > 0 ? ('员工#' . $assignedUid) : '待分配';
        }

        $order = ErpRefurbishOrder::create([
            'site_id' => $this->site_id,
            'order_no' => $this->makeNo('ZB'),
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'status' => ErpDict::REFURBISH_PROCESSING,
            'assigned_uid' => $assignedUid,
            'assigned_name' => $assignedName,
            'planned_finish_at' => 0,
            'started_at' => $now,
            'remark' => trim((string)$plan['reason']),
            'create_at' => $now,
            'update_at' => $now,
        ]);

        foreach ($plan['suggested_items'] as $item) {
            $name = is_array($item) ? trim((string)($item['item_name'] ?? $item['name'] ?? '')) : trim((string)$item);
            if ($name === '') {
                continue;
            }
            ErpRefurbishItem::create([
                'site_id' => $this->site_id,
                'order_id' => (int)$order->id,
                'item_type' => is_array($item) ? (string)($item['item_type'] ?? $item['type'] ?? 'other') : 'other',
                'item_name' => $name,
                'amount' => '0.00',
                'remark' => '',
                'create_at' => $now,
                'update_at' => $now,
            ]);
        }

        $beforeStatus = (string)$asset->inventory_status;
        $asset->save([
            'inventory_status' => ErpDict::INVENTORY_REFURBISHING,
            'version' => (int)$asset->version + 1,
            'update_at' => $now,
        ]);
        ErpAssetCycle::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->cycle_id],
        ])->update([
            'status' => ErpDict::INVENTORY_REFURBISHING,
            'update_at' => $now,
        ]);
        ErpStockLedger::create([
            'site_id' => $this->site_id,
            'ledger_no' => $this->makeNo('SL'),
            'asset_id' => (int)$asset->id,
            'cycle_id' => (int)$asset->cycle_id,
            'stock_order_id' => $stockOrderId,
            'action' => 'auto_create_refurbishment',
            'before_status' => $beforeStatus,
            'after_status' => ErpDict::INVENTORY_REFURBISHING,
            'warehouse_id' => (int)$asset->warehouse_id,
            'location_id' => (int)$asset->location_id,
            'operator_id' => $this->uid,
            'operator_name' => $this->username ?: '',
            'occurred_at' => $now,
            'payload' => [
                'refurbish_order_id' => (int)$order->id,
                'refurbish_order_no' => (string)$order->order_no,
                'reason' => $plan['reason'],
                'estimated_cost' => $plan['estimated_cost'],
            ],
        ]);

        return $order;
    }

    private function writeDecisionEvent(ErpAsset $asset, string $eventName, int $stockOrderId, array $payload, int $now): int
    {
        $event = ErpDomainEvent::create(
            $this->site_id,
            $eventName,
            $this->makeEventId(str_replace('.', '-', $eventName), (int)$asset->id),
            'asset',
            (int)$asset->id,
            ['type' => 'staff', 'id' => $this->uid, 'name' => $this->username ?: ''],
            ['plugin' => 'hsx_erp', 'type' => 'stock_in', 'id' => $stockOrderId],
            array_merge([
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'stock_order_id' => $stockOrderId,
                'source_device_id' => (int)$asset->source_device_id,
                'counterparty_id' => (int)$asset->counterparty_id,
            ], $payload),
            $now
        );
        return (int)ErpDomainEvent::writeOutbox($event, $now)->id;
    }

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT) . random_int(100000, 999999);
    }

    private function makeEventId(string $prefix, int $id): string
    {
        return $prefix . '-' . $id . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
    }

    private function appendCounterparties(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        // counterparty_id(销售买家)与 source_member_id(回收来源)都是"会员ID", 统一用 member→人+主体 口径解析
        $memberIds = [];
        foreach ($rows as $r) {
            $memberIds[] = (int)($r['source_member_id'] ?? 0);
            $memberIds[] = (int)($r['counterparty_id'] ?? 0);
        }
        $memberIds = array_values(array_unique(array_filter($memberIds)));
        $mmap = !empty($memberIds)
            ? FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, $memberIds)
            : [];
        $sold = [ErpDict::INVENTORY_OUTBOUND, ErpDict::INVENTORY_LOCKED];
        $party = static function (?array $m): ?array {
            if (!$m) { return null; }
            return [
                'name'        => (string)$m['name'],
                'mobile'      => (string)$m['mobile'],
                'entity_name' => (string)$m['entity_name'],
                'entity_id'   => (int)$m['entity_id'],
            ];
        };
        foreach ($rows as &$row) {
            $rm = $mmap[(int)($row['source_member_id'] ?? 0)] ?? null;
            // 回收单位(从谁收的)
            $row['recycle_party'] = $party($rm);
            // 销售单位(卖给谁): 仅已售出/锁定的资产才有买家
            $sm = in_array((string)($row['inventory_status'] ?? ''), $sold, true)
                ? ($mmap[(int)($row['counterparty_id'] ?? 0)] ?? null)
                : null;
            $row['sales_party'] = $party($sm);
            // 旧字段兼容(以回收来源为准)
            $row['contact'] = [
                'unit_name'     => (string)($rm['entity_name'] ?? ''),
                'person_name'   => (string)($rm['name'] ?? ''),
                'person_mobile' => (string)($rm['mobile'] ?? ''),
            ];
        }
        unset($row);
    }

    /**
     * 库存概览（设备中心顶部卡片）：在手、可售、本月已售毛利、平均库龄
     */
    public function inventoryOverview(): array
    {
        $scope = $this->scopedLocationIds();
        $base = function () use ($scope) {
            $q = ErpAsset::where([['site_id', '=', $this->site_id]]);
            if ($scope !== null) {
                $q->whereIn('location_id', $scope);
            }
            return $q;
        };

        $onHand = [
            ErpDict::INVENTORY_IN_STOCK,
            ErpDict::INVENTORY_REFURBISHING,
            ErpDict::INVENTORY_PENDING_PRICING,
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
        ];

        // 在手库存
        $onHandCount = $base()->whereIn('inventory_status', $onHand)->count();
        $onHandCost  = round((float)$base()->whereIn('inventory_status', $onHand)->sum('current_cost'), 2);

        // 可售
        $sellableCount  = $base()->where('inventory_status', '=', ErpDict::INVENTORY_AVAILABLE_FOR_SALE)->count();
        $sellableAmount = round((float)$base()->where('inventory_status', '=', ErpDict::INVENTORY_AVAILABLE_FOR_SALE)->sum('current_sale_price'), 2);

        // 本月已售（毛利）
        $mStart = strtotime(date('Y-m') . '-01 00:00:00');
        $now = time();
        $soldQ = fn() => $base()->where('inventory_status', '=', ErpDict::INVENTORY_OUTBOUND)
            ->where('stock_out_at', '>=', $mStart)->where('stock_out_at', '<=', $now);
        $soldCount = $soldQ()->count();
        $soldSale  = round((float)$soldQ()->sum('current_sale_price'), 2);
        $soldCost  = round((float)$soldQ()->sum('current_cost'), 2);
        $grossProfit = round($soldSale - $soldCost, 2);

        // 平均库龄(在手)
        $avgStockIn = (float)$base()->whereIn('inventory_status', $onHand)->where('stock_in_at', '>', 0)->avg('stock_in_at');
        $avgAgeDays = $avgStockIn > 0 ? round(($now - $avgStockIn) / 86400, 1) : 0;

        return [
            'on_hand'        => ['count' => $onHandCount, 'cost' => $onHandCost],
            'sellable'       => ['count' => $sellableCount, 'amount' => $sellableAmount],
            'sold_month'     => ['count' => $soldCount, 'amount' => $soldSale, 'gross_profit' => $grossProfit],
            'avg_age_days'   => $avgAgeDays,
        ];
    }

    /**
     * 经营报表（按发生时间区间聚合资产，真实毛利）
     * - 采购入库：stock_in_at 落在区间
     * - 销售：stock_out_at 落在区间 且 已出库，毛利 = 售价 - 当前成本
     * - 期末在库：当前在库设备及其成本
     */
    public function businessReport(int $start, int $end): array
    {
        $base = fn() => ErpAsset::where([['site_id', '=', $this->site_id]]);

        // 采购入库
        $inCount = $base()->where('stock_in_at', '>=', $start)->where('stock_in_at', '<=', $end)->count();
        $inCost  = round((float)$base()->where('stock_in_at', '>=', $start)->where('stock_in_at', '<=', $end)->sum('purchase_cost'), 2);

        // 销售出库（毛利）：按出库单统计——同行销售、未作废；现结(已出库)与挂单(已售未发货/锁定)都算。
        // 按出库时刻 out_at 落区间；台数/销售额/成本取明细，挂单未回填价时 sale_price=0 但台数仍计入。
        $orderIds = ErpOutboundOrder::where([['site_id', '=', $this->site_id]])
            ->where('outbound_type', '=', ErpDict::OUTBOUND_TYPE_PEER_SALE)
            ->where('status', '<>', ErpDict::OUTBOUND_STATUS_VOID)
            ->where('out_at', '>=', $start)->where('out_at', '<=', $end)
            ->column('id');
        $soldCount = 0;
        $saleAmount = 0.0;
        $soldCost = 0.0;
        if (!empty($orderIds)) {
            $items = ErpOutboundItem::where([['site_id', '=', $this->site_id]])
                ->whereIn('outbound_id', array_map('intval', $orderIds))
                ->field('sale_price, cost')->select()->toArray();
            $soldCount = count($items);
            foreach ($items as $it) {
                $saleAmount += (float)($it['sale_price'] ?? 0);
                $soldCost   += (float)($it['cost'] ?? 0);
            }
        }
        $saleAmount  = round($saleAmount, 2);
        $soldCost    = round($soldCost, 2);
        $grossProfit = round($saleAmount - $soldCost, 2);

        // 期末在库 = 真正在手可售/在途整备的货；不含 LOCKED(销售锁定=挂单已售给同行,待结/待发,已不算在库)
        $onHand = [
            ErpDict::INVENTORY_IN_STOCK,
            ErpDict::INVENTORY_REFURBISHING,
            ErpDict::INVENTORY_PENDING_PRICING,
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
        ];
        $stockCount = $base()->whereIn('inventory_status', $onHand)->count();
        $stockCost  = round((float)$base()->whereIn('inventory_status', $onHand)->sum('current_cost'), 2);
        // 已售锁定(挂单卖给同行, 未结/未发) 单列, 避免和"在库"混淆
        $lockedCount = $base()->where('inventory_status', '=', ErpDict::INVENTORY_LOCKED)->count();
        $lockedCost  = round((float)$base()->where('inventory_status', '=', ErpDict::INVENTORY_LOCKED)->sum('current_cost'), 2);

        return [
            'range'     => ['start' => date('Y-m-d', $start), 'end' => date('Y-m-d', $end)],
            'purchase'  => ['count' => $inCount, 'cost' => $inCost],
            'sales'     => ['count' => $soldCount, 'amount' => $saleAmount, 'cost' => $soldCost, 'gross_profit' => $grossProfit],
            'inventory' => ['on_hand_count' => $stockCount, 'on_hand_cost' => $stockCost],
            'locked'    => ['count' => $lockedCount, 'cost' => $lockedCost], // 已售锁定(挂单待结/待发)
        ];
    }

    /**
     * 给 AI「列出库存设备」用：按状态列设备(默认在手未售)，可按型号/IMEI 过滤。
     * 受当前用户库位范围限制(员工只看自己负责库位)。
     * @param string $status onhand在手(默认)/available可售/in_stock在库/refurbishing整备中/locked已售锁定/sold已售/all全部
     */
    public function listForAi(string $status = 'onhand', string $keyword = '', int $limit = 50): array
    {
        $statusMap = [
            'onhand' => [ErpDict::INVENTORY_IN_STOCK, ErpDict::INVENTORY_REFURBISHING, ErpDict::INVENTORY_PENDING_PRICING, ErpDict::INVENTORY_AVAILABLE_FOR_SALE],
            'available' => [ErpDict::INVENTORY_AVAILABLE_FOR_SALE],
            'in_stock' => [ErpDict::INVENTORY_IN_STOCK],
            'refurbishing' => [ErpDict::INVENTORY_REFURBISHING],
            'pending_pricing' => [ErpDict::INVENTORY_PENDING_PRICING],
            'locked' => [ErpDict::INVENTORY_LOCKED],
            'sold' => [ErpDict::INVENTORY_LOCKED, ErpDict::INVENTORY_OUTBOUND],
        ];
        $query = ErpAsset::where([['site_id', '=', $this->site_id]]);
        if ($status !== 'all' && isset($statusMap[$status])) {
            $query->whereIn('inventory_status', $statusMap[$status]);
        }
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('model|imei|imei2|sn|asset_no', '%' . $keyword . '%');
            });
        }
        // 员工只看自己负责库位
        $scope = $this->scopedLocationIds();
        if ($scope !== null) {
            $query->whereIn('location_id', $scope);
        }
        $limit = max(1, min($limit, 100));
        $total = (clone $query)->count();
        $rows = $query->order('id desc')->limit($limit)->select()->toArray();
        $this->appendStatusLabels($rows);
        $this->appendWarehouseNames($rows);
        $records = [];
        foreach ($rows as $r) {
            $records[] = [
                'device_id'   => (int)($r['source_device_id'] ?? 0),
                'asset_no'    => (string)($r['asset_no'] ?? ''),
                'model'       => (string)($r['model'] ?? ''),
                'imei'        => (string)($r['imei'] ?? ''),
                'cost'        => round((float)($r['current_cost'] ?? 0), 2),
                'sale_price'  => round((float)($r['current_sale_price'] ?? 0), 2),
                'status'      => (string)($r['status_text'] ?? $r['inventory_status'] ?? ''),
                'warehouse'   => (string)($r['warehouse_name'] ?? ''),
            ];
        }
        return ['count' => count($records), 'total' => $total, 'status' => $status, 'records' => $records];
    }
}
