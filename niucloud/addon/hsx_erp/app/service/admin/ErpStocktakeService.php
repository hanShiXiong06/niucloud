<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpStocktake;
use addon\hsx_erp\app\model\ErpStocktakeItem;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use addon\hsx_erp\app\support\ErpIdempotency;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 设备级库存盘点：快照、扫码、差异、复核、库存流水。 */
class ErpStocktakeService extends BaseAdminService
{
    private const ACTIVE_STATUSES = ['counting', 'pending_review'];
    private const RESULTS = ['pending', 'normal', 'missing', 'surplus', 'location_mismatch', 'status_abnormal'];

    public function getPage(array $where): array
    {
        $query = ErpStocktake::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['status'])) $query->where('status', (string)$where['status']);
        if (!empty($where['workflow_mode'])) $query->where('workflow_mode', (string)$where['workflow_mode']);
        if (!empty($where['warehouse_id'])) $query->where('warehouse_id', (int)$where['warehouse_id']);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('stocktake_no|warehouse_name|location_name|counter_name|reviewer_name', '%' . $kw . '%');
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, (int)($where['limit'] ?? 15)),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        foreach ($page['data'] as &$row) $row = $this->decorateTask($row);
        unset($row);
        return $page;
    }

    public function info(int $id): array
    {
        $task = $this->findTask($id)->toArray();
        $task = $this->decorateTask($task);
        $task['locations'] = ErpWarehouseLocation::where([
            ['site_id', '=', $this->site_id], ['warehouse_id', '=', (int)$task['warehouse_id']], ['status', '=', 1],
        ])->order('sort asc,id asc')->select()->toArray();
        return $task;
    }

    public function itemPage(int $id, array $where): array
    {
        $this->findTask($id);
        $query = ErpStocktakeItem::where([['site_id', '=', $this->site_id], ['stocktake_id', '=', $id]]);
        if (!empty($where['result'])) $query->where('result', (string)$where['result']);
        if (!empty($where['resolution_status'])) $query->where('resolution_status', (string)$where['resolution_status']);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('asset_no|imei|sn|model|spec|scan_code', '%' . $kw . '%');
        }
        $page = $query->orderRaw("FIELD(result,'status_abnormal','missing','surplus','location_mismatch','pending','normal'),id asc")
            ->paginate(['list_rows' => max(1, (int)($where['limit'] ?? 30)), 'page' => max(1, (int)($where['page'] ?? 1))])
            ->toArray();
        foreach ($page['data'] as &$row) $row = $this->decorateItem($row);
        unset($row);
        return $page;
    }

    public function create(array $data): int
    {
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        if ($requestId !== '') {
            $existing = ErpStocktake::where([['site_id', '=', $this->site_id], ['request_id', '=', $requestId]])->findOrEmpty();
            if (!$existing->isEmpty()) return (int)$existing->id;
        }
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $locationId = (int)($data['location_id'] ?? 0);
        $warehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $warehouseId], ['status', '=', 1]])->findOrEmpty();
        if ($warehouse->isEmpty()) throw new CommonException('请选择有效的盘点仓库');
        $location = null;
        if ($locationId > 0) {
            $found = ErpWarehouseLocation::where([
                ['site_id', '=', $this->site_id], ['warehouse_id', '=', $warehouseId], ['id', '=', $locationId], ['status', '=', 1],
            ])->findOrEmpty();
            if ($found->isEmpty()) throw new CommonException('盘点库位不属于所选仓库');
            $location = $found;
        }
        $this->assertScopeAvailable($warehouseId, $locationId);
        $scopeType = (string)($data['scope_type'] ?? 'all') === 'selected' ? 'selected' : 'all';
        $assetIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['asset_ids'] ?? [])))));
        if ($scopeType === 'selected' && $assetIds === []) throw new CommonException('抽盘必须选择设备');

        $assetQuery = ErpAsset::where([
            ['site_id', '=', $this->site_id], ['status', '=', ErpDict::ASSET_IN_STOCK], ['warehouse_id', '=', $warehouseId],
        ]);
        if ($locationId > 0) $assetQuery->where('location_id', $locationId);
        if ($scopeType === 'selected') $assetQuery->whereIn('id', $assetIds);
        $assets = $assetQuery->order('id asc')->select()->toArray();
        if ($assets === []) throw new CommonException('当前范围没有可盘点设备');
        if ($scopeType === 'selected' && count($assets) !== count($assetIds)) throw new CommonException('部分抽盘设备已不在所选仓库，请刷新库存后重试');

        $workflowMode = (string)($data['workflow_mode'] ?? 'simple') === 'team' ? 'team' : 'simple';
        $staffService = new ErpStaffService();
        $counter = $staffService->resolve((int)($data['counter_uid'] ?? 0), '盘点人');
        $counterUid = (int)$counter['uid'];
        $counterName = (string)$counter['name'];
        $reviewerUid = $workflowMode === 'team' ? (int)($data['reviewer_uid'] ?? 0) : (int)$this->uid;
        if ($workflowMode === 'team' && $reviewerUid <= 0) throw new CommonException('团队盘点请选择复核人');
        $reviewer = $staffService->resolve($reviewerUid, '复核人');
        $reviewerName = (string)$reviewer['name'];
        $now = time(); $taskId = 0;
        Db::transaction(function () use ($data, $requestId, $warehouse, $location, $scopeType, $workflowMode, $counterUid, $counterName, $reviewerUid, $reviewerName, $assets, $now, &$taskId) {
            $task = ErpStocktake::create([
                'site_id' => $this->site_id, 'request_id' => $requestId !== '' ? $requestId : null,
                'stocktake_no' => ErpLedgerService::makeNo('PD'), 'warehouse_id' => (int)$warehouse->id,
                'warehouse_name' => (string)$warehouse->warehouse_name, 'location_id' => (int)($location?->id ?? 0),
                'location_name' => (string)($location?->location_name ?? ''), 'scope_type' => $scopeType,
                'workflow_mode' => $workflowMode, 'status' => 'counting', 'snapshot_at' => $now,
                'expected_count' => count($assets), 'scanned_count' => 0, 'normal_count' => 0,
                'missing_count' => 0, 'surplus_count' => 0, 'location_mismatch_count' => 0,
                'status_abnormal_count' => 0, 'unresolved_count' => 0,
                'counter_uid' => $counterUid, 'counter_name' => $counterName,
                'reviewer_uid' => $reviewerUid, 'reviewer_name' => $reviewerName,
                'creator_uid' => (int)$this->uid, 'creator_name' => (string)$this->username,
                'started_at' => $now, 'remark' => trim((string)($data['remark'] ?? '')),
                'create_at' => $now, 'update_at' => $now,
            ]);
            $taskId = (int)$task->id;
            $rows = [];
            foreach ($assets as $asset) {
                $rows[] = $this->snapshotItem($taskId, $asset, $now);
                if (count($rows) >= 300) { Db::name('erp_stocktake_item')->insertAll($rows); $rows = []; }
            }
            if ($rows !== []) Db::name('erp_stocktake_item')->insertAll($rows);
            (new ErpOperationLogService())->record('stocktake_create', 'stocktake', $taskId, (string)$task->stocktake_no, '创建库存盘点任务', [
                'warehouse_id' => (int)$warehouse->id, 'location_id' => (int)($location?->id ?? 0), 'expected_count' => count($assets),
            ]);
        });
        return $taskId;
    }

    public function scan(int $id, array $data): array
    {
        $code = trim((string)($data['code'] ?? ''));
        if ($code === '') throw new CommonException('请扫描或输入设备串号');
        $task = $this->findTask($id, true);
        if ((string)$task->status !== 'counting') throw new CommonException('当前盘点单已停止扫码');
        $asset = $this->findAssetByCode($code);
        $actualLocationId = (int)($data['actual_location_id'] ?? 0);
        $actualWarehouseId = (int)$task->warehouse_id;
        $actualLocationName = '';
        if ($actualLocationId > 0) {
            $location = ErpWarehouseLocation::where([
                ['site_id', '=', $this->site_id], ['warehouse_id', '=', $actualWarehouseId], ['id', '=', $actualLocationId], ['status', '=', 1],
            ])->findOrEmpty();
            if ($location->isEmpty()) throw new CommonException('实际库位不属于盘点仓库');
            $actualLocationName = (string)$location->location_name;
        }

        $item = null;
        if ($asset !== null) {
            $found = ErpStocktakeItem::where([
                ['site_id', '=', $this->site_id], ['stocktake_id', '=', $id], ['asset_id', '=', (int)$asset->id],
            ])->findOrEmpty();
            $item = $found->isEmpty() ? null : $found;
        }
        if ($item === null) {
            $identity = $asset ? 'asset:' . (int)$asset->id : 'code:' . hash('sha256', $code);
            $found = ErpStocktakeItem::where([
                ['site_id', '=', $this->site_id], ['stocktake_id', '=', $id], ['identity_key', '=', $identity],
            ])->findOrEmpty();
            if (!$found->isEmpty()) $item = $found;
            else {
                $now = time();
                $item = ErpStocktakeItem::create([
                    'site_id' => $this->site_id, 'stocktake_id' => $id, 'identity_key' => $identity,
                    'asset_id' => (int)($asset?->id ?? 0), 'asset_no' => (string)($asset?->asset_no ?? ''),
                    'imei' => (string)($asset?->imei ?? ($asset ? '' : $code)), 'sn' => (string)($asset?->sn ?? ''),
                    'model' => (string)($asset?->model ?? ''), 'spec' => (string)($asset?->spec ?? ''),
                    'expected_status' => '', 'expected_warehouse_id' => 0, 'expected_warehouse_name' => '',
                    'expected_location_id' => 0, 'expected_location_name' => '', 'result' => 'surplus',
                    'resolution_status' => 'pending', 'scan_count' => 0, 'create_at' => $now, 'update_at' => $now,
                ]);
            }
        }
        $duplicate = (int)$item->scanned_at > 0;
        $result = $this->scanResult($task, $item, $asset, $actualLocationId);
        $now = time();
        $item->save([
            'scan_code' => $code, 'actual_warehouse_id' => $actualWarehouseId,
            'actual_warehouse_name' => (string)$task->warehouse_name,
            'actual_location_id' => $actualLocationId ?: (int)($asset?->location_id ?? 0),
            'actual_location_name' => $actualLocationName !== '' ? $actualLocationName : (string)($asset?->location_name ?? ''),
            'result' => $result, 'resolution_status' => $result === 'normal' ? 'not_required' : 'pending',
            'resolution_action' => '', 'resolution_remark' => '', 'scanner_uid' => (int)$this->uid,
            'scanner_name' => (string)$this->username, 'scanned_at' => $now,
            'scan_count' => (int)$item->scan_count + 1, 'update_at' => $now,
        ]);
        $this->refreshCounts($id);
        return ['duplicate' => $duplicate, 'item' => $this->decorateItem($item->refresh()->toArray()), 'task' => $this->info($id)];
    }

    public function submit(int $id, bool $autoComplete = false): array
    {
        $task = $this->findTask($id, true);
        if ((string)$task->status !== 'counting') throw new CommonException('只有盘点中的任务可以提交');
        $now = time();
        ErpStocktakeItem::where([
            ['site_id', '=', $this->site_id], ['stocktake_id', '=', $id], ['scanned_at', '=', 0], ['expected_status', '<>', ''],
        ])->update(['result' => 'missing', 'resolution_status' => 'pending', 'update_at' => $now]);
        $this->refreshScannedMovementResults($id);
        $task->save(['status' => 'pending_review', 'submitted_at' => $now, 'update_at' => $now]);
        $this->refreshCounts($id);
        $fresh = $this->findTask($id);
        (new ErpOperationLogService())->record('stocktake_submit', 'stocktake', $id, (string)$fresh->stocktake_no, '提交盘点差异复核');
        if ($autoComplete && (int)$fresh->unresolved_count === 0) {
            $this->complete($id, '小团队模式：无差异自动完成');
            return $this->info($id);
        }
        return $this->info($id);
    }

    public function resolveItem(int $id, int $itemId, array $data): array
    {
        $task = $this->findTask($id);
        if (!in_array((string)$task->status, ['counting', 'pending_review'], true)) throw new CommonException('当前盘点单不能处理差异');
        $item = ErpStocktakeItem::where([
            ['site_id', '=', $this->site_id], ['stocktake_id', '=', $id], ['id', '=', $itemId],
        ])->findOrEmpty();
        if ($item->isEmpty()) throw new CommonException('盘点差异不存在');
        if ((string)$item->result === 'normal') throw new CommonException('正常设备无需处理');
        $action = trim((string)($data['action'] ?? ''));
        $allowed = [
            'missing' => ['confirm_missing', 'keep_in_stock'],
            'location_mismatch' => ['correct_location', 'keep_current'],
            'surplus' => ['pending_inbound', 'exclude'],
            'status_abnormal' => ['business_review', 'keep_current'],
        ];
        if (!in_array($action, $allowed[(string)$item->result] ?? [], true)) throw new CommonException('请选择与当前差异匹配的处理方式');
        $remark = trim((string)($data['remark'] ?? ''));
        if ($remark === '') throw new CommonException('请填写差异处理说明');
        $actualWarehouseId = (int)($data['actual_warehouse_id'] ?? 0) ?: (int)$task->warehouse_id;
        $actualLocationId = (int)($data['actual_location_id'] ?? 0) ?: (int)$item->actual_location_id;
        $actualWarehouseName = (string)$task->warehouse_name;
        $actualLocationName = (string)$item->actual_location_name;
        if ($action === 'correct_location') {
            if ($actualLocationId <= 0) throw new CommonException('修正库位时请选择实际库位');
            $location = ErpWarehouseLocation::where([
                ['site_id', '=', $this->site_id], ['warehouse_id', '=', $actualWarehouseId], ['id', '=', $actualLocationId], ['status', '=', 1],
            ])->findOrEmpty();
            if ($location->isEmpty()) throw new CommonException('实际库位不存在');
            $actualLocationName = (string)$location->location_name;
        }
        $now = time();
        $item->save([
            'resolution_status' => 'resolved', 'resolution_action' => $action, 'resolution_remark' => $remark,
            'actual_warehouse_id' => $actualWarehouseId, 'actual_warehouse_name' => $actualWarehouseName,
            'actual_location_id' => $actualLocationId, 'actual_location_name' => $actualLocationName,
            'resolver_uid' => (int)$this->uid, 'resolver_name' => (string)$this->username,
            'resolved_at' => $now, 'update_at' => $now,
        ]);
        $this->refreshCounts($id);
        return $this->decorateItem($item->refresh()->toArray());
    }

    public function complete(int $id, string $remark = ''): bool
    {
        $task = $this->findTask($id, true);
        if ((string)$task->status !== 'pending_review') throw new CommonException('请先提交盘点结果');
        $this->refreshCounts($id);
        $task = $this->findTask($id, true);
        if ((int)$task->unresolved_count > 0) throw new CommonException('仍有未处理的盘点差异，不能完成');
        $items = ErpStocktakeItem::where([['site_id', '=', $this->site_id], ['stocktake_id', '=', $id]])->order('id asc')->select();
        $now = time();
        Db::transaction(function () use ($task, $items, $remark, $now) {
            $ledger = new ErpLedgerService();
            foreach ($items as $item) {
                $action = (string)$item->resolution_action;
                $assetId = (int)$item->asset_id;
                if ($assetId <= 0 || !in_array($action, ['confirm_missing', 'correct_location'], true)) continue;
                $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->lock(true)->findOrEmpty();
                if ($asset->isEmpty()) continue;
                $beforeStatus = (string)$asset->status;
                $beforeWarehouseId = (int)$asset->warehouse_id; $beforeWarehouseName = (string)$asset->warehouse_name;
                $beforeLocationId = (int)$asset->location_id; $beforeLocationName = (string)$asset->location_name;
                if ($action === 'confirm_missing') {
                    if ($beforeStatus !== ErpDict::ASSET_IN_STOCK) throw new CommonException('盘亏设备状态已变化，请重新核查');
                    $asset->save(['status' => ErpDict::ASSET_LOST, 'update_at' => $now]);
                } else {
                    if ($beforeStatus !== ErpDict::ASSET_IN_STOCK) throw new CommonException('库位修正设备状态已变化，请重新核查');
                    $asset->save([
                        'warehouse_id' => (int)$item->actual_warehouse_id, 'warehouse_name' => (string)$item->actual_warehouse_name,
                        'location_id' => (int)$item->actual_location_id, 'location_name' => (string)$item->actual_location_name,
                        'update_at' => $now,
                    ]);
                }
                $ledger->asset([
                    'asset_id' => $assetId, 'action' => $action === 'confirm_missing' ? 'stocktake_loss' : 'stocktake_location',
                    'before_status' => $beforeStatus, 'after_status' => (string)$asset->status,
                    'before_warehouse_id' => $beforeWarehouseId, 'before_warehouse_name' => $beforeWarehouseName,
                    'before_location_id' => $beforeLocationId, 'before_location_name' => $beforeLocationName,
                    'after_warehouse_id' => (int)$asset->warehouse_id, 'after_warehouse_name' => (string)$asset->warehouse_name,
                    'after_location_id' => (int)$asset->location_id, 'after_location_name' => (string)$asset->location_name,
                    'source_type' => 'stocktake', 'source_id' => (int)$task->id, 'source_no' => (string)$task->stocktake_no,
                    'remark' => (string)$item->resolution_remark,
                ]);
            }
            $task->save([
                'status' => 'completed', 'reviewer_uid' => (int)$this->uid, 'reviewer_name' => (string)$this->username,
                'completed_at' => $now, 'completion_remark' => trim($remark), 'update_at' => $now,
            ]);
            (new ErpOperationLogService())->record('stocktake_complete', 'stocktake', (int)$task->id, (string)$task->stocktake_no, '确认完成库存盘点', [
                'missing_count' => (int)$task->missing_count, 'surplus_count' => (int)$task->surplus_count,
                'location_mismatch_count' => (int)$task->location_mismatch_count,
            ]);
        });
        return true;
    }

    public function cancel(int $id, string $remark = ''): bool
    {
        $task = $this->findTask($id, true);
        if (!in_array((string)$task->status, self::ACTIVE_STATUSES, true)) throw new CommonException('当前盘点单不能取消');
        if (trim($remark) === '') throw new CommonException('请填写取消原因');
        $now = time();
        $task->save(['status' => 'cancelled', 'cancelled_at' => $now, 'completion_remark' => trim($remark), 'update_at' => $now]);
        (new ErpOperationLogService())->record('stocktake_cancel', 'stocktake', $id, (string)$task->stocktake_no, trim($remark));
        return true;
    }

    private function snapshotItem(int $taskId, array $asset, int $now): array
    {
        return [
            'site_id' => $this->site_id, 'stocktake_id' => $taskId, 'identity_key' => 'asset:' . (int)$asset['id'],
            'asset_id' => (int)$asset['id'], 'asset_no' => (string)$asset['asset_no'], 'imei' => (string)$asset['imei'],
            'sn' => (string)$asset['sn'], 'model' => (string)$asset['model'], 'spec' => (string)$asset['spec'],
            'expected_status' => (string)$asset['status'], 'expected_warehouse_id' => (int)$asset['warehouse_id'],
            'expected_warehouse_name' => (string)$asset['warehouse_name'], 'expected_location_id' => (int)$asset['location_id'],
            'expected_location_name' => (string)$asset['location_name'], 'result' => 'pending',
            'resolution_status' => 'pending', 'scan_count' => 0, 'create_at' => $now, 'update_at' => $now,
        ];
    }

    private function scanResult(ErpStocktake $task, ErpStocktakeItem $item, ?ErpAsset $asset, int $actualLocationId): string
    {
        if ($asset === null) return 'surplus';
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) return 'status_abnormal';
        if ((int)$asset->warehouse_id !== (int)$task->warehouse_id) return 'location_mismatch';
        if ((int)$task->location_id > 0 && (int)$asset->location_id !== (int)$task->location_id) return 'location_mismatch';
        if ($actualLocationId > 0 && (int)$item->expected_location_id > 0 && $actualLocationId !== (int)$item->expected_location_id) return 'location_mismatch';
        if ((string)$item->expected_status === '') return 'surplus';
        return 'normal';
    }

    private function refreshScannedMovementResults(int $taskId): void
    {
        $task = $this->findTask($taskId);
        $items = ErpStocktakeItem::where([
            ['site_id', '=', $this->site_id], ['stocktake_id', '=', $taskId], ['scanned_at', '>', 0], ['asset_id', '>', 0],
        ])->select();
        foreach ($items as $item) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
            $result = $this->scanResult($task, $item, $asset->isEmpty() ? null : $asset, (int)$item->actual_location_id);
            if ($result !== (string)$item->result) {
                $item->save(['result' => $result, 'resolution_status' => $result === 'normal' ? 'not_required' : 'pending', 'resolution_action' => '', 'update_at' => time()]);
            }
        }
    }

    private function refreshCounts(int $id): void
    {
        $rows = ErpStocktakeItem::where([['site_id', '=', $this->site_id], ['stocktake_id', '=', $id]])
            ->field('result,count(*) count')->group('result')->select()->toArray();
        $map = [];
        foreach ($rows as $row) $map[(string)$row['result']] = (int)$row['count'];
        $scanned = ErpStocktakeItem::where([['site_id', '=', $this->site_id], ['stocktake_id', '=', $id], ['scanned_at', '>', 0]])->count();
        $unresolved = ErpStocktakeItem::where([
            ['site_id', '=', $this->site_id], ['stocktake_id', '=', $id], ['result', '<>', 'normal'], ['result', '<>', 'pending'], ['resolution_status', '<>', 'resolved'],
        ])->count();
        ErpStocktake::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->update([
            'scanned_count' => $scanned, 'normal_count' => $map['normal'] ?? 0, 'missing_count' => $map['missing'] ?? 0,
            'surplus_count' => $map['surplus'] ?? 0, 'location_mismatch_count' => $map['location_mismatch'] ?? 0,
            'status_abnormal_count' => $map['status_abnormal'] ?? 0, 'unresolved_count' => $unresolved, 'update_at' => time(),
        ]);
    }

    private function findAssetByCode(string $code): ?ErpAsset
    {
        $rows = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where(function ($query) use ($code) {
                $query->where('asset_no', $code)->whereOr('imei', $code)->whereOr('sn', $code);
            })->order('id desc')->select();
        if ($rows->isEmpty()) return null;
        foreach ($rows as $row) if ((string)$row->status === ErpDict::ASSET_IN_STOCK) return $row;
        return $rows->first();
    }

    private function assertScopeAvailable(int $warehouseId, int $locationId): void
    {
        $tasks = ErpStocktake::where([['site_id', '=', $this->site_id], ['warehouse_id', '=', $warehouseId]])
            ->whereIn('status', self::ACTIVE_STATUSES)->select();
        foreach ($tasks as $task) {
            if ($locationId === 0 || (int)$task->location_id === 0 || (int)$task->location_id === $locationId) {
                throw new CommonException('该仓库或库位已有进行中的盘点任务');
            }
        }
    }

    private function findTask(int $id, bool $lock = false): ErpStocktake
    {
        $query = ErpStocktake::where([['site_id', '=', $this->site_id], ['id', '=', $id]]);
        if ($lock) $query->lock(true);
        $task = $query->findOrEmpty();
        if ($task->isEmpty()) throw new CommonException('盘点单不存在');
        return $task;
    }

    private function decorateTask(array $row): array
    {
        $statusMap = [
            'counting' => ['label' => '盘点中', 'type' => 'primary'], 'pending_review' => ['label' => '待复核', 'type' => 'warning'],
            'completed' => ['label' => '已完成', 'type' => 'success'], 'cancelled' => ['label' => '已取消', 'type' => 'info'],
        ];
        $row['status_meta'] = $statusMap[(string)($row['status'] ?? '')] ?? ['label' => '未知状态', 'type' => 'info'];
        $row['progress_percent'] = (int)($row['expected_count'] ?? 0) > 0
            ? min(100, round((int)($row['scanned_count'] ?? 0) * 100 / (int)$row['expected_count'])) : 0;
        $row['difference_count'] = (int)($row['missing_count'] ?? 0) + (int)($row['surplus_count'] ?? 0)
            + (int)($row['location_mismatch_count'] ?? 0) + (int)($row['status_abnormal_count'] ?? 0);
        return $row;
    }

    private function decorateItem(array $row): array
    {
        $map = [
            'pending' => ['label' => '待盘', 'type' => 'info'], 'normal' => ['label' => '正常', 'type' => 'success'],
            'missing' => ['label' => '盘亏', 'type' => 'danger'], 'surplus' => ['label' => '盘盈', 'type' => 'warning'],
            'location_mismatch' => ['label' => '位置不符', 'type' => 'warning'], 'status_abnormal' => ['label' => '状态异常', 'type' => 'danger'],
        ];
        $row['result_meta'] = $map[(string)($row['result'] ?? '')] ?? ['label' => '未知', 'type' => 'info'];
        return $row;
    }
}
