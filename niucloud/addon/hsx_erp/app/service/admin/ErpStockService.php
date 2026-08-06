<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\model\ErpSettlementLink;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpListingFormContract;
use addon\hsx_erp\app\support\ErpListingWorkflow;
use addon\hsx_erp\app\support\ErpPurchaseReturnPolicy;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

class ErpStockService extends BaseAdminService
{
    public static function forSite(int $siteId, int $operatorUid = 0, string $operatorName = '系统自动'): self
    {
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $operatorUid;
        $service->username = $operatorName;
        return $service;
    }

    /**
     * 批量送修：一筐设备只选择一次整备商并确认一次。
     * 不创建“整备仓”，设备库存归属保持不变，外部保管信息记录在整备快照中。
     */
    public function sendRefurbish(array $assetIds, int $providerPartyId = 0, string $remark = '', string $trackingMode = '', string $requestId = ''): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));
        if ($assetIds === []) throw new CommonException('请至少选择一台待整备设备');
        if (count($assetIds) > 200) throw new CommonException('单次送修最多选择200台设备');
        $requestId = ErpIdempotency::normalize($requestId);
        if ($requestId !== '') {
            $existing = ErpAssetLedger::where([
                ['site_id', '=', $this->site_id], ['request_id', '=', ErpIdempotency::child($requestId, (string)$assetIds[0])], ['action', '=', 'refurbish_send'],
            ])->findOrEmpty();
            if (!$existing->isEmpty()) return ['batch_no' => (string)$existing->source_no, 'count' => count($assetIds), 'idempotent' => true];
        }
        $rules = ErpConfigService::forSite((int)$this->site_id)->getRules();
        if ((int)($rules['refurbish']['enabled'] ?? 1) !== 1) throw new CommonException('整备流程尚未启用');
        $trackingMode = in_array($trackingMode, ['simple', 'external'], true)
            ? $trackingMode
            : (string)($rules['refurbish']['tracking_mode'] ?? 'simple');
        $providerName = '';
        if ($providerPartyId > 0) {
            $party = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', $providerPartyId], ['status', '=', 1]])->findOrEmpty();
            if ($party->isEmpty()) throw new CommonException('整备服务商不存在或已停用');
            $providerName = (string)$party->party_name;
        } elseif ($trackingMode === 'external') {
            throw new CommonException('外送追踪模式请选择整备商');
        }
        $batchNo = ErpLedgerService::makeNo('RB');
        $now = time();
        Db::transaction(function () use ($assetIds, $providerPartyId, $providerName, $remark, $batchNo, $now, $requestId) {
            $assets = ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('id', $assetIds)->lock(true)->select();
            if (count($assets) !== count($assetIds)) throw new CommonException('部分设备不存在，请刷新后重试');
            $ledger = new ErpLedgerService();
            foreach ($assets as $asset) {
                if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) throw new CommonException('只有在库设备可以送修');
                if ((string)$asset->refurbish_status !== 'pending') {
                    throw new CommonException('设备【' . (string)($asset->model ?: $asset->asset_no) . '】不是待整备状态');
                }
                $asset->save([
                    'refurbish_status' => 'processing',
                    'listing_status' => 'none',
                    'refurbish_batch_no' => $batchNo,
                    'refurbish_provider_id' => $providerPartyId,
                    'refurbish_provider_name' => $providerName,
                    'refurbish_sent_at' => $now,
                    'refurbish_sent_uid' => (int)$this->uid,
                    'refurbish_sent_name' => (string)$this->username,
                    'refurbish_remark' => mb_substr(trim($remark), 0, 500),
                    'update_at' => $now,
                ]);
                $ledger->asset([
                    'asset_id' => (int)$asset->id,
                    'request_id' => $requestId !== '' ? ErpIdempotency::child($requestId, (string)$asset->id) : null,
                    'action' => 'refurbish_send',
                    'before_status' => 'in_stock/pending',
                    'after_status' => 'in_stock/processing',
                    'party_id' => $providerPartyId,
                    'party_name' => $providerName,
                    'source_type' => 'refurbish',
                    'source_id' => (int)$asset->id,
                    'source_no' => $batchNo,
                    'remark' => $providerName !== '' ? ('送交' . $providerName . '整备') : '开始店内整备',
                    'extra' => ['refurbish_batch_no' => $batchNo, 'provider_id' => $providerPartyId, 'provider_name' => $providerName, 'remark' => trim($remark)],
                ]);
            }
        });
        return ['batch_no' => $batchNo, 'count' => count($assetIds), 'provider_id' => $providerPartyId, 'provider_name' => $providerName];
    }

    /** 扫码完工：最终清单才形成设备成本及服务商应付。 */
    public function completeRefurbish(int $id, array $data): array
    {
        $result = in_array((string)($data['result'] ?? ''), ['success', 'partial', 'failed'], true)
            ? (string)$data['result'] : '';
        if ($result === '') throw new CommonException('请选择整备结果');
        $requestId = ErpIdempotency::normalize((string)($data['request_id'] ?? ''));
        if ($requestId !== '' && ErpAssetLedger::where('site_id', '=', $this->site_id)
            ->where('request_id', '=', $requestId)->whereIn('action', ['refurbish', 'refurbish_complete'])->count() > 0) {
            return $this->info($id);
        }
        $asset = $this->findAsset($id);
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK || !in_array((string)$asset->refurbish_status, ['pending', 'processing'], true)) {
            throw new CommonException('只有待整备或整备中的在库设备可以登记完工');
        }
        $destination = $this->resolveRefurbishDestination($asset, (int)($data['warehouse_id'] ?? 0), (int)($data['location_id'] ?? 0));
        $items = array_values(array_filter((array)($data['refurbish_items'] ?? []), 'is_array'));
        $total = round(array_sum(array_map(static fn(array $item): float => (float)($item['amount'] ?? 0), $items)), 2);
        $context = [
            'workflow_complete' => 1,
            'result' => $result,
            'refurbish_items' => $items,
            'expense_type_key' => count($items) > 1 ? 'refurbish_mixed' : (string)($data['expense_type_key'] ?? 'refurbish_mixed'),
            'destination' => $destination,
            'voucher_urls' => $data['voucher_urls'] ?? '',
        ];
        $reason = mb_substr(trim((string)($data['remark'] ?? '')), 0, 500);
        if ($total > 0) {
            $this->adjustCost($id, round((float)$asset->total_cost + $total, 2), $reason, false, $requestId, 'refurbish', $context);
        } else {
            $this->completeRefurbishWithoutCost($id, $result, $reason, $requestId, $destination, $data['voucher_urls'] ?? '');
        }
        // 整备完工先形成库存事实；资料满足条件后按业务规则自动发布，
        // 否则保留明确的人工待办，外部渠道故障不能回滚整备事实。
        return $this->info($id);
    }

    private function resolveRefurbishDestination(ErpAsset $asset, int $warehouseId, int $locationId): array
    {
        if ($warehouseId <= 0) {
            return [
                'warehouse_id' => (int)$asset->warehouse_id, 'warehouse_name' => (string)$asset->warehouse_name,
                'location_id' => (int)$asset->location_id, 'location_name' => (string)$asset->location_name,
                'sale_target' => (string)$asset->sale_target,
            ];
        }
        $warehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $warehouseId], ['status', '=', 1]])->findOrEmpty();
        if ($warehouse->isEmpty()) throw new CommonException('目标仓库不存在或已停用');
        $locationName = '';
        if ($locationId > 0) {
            $location = ErpWarehouseLocation::where([['site_id', '=', $this->site_id], ['id', '=', $locationId], ['warehouse_id', '=', $warehouseId], ['status', '=', 1]])->findOrEmpty();
            if ($location->isEmpty()) throw new CommonException('目标库位不属于所选仓库或已停用');
            $locationName = (string)$location->location_name;
        }
        return [
            'warehouse_id' => $warehouseId, 'warehouse_name' => (string)$warehouse->warehouse_name,
            'location_id' => $locationId, 'location_name' => $locationName,
            'sale_target' => (string)$warehouse->default_sale_target,
        ];
    }

    private function listingStatusAfterRefurbish(ErpAsset $asset, array $destination): string
    {
        $warehouseId = (int)($destination['warehouse_id'] ?? $asset->warehouse_id);
        $warehouse = $warehouseId > 0
            ? ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $warehouseId], ['status', '=', 1]])->findOrEmpty()
            : null;
        $warehouseData = $warehouse && !$warehouse->isEmpty() ? $warehouse->toArray() : null;
        $projected = array_merge($asset->toArray(), $destination, [
            'status' => ErpDict::ASSET_IN_STOCK,
            'refurbish_status' => 'done',
        ]);
        $policy = (new ErpWarehousePolicyService())->evaluate($projected, $warehouseData);
        if ((int)$policy['allow_mall'] !== 1) return 'none';
        return ErpListingWorkflow::statusFromPolicy($policy);
    }

    private function completeRefurbishWithoutCost(int $id, string $result, string $reason, string $requestId, array $destination, mixed $voucherUrls): void
    {
        $requestId = ErpIdempotency::normalize($requestId);
        if ($requestId !== '' && ErpAssetLedger::where([
            ['site_id', '=', $this->site_id], ['request_id', '=', $requestId], ['action', '=', 'refurbish_complete'],
        ])->count() > 0) return;
        Db::transaction(function () use ($id, $result, $reason, $requestId, $destination, $voucherUrls) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty() || (string)$asset->status !== ErpDict::ASSET_IN_STOCK || !in_array((string)$asset->refurbish_status, ['pending', 'processing'], true)) {
                throw new CommonException('只有待整备或整备中的在库设备可以登记完工');
            }
            $before = [
                'warehouse_id' => (int)$asset->warehouse_id, 'warehouse_name' => (string)$asset->warehouse_name,
                'location_id' => (int)$asset->location_id, 'location_name' => (string)$asset->location_name,
            ];
            $beforeRefurbishStatus = (string)$asset->refurbish_status;
            $status = $result === 'success' ? 'done' : 'failed';
            $now = time();
            $asset->save(array_merge([
                'refurbish_status' => $status,
                'refurbish_result' => $result,
                'refurbish_completed_at' => $now,
                'refurbish_completed_uid' => (int)$this->uid,
                'refurbish_completed_name' => (string)$this->username,
                'refurbish_voucher_urls' => is_array($voucherUrls) ? json_encode($voucherUrls, JSON_UNESCAPED_UNICODE) : trim((string)$voucherUrls),
                'refurbish_remark' => $reason,
                'listing_status' => $status === 'done' ? $this->listingStatusAfterRefurbish($asset, $destination) : 'none',
                'update_at' => $now,
            ], $destination));
            (new ErpLedgerService())->asset([
                'asset_id' => $id,
                'request_id' => $requestId !== '' ? $requestId : null,
                'action' => 'refurbish_complete',
                'before_status' => 'in_stock/' . $beforeRefurbishStatus,
                'after_status' => 'in_stock/' . $status,
                'before_warehouse_id' => $before['warehouse_id'], 'before_warehouse_name' => $before['warehouse_name'],
                'before_location_id' => $before['location_id'], 'before_location_name' => $before['location_name'],
                'after_warehouse_id' => (int)($destination['warehouse_id'] ?? $before['warehouse_id']),
                'after_warehouse_name' => (string)($destination['warehouse_name'] ?? $before['warehouse_name']),
                'after_location_id' => (int)($destination['location_id'] ?? $before['location_id']),
                'after_location_name' => (string)($destination['location_name'] ?? $before['location_name']),
                'before_total_cost' => (float)$asset->total_cost, 'after_total_cost' => (float)$asset->total_cost, 'cost_delta' => 0,
                'source_type' => 'refurbish', 'source_id' => $id, 'source_no' => (string)$asset->refurbish_batch_no,
                'remark' => $reason !== '' ? $reason : ($status === 'done' ? '整备完成（无新增费用）' : '整备未成功'),
                'extra' => ['result' => $result, 'voucher_urls' => $voucherUrls, 'destination' => $destination],
            ]);
        });
    }
    /** 串号追踪：同一 IMEI/SN 允许多次入库，每次资产记录独立展示，最新在前。 */
    public function serialTracePage(array $where): array
    {
        $query = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where(function ($sub) { $sub->where('imei', '<>', '')->whereOr('sn', '<>', ''); });
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->whereLike('imei|sn|asset_no|model|spec|party_name', '%' . $keyword . '%');
        }
        if (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }
        $stockInExpr = 'COALESCE(NULLIF(stock_in_at, 0), create_at)';
        if (!empty($where['start_at'])) {
            $query->whereRaw($stockInExpr . ' >= ' . (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->whereRaw($stockInExpr . ' <= ' . (int)$where['end_at']);
        }
        $page = $query->field('id,asset_no,imei,sn,model,spec,status,party_id,party_name,purchase_order_id,warehouse_name,location_name,stock_in_at,create_at,update_at,total_cost')
            ->order('stock_in_at desc,id desc')->paginate([
                'list_rows' => (int)($where['limit'] ?? 15),
                'page' => (int)($where['page'] ?? 1),
            ])->toArray();
        $serialCounts = [];
        foreach ($page['data'] ?? [] as $row) {
            $serial = trim((string)($row['imei'] ?: $row['sn']));
            if ($serial !== '' && !isset($serialCounts[$serial])) {
                $serialCounts[$serial] = (int)ErpAsset::where('site_id', '=', $this->site_id)
                    ->where(function ($sub) use ($serial) { $sub->where('imei', '=', $serial)->whereOr('sn', '=', $serial); })->count();
            }
        }
        foreach ($page['data'] as &$row) {
            $serial = trim((string)($row['imei'] ?: $row['sn']));
            $row['serial_no'] = $serial;
            $row['inbound_count'] = $serialCounts[$serial] ?? 1;
        }
        unset($row);
        return (new ErpDataVisibilityService())->sanitizeSerialTracePage($page);
    }

    /**
     * 串号生命周期：聚合同一 IMEI/SN 的全部入库周期和库存动作。
     * 设备重复入库时会生成新的资产记录，因此不能只读取当前 asset_id 的流水。
     */
    public function serialTraceDetail(int $id): array
    {
        $selected = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($selected->isEmpty()) throw new CommonException('设备记录不存在');

        $serial = trim((string)($selected->imei ?: $selected->sn));
        if ($serial === '') throw new CommonException('该设备未录入 IMEI 或序列号');

        $assets = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where(function ($query) use ($serial) {
                $query->where('imei', '=', $serial)->whereOr('sn', '=', $serial);
            })
            ->field('id,asset_no,imei,sn,model,spec,status,party_id,party_name,purchase_order_id,warehouse_name,location_name,stock_in_at,create_at,update_at,purchase_cost,total_cost,sale_price,profit')
            ->order('stock_in_at asc,id asc')->select()->toArray();

        $assetIds = array_values(array_map(static fn(array $asset): int => (int)$asset['id'], $assets));
        $ledgerGroups = [];
        if ($assetIds !== []) {
            $ledgers = $this->enrichAssetLedgers(
                ErpAssetLedger::where([['site_id', '=', $this->site_id]])
                    ->whereIn('asset_id', $assetIds)
                    ->order('occurred_at asc,id asc')->select()->toArray()
            );
            foreach ($ledgers as $ledger) {
                $ledgerGroups[(int)($ledger['asset_id'] ?? 0)][] = $ledger;
            }
        }

        $timeline = [];
        foreach ($assets as $index => &$asset) {
            $cycle = $index + 1;
            $asset['cycle_no'] = $cycle;
            $asset['serial_no'] = $serial;
            $asset['is_selected'] = (int)$asset['id'] === $id;
            $asset['is_current'] = $index === count($assets) - 1;
            $asset['ledgers'] = $ledgerGroups[(int)$asset['id']] ?? [];
            foreach ($asset['ledgers'] as $ledger) {
                $ledger['cycle_no'] = $cycle;
                $ledger['asset_no'] = (string)$asset['asset_no'];
                $ledger['model'] = (string)$asset['model'];
                if (trim((string)($ledger['party_name'] ?? '')) === '') {
                    $ledger['party_name'] = (string)$asset['party_name'];
                }
                $timeline[] = $ledger;
            }
        }
        unset($asset);

        usort($timeline, static function (array $left, array $right): int {
            $leftAt = (int)($left['occurred_at'] ?? $left['create_at'] ?? 0);
            $rightAt = (int)($right['occurred_at'] ?? $right['create_at'] ?? 0);
            if ($leftAt === $rightAt) return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
            return $leftAt <=> $rightAt;
        });

        $current = $assets === [] ? [] : $assets[count($assets) - 1];
        return (new ErpDataVisibilityService())->sanitizeSerialTraceDetail([
            'serial_no' => $serial,
            'model' => (string)($current['model'] ?? $selected->model),
            'spec' => (string)($current['spec'] ?? $selected->spec),
            'current_status' => (string)($current['status'] ?? $selected->status),
            'current_asset_id' => (int)($current['id'] ?? $id),
            'inbound_count' => count($assets),
            'sale_count' => count(array_filter($timeline, static fn(array $row): bool => (string)($row['action'] ?? '') === 'sold')),
            'after_sale_count' => count(array_filter($timeline, static fn(array $row): bool => (string)($row['action'] ?? '') === 'sale_return')),
            'purchase_return_count' => count(array_filter($timeline, static fn(array $row): bool => (string)($row['action'] ?? '') === 'purchase_return')),
            'cycles' => $assets,
            'timeline' => $timeline,
        ]);
    }

    public function getPage(array $where): array
    {
        $visibility = new ErpDataVisibilityService();
        $saleTable = (new ErpSaleOrder())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($saleTable . ' s', 's.id = a.sale_order_id AND s.site_id = a.site_id')
            ->where([['a.site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $keywordFields = 'a.asset_no|a.imei|a.sn|a.model|a.spec|a.category_name|a.warehouse_name|a.location_name|s.sale_no|s.party_name';
            if ($visibility->can('view_supplier')) $keywordFields .= '|a.party_name';
            $query->whereLike($keywordFields, '%' . $kw . '%');
        }
        if (!empty($where['status'])) {
            $query->where('a.status', '=', (string)$where['status']);
        }
        if (!empty($where['refurbish_status'])) {
            $query->where('a.refurbish_status', '=', (string)$where['refurbish_status']);
        }
        if (!empty($where['sale_target'])) {
            $query->where('a.sale_target', '=', (string)$where['sale_target']);
        }
        if (!empty($where['listing_status'])) {
            $listingStatus = (string)$where['listing_status'];
            if ($listingStatus === 'incomplete') {
                $query->whereIn('a.listing_status', ['need_photo', 'need_price', 'need_material']);
            } elseif ($listingStatus === 'publish') {
                $query->whereIn('a.listing_status', ['need_material', 'ready', 'pending_shop']);
            } else {
                $query->where('a.listing_status', '=', $listingStatus);
            }
        }
        if (!empty($where['task_stage_key'])) {
            $query->where('a.task_stage_key', '=', trim((string)$where['task_stage_key']));
        }
        if ((int)($where['my_task'] ?? 0) === 1) {
            $query->where('a.task_assignee_uid', '=', (int)$this->uid)
                ->where('a.task_stage_key', '<>', '');
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('a.warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('a.location_id', '=', (int)$where['location_id']);
        }
        $partyScope = (string)($where['party_scope'] ?? 'supplier') === 'customer' ? 'customer' : 'supplier';
        if (!empty($where['party_id'])) {
            if ($partyScope === 'customer') {
                $this->applySnapshotPartyFilter($query, 's.party_id', 's.party_name', (int)$where['party_id']);
            } elseif ($visibility->can('view_supplier')) {
                $this->applySnapshotPartyFilter($query, 'a.party_id', 'a.party_name', (int)$where['party_id']);
            }
        }
        // 已选择主体ID时只按稳定ID查询，避免主体改名后被历史名称快照二次过滤掉。
        if (empty($where['party_id']) && !empty($where['party_name'])) {
            $partyNameColumn = $partyScope === 'customer' ? 's.party_name' : 'a.party_name';
            if ($partyScope === 'customer' || $visibility->can('view_supplier')) {
                $query->whereLike($partyNameColumn, '%' . trim((string)$where['party_name']) . '%');
            }
        }
        if (!empty($where['origin_plugin'])) {
            $sourceColumn = $partyScope === 'customer' ? 's.origin_plugin' : 'a.source_plugin';
            $this->applyBusinessSourceFilter($query, $sourceColumn, (string)$where['origin_plugin']);
        }
        if ($partyScope === 'customer' && !empty($where['sale_channel_key'])) {
            $query->where('s.sale_channel_key', '=', trim((string)$where['sale_channel_key']));
        }
        if (!empty($where['catalog_product_id'])) $query->where('a.catalog_product_id', '=', (int)$where['catalog_product_id']);
        foreach ([
            'asset_no' => 'a.asset_no',
            'imei' => 'a.imei',
            'sn' => 'a.sn',
            'model' => 'a.model',
            'spec' => 'a.spec',
            'warehouse_name' => 'a.warehouse_name',
            'location_name' => 'a.location_name',
            'category_name' => 'a.category_name',
        ] as $key => $column) {
            if (!empty($where[$key])) {
                $query->whereLike($column, '%' . trim((string)$where[$key]) . '%');
            }
        }
        if ($visibility->can('view_cost') && ($where['min_cost'] ?? '') !== '') {
            $query->where('a.total_cost', '>=', (float)$where['min_cost']);
        }
        if ($visibility->can('view_cost') && ($where['max_cost'] ?? '') !== '') {
            $query->where('a.total_cost', '<=', (float)$where['max_cost']);
        }
        if (($where['min_price'] ?? '') !== '') {
            $minPrice = (float)$where['min_price'];
            $query->where(function ($q) use ($minPrice) {
                $q->where('a.retail_price', '>=', $minPrice)->whereOr('a.estimate_sale_price', '>=', $minPrice);
            });
        }
        if (($where['max_price'] ?? '') !== '') {
            $maxPrice = (float)$where['max_price'];
            $query->where(function ($q) use ($maxPrice) {
                $q->where('a.retail_price', '<=', $maxPrice)->whereOr('a.estimate_sale_price', '<=', $maxPrice);
            });
        }
        if (($where['stock_age_min'] ?? '') !== '') {
            $query->whereRaw('IF(a.stock_in_at > 0, a.stock_in_at, a.create_at) <= ' . (time() - max(0, (int)$where['stock_age_min']) * 86400));
        }
        if (($where['stock_age_max'] ?? '') !== '') {
            $query->whereRaw('IF(a.stock_in_at > 0, a.stock_in_at, a.create_at) >= ' . (time() - max(0, (int)$where['stock_age_max']) * 86400));
        }
        if (!empty($where['turnover_level'])) {
            $rules = (new ErpTurnoverService())->rules();
            $attention = (int)($rules['attention_days'] ?? 7);
            $warning = (int)($rules['warning_days'] ?? 15);
            $critical = (int)($rules['critical_days'] ?? 30);
            $ageColumn = 'IF(a.stock_in_at > 0, a.stock_in_at, a.create_at)';
            $attentionCutoff = time() - ($attention + 1) * 86400;
            $warningCutoff = time() - ($warning + 1) * 86400;
            $criticalCutoff = time() - ($critical + 1) * 86400;
            $level = (string)$where['turnover_level'];
            $query->where('a.status', '=', ErpDict::ASSET_IN_STOCK);
            if ($level === 'healthy') {
                $query->whereRaw($ageColumn . ' > ' . $attentionCutoff);
            } elseif ($level === 'attention') {
                $query->whereRaw($ageColumn . ' <= ' . $attentionCutoff)
                    ->whereRaw($ageColumn . ' > ' . $warningCutoff);
            } elseif ($level === 'risk') {
                $query->whereRaw($ageColumn . ' <= ' . $warningCutoff);
            } elseif ($level === 'warning') {
                $query->whereRaw($ageColumn . ' <= ' . $warningCutoff)
                    ->whereRaw($ageColumn . ' > ' . $criticalCutoff);
            } elseif ($level === 'critical') {
                $query->whereRaw($ageColumn . ' <= ' . $criticalCutoff);
            }
        }
        $businessTimeField = $partyScope === 'customer'
            ? 's.sale_at'
            : 'IF(a.stock_in_at > 0, a.stock_in_at, a.create_at)';
        if (!empty($where['start_at'])) {
            $query->whereRaw($businessTimeField . ' >= ?', [(int)$where['start_at']]);
        }
        if (!empty($where['end_at'])) {
            $query->whereRaw($businessTimeField . ' <= ?', [(int)$where['end_at']]);
        }
        $order = !empty($where['turnover_level'])
            ? 'IF(a.stock_in_at > 0, a.stock_in_at, a.create_at) asc,a.id asc'
            : 'a.id desc';
        $page = $query->field([
            'a.*',
            's.sale_no',
            's.party_name as sale_party_name',
            's.sale_channel',
            's.sale_at',
            's.finance_status as sale_finance_status',
        ])->order($order)->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        $page['data'] = $this->appendLifecycleContext((array)($page['data'] ?? []));
        $page['data'] = (new ErpTurnoverService())->decorate((array)$page['data']);
        $page['data'] = $this->appendListingSyncState((array)$page['data']);
        $page['data'] = $visibility->sanitizeStockRows((array)$page['data']);
        $page['capabilities'] = $visibility->stockCapabilities();
        return $page;
    }

    /** ERP 历史数据中手工业务曾使用 erp/hsx_erp 两种命名，查询层统一成一个入口。 */
    private function applyBusinessSourceFilter($query, string $column, string $source): void
    {
        $source = trim($source);
        if ($source === 'erp') {
            $query->whereIn($column, ['erp', 'hsx_erp']);
            return;
        }
        if ($source !== '') $query->where($column, '=', $source);
    }

    /** 已选主体优先按 ID；只为未绑定 ID 的历史业务快照提供精确名称兜底。 */
    private function applySnapshotPartyFilter($query, string $idColumn, string $nameColumn, int $partyId): void
    {
        $partyName = trim((string)ErpParty::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $partyId],
        ])->value('party_name'));
        $query->where(function ($sub) use ($idColumn, $nameColumn, $partyId, $partyName) {
            $sub->where($idColumn, '=', $partyId);
            if ($partyName !== '') {
                $sub->whereOr(function ($legacy) use ($idColumn, $nameColumn, $partyName) {
                    $legacy->where($idColumn, '=', 0)->where($nameColumn, '=', $partyName);
                });
            }
        });
    }

    public function turnoverSummary(): array
    {
        return (new ErpDataVisibilityService())->sanitizeTurnoverSummary((new ErpTurnoverService())->summary());
    }

    /** 今日商城上架协作量：按设备、环节、经办人去重，避免重复保存虚增工作量。 */
    public function listingWorkloadSummary(): array
    {
        $startAt = strtotime(date('Y-m-d'));
        $endAt = $startAt + 86400;
        $actionStage = [
            'listing_photo_complete' => 'photo',
            'listing_price_complete' => 'price',
            'retail_price_adjust' => 'price',
            'listing_material_complete' => 'material',
            'listing_publish' => 'publish',
        ];
        $visibility = new ErpDataVisibilityService();
        $rowsQuery = ErpAssetLedger::where('site_id', '=', $this->site_id)
            ->whereIn('action', array_keys($actionStage))
            ->where('occurred_at', '>=', $startAt)
            ->where('occurred_at', '<', $endAt);
        if (!$visibility->can('view_team_workload')) $rowsQuery->where('operator_uid', '=', (int)$this->uid);
        $rows = $rowsQuery
            ->field('id,asset_id,action,operator_uid,operator_name,occurred_at')
            ->order('occurred_at asc,id asc')->select()->toArray();

        $totals = ['photo' => 0, 'price' => 0, 'material' => 0, 'publish' => 0];
        $staff = [];
        $totalSeen = [];
        $staffSeen = [];
        foreach ($rows as $row) {
            $stage = $actionStage[(string)($row['action'] ?? '')] ?? '';
            $assetId = (int)($row['asset_id'] ?? 0);
            if ($stage === '' || $assetId <= 0) continue;
            $uid = (int)($row['operator_uid'] ?? 0);
            $name = trim((string)($row['operator_name'] ?? '')) ?: ($uid > 0 ? ('员工 #' . $uid) : '系统自动');
            $staffKey = $uid > 0 ? ('uid:' . $uid) : ('name:' . $name);
            $totalKey = $stage . ':' . $assetId;
            if (!isset($totalSeen[$totalKey])) {
                $totalSeen[$totalKey] = true;
                $totals[$stage]++;
            }
            $dedupeKey = $staffKey . ':' . $stage . ':' . $assetId;
            if (isset($staffSeen[$dedupeKey])) continue;
            $staffSeen[$dedupeKey] = true;
            if (!isset($staff[$staffKey])) {
                $staff[$staffKey] = [
                    'uid' => $uid,
                    'name' => $name,
                    'photo_count' => 0,
                    'price_count' => 0,
                    'material_count' => 0,
                    'publish_count' => 0,
                    'total_count' => 0,
                ];
            }
            $staff[$staffKey][$stage . '_count']++;
            $staff[$staffKey]['total_count']++;
        }
        $staff = array_values($staff);
        usort($staff, static fn(array $a, array $b): int => ($b['total_count'] <=> $a['total_count']) ?: strcmp((string)$a['name'], (string)$b['name']));
        return [
            'date' => date('Y-m-d', $startAt),
            'totals' => $totals,
            'staff' => $staff,
            'stage_labels' => ['photo' => '商品拍摄', 'price' => '销售定价', 'material' => '资料整理', 'publish' => '成功上架'],
            'scope' => $visibility->can('view_team_workload') ? 'team' : 'self',
            'capabilities' => $visibility->stockCapabilities(),
        ];
    }

    /** 库存周转处理专用：设置或调整销售零售价，不改变采购/整备成本。 */
    public function adjustRetailPrice(int $id, float $retailPrice, string $reason = '', string $requestId = ''): array
    {
        $retailPrice = round($retailPrice, 2);
        if ($retailPrice <= 0) throw new CommonException('零售价必须大于0');
        $requestId = ErpIdempotency::normalize($requestId);
        if ($requestId !== '') {
            $existing = ErpAssetLedger::where([
                ['site_id', '=', $this->site_id], ['request_id', '=', $requestId], ['action', '=', 'retail_price_adjust'],
            ])->findOrEmpty();
            if (!$existing->isEmpty()) return $this->info($id);
        }

        Db::transaction(function () use ($id, $retailPrice, $reason, $requestId) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty() || (string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
                throw new CommonException('只有在库设备可以调整零售价');
            }
            $beforePrice = round((float)$asset->retail_price, 2);
            if (abs($beforePrice - $retailPrice) <= 0.0001) throw new CommonException('零售价没有变化');
            $normalizedReason = trim($reason);
            if ($beforePrice > 0 && $normalizedReason === '') throw new CommonException('调整已有零售价时请填写原因');

            $save = ['retail_price' => $retailPrice, 'update_at' => time()];
            if ((string)$asset->sale_target === 'mall'
                && !in_array((string)$asset->listing_status, ['pending_shop', 'listed'], true)) {
                $projected = array_merge($asset->toArray(), $save);
                $warehouse = ErpWarehouse::where([
                    ['site_id', '=', $this->site_id], ['id', '=', (int)$asset->warehouse_id], ['status', '=', 1],
                ])->findOrEmpty();
                $policy = (new ErpWarehousePolicyService())->evaluate($projected, $warehouse->isEmpty() ? null : $warehouse->toArray());
                if ((int)$policy['can_prepare_mall'] === 1) {
                    $save['listing_status'] = ErpListingWorkflow::statusFromAsset($projected, $policy);
                }
            }
            $asset->save($save);
            (new ErpLedgerService())->asset([
                'asset_id' => $id,
                'request_id' => $requestId !== '' ? $requestId : null,
                'action' => 'retail_price_adjust',
                'before_status' => (string)$asset->status,
                'after_status' => (string)$asset->status,
                'before_total_cost' => (float)$asset->total_cost,
                'after_total_cost' => (float)$asset->total_cost,
                'source_type' => 'stock_turnover',
                'source_id' => $id,
                'remark' => '零售价 ' . number_format($beforePrice, 2, '.', '') . ' → ' . number_format($retailPrice, 2, '.', '')
                    . ($normalizedReason !== '' ? '；' . $normalizedReason : '；首次定价'),
                'extra' => ['before_retail_price' => $beforePrice, 'after_retail_price' => $retailPrice, 'reason' => $normalizedReason],
            ]);
        });
        return $this->info($id);
    }

    /** 调拨前置决策：前端据此展示普通调拨、完善资料、禁止或代卖转自有。 */
    public function transferPreview(array $assetIds, int $warehouseId, int $locationId): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));
        if ($assetIds === []) throw new CommonException('请选择要调拨的设备');
        if (count($assetIds) > 200) throw new CommonException('单次最多判断200台设备');
        [$targetWarehouse, $targetLocation] = (new ErpWarehouseService())->validateInboundLocation($warehouseId, $locationId);
        $assets = ErpAsset::where('site_id', '=', $this->site_id)->whereIn('id', $assetIds)->select();
        if (count($assets) !== count($assetIds)) throw new CommonException('部分设备不存在，请刷新库存后重试');
        $warehouseIds = [];
        foreach ($assets as $asset) $warehouseIds[] = (int)$asset->warehouse_id;
        $warehouseIds = array_values(array_unique(array_filter($warehouseIds)));
        $sourceMap = [];
        if ($warehouseIds !== []) {
            foreach (ErpWarehouse::where('site_id', '=', $this->site_id)->whereIn('id', $warehouseIds)->select()->toArray() as $warehouse) {
                $sourceMap[(int)$warehouse['id']] = $warehouse;
            }
        }
        $policy = new ErpWarehousePolicyService();
        $items = [];
        $actions = [];
        foreach ($assets as $asset) {
            if ((int)$asset->warehouse_id === (int)$targetWarehouse->id && (int)$asset->location_id === (int)$targetLocation->id) {
                $decision = [
                    'action' => 'blocked', 'allowed' => 0, 'label' => '无需调拨',
                    'reason' => '设备已经位于所选仓库和库位', 'missing_fields' => [],
                    'missing_labels' => [], 'requires_finance' => 0,
                ];
            } else {
                $decision = $policy->transferDecision(
                    $asset->toArray(),
                    $sourceMap[(int)$asset->warehouse_id] ?? null,
                    $targetWarehouse->toArray()
                );
            }
            $actions[] = (string)$decision['action'];
            $items[] = array_merge([
                'asset_id' => (int)$asset->id,
                'asset_no' => (string)$asset->asset_no,
                'model' => (string)$asset->model,
                'imei' => (string)$asset->imei,
                'party_id' => (int)($asset->owner_party_id ?: $asset->party_id),
                'party_name' => (string)($asset->owner_party_name ?: $asset->party_name),
                'source_warehouse_id' => (int)$asset->warehouse_id,
                'source_warehouse_name' => (string)$asset->warehouse_name,
            ], $decision);
        }
        $actions = array_values(array_unique($actions));
        $batchAction = count($actions) === 1 ? $actions[0] : 'mixed';
        $allowed = $batchAction !== 'mixed' && !in_array($batchAction, ['blocked', 'complete_profile'], true);
        if ($batchAction === 'buyout' && count($items) > 1) {
            $allowed = false;
        }
        return [
            'action' => $batchAction,
            'allowed' => $allowed ? 1 : 0,
            'label' => $batchAction === 'buyout' ? '转为自有' : ($batchAction === 'transfer' ? '普通调拨' : '需要分别处理'),
            'reason' => $batchAction === 'mixed'
                ? '所选设备包含不同物权或不同处理方式，请按处理类型分别操作'
                : ($batchAction === 'buyout' && count($items) > 1 ? '代卖转自有必须逐台确认回收价' : (string)($items[0]['reason'] ?? '')),
            'target_warehouse' => [
                'id' => (int)$targetWarehouse->id,
                'warehouse_name' => (string)$targetWarehouse->warehouse_name,
                'warehouse_type' => (string)$targetWarehouse->warehouse_type,
                'ownership_type' => (string)$targetWarehouse->ownership_type,
            ],
            'target_location' => ['id' => (int)$targetLocation->id, 'location_name' => (string)$targetLocation->location_name],
            'items' => $items,
        ];
    }

    /**
     * 代卖设备转为自有：一次提交完成物权变更、采购事实、设备应付、库存移动和插件同步事件。
     */
    public function buyoutConsignment(
        int $assetId,
        int $warehouseId,
        int $locationId,
        float $buyoutAmount,
        string $reason = '',
        string $requestId = ''
    ): array {
        if ($assetId <= 0) throw new CommonException('请选择代卖设备');
        $buyoutAmount = round($buyoutAmount, 2);
        if ($buyoutAmount <= 0) throw new CommonException('请填写有效回收价');
        [$targetWarehouse, $targetLocation] = (new ErpWarehouseService())->validateInboundLocation($warehouseId, $locationId);
        if ((string)$targetWarehouse->ownership_type === 'consigned' || (string)$targetWarehouse->warehouse_type === 'consignment') {
            throw new CommonException('转为自有必须选择二手机仓或其他自有仓');
        }
        $requestId = ErpIdempotency::normalize($requestId);
        if ($requestId !== '') {
            $existing = ErpAssetLedger::where([
                ['site_id', '=', $this->site_id], ['request_id', '=', $requestId], ['action', '=', 'ownership_purchase'],
            ])->findOrEmpty();
            if (!$existing->isEmpty()) {
                $payable = ErpPayable::where([
                    ['site_id', '=', $this->site_id], ['source_type', '=', 'purchase_asset'], ['asset_id', '=', $assetId],
                    ['source_no', '=', (string)$existing->source_no],
                ])->findOrEmpty();
                return [
                    'asset_id' => $assetId, 'purchase_order_id' => (int)$existing->source_id,
                    'purchase_no' => (string)$existing->source_no, 'payable_id' => (int)($payable->id ?? 0),
                    'idempotent' => true,
                ];
            }
        }

        $result = [];
        $outboxId = 0;
        Db::transaction(function () use (
            $assetId, $targetWarehouse, $targetLocation, $buyoutAmount, $reason, $requestId, &$result, &$outboxId
        ) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty()) throw new CommonException('代卖设备不存在');
            if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) throw new CommonException('只有在库代卖设备可以转为自有');
            $sourceWarehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset->warehouse_id]])->findOrEmpty();
            $decision = (new ErpWarehousePolicyService())->transferDecision(
                $asset->toArray(),
                $sourceWarehouse->isEmpty() ? null : $sourceWarehouse->toArray(),
                $targetWarehouse->toArray()
            );
            if ((string)$decision['action'] !== 'buyout') {
                throw new CommonException((string)($decision['reason'] ?? '当前设备不是可收购的代卖设备'));
            }
            $this->validateConsignmentBuyoutExtension($asset);
            $partyId = (int)($asset->owner_party_id ?: $asset->party_id);
            $partyName = trim((string)($asset->owner_party_name ?: $asset->party_name));
            if ($partyId <= 0 || $partyName === '') throw new CommonException('代卖设备缺少物权客户，请先完善往来主体');
            $party = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', $partyId], ['status', '=', 1]])->findOrEmpty();
            if ($party->isEmpty()) throw new CommonException('代卖设备物权客户不存在或已停用');
            $partyRoles = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$party->role_flags)))));
            if (!in_array('purchase_supplier', $partyRoles, true)) {
                $partyRoles[] = 'purchase_supplier';
                $party->save(['role_flags' => implode(',', $partyRoles), 'update_at' => time()]);
            }

            $now = time();
            $purchaseNo = ErpLedgerService::makeNo('PO');
            $sourceSnapshot = json_decode((string)$asset->spec_json, true);
            if (!is_array($sourceSnapshot)) $sourceSnapshot = [];
            $purchaseSourceService = new ErpFinanceSourceService();
            $purchaseSource = $purchaseSourceService->purchase([
                'origin_plugin' => (string)($asset->source_plugin ?: 'hsx_erp'),
                'origin_plugin_name' => (string)$asset->source_plugin === 'hsx_recycle' ? '回收插件' : '二手机ERP',
                'origin_type' => 'hsx_erp.consignment_buyout',
                'origin_name' => '代卖转自有',
                'origin_id' => (string)($sourceSnapshot['source_device_id'] ?? $asset->source_id),
                'origin_no' => (string)($sourceSnapshot['source_order_no'] ?? ''),
                'purchase_channel_key' => 'consignment_buyout',
                'purchase_channel' => '代卖买断',
            ]);
            $order = ErpPurchaseOrder::create([
                'site_id' => $this->site_id,
                'request_id' => $requestId !== '' ? ErpIdempotency::child($requestId, 'purchase') : null,
                'purchase_no' => $purchaseNo,
                'party_id' => $partyId, 'party_name' => $partyName,
                'm_no' => (string)$party->m_no,
                'purchase_channel' => '代卖买断', 'settle_method' => '挂账',
                'warehouse_id' => (int)$targetWarehouse->id, 'warehouse_name' => (string)$targetWarehouse->warehouse_name,
                'location_id' => (int)$targetLocation->id, 'location_name' => (string)$targetLocation->location_name,
                'purchaser_uid' => (int)$this->uid, 'purchaser_name' => (string)$this->username,
                'total_cost' => $buyoutAmount, 'paid_amount' => 0, 'payable_amount' => $buyoutAmount,
                'finance_status' => ErpDict::STATUS_PENDING, 'status' => ErpDict::STATUS_COMPLETED,
                'source_plugin' => 'erp', 'source_type' => 'consignment_buyout', 'source_id' => (string)$asset->id,
                'origin_plugin' => (string)$purchaseSource['origin_plugin'],
                'origin_plugin_name' => (string)$purchaseSource['origin_plugin_name'],
                'origin_type' => (string)$purchaseSource['origin_type'], 'origin_name' => (string)$purchaseSource['origin_name'],
                'origin_id' => (string)$purchaseSource['origin_id'], 'origin_no' => (string)$purchaseSource['origin_no'],
                'operator_uid' => (int)$this->uid, 'operator_name' => (string)$this->username,
                'purchase_at' => $now,
                'remark' => trim($reason) ?: '代卖设备确认收购并转为自有库存',
                'create_at' => $now, 'update_at' => $now,
            ]);
            $purchaseItem = ErpPurchaseItem::create([
                'site_id' => $this->site_id, 'purchase_order_id' => (int)$order->id, 'asset_id' => (int)$asset->id,
                'warehouse_id' => (int)$targetWarehouse->id, 'warehouse_name' => (string)$targetWarehouse->warehouse_name,
                'location_id' => (int)$targetLocation->id, 'location_name' => (string)$targetLocation->location_name,
                'imei' => (string)$asset->imei, 'sn' => (string)$asset->sn, 'model' => (string)$asset->model,
                'spec' => (string)$asset->spec, 'spec_json' => (string)$asset->spec_json,
                'color' => (string)$asset->color, 'battery' => (int)$asset->battery, 'warranty' => (int)$asset->warranty,
                'catalog_product_id' => (int)$asset->catalog_product_id,
                'category_name' => (string)$asset->category_name, 'category_path' => (string)$asset->category_path,
                'inspector_uid' => (int)$asset->inspector_uid, 'inspector_name' => (string)$asset->inspector_name,
                'estimate_sale_price' => (float)$asset->estimate_sale_price, 'retail_price' => (float)$asset->retail_price,
                'image_urls' => (string)$asset->image_urls, 'video_url' => (string)($asset->video_url ?? ''),
                'quality_remark' => (string)$asset->quality_remark,
                'purchase_cost' => $buyoutAmount, 'adjust_cost' => 0, 'total_cost' => $buyoutAmount,
                'status' => ErpDict::ASSET_IN_STOCK,
                'remark' => trim($reason) ?: '代卖转自有', 'create_at' => $now, 'update_at' => $now,
            ]);
            $before = $asset->toArray();
            $internalCost = max(0, round((float)$asset->adjust_cost + (float)$asset->refurbish_cost, 2));
            $afterCost = round($buyoutAmount + $internalCost, 2);
            $projected = array_merge($before, [
                'warehouse_id' => (int)$targetWarehouse->id, 'warehouse_name' => (string)$targetWarehouse->warehouse_name,
                'location_id' => (int)$targetLocation->id, 'location_name' => (string)$targetLocation->location_name,
                'ownership_type' => 'owned', 'purchase_cost' => $buyoutAmount, 'total_cost' => $afterCost,
                'sale_target' => (string)$targetWarehouse->default_sale_target,
            ]);
            $targetPolicy = (new ErpWarehousePolicyService())->evaluate($projected, $targetWarehouse->toArray());
            $listingStatus = 'none';
            if ((int)$targetPolicy['can_prepare_mall'] === 1) {
                $listingStatus = ErpListingWorkflow::statusFromPolicy($targetPolicy);
            }
            $asset->save([
                'purchase_order_id' => (int)$order->id, 'purchase_item_id' => (int)$purchaseItem->id,
                'party_id' => $partyId, 'party_name' => $partyName,
                'ownership_type' => 'owned', 'owner_party_id' => 0, 'owner_party_name' => '本公司',
                'ownership_source_type' => 'consignment_buyout', 'ownership_source_id' => (int)$order->id,
                'ownership_source_no' => $purchaseNo, 'ownership_changed_at' => $now,
                'warehouse_id' => (int)$targetWarehouse->id, 'warehouse_name' => (string)$targetWarehouse->warehouse_name,
                'location_id' => (int)$targetLocation->id, 'location_name' => (string)$targetLocation->location_name,
                'purchase_cost' => $buyoutAmount, 'total_cost' => $afterCost,
                'sale_target' => (string)$targetWarehouse->default_sale_target, 'listing_status' => $listingStatus,
                'update_at' => $now,
            ]);
            $businessReason = sprintf(
                '向客户【%s】买断代卖设备【%s / IMEI %s】，回收价 ¥%.2f，形成设备级采购应付。',
                $partyName, (string)($asset->model ?: $asset->asset_no), (string)($asset->imei ?: '-'), $buyoutAmount
            );
            $payable = ErpPayable::create(array_merge([
                'site_id' => $this->site_id, 'payable_no' => ErpLedgerService::makeNo('AP'),
                'party_id' => $partyId, 'party_name' => $partyName,
                // 继续使用财务模块统一的设备级采购关联；“代卖买断”通过来源快照、
                // 业务说明和采购单 source_type 表达，确保应付详情/批次结算能直接复用。
                'source_type' => 'purchase_asset', 'source_id' => (int)$asset->id, 'source_no' => $purchaseNo,
                'asset_id' => (int)$asset->id, 'amount' => $buyoutAmount, 'settled_amount' => 0,
                'status' => ErpDict::STATUS_PENDING, 'occurred_at' => $now,
                'remark' => '代卖转自有：' . (string)($asset->model ?: $asset->asset_no),
                'create_at' => $now, 'update_at' => $now,
            ], $purchaseSourceService->persistable(array_merge($purchaseSource, [
                'business_reason' => $businessReason,
            ]))));
            $ledger = new ErpLedgerService();
            $ledger->asset([
                'asset_id' => (int)$asset->id, 'request_id' => $requestId !== '' ? $requestId : null,
                'action' => 'ownership_purchase',
                'before_status' => (string)$before['status'], 'after_status' => (string)$before['status'],
                'before_warehouse_id' => (int)$before['warehouse_id'], 'before_warehouse_name' => (string)$before['warehouse_name'],
                'before_location_id' => (int)$before['location_id'], 'before_location_name' => (string)$before['location_name'],
                'after_warehouse_id' => (int)$targetWarehouse->id, 'after_warehouse_name' => (string)$targetWarehouse->warehouse_name,
                'after_location_id' => (int)$targetLocation->id, 'after_location_name' => (string)$targetLocation->location_name,
                'before_total_cost' => (float)$before['total_cost'], 'after_total_cost' => $afterCost,
                'cost_delta' => round($afterCost - (float)$before['total_cost'], 2),
                'party_id' => $partyId, 'party_name' => $partyName,
                'source_type' => 'consignment_buyout', 'source_id' => (int)$order->id, 'source_no' => $purchaseNo,
                'remark' => trim($reason) ?: '代卖设备转为自有库存并生成采购应付',
                'extra' => [
                    'business_action' => 'consignment_buyout', 'before_ownership' => 'consigned', 'after_ownership' => 'owned',
                    'buyout_amount' => $buyoutAmount, 'internal_cost' => $internalCost,
                    'purchase_order_id' => (int)$order->id, 'purchase_no' => $purchaseNo,
                    'payable_id' => (int)$payable->id, 'payable_no' => (string)$payable->payable_no,
                    'source_warehouse_type' => (string)$sourceWarehouse->warehouse_type,
                    'target_warehouse_type' => (string)$targetWarehouse->warehouse_type,
                ],
            ]);
            $ledger->account([
                'biz_type' => 'consignment_buyout', 'direction' => 'increase', 'amount' => $buyoutAmount,
                'party_id' => $partyId, 'party_name' => $partyName, 'asset_id' => (int)$asset->id,
                'source_type' => 'consignment_buyout', 'source_id' => (int)$order->id, 'source_no' => $purchaseNo,
                'remark' => '代卖买断形成自有设备采购成本',
            ]);
            (new ErpOperationLogService())->record(
                'consignment_buyout', 'asset', (int)$asset->id, (string)$asset->asset_no,
                '代卖设备转为自有并生成采购应付',
                ['buyout_amount' => $buyoutAmount, 'purchase_no' => $purchaseNo, 'payable_no' => (string)$payable->payable_no]
            );

            $sourceDeviceId = (int)($sourceSnapshot['source_device_id'] ?? ((string)$asset->source_plugin === 'hsx_recycle' ? $asset->source_id : 0));
            if ((string)$asset->source_plugin === 'hsx_recycle' && $sourceDeviceId > 0) {
                $queued = (new ErpIntegrationService())->enqueueDomainEvent(
                    'erp.asset.consignment_bought_out.v1', 'asset', (int)$asset->id,
                    [
                        'asset_id' => (int)$asset->id, 'asset_no' => (string)$asset->asset_no,
                        'source_device_id' => $sourceDeviceId,
                        'source_order_no' => (string)($sourceSnapshot['source_order_no'] ?? ''),
                        'buyout_amount' => $buyoutAmount, 'purchase_order_id' => (int)$order->id,
                        'purchase_no' => $purchaseNo, 'payable_id' => (int)$payable->id,
                        'payable_no' => (string)$payable->payable_no, 'occurred_at' => $now,
                    ],
                    ['plugin' => 'hsx_erp', 'type' => 'consignment_buyout', 'id' => (int)$asset->id],
                    ['hsx_recycle']
                );
                $outboxId = (int)$queued['id'];
            }
            $result = [
                'asset_id' => (int)$asset->id, 'purchase_order_id' => (int)$order->id, 'purchase_no' => $purchaseNo,
                'payable_id' => (int)$payable->id, 'payable_no' => (string)$payable->payable_no,
                'buyout_amount' => $buyoutAmount, 'idempotent' => false,
            ];
        });
        if ($outboxId > 0) {
            $result['plugin_sync'] = (new ErpIntegrationService())->dispatchDomainEvent($outboxId);
        }
        return $result;
    }

    /**
     * 插件侧只做买断前的只读校验，避免 ERP 已生成应付后才发现原代卖单已成交。
     * 未安装来源插件时不阻塞 ERP；安装后的插件可通过 Hook 明确拒绝操作。
     */
    private function validateConsignmentBuyoutExtension(ErpAsset $asset): void
    {
        if ((string)$asset->source_plugin !== 'hsx_recycle') return;
        $snapshot = json_decode((string)$asset->spec_json, true);
        if (!is_array($snapshot)) $snapshot = [];
        $deviceId = (int)($snapshot['source_device_id'] ?? $asset->source_id ?? 0);
        if ($deviceId <= 0) return;
        $responses = (array)event('HsxErpConsignmentBuyoutValidate', [
            'site_id' => $this->site_id,
            'asset_id' => (int)$asset->id,
            'source_device_id' => $deviceId,
        ]);
        foreach ($responses as $response) {
            if (!is_array($response) || (int)($response['handled'] ?? 0) !== 1) continue;
            if ((int)($response['allowed'] ?? 0) === 1) continue;
            throw new CommonException((string)($response['message'] ?? '来源业务状态不允许买断'));
        }
    }

    /** 批量调拨库存设备；仓库配置决定是否允许调拨及调拨后的销售去向。 */
    public function transfer(array $assetIds, int $warehouseId, int $locationId, string $reason = '', string $requestId = ''): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));
        if ($assetIds === []) throw new CommonException('请选择要调拨的设备');
        if (count($assetIds) > 200) throw new CommonException('单次最多调拨200台设备');
        [$targetWarehouse, $targetLocation] = (new ErpWarehouseService())->validateInboundLocation($warehouseId, $locationId);
        $requestId = ErpIdempotency::normalize($requestId);
        if ($requestId !== '') {
            $existing = ErpAssetLedger::where([
                ['site_id', '=', $this->site_id],
                ['request_id', '=', ErpIdempotency::child($requestId, (string)$assetIds[0])],
                ['action', '=', 'transfer'],
            ])->findOrEmpty();
            if (!$existing->isEmpty()) return ['count' => count($assetIds), 'idempotent' => true];
        }
        $transferNo = ErpLedgerService::makeNo('TF');
        Db::transaction(function () use ($assetIds, $targetWarehouse, $targetLocation, $reason, $requestId, $transferNo) {
            $assets = ErpAsset::where('site_id', '=', $this->site_id)->whereIn('id', $assetIds)->lock(true)->select();
            if (count($assets) !== count($assetIds)) throw new CommonException('部分设备不存在，请刷新库存后重试');
            foreach ($assets as $asset) {
                if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) throw new CommonException('只有在库设备可以调拨');
                if (in_array((string)$asset->refurbish_status, ['pending', 'processing', 'failed'], true)) {
                    throw new CommonException('设备【' . (string)($asset->model ?: $asset->asset_no) . '】处于整备流程，不能普通调拨');
                }
                $sourceWarehouse = ErpWarehouse::where([
                    ['site_id', '=', $this->site_id], ['id', '=', (int)$asset->warehouse_id],
                ])->findOrEmpty();
                $decision = (new ErpWarehousePolicyService())->transferDecision(
                    $asset->toArray(),
                    $sourceWarehouse->isEmpty() ? null : $sourceWarehouse->toArray(),
                    $targetWarehouse->toArray()
                );
                if ((string)$decision['action'] !== 'transfer' || (int)$decision['allowed'] !== 1) {
                    throw new CommonException((string)$decision['reason']);
                }
                // 历史仓库被停用或删除时允许纠正性调拨，否则设备会永久卡在无效仓库。
                // 有效仓库仍严格服从 allow_transfer 配置。
                if (!$sourceWarehouse->isEmpty() && (int)$sourceWarehouse->allow_transfer !== 1) {
                    throw new CommonException('设备【' . (string)($asset->model ?: $asset->asset_no) . '】所在仓库不允许调拨');
                }
                if ((int)$asset->warehouse_id === (int)$targetWarehouse->id && (int)$asset->location_id === (int)$targetLocation->id) {
                    throw new CommonException('设备已经位于所选仓库和库位');
                }
                if ((string)$asset->listing_status === 'listed' && (string)$targetWarehouse->default_sale_target !== 'mall') {
                    throw new CommonException('设备【' . (string)($asset->model ?: $asset->asset_no) . '】已上架商城，请先下架再调拨');
                }

                $before = $asset->toArray();
                $projected = array_merge($before, [
                    'warehouse_id' => (int)$targetWarehouse->id,
                    'warehouse_name' => (string)$targetWarehouse->warehouse_name,
                    'location_id' => (int)$targetLocation->id,
                    'location_name' => (string)$targetLocation->location_name,
                    'sale_target' => (string)$targetWarehouse->default_sale_target,
                ]);
                $policy = (new ErpWarehousePolicyService())->evaluate($projected, $targetWarehouse->toArray());
                $listingStatus = 'none';
                if ((int)$policy['can_prepare_mall'] === 1) {
                    $listingStatus = ErpListingWorkflow::statusFromPolicy($policy);
                }
                $asset->save([
                    'warehouse_id' => (int)$targetWarehouse->id,
                    'warehouse_name' => (string)$targetWarehouse->warehouse_name,
                    'location_id' => (int)$targetLocation->id,
                    'location_name' => (string)$targetLocation->location_name,
                    'sale_target' => (string)$targetWarehouse->default_sale_target,
                    'listing_status' => $listingStatus,
                    'update_at' => time(),
                ]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'request_id' => $requestId !== '' ? ErpIdempotency::child($requestId, (string)$asset->id) : null,
                    'action' => 'transfer',
                    'before_status' => (string)$before['status'], 'after_status' => (string)$before['status'],
                    'before_warehouse_id' => (int)$before['warehouse_id'], 'before_warehouse_name' => (string)$before['warehouse_name'],
                    'before_location_id' => (int)$before['location_id'], 'before_location_name' => (string)$before['location_name'],
                    'after_warehouse_id' => (int)$targetWarehouse->id, 'after_warehouse_name' => (string)$targetWarehouse->warehouse_name,
                    'after_location_id' => (int)$targetLocation->id, 'after_location_name' => (string)$targetLocation->location_name,
                    'before_total_cost' => (float)$before['total_cost'], 'after_total_cost' => (float)$before['total_cost'],
                    'source_type' => 'stock_transfer', 'source_id' => (int)$asset->id, 'source_no' => $transferNo,
                    'remark' => trim($reason) ?: ('库存调拨至' . (string)$targetWarehouse->warehouse_name . ' / ' . (string)$targetLocation->location_name),
                    'extra' => [
                        'business_action' => 'stock_transfer',
                        'source_warehouse_type' => (string)($sourceWarehouse->warehouse_type ?? ''),
                        'target_warehouse_type' => (string)$targetWarehouse->warehouse_type,
                        'before_ownership' => (string)($asset->ownership_type ?? 'owned'),
                        'after_ownership' => (string)($asset->ownership_type ?? 'owned'),
                        'reason' => trim($reason),
                    ],
                ]);
            }
        });
        return ['count' => count($assetIds), 'transfer_no' => $transferNo, 'idempotent' => false];
    }

    /**
     * 库存中心统一补齐设备入库与最近一次销售出库快照。
     * 即使设备尚未销售，也返回稳定的空出库字段，前端无需按状态拼接多套结构。
     */
    private function appendLifecycleContext(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $assetIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows)));
        $purchaseOrderIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['purchase_order_id'] ?? 0), $rows))));
        $purchaseItemIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['purchase_item_id'] ?? 0), $rows))));

        $purchaseOrderMap = [];
        if ($purchaseOrderIds !== []) {
            $purchaseOrders = ErpPurchaseOrder::where('site_id', '=', $this->site_id)
                ->whereIn('id', $purchaseOrderIds)
                ->field('id,purchase_no,party_name,purchaser_name,total_cost,purchase_at,create_at,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_no')
                ->select()->toArray();
            foreach ($purchaseOrders as $order) {
                $purchaseOrderMap[(int)$order['id']] = $order;
            }
        }
        $purchaseItemMap = [];
        if ($purchaseItemIds !== []) {
            $purchaseItems = ErpPurchaseItem::where('site_id', '=', $this->site_id)
                ->whereIn('id', $purchaseItemIds)
                ->field('id,warehouse_name,location_name')
                ->select()->toArray();
            foreach ($purchaseItems as $item) {
                $purchaseItemMap[(int)$item['id']] = $item;
            }
        }

        $compensationMap = [];
        if ($assetIds !== []) {
            $compensationRows = ErpAccountLedger::where([
                ['site_id', '=', $this->site_id],
                ['biz_type', '=', 'sale_compensation'],
            ])->whereIn('asset_id', $assetIds)
                ->field('asset_id,direction,amount')->select()->toArray();
            foreach ($compensationRows as $compensationRow) {
                $assetId = (int)$compensationRow['asset_id'];
                $signedAmount = (string)$compensationRow['direction'] === 'decrease'
                    ? -(float)$compensationRow['amount']
                    : (float)$compensationRow['amount'];
                $compensationMap[$assetId] = round((float)($compensationMap[$assetId] ?? 0) + $signedAmount, 2);
            }
        }

        $latestSaleItemMap = [];
        $saleOrderIds = [];
        if ($assetIds !== []) {
            $saleItems = ErpSaleItem::where('site_id', '=', $this->site_id)
                ->whereIn('asset_id', $assetIds)
                ->field('id,sale_order_id,asset_id,cost,sale_price,profit,status,remark,create_at,update_at')
                ->order('id desc')->select()->toArray();
            foreach ($saleItems as $item) {
                $assetId = (int)$item['asset_id'];
                if (!isset($latestSaleItemMap[$assetId])) {
                    $latestSaleItemMap[$assetId] = $item;
                    $saleOrderIds[] = (int)$item['sale_order_id'];
                }
            }
        }
        $saleOrderMap = [];
        $saleOrderIds = array_values(array_unique(array_filter($saleOrderIds)));
        if ($saleOrderIds !== []) {
            $saleOrders = ErpSaleOrder::where('site_id', '=', $this->site_id)
                ->whereIn('id', $saleOrderIds)
                ->field('id,sale_no,party_name,sale_channel,salesman_name,total_amount,received_amount,receivable_amount,finance_status,status,sale_at,create_at,origin_plugin,origin_plugin_name,origin_type,origin_name,origin_no')
                ->select()->toArray();
            foreach ($saleOrders as $order) {
                $saleOrderMap[(int)$order['id']] = $order;
            }
        }

        $payableMap = [];
        if ($assetIds !== []) {
            $payables = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase_asset'],
            ])->whereIn('source_id', $assetIds)
                ->field('source_id,amount,settled_amount,status')
                ->select()->toArray();
            foreach ($payables as $payable) {
                $payableMap[(int)$payable['source_id']] = $payable;
            }
        }

        // 兼容历史整单应付：新数据按设备建应付，旧数据可能只在采购单维度记录折账。
        $orderPayableMap = [];
        if ($purchaseOrderIds !== []) {
            $orderPayables = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase'],
            ])->whereIn('source_id', $purchaseOrderIds)
                ->field('source_id,amount,settled_amount,status')
                ->select()->toArray();
            foreach ($orderPayables as $orderPayable) {
                $orderPayableMap[(int)$orderPayable['source_id']] = $orderPayable;
            }
        }

        $receivableMap = [];
        $receiptAssetMap = [];
        $receiptExplicitTotalMap = [];
        if ($saleOrderIds !== []) {
            $receivables = ErpReceivable::where('site_id', '=', $this->site_id)
                ->whereIn('source_id', $saleOrderIds)
                ->where('status', '<>', ErpDict::STATUS_VOID)
                ->field('id,source_type,source_id,origin_plugin,amount,settled_amount,status')
                ->select()->toArray();
            $receivableIds = [];
            foreach ($receivables as $receivable) {
                if (!$this->isSaleReceivableRow($receivable)) {
                    continue;
                }
                $receivableMap[(int)$receivable['source_id']] = $receivable;
                $receivableIds[] = (int)$receivable['id'];
            }
            $receivableIds = array_values(array_unique(array_filter($receivableIds)));
            if ($receivableIds !== []) {
                $receiptRows = ErpAccountLedger::where([
                    ['site_id', '=', $this->site_id],
                    ['biz_type', '=', 'receipt'],
                    ['source_type', '=', 'receivable'],
                ])->whereIn('source_id', $receivableIds)
                    ->where('asset_id', '>', 0)
                    ->field('source_id,asset_id,SUM(amount) as amount')
                    ->group('source_id,asset_id')
                    ->select()->toArray();
                foreach ($receiptRows as $receiptRow) {
                    $receivableId = (int)$receiptRow['source_id'];
                    $assetId = (int)$receiptRow['asset_id'];
                    $amount = round((float)$receiptRow['amount'], 2);
                    $receiptAssetMap[$receivableId][$assetId] = $amount;
                    $receiptExplicitTotalMap[$receivableId] = round(($receiptExplicitTotalMap[$receivableId] ?? 0) + $amount, 2);
                }
            }
        }

        foreach ($rows as &$row) {
            $purchaseOrder = $purchaseOrderMap[(int)($row['purchase_order_id'] ?? 0)] ?? [];
            $purchaseItem = $purchaseItemMap[(int)($row['purchase_item_id'] ?? 0)] ?? [];
            $saleItem = $latestSaleItemMap[(int)($row['id'] ?? 0)] ?? [];
            $saleOrder = $saleOrderMap[(int)($saleItem['sale_order_id'] ?? 0)] ?? [];
            $payable = $payableMap[(int)($row['id'] ?? 0)] ?? [];
            $orderPayable = $orderPayableMap[(int)($row['purchase_order_id'] ?? 0)] ?? [];
            $receivable = $receivableMap[(int)($saleItem['sale_order_id'] ?? 0)] ?? [];
            $row['inbound_purchase_no'] = (string)($purchaseOrder['purchase_no'] ?? '');
            $row['inbound_origin_name'] = (string)($purchaseOrder['origin_name'] ?? '');
            $row['inbound_origin_plugin_name'] = (string)($purchaseOrder['origin_plugin_name'] ?? '');
            $row['inbound_origin_no'] = (string)($purchaseOrder['origin_no'] ?? '');
            $row['inbound_party_name'] = (string)($purchaseOrder['party_name'] ?? $row['party_name'] ?? '');
            $row['inbound_operator_name'] = (string)($purchaseOrder['purchaser_name'] ?? '');
            $row['inbound_at'] = (int)($purchaseOrder['purchase_at'] ?? 0) ?: (int)($purchaseOrder['create_at'] ?? $row['stock_in_at'] ?? 0);
            $row['inbound_warehouse_name'] = (string)($purchaseItem['warehouse_name'] ?? $row['warehouse_name'] ?? '');
            $row['inbound_location_name'] = (string)($purchaseItem['location_name'] ?? $row['location_name'] ?? '');
            $row['inbound_settlement_amount'] = round((float)($payable['amount'] ?? $row['purchase_cost'] ?? 0), 2);
            if ($payable !== []) {
                $row['inbound_settled_amount'] = min((float)$row['inbound_settlement_amount'], round((float)$payable['settled_amount'], 2));
            } elseif ($orderPayable !== []) {
                $orderTotal = round((float)($purchaseOrder['total_cost'] ?? $orderPayable['amount'] ?? 0), 2);
                $orderSettled = round((float)($orderPayable['settled_amount'] ?? 0), 2);
                $row['inbound_settled_amount'] = $orderTotal > 0.0001
                    ? min((float)$row['inbound_settlement_amount'], round((float)$row['inbound_settlement_amount'] / $orderTotal * $orderSettled, 2))
                    : 0.0;
            } else {
                $row['inbound_settled_amount'] = 0.0;
            }
            $row['inbound_finance_status'] = (float)$row['inbound_settlement_amount'] > 0
                ? ErpDict::financeStatus((float)$row['inbound_settlement_amount'], (float)$row['inbound_settled_amount'])
                : '';
            $row['outbound_sale_item_id'] = (int)($saleItem['id'] ?? 0);
            $row['outbound_sale_no'] = (string)($saleOrder['sale_no'] ?? '');
            $row['outbound_origin_name'] = (string)($saleOrder['origin_name'] ?? '');
            $row['outbound_origin_plugin_name'] = (string)($saleOrder['origin_plugin_name'] ?? '');
            $row['outbound_origin_no'] = (string)($saleOrder['origin_no'] ?? '');
            $row['outbound_party_name'] = (string)($saleOrder['party_name'] ?? '');
            $row['outbound_channel'] = (string)($saleOrder['sale_channel'] ?? '');
            $row['outbound_operator_name'] = (string)($saleOrder['salesman_name'] ?? '');
            $row['outbound_status'] = (string)($saleItem['status'] ?? '');
            $row['outbound_finance_status'] = (string)($saleOrder['finance_status'] ?? '');
            $row['outbound_at'] = (string)($saleItem['status'] ?? '') === ErpDict::STATUS_VOID
                ? ((int)($saleItem['update_at'] ?? 0) ?: (int)($saleItem['create_at'] ?? 0))
                : ((int)($saleOrder['sale_at'] ?? 0) ?: (int)($saleOrder['create_at'] ?? $saleItem['create_at'] ?? 0));
            $row['outbound_sale_price'] = round((float)($saleItem['sale_price'] ?? 0), 2);
            $row['outbound_compensation_amount'] = max(0, round((float)($compensationMap[(int)($row['id'] ?? 0)] ?? 0), 2));
            $row['outbound_net_sale_amount'] = max(0, round((float)$row['outbound_sale_price'] - (float)$row['outbound_compensation_amount'], 2));
            $row['outbound_cost'] = round((float)($saleItem['cost'] ?? 0), 2);
            $row['outbound_profit'] = round((float)($saleItem['profit'] ?? 0), 2);
            $row['outbound_remark'] = (string)($saleItem['remark'] ?? '');
            $salePrice = round((float)($saleItem['sale_price'] ?? 0), 2);
            $receivableId = (int)($receivable['id'] ?? 0);
            if ($receivableId > 0) {
                $explicitSettled = round((float)($receiptAssetMap[$receivableId][(int)($row['id'] ?? 0)] ?? 0), 2);
                $fallbackSettled = max(0, round((float)($receivable['settled_amount'] ?? 0) - (float)($receiptExplicitTotalMap[$receivableId] ?? 0), 2));
                $receivableAmount = round((float)($receivable['amount'] ?? 0), 2);
                $fallbackAllocated = $receivableAmount > 0.0001
                    ? round($salePrice / $receivableAmount * $fallbackSettled, 2)
                    : 0.0;
                $settledAmount = round($explicitSettled + $fallbackAllocated, 2);
            } else {
                // 线上现结可能不产生应收明细，按销售单已收金额比例分摊到设备。
                $orderTotal = round((float)($saleOrder['total_amount'] ?? 0), 2);
                $orderReceived = min($orderTotal, round((float)($saleOrder['received_amount'] ?? 0), 2));
                $settledAmount = $orderTotal > 0.0001
                    ? round($salePrice / $orderTotal * $orderReceived, 2)
                    : 0.0;
            }
            $row['outbound_settlement_amount'] = $salePrice;
            $row['outbound_settled_amount'] = min($salePrice, $settledAmount);
            $row['outbound_finance_status'] = (string)($saleItem['status'] ?? '') === ErpDict::ASSET_SOLD && $salePrice > 0
                ? ErpDict::financeStatus($salePrice, (float)$row['outbound_settled_amount'])
                : '';
        }
        unset($row);
        return $rows;
    }

    /** ERP 原生销售与商城销售都属于销售应收，其他同 source_id 的业务应收不能混入。 */
    private function isSaleReceivableRow(array $row): bool
    {
        if ((string)($row['source_type'] ?? '') === 'sale') {
            return true;
        }
        if ((string)($row['origin_plugin'] ?? '') === 'phone_shop') {
            return true;
        }
        $sourceType = (string)($row['source_type'] ?? '');
        return (string)($row['origin_plugin'] ?? '') === '' && (
            str_starts_with($sourceType, 'phone_shop.')
            || in_array($sourceType, ['external.native_goods_sale', 'external.mall_sale'], true)
        );
    }

    public function info(int $id): array
    {
        $asset = $this->findAsset($id)->toArray();
        $asset['purchase_order'] = null;
        $asset['sale_order'] = null;
        if ((int)$asset['purchase_order_id'] > 0) {
            $purchase = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset['purchase_order_id']]])->findOrEmpty();
            $asset['purchase_order'] = $purchase->isEmpty() ? null : $purchase->toArray();
        }
        if ((int)$asset['sale_order_id'] > 0) {
            $sale = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset['sale_order_id']]])->findOrEmpty();
            $asset['sale_order'] = $sale->isEmpty() ? null : $sale->toArray();
        }
        $asset['last_sale_item'] = null;
        if ($asset['sale_order'] === null) {
            $lastSaleItem = ErpSaleItem::where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $id],
            ])->order('id desc')->findOrEmpty();
            if (!$lastSaleItem->isEmpty()) {
                $asset['last_sale_item'] = $lastSaleItem->toArray();
                $lastSale = ErpSaleOrder::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', (int)$lastSaleItem->sale_order_id],
                ])->findOrEmpty();
                $asset['sale_order'] = $lastSale->isEmpty() ? null : $lastSale->toArray();
            }
        }
        $asset['asset_ledgers'] = $this->enrichAssetLedgers(
            ErpAssetLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])
                ->order('id desc')->limit(30)->select()->toArray()
        );
        $accountLedgers = ErpAccountLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])
            ->order('id desc')->limit(30)->select()->toArray();
        $asset['account_ledgers'] = $this->enrichAssetAccountLedgers($accountLedgers, $asset);
        $compensationAmount = 0.0;
        foreach ($accountLedgers as $accountLedger) {
            if (strtolower((string)($accountLedger['biz_type'] ?? '')) !== 'sale_compensation') continue;
            $signedAmount = (string)($accountLedger['direction'] ?? '') === 'decrease'
                ? -(float)($accountLedger['amount'] ?? 0)
                : (float)($accountLedger['amount'] ?? 0);
            $compensationAmount = round($compensationAmount + $signedAmount, 2);
        }
        $grossSaleAmount = round((float)($asset['sale_price'] ?? $asset['last_sale_item']['sale_price'] ?? 0), 2);
        $asset['sale_compensation_amount'] = max(0, $compensationAmount);
        $asset['net_sale_amount'] = max(0, round($grossSaleAmount - (float)$asset['sale_compensation_amount'], 2));
        $supplierAmount = round((float)($asset['purchase_cost'] ?? 0), 2);
        $paidAmount = 0.0;
        $payable = ErpPayable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'purchase_asset'],
            ['source_id', '=', $id],
        ])->findOrEmpty();
        if (!$payable->isEmpty()) {
            $supplierAmount = round((float)$payable->amount, 2);
            $paidAmount = round((float)$payable->settled_amount, 2);
        }
        $purchaseCost = round((float)($asset['purchase_cost'] ?? 0), 2);
        $refurbishCost = round((float)($asset['refurbish_cost'] ?? 0), 2);
        $totalCost = round((float)($asset['total_cost'] ?? 0), 2);
        $asset['cost_summary'] = [
            'purchase_cost' => $purchaseCost,
            'supplier_adjust_cost' => round($supplierAmount - $purchaseCost, 2),
            'supplier_amount' => $supplierAmount,
            'refurbish_cost' => $refurbishCost,
            'internal_adjust_cost' => round($totalCost - $supplierAmount - $refurbishCost, 2),
            'total_cost' => $totalCost,
        ];
        $asset['return_flow'] = ErpPurchaseReturnPolicy::assess($asset, $supplierAmount, $paidAmount);
        $asset = (new ErpTurnoverService())->decorate([$asset])[0] ?? $asset;
        $asset = $this->appendListingSyncState([$asset])[0] ?? $asset;
        return (new ErpDataVisibilityService())->sanitizeStockDetail($asset);
    }

    /** 返回用户能直接理解的商城上架状态，不暴露拍照中台或 Outbox 等内部实现。 */
    private function appendListingSyncState(array $rows): array
    {
        $rules = (new ErpConfigService())->getRules();
        $materialOwner = (string)($rules['marketplace']['recycle_material_owner'] ?? 'erp');
        $workspace = (new ErpListingWorkspaceService())->describe();
        $workspaceMode = (string)($workspace['mode'] ?? 'one_stop');
        $channelStateMap = (new ErpChannelMappingService())->listingStateMap(
            (int)$this->site_id,
            array_column($rows, 'id'),
            'phone_shop'
        );
        foreach ($rows as &$row) {
            $policy = (array)($row['warehouse_policy'] ?? []);
            $status = (string)($row['listing_status'] ?? 'none');
            $channelState = (array)($channelStateMap[(int)($row['id'] ?? 0)] ?? []);
            if (!in_array($status, ['pending_shop', 'listed'], true)
                && (string)($row['status'] ?? '') === ErpDict::ASSET_IN_STOCK
                && (string)($row['sale_target'] ?? '') === 'mall') {
                $status = ErpListingWorkflow::statusFromAsset($row, $policy);
                $row['listing_status'] = $status;
            }
            $row['listing_material_owner'] = $materialOwner;
            $row['listing_workspace'] = $workspace;
            $row['can_handoff_shop'] = (int)(
                $materialOwner === 'phone_shop'
                && ErpListingWorkflow::canHandoffToShop($row, $policy)
                && !in_array($status, ['pending_shop', 'listed'], true)
            );
            if ((string)($row['status'] ?? '') === ErpDict::ASSET_IN_STOCK && (string)($row['sale_target'] ?? '') === 'mall') {
                if (in_array($status, ['need_photo', 'need_price'], true) && (string)($workspace['media_provider'] ?? 'erp') === 'device_asset') {
                    $row['turnover_action_key'] = 'prepare_listing_media';
                    $row['turnover_action_label'] = $status === 'need_photo' ? '开始标准拍摄' : '继续销售定价';
                    $row['turnover_action_reason'] = '已启用拍照中台，从当前 ERP 入口创建或继续任务，完成后自动回写';
                } elseif ($workspaceMode === 'one_stop' && in_array($status, ['need_photo', 'need_price', 'need_material'], true)) {
                    $row['turnover_action_key'] = 'complete_listing';
                    $row['turnover_action_label'] = (int)($workspace['auto_publish'] ?? 0) === 1 ? '一次完善并上架' : '一次完善商品资料';
                    $row['turnover_action_reason'] = (int)($workspace['auto_publish'] ?? 0) === 1
                        ? '在一个表单完成型号、规格、图片和销售价格，保存后按规则自动发布'
                        : '在一个表单完成型号、规格、图片和销售价格，保存后人工确认发布';
                } elseif ($workspaceMode === 'photo_price' && in_array($status, ['need_photo', 'need_price'], true)) {
                    $row['turnover_action_key'] = 'complete_listing_media_price';
                    $row['turnover_action_label'] = '拍摄并销售定价';
                    $row['turnover_action_reason'] = '当前岗位连续完成商品拍摄和销售定价';
                } elseif ($status === 'need_photo') {
                    $row['turnover_action_key'] = 'complete_listing_photo';
                    $row['turnover_action_label'] = '上传商品图片';
                    $row['turnover_action_reason'] = '当前由商品拍摄人员完成标准图片';
                } elseif ($status === 'need_price') {
                    $row['turnover_action_key'] = 'complete_listing_price';
                    $row['turnover_action_label'] = '设置销售价格';
                    $row['turnover_action_reason'] = '图片已完成，等待库位负责人确定销售价格';
                } elseif ((int)$row['can_handoff_shop'] === 1) {
                    $row['turnover_action_key'] = 'publish_listing';
                    $row['turnover_action_label'] = '交接商城资料运营';
                    $row['turnover_action_reason'] = '图片和售价已完成，由商城资料运营对应分类、规格、标签并发布';
                } elseif ($status === 'need_material') {
                    $row['turnover_action_key'] = 'complete_listing_material';
                    $row['turnover_action_label'] = '整理商城资料';
                    $row['turnover_action_reason'] = '补齐商品型号、规格和对外展示资料后即可上架';
                }
            }
            $row['listing_sync'] = [
                'status' => $status,
                'status_label' => match ($status) {
                    'listed' => '商城已上架',
                    'ready' => '商品资料完整',
                    'pending_shop' => '待商城资料运营处理',
                    'need_photo' => '待商品拍摄',
                    'need_price' => '待销售定价',
                    'need_material' => '待商城资料整理',
                    default => (string)($row['sale_target'] ?? '') === 'mall' ? '待完善商品资料' : '无需上架',
                },
                'channel_status' => (string)($channelState['status'] ?? ''),
                'channel_status_label' => match ((string)($channelState['status'] ?? '')) {
                    'pending' => '商城处理中',
                    'published' => '商城已发布',
                    'offline' => '商城已下架',
                    'sold' => '商城已售',
                    'error' => '商城处理失败',
                    default => '尚未发起',
                },
                'last_error' => trim((string)($channelState['last_error'] ?? '')),
                'goods_id' => (int)($channelState['channel_item_id'] ?? 0),
                'intake_id' => (int)($channelState['channel_intake_id'] ?? 0),
                'updated_at' => (int)($channelState['update_at'] ?? 0),
            ];
        }
        unset($row);
        return $rows;
    }

    /** 在 ERP 当前入口准备拍摄能力；中台异常时返回 ERP 上传降级方案。 */
    public function prepareListingMedia(int $id): array
    {
        return (new ErpListingWorkspaceService())->prepareMedia($id);
    }

    private function enrichAssetAccountLedgers(array $rows, array $asset): array
    {
        if ($rows === []) return [];
        $assetId = (int)($asset['id'] ?? 0);
        $purchaseOrderId = (int)($asset['purchase_order_id'] ?? 0);
        $saleOrderId = (int)($asset['sale_order_id'] ?? 0);
        if ($saleOrderId <= 0) {
            // 销售撤销/退货会清空资产当前销售单，但设备档案仍需关联最近一次销售账务。
            $saleOrderId = (int)($asset['last_sale_item']['sale_order_id'] ?? 0);
        }

        $payables = ErpPayable::where([['site_id', '=', $this->site_id]])
            ->where(function ($query) use ($assetId, $purchaseOrderId) {
                $query->where('asset_id', '=', $assetId)
                    ->whereOr(function ($sub) use ($assetId) { $sub->where('source_type', '=', 'purchase_asset')->where('source_id', '=', $assetId); });
                if ($purchaseOrderId > 0) $query->whereOr(function ($sub) use ($purchaseOrderId) { $sub->where('source_type', '=', 'purchase')->where('source_id', '=', $purchaseOrderId); });
            })->field('id,asset_id,source_type,source_id')->select()->toArray();
        $saleOrderIds = [];
        if ($saleOrderId > 0) $saleOrderIds[] = $saleOrderId;
        foreach ($rows as $ledgerRow) {
            if (strtolower((string)($ledgerRow['biz_type'] ?? '')) === 'sale' && (int)($ledgerRow['source_id'] ?? 0) > 0) {
                $saleOrderIds[] = (int)$ledgerRow['source_id'];
            }
        }
        $saleOrderIds = array_values(array_unique($saleOrderIds));
        $receivables = $saleOrderIds !== []
            ? ErpReceivable::where([['site_id', '=', $this->site_id], ['source_type', '=', 'sale']])->whereIn('source_id', $saleOrderIds)->field('id,source_id')->select()->toArray()
            : [];

        $targetGroups = ['purchase' => [], 'sale_compensation' => [], 'refurbish' => [], 'sale' => []];
        $payableSourceMap = [];
        $targetLookup = [];
        foreach ($payables as $payable) {
            $sourceType = (string)$payable['source_type'];
            $sourceId = (int)$payable['source_id'];
            $group = $sourceType === 'sale_return' ? 'sale_compensation' : ($sourceType === 'refurbish' ? 'refurbish' : 'purchase');
            $targetGroups[$group][] = (int)$payable['id'];
            $payableSourceMap[(int)$payable['id']] = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ];
            $contextKey = $group === 'purchase' ? 'purchase' : ($group . '_' . $sourceId);
            $targetLookup[ErpDict::TARGET_PAYABLE . '_' . (int)$payable['id']] = $contextKey;
        }
        $targetGroups['sale'] = array_map(static fn(array $row): int => (int)$row['id'], $receivables);
        foreach ($receivables as $receivable) {
            $targetLookup[ErpDict::TARGET_RECEIVABLE . '_' . (int)$receivable['id']] = 'sale_' . (int)$receivable['source_id'];
        }
        $links = [];
        if ($targetLookup !== []) {
            $links = ErpSettlementLink::where([['site_id', '=', $this->site_id]])
                ->where(function ($query) use ($targetGroups) {
                    $payableIds = array_values(array_unique(array_merge($targetGroups['purchase'], $targetGroups['sale_compensation'], $targetGroups['refurbish'])));
                    if ($payableIds !== []) $query->where(function ($sub) use ($payableIds) { $sub->where('target_type', '=', ErpDict::TARGET_PAYABLE)->whereIn('target_id', $payableIds); });
                    if ($targetGroups['sale'] !== []) $query->whereOr(function ($sub) use ($targetGroups) { $sub->where('target_type', '=', ErpDict::TARGET_RECEIVABLE)->whereIn('target_id', $targetGroups['sale']); });
                })->select()->toArray();
        }
        $settlementIds = array_values(array_unique(array_filter(array_map(static fn(array $link): int => (int)$link['settlement_id'], $links))));
        $settlementMap = $settlementIds === [] ? [] : ErpSettlement::where([['site_id', '=', $this->site_id]])->whereIn('id', $settlementIds)->column('settlement_type', 'id');
        $methodMap = [];
        $methodLabels = ['payment' => '实际付款', 'receipt' => '实际收款', 'offset' => '折账结清'];
        foreach ($links as $link) {
            $group = $targetLookup[(string)$link['target_type'] . '_' . (int)$link['target_id']] ?? '';
            $type = (string)($settlementMap[(int)$link['settlement_id']] ?? '');
            if ($group !== '' && isset($methodLabels[$type])) $methodMap[$group][$methodLabels[$type]] = $methodLabels[$type];
        }
        foreach ($rows as &$row) {
            $type = strtolower((string)($row['biz_type'] ?? ''));
            $sourceType = strtolower((string)($row['source_type'] ?? ''));
            $direction = strtolower((string)($row['direction'] ?? ''));
            $row['biz_type_text'] = FinanceDict::bizTypeText($type);
            $row['source_type_text'] = FinanceDict::sourceTypeText($sourceType);
            $row['direction_text'] = $direction === 'decrease' ? '账款减少' : '账款增加';
            $row['amount_sign'] = $direction === 'decrease' ? '-' : '+';
            $row['signed_amount'] = $direction === 'decrease'
                ? -round((float)($row['amount'] ?? 0), 2)
                : round((float)($row['amount'] ?? 0), 2);
            if ($type === 'sale_compensation' && (int)($row['source_id'] ?? 0) > 0) {
                $row['lifecycle_key'] = 'sale_return_' . (int)$row['source_id'];
            } elseif ($type === 'payment' && (string)($row['source_type'] ?? '') === 'payable') {
                $payableSource = $payableSourceMap[(int)($row['source_id'] ?? 0)] ?? null;
                if (($payableSource['source_type'] ?? '') === 'sale_return' && (int)($payableSource['source_id'] ?? 0) > 0) {
                    $row['lifecycle_key'] = 'sale_return_' . (int)$payableSource['source_id'];
                }
            }
            if ($type === 'payment') {
                // 付款流水本身就是资金事实，不能继承采购应付的折账方式。
                $row['settlement_methods'] = ['实际付款'];
                continue;
            }
            if ($type === 'receipt') {
                $row['settlement_methods'] = ['实际收款'];
                continue;
            }
            if ($type === 'offset') {
                $row['settlement_methods'] = ['折账结清'];
                continue;
            }
            if (in_array($type, ['sale_cancel', 'sale_item_cancel', 'sale_return', 'purchase_cancel', 'purchase_return'], true)) {
                $row['settlement_methods'] = ['冲销完成'];
                continue;
            }
            if ($type === 'purchase_return_loss') {
                $row['settlement_methods'] = ['计入退货损失'];
                continue;
            }
            if ($type === 'sale_compensation') {
                $contextKey = 'sale_compensation_' . (int)($row['source_id'] ?? 0);
            } elseif ($type === 'sale') {
                $contextKey = 'sale_' . (int)($row['source_id'] ?? 0);
            } elseif ($type === 'refurbish') {
                $contextKey = 'refurbish_' . (int)($row['source_id'] ?? 0);
            } else {
                $contextKey = 'purchase';
            }
            $row['settlement_methods'] = array_values($methodMap[$contextKey] ?? []);
        }
        unset($row);
        return $this->markReversedAccountLedgers($rows);
    }

    /**
     * 将销售形成的应收与后续撤销/退货冲回配对，避免历史应收继续显示“待结算”。
     * 一台设备允许多次销售，因此按发生时间逐次配对最近一笔尚未冲销的销售记录。
     */
    private function markReversedAccountLedgers(array $rows): array
    {
        if ($rows === []) return [];
        $indexes = array_keys($rows);
        usort($indexes, static function ($left, $right) use ($rows): int {
            $leftAt = (int)($rows[$left]['occurred_at'] ?? $rows[$left]['create_at'] ?? 0);
            $rightAt = (int)($rows[$right]['occurred_at'] ?? $rows[$right]['create_at'] ?? 0);
            return [$leftAt, (int)($rows[$left]['id'] ?? 0)] <=> [$rightAt, (int)($rows[$right]['id'] ?? 0)];
        });

        $openSales = [];
        foreach ($indexes as $index) {
            $type = strtolower((string)($rows[$index]['biz_type'] ?? ''));
            if ($type === 'sale') {
                $openSales[] = $index;
                continue;
            }
            if (!in_array($type, ['sale_cancel', 'sale_item_cancel', 'sale_return'], true)) continue;

            $matchedPosition = null;
            $sourceNo = (string)($rows[$index]['source_no'] ?? '');
            for ($position = count($openSales) - 1; $position >= 0; --$position) {
                $saleIndex = $openSales[$position];
                $saleSourceNo = (string)($rows[$saleIndex]['source_no'] ?? '');
                if ($type === 'sale_return' || $sourceNo === '' || $saleSourceNo === '' || $saleSourceNo === $sourceNo) {
                    $matchedPosition = $position;
                    break;
                }
            }

            $rows[$index]['business_state'] = 'reversal_completed';
            $rows[$index]['business_state_text'] = '冲销完成';
            $rows[$index]['settlement_methods'] = array_values(array_unique(array_merge(
                (array)($rows[$index]['settlement_methods'] ?? []),
                ['冲销完成']
            )));
            if ($matchedPosition === null) continue;

            $saleIndex = $openSales[$matchedPosition];
            array_splice($openSales, $matchedPosition, 1);
            $rows[$saleIndex]['business_state'] = 'reversed';
            $rows[$saleIndex]['business_state_text'] = '已冲销';
            $rows[$saleIndex]['reversed_by_ledger_no'] = (string)($rows[$index]['ledger_no'] ?? '');
            $rows[$saleIndex]['settlement_methods'] = array_values(array_unique(array_merge(
                (array)($rows[$saleIndex]['settlement_methods'] ?? []),
                ['已冲销']
            )));
            $rows[$index]['reverses_ledger_no'] = (string)($rows[$saleIndex]['ledger_no'] ?? '');
        }
        return $rows;
    }

    public function ledgerPage(array $where): array
    {
        $query = ErpAssetLedger::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('ledger_no|asset_no|imei|model|party_name|source_no|remark', '%' . $kw . '%');
        }
        if (!empty($where['asset_id'])) {
            $query->where('asset_id', '=', (int)$where['asset_id']);
        }
        if (!empty($where['action'])) {
            $query->where('action', '=', (string)$where['action']);
        }
        if (!empty($where['source_type'])) {
            $query->where('source_type', '=', (string)$where['source_type']);
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        $page['data'] = $this->enrichAssetLedgers((array)($page['data'] ?? []));
        return $page;
    }

    /**
     * 给设备账本补充稳定中文字典，PC/移动端可直接消费，未知插件值也不会裸露英文。
     */
    private function enrichAssetLedgers(array $rows): array
    {
        $costTypeMap = ErpDict::getCostTypeMap();
        foreach ($rows as &$row) {
            $action = trim((string)($row['action'] ?? ''));
            $sourceType = trim((string)($row['source_type'] ?? ''));
            $extra = json_decode((string)($row['extra_json'] ?? ''), true);
            $costType = trim((string)(is_array($extra) ? ($extra['cost_type'] ?? '') : ''));
            if ($costType === '' && isset($costTypeMap[$sourceType])) $costType = $sourceType;
            if ($costType === '' && isset($costTypeMap[$action])) $costType = $action;
            $row['action_text'] = ErpDict::ledgerActionText($action);
            $row['source_type_text'] = FinanceDict::sourceTypeText($sourceType);
            $row['before_status_text'] = ErpDict::assetStatusText((string)($row['before_status'] ?? ''));
            $row['after_status_text'] = ErpDict::assetStatusText((string)($row['after_status'] ?? ''));
            $row['cost_type'] = $costType;
            $row['cost_type_text'] = $costType !== '' ? ErpDict::costTypeText($costType) : '';
            $operatorName = trim((string)($row['operator_name'] ?? ''));
            $operatorId = (int)($row['operator_id'] ?? $row['operator_uid'] ?? 0);
            $row['operator_name'] = $operatorName;
            // 旧流水可能没有经办人快照。前端统一消费该字段，避免留白或展示内部 ID。
            $row['operator_display'] = $operatorName !== '' ? $operatorName : ($operatorId > 0 ? ('员工 #' . $operatorId) : '系统自动');
        }
        unset($row);
        return $rows;
    }

    public function updateFlow(int $id, array $data): void
    {
        $asset = $this->findAsset($id);
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
            throw new CommonException('只有库存中的设备才能调整流转状态');
        }
        $workflowAction = trim((string)($data['workflow_action'] ?? ''));
        $workspaceRules = (array)(ErpConfigService::forSite((int)$this->site_id)->getRules()['listing_workspace'] ?? []);
        $workflowActions = [
            ErpListingFormContract::ACTION_ONE_STOP,
            ErpListingFormContract::ACTION_PHOTO,
            ErpListingFormContract::ACTION_PRICE,
            ErpListingFormContract::ACTION_MEDIA_PRICE,
            ErpListingFormContract::ACTION_MATERIAL,
        ];
        if ($workflowAction !== '') {
            if (!in_array($workflowAction, $workflowActions, true)) {
                throw new CommonException('销售资料处理步骤不正确，请刷新页面后重试');
            }
            $editableFields = ErpListingFormContract::editableFields($workflowAction, $workspaceRules);
            foreach ([
                'estimate_sale_price', 'retail_price', 'image_urls', 'video_url',
                'catalog_product_id', 'spec', 'quality_remark', 'remark_public', 'remark_internal',
            ] as $field) {
                if (!in_array($field, $editableFields, true)) $data[$field] = null;
            }
            $projectedAsset = $asset->toArray();
            foreach ($editableFields as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null) {
                    $projectedAsset[$field] = $data[$field];
                }
            }
            $missingLabels = [];
            foreach (ErpListingFormContract::requiredFields($workflowAction, $workspaceRules) as $field) {
                if (!ErpListingFormContract::hasValue($field, $projectedAsset[$field] ?? null)) {
                    $missingLabels[] = ErpListingFormContract::fieldLabel($field);
                }
            }
            if ($missingLabels !== []) {
                throw new CommonException('请先完善' . implode('、', $missingLabels));
            }
        }
        $refurbishStatus = (string)($data['refurbish_status'] ?? '');
        $saleTarget = (string)($data['sale_target'] ?? '');
        $listingStatus = (string)($data['listing_status'] ?? '');
        $allowedRefurbish = ['none', 'pending'];
        $allowedTarget = ['unset', 'peer', 'mall'];
        $currentListingStatus = (string)$asset->listing_status;
        $terminalListingStatuses = ['pending_shop', 'listed'];
        $warehouse = ErpWarehouse::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$asset->warehouse_id], ['status', '=', 1],
        ])->findOrEmpty();
        $save = ['update_at' => time()];
        $changes = [];
        // 兼容旧版前端继续回传当前值，但禁止任何客户端直接推进上架终态。
        if ($listingStatus !== '' && $listingStatus !== $currentListingStatus) {
            throw new CommonException('商城交接和上架状态由系统及渠道回执自动更新，不能手工设置');
        }
        if ($refurbishStatus !== '') {
            if (!in_array($refurbishStatus, $allowedRefurbish, true)) {
                throw new CommonException('整备中、完工和异常状态必须通过整备流程操作，不能手工改状态');
            }
            if ($refurbishStatus === 'pending' && in_array($currentListingStatus, $terminalListingStatuses, true)) {
                throw new CommonException('设备已交接或上架商城，请先在商城完成撤回/下架，再进入整备');
            }
            if ($refurbishStatus !== (string)$asset->refurbish_status) {
                $save['refurbish_status'] = $refurbishStatus;
                if ($refurbishStatus === 'pending') {
                    $save['refurbish_pending_at'] = time();
                    $save['listing_status'] = 'none';
                } elseif ($refurbishStatus === 'none') {
                    $save['refurbish_pending_at'] = 0;
                }
                $changes[] = '整备状态';
            }
        }
        if ($saleTarget !== '') {
            if (!in_array($saleTarget, $allowedTarget, true)) {
                throw new CommonException('销售去向不正确');
            }
            if ($saleTarget === 'mall' && ($warehouse->isEmpty() || (string)$warehouse->default_sale_target !== 'mall')) {
                throw new CommonException('当前仓库未配置为商城销售仓，不能设置为上商城');
            }
            if ($saleTarget !== 'mall' && in_array($currentListingStatus, $terminalListingStatuses, true)) {
                throw new CommonException('设备已交接或上架商城，请先在商城完成撤回/下架，再修改销售去向');
            }
            if ($saleTarget !== (string)$asset->sale_target) {
                $save['sale_target'] = $saleTarget;
                $changes[] = '销售去向';
            }
        }
        if (array_key_exists('estimate_sale_price', $data) && $data['estimate_sale_price'] !== null) {
            $estimate = round((float)$data['estimate_sale_price'], 2);
            if ($estimate < 0) {
                throw new CommonException('预计卖价不能小于0');
            }
            if (abs($estimate - round((float)$asset->estimate_sale_price, 2)) > 0.0001) {
                $save['estimate_sale_price'] = $estimate;
                $changes[] = '预计卖价';
            }
        }
        if (array_key_exists('retail_price', $data) && $data['retail_price'] !== null) {
            $retailPrice = round((float)$data['retail_price'], 2);
            if ($retailPrice < 0) throw new CommonException('零售价不能小于0');
            if (abs($retailPrice - round((float)$asset->retail_price, 2)) > 0.0001) {
                $save['retail_price'] = $retailPrice;
                $changes[] = '零售价';
            }
        }
        if (array_key_exists('image_urls', $data) && $data['image_urls'] !== null) {
            $imageUrls = trim((string)$data['image_urls']);
            if ($imageUrls !== (string)$asset->image_urls) {
                $save['image_urls'] = $imageUrls;
                $changes[] = '图片';
            }
        }
        if (array_key_exists('video_url', $data) && $data['video_url'] !== null) {
            $videoUrl = trim((string)$data['video_url']);
            if ($videoUrl !== (string)($asset->video_url ?? '')) {
                $save['video_url'] = $videoUrl;
                $changes[] = '视频';
            }
        }
        if (array_key_exists('catalog_product_id', $data) && $data['catalog_product_id'] !== null) {
            $catalogProductId = max(0, (int)$data['catalog_product_id']);
            if ($catalogProductId !== (int)($asset->catalog_product_id ?? 0)) {
                $catalog = (new ErpGoodsCatalogService())->productSnapshot($catalogProductId);
                $save['catalog_product_id'] = $catalogProductId;
                $save['category_name'] = $catalog['category_name'];
                $save['category_path'] = $catalog['category_path'];
                if (trim((string)$asset->model) === '' && $catalog['product_name'] !== '') $save['model'] = $catalog['product_name'];
                $changes[] = '商品型号';
            }
        }
        if (array_key_exists('spec', $data) && $data['spec'] !== null) {
            $spec = trim((string)$data['spec']);
            if ($spec !== (string)$asset->spec) {
                $save['spec'] = $spec;
                $changes[] = '规格';
            }
        }
        if (array_key_exists('quality_remark', $data) && $data['quality_remark'] !== null) {
            $qualityRemark = trim((string)$data['quality_remark']);
            if ($qualityRemark !== (string)$asset->quality_remark) {
                $save['quality_remark'] = $qualityRemark;
                $changes[] = '备注';
            }
        }
        foreach (['remark_public' => '对外说明', 'remark_internal' => '对内备注'] as $field => $label) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $value = trim((string)$data[$field]);
                if ($value !== (string)$asset->{$field}) {
                    $save[$field] = $value;
                    $changes[] = $label;
                }
            }
        }
        // 上架状态只有一个真相来源：仓库规则 + 本次保存后的资料。
        // pending_shop / listed 只能由渠道回执推进，普通资料保存不得覆盖终态。
        $projectedTarget = (string)($save['sale_target'] ?? $asset->sale_target);
        $autoListingStatus = $currentListingStatus;
        if (!in_array($currentListingStatus, $terminalListingStatuses, true)) {
            if ($projectedTarget === 'mall') {
                $projected = array_merge($asset->toArray(), $save, ['sale_target' => $projectedTarget]);
                $policy = (new ErpWarehousePolicyService())->evaluate($projected, $warehouse->isEmpty() ? null : $warehouse->toArray());
                $autoListingStatus = ErpListingWorkflow::statusFromAsset($projected, $policy);
            } else {
                $autoListingStatus = 'none';
            }
        }
        if ($autoListingStatus !== $currentListingStatus) {
            $save['listing_status'] = $autoListingStatus;
            $changes[] = '上架状态';
        }
        if (count($save) <= 1) {
            if ($workflowAction !== '') return;
            throw new CommonException('没有需要保存的内容');
        }
        // ThinkORM save() 会同步修改当前模型对象；必须先固定快照，后续岗位工作量才能准确比较前后值。
        $beforeAsset = $asset->toArray();
        $before = (string)$asset->refurbish_status . '/' . (string)$asset->sale_target . '/' . (string)$asset->listing_status;
        $asset->save($save);
        $afterAsset = $this->findAsset($id);
        $after = (string)$afterAsset->refurbish_status . '/' . (string)$afterAsset->sale_target . '/' . (string)$afterAsset->listing_status;
        (new ErpLedgerService())->asset([
            'asset_id' => $id,
            'action' => 'flow',
            'before_status' => $before,
            'after_status' => $after,
            'source_type' => 'asset',
            'source_id' => $id,
            'remark' => trim((string)($data['remark'] ?? '')) ?: ('更新' . implode('、', array_unique($changes))),
        ]);
        $workflowLedger = new ErpLedgerService();
        $beforeImages = $this->normalizeImageUrls($beforeAsset['image_urls'] ?? '');
        $afterImages = $this->normalizeImageUrls($afterAsset->image_urls);
        if ($beforeImages !== $afterImages && $afterImages !== []) {
            $workflowLedger->asset([
                'asset_id' => $id,
                'action' => 'listing_photo_complete',
                'before_status' => $before,
                'after_status' => $after,
                'source_type' => 'listing_workflow',
                'source_id' => $id,
                'remark' => '完成商品图片整理，共 ' . count($afterImages) . ' 张',
                'extra' => ['image_count' => count($afterImages)],
            ]);
        }
        if (abs(round((float)($beforeAsset['retail_price'] ?? 0), 2) - round((float)$afterAsset->retail_price, 2)) > 0.0001
            && (float)$afterAsset->retail_price > 0) {
            $workflowLedger->asset([
                'asset_id' => $id,
                'action' => 'listing_price_complete',
                'before_status' => $before,
                'after_status' => $after,
                'source_type' => 'listing_workflow',
                'source_id' => $id,
                'remark' => '完成销售定价：¥' . number_format((float)$afterAsset->retail_price, 2, '.', ''),
                'extra' => ['before_retail_price' => round((float)($beforeAsset['retail_price'] ?? 0), 2), 'after_retail_price' => round((float)$afterAsset->retail_price, 2)],
            ]);
        }
        $materialChanged = (int)($beforeAsset['catalog_product_id'] ?? 0) !== (int)($afterAsset->catalog_product_id ?? 0)
            || trim((string)($beforeAsset['spec'] ?? '')) !== trim((string)$afterAsset->spec);
        if ($materialChanged && (int)($afterAsset->catalog_product_id ?? 0) > 0 && trim((string)$afterAsset->spec) !== '') {
            $workflowLedger->asset([
                'asset_id' => $id,
                'action' => 'listing_material_complete',
                'before_status' => $before,
                'after_status' => $after,
                'source_type' => 'listing_workflow',
                'source_id' => $id,
                'remark' => '完成 ERP 商品型号与规格资料整理',
            ]);
        }
        // 资料保存与外部插件同步必须解耦：未安装商城时 ERP 仍可独立使用。
        // 资料完整后由“上架商城”显式触发 Hook；拍照中台不是主流程依赖。
    }

    /**
     * 由 ERP 直接把资料完整的设备发布到已安装商城。
     * 旧方法名保留给已部署前端兼容，语义已经从“同步中台”改为“直接上架”。
     */
    public function syncListing(int $id): array
    {
        $asset = $this->findAsset($id);
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
            throw new CommonException('只有库存中的设备可以上架商城');
        }
        if ((string)$asset->sale_target !== 'mall') {
            throw new CommonException('请先将设备销售去向设置为上商城');
        }
        $warehouse = ErpWarehouse::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$asset->warehouse_id], ['status', '=', 1],
        ])->findOrEmpty();
        $policy = (new ErpWarehousePolicyService())->evaluate($asset->toArray(), $warehouse->isEmpty() ? null : $warehouse->toArray());
        $channelPolicy = (new ErpConfigService())->getMarketplaceChannel('phone_shop', (int)$this->site_id);
        if ((int)($channelPolicy['enabled'] ?? 0) !== 1) {
            throw new CommonException('自有商城渠道已关闭；设备资料仍保留在 ERP');
        }
        $shopCompletion = (string)($channelPolicy['publish_mode'] ?? 'direct') === 'manual';
        if ((int)$policy['allow_mall'] !== 1) {
            throw new CommonException('当前仓库未配置为商城销售仓，请先调拨到允许上商城的仓库');
        }
        if (in_array((string)$asset->refurbish_status, ['pending', 'processing', 'failed'], true)) {
            throw new CommonException('设备整备完成前不能上架商城');
        }

        $specMeta = $this->decodeJsonObject($asset->spec_json);
        $erpContext = $this->marketplaceErpContext($asset, $specMeta);
        $channelMapping = (new ErpChannelMappingService())->resolve([
            'site_id' => (int)$this->site_id,
            'channel_key' => 'phone_shop',
            'erp_asset_id' => $id,
            'erp_context' => $erpContext,
        ]);
        // 独立分类/规格模式下，首次没有映射时自动进入商城人工待办；完成一次后映射可复用。
        if (!$shopCompletion
            && ((string)$channelPolicy['category_mode'] === 'independent' || (string)$channelPolicy['spec_mode'] === 'independent')
            && empty($channelMapping['ready'])) {
            $shopCompletion = true;
        }
        if ($shopCompletion && !ErpListingWorkflow::canHandoffToShop($asset->toArray(), $policy)) {
            $status = ErpListingWorkflow::statusFromAsset($asset->toArray(), $policy);
            if ($status === 'need_photo') throw new CommonException('请先完成商品拍摄并上传可售图片，再交接商城资料运营');
            if ($status === 'need_price') throw new CommonException('请先由库位负责人完成销售定价，再交接商城资料运营');
            throw new CommonException('图片和销售价格尚未完成，暂不能交接商城资料运营');
        }
        if (!$shopCompletion && (int)($policy['can_list_mall'] ?? 0) !== 1) {
            $missing = implode('、', (array)($policy['missing_labels'] ?? []));
            throw new CommonException($missing !== '' ? ('请先补充' . $missing) : '商品资料未达到上架条件');
        }
        if ((int)($policy['marketplace_available'] ?? 0) !== 1) {
            throw new CommonException('当前站点未安装商城；商品资料已保存在 ERP，无需再走上架流程');
        }
        if ((string)$asset->listing_status === 'listed') {
            return ['ok' => true, 'status' => 'duplicate', 'message' => '商品已经上架商城'];
        }

        // 分类来源与发布方式是两个独立维度：
        // - 消费 ERP 目录：无论直发还是运营确认，都先完成目录投影并自动预填；
        // - 商城独立分类：已有映射时复用，首次没有映射时交给商城运营选择。
        if ((string)$channelPolicy['category_mode'] === 'erp') {
            $categoryPath = $this->marketplaceCategoryPath($asset);
        } elseif (!$shopCompletion) {
            $categoryPath = array_values(array_filter(array_map('intval', (array)($channelMapping['category_ids'] ?? []))));
        } else {
            $categoryPath = [];
        }
        $images = $this->normalizeImageUrls($asset->image_urls);
        $qualityRemark = trim((string)$asset->quality_remark);
        // 商品详情仅传公开卖点/购买说明；质检备注通过 qc_info 独立进入商城质检组件。
        $description = trim((string)$asset->remark_public);
        $assetColor = trim((string)$asset->color);
        $memory = $this->specFieldText($specMeta, ['memory', 'storage', 'capacity'], ['内存', '容量', '存储']);
        $specColor = $this->specFieldText($specMeta, ['color'], ['颜色', '色']);
        $conditionGrade = $this->specValueText($specMeta['condition_grade'] ?? $specMeta['condition'] ?? $specMeta['grade'] ?? '');
        if (!$shopCompletion && (string)$channelPolicy['spec_mode'] === 'independent') {
            $memory = trim((string)($channelMapping['specs']['memory'] ?? $memory));
            $conditionGrade = trim((string)($channelMapping['specs']['condition_grade'] ?? $conditionGrade));
        }
        $payload = [
            'erp_asset_id' => $id,
            'device_id' => (int)((string)$asset->source_plugin === 'hsx_recycle' ? $asset->source_id : 0),
            'asset_no' => (string)$asset->asset_no,
            'imei' => (string)$asset->imei,
            'model_name' => (string)$asset->model,
            'brand_name' => $this->specValueText($specMeta['brand'] ?? ''),
            'memory' => $memory,
            'color' => $assetColor !== '' ? $assetColor : $specColor,
            'battery_health' => (int)$asset->battery,
            'warranty_expire_time' => (int)$asset->warranty,
            'condition_grade' => $conditionGrade,
            'images' => $images,
            'video_url' => trim((string)($asset->video_url ?? '')),
            'sale_price' => round((float)$asset->retail_price, 2),
            'peer_price' => round((float)$asset->estimate_sale_price, 2),
            'cost_price' => round((float)$asset->total_cost, 2),
            'qc_info' => $this->marketplaceQcSnapshot($asset, $qualityRemark),
            'goods_name' => (string)$asset->model,
            'sub_title' => (string)$asset->spec,
            'goods_desc' => $description,
            'goods_category' => $categoryPath,
            'source_plugin' => (string)$asset->source_plugin,
            'source_type' => (string)$asset->source_type,
            'source_id' => (string)$asset->source_id,
            'completion_mode' => $shopCompletion ? 'phone_shop' : 'erp',
            'channel_policy' => $channelPolicy,
            'channel_mapping' => $channelMapping,
            'erp_context' => $erpContext,
        ];

        $success = null;
        $pending = null;
        $failureMessage = '';
        foreach ((array)event('HsxErpPublishListing', ['site_id' => $this->site_id, 'payload' => $payload]) as $result) {
            if (!is_array($result) || (string)($result['provider'] ?? '') !== 'phone_shop') continue;
            if (in_array((string)($result['status'] ?? ''), ['published', 'duplicate'], true) && (int)($result['goods_id'] ?? 0) > 0) {
                $success = $result;
                break;
            }
            if ((string)($result['status'] ?? '') === 'pending' && (int)($result['intake_id'] ?? 0) > 0) {
                $pending = $result;
                break;
            }
            $failureMessage = trim((string)($result['message'] ?? $failureMessage));
        }
        if ($pending !== null) {
            $beforeListingStatus = (string)$asset->listing_status;
            $asset->save(['listing_status' => 'pending_shop', 'update_at' => time()]);
            (new ErpLedgerService())->asset([
                'asset_id' => $id,
                'action' => 'listing_handoff',
                'before_status' => (string)$asset->status . '/' . $beforeListingStatus,
                'after_status' => (string)$asset->status . '/pending_shop',
                'source_type' => 'phone_shop_intake',
                'source_id' => (int)$pending['intake_id'],
                'remark' => '设备已交接商城运营完善分类、规格并上架',
                'extra' => ['provider' => 'phone_shop', 'intake_id' => (int)$pending['intake_id']],
            ]);
            (new ErpChannelMappingService())->recordListing([
                'site_id' => (int)$this->site_id,
                'channel_key' => 'phone_shop',
                'erp_asset_id' => $id,
                'channel_intake_id' => (string)$pending['intake_id'],
                'status' => 'pending',
                'publish_mode' => 'manual',
                'mapping_snapshot' => $channelMapping,
                'payload' => $payload,
            ]);
            return ['ok' => true, 'status' => 'pending', 'intake_id' => (int)$pending['intake_id'], 'message' => '已交接商城运营完善资料'];
        }
        if ($success === null) {
            (new ErpChannelMappingService())->recordListing([
                'site_id' => (int)$this->site_id,
                'channel_key' => 'phone_shop',
                'erp_asset_id' => $id,
                'status' => 'error',
                'publish_mode' => $shopCompletion ? 'manual' : 'direct',
                'mapping_snapshot' => $channelMapping,
                'payload' => $payload,
                'last_error' => $failureMessage ?: '商城上架失败',
            ]);
            throw new CommonException($failureMessage ?: '商城上架失败，请稍后重试');
        }

        $beforeListingStatus = (string)$asset->listing_status;
        $asset->save(['listing_status' => 'listed', 'update_at' => time()]);
        (new ErpLedgerService())->asset([
            'asset_id' => $id,
            'action' => 'listing_publish',
            'before_status' => (string)$asset->status . '/' . $beforeListingStatus,
            'after_status' => (string)$asset->status . '/listed',
            'source_type' => 'phone_shop_goods',
            'source_id' => (int)$success['goods_id'],
            'remark' => '商品资料由 ERP 直接上架商城',
            'extra' => ['provider' => 'phone_shop', 'goods_id' => (int)$success['goods_id']],
        ]);
        (new ErpChannelMappingService())->recordListing([
            'site_id' => (int)$this->site_id,
            'channel_key' => 'phone_shop',
            'erp_asset_id' => $id,
            'channel_item_id' => (string)$success['goods_id'],
            'status' => 'published',
            'publish_mode' => 'direct',
            'mapping_snapshot' => $channelMapping,
            'payload' => $payload,
        ]);
        return ['ok' => true, 'status' => (string)$success['status'], 'goods_id' => (int)$success['goods_id'], 'message' => '已直接上架商城'];
    }

    /**
     * 资料保存后的非阻塞自动流转。
     *
     * - ERP 直发模式：资料齐全后直接发布；
     * - 商城运营模式：图片和销售价格完成后自动交接商城待办；
     * - 独立分类/规格首次无映射：即使渠道配置为直发，也自动降级为运营交接。
     *
     * 渠道失败只保留待办和错误留痕，不能让图片、价格等 ERP 主数据保存失败。
     */
    public function autoPublishListingIfReady(int $id): array
    {
        $rules = ErpConfigService::forSite((int)$this->site_id)->getRules();
        if ((int)($rules['listing_workspace']['auto_publish'] ?? 0) !== 1) {
            return ['triggered' => false, 'reason' => 'manual_confirmation'];
        }
        $channel = (array)($rules['marketplace']['channels']['phone_shop'] ?? []);
        if ((int)($channel['enabled'] ?? 0) !== 1) {
            return ['triggered' => false, 'reason' => 'channel_disabled'];
        }
        $asset = $this->findAsset($id);
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK
            || (string)$asset->sale_target !== 'mall'
            || in_array((string)$asset->listing_status, ['pending_shop', 'listed'], true)) {
            return ['triggered' => false, 'reason' => 'not_ready'];
        }
        $warehouse = ErpWarehouse::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->warehouse_id],
            ['status', '=', 1],
        ])->findOrEmpty();
        $policy = ErpWarehousePolicyService::forSite((int)$this->site_id)
            ->evaluate($asset->toArray(), $warehouse->isEmpty() ? null : $warehouse->toArray());
        if ((int)($policy['marketplace_available'] ?? 0) !== 1) {
            return ['triggered' => false, 'reason' => 'marketplace_unavailable'];
        }
        $canFallbackToShop = (string)($channel['publish_mode'] ?? 'direct') === 'manual'
            || (string)($channel['category_mode'] ?? 'erp') === 'independent'
            || (string)($channel['spec_mode'] ?? 'erp') === 'independent';
        $ready = (string)$asset->listing_status === 'ready'
            || ($canFallbackToShop && ErpListingWorkflow::canHandoffToShop($asset->toArray(), $policy));
        if (!$ready) {
            return ['triggered' => false, 'reason' => 'not_ready'];
        }
        try {
            return ['triggered' => true, 'result' => $this->syncListing($id)];
        } catch (\Throwable $e) {
            Log::warning('ERP销售资料自动发布失败，已保留人工待办', [
                'site_id' => (int)$this->site_id,
                'asset_id' => $id,
                'message' => $e->getMessage(),
            ]);
            return ['triggered' => true, 'failed' => true, 'message' => $e->getMessage()];
        }
    }

    /** 商城只消费 ERP 快照，不再要求运行时回查回收插件数据库。 */
    private function marketplaceQcSnapshot(ErpAsset $asset, string $qualityRemark): array
    {
        $snapshot = $this->decodeJsonObject($asset->qc_report ?? '');
        if ($snapshot === []) {
            $snapshot = ['version' => 1, 'report' => [], 'raw' => []];
        }
        $snapshot['template_id'] = (int)($asset->qc_template_id ?? 0);
        $snapshot['summary'] = array_filter([
            '规格' => (string)$asset->spec,
            '质检说明' => $qualityRemark,
        ]);
        return $snapshot;
    }

    private function marketplaceErpContext(ErpAsset $asset, array $specMeta): array
    {
        $categoryPath = trim((string)($asset->category_path ?? ''));
        if ($categoryPath === '' && (int)($asset->catalog_product_id ?? 0) > 0) {
            $categoryPath = (string)Db::name('erp_site_catalog_product')->where([
                ['site_id', '=', (int)$this->site_id],
                ['site_product_id', '=', (int)$asset->catalog_product_id],
            ])->value('category_path');
        }
        return [
            'catalog_product_id' => (int)($asset->catalog_product_id ?? 0),
            'category_path' => $categoryPath,
            'model_name' => (string)$asset->model,
            'memory' => $this->specFieldText($specMeta, ['memory', 'storage', 'capacity'], ['内存', '容量', '存储']),
            'condition_grade' => $this->specValueText($specMeta['condition_grade'] ?? $specMeta['condition'] ?? $specMeta['grade'] ?? ''),
            'specs' => $specMeta,
        ];
    }

    private function marketplaceCategoryPath(ErpAsset $asset): array
    {
        $projection = (new ErpCatalogChannelProjectionService())->ensurePhoneShopPath(
            (int)$this->site_id,
            (int)($asset->catalog_product_id ?? 0)
        );
        return array_values(array_unique(array_map('intval', (array)$projection['category_ids'])));
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode(trim((string)$value), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** 规格组件可能保存字符串、选项对象或多选数组，上架前统一转换成可读文本。 */
    private function specValueText(mixed $value): string
    {
        if ($value === null || is_bool($value)) return $value === true ? '是' : '';
        if (is_string($value) || is_int($value) || is_float($value)) return trim((string)$value);
        if (!is_array($value)) return '';

        if (!array_is_list($value)) {
            foreach (['label', 'name', 'text', 'title', 'value_name', 'value'] as $key) {
                if (!array_key_exists($key, $value)) continue;
                $text = $this->specValueText($value[$key]);
                if ($text !== '') return $text;
            }
        }

        $parts = [];
        foreach ($value as $item) {
            $text = $this->specValueText($item);
            if ($text !== '') $parts[] = $text;
        }
        return implode(' / ', array_values(array_unique($parts)));
    }

    /** 同时兼容旧版根字段和规格组件 specs.{key}.{label,value,group_label} 结构。 */
    private function specFieldText(array $meta, array $keys, array $groupLabels = []): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $meta)) continue;
            $text = $this->specValueText($meta[$key]);
            if ($text !== '') return $text;
        }
        foreach ((array)($meta['specs'] ?? []) as $key => $option) {
            if (!is_array($option)) continue;
            $groupKey = trim((string)($option['group_key'] ?? $key));
            $groupLabel = trim((string)($option['group_label'] ?? ''));
            $matched = in_array($groupKey, $keys, true);
            if (!$matched) {
                foreach ($groupLabels as $label) {
                    if ($groupLabel === $label || ($label !== '' && str_contains($groupLabel, $label))) {
                        $matched = true;
                        break;
                    }
                }
            }
            if (!$matched) continue;
            $text = $this->specValueText($option['label'] ?? $option['value'] ?? $option);
            if ($text !== '') return $text;
        }
        return '';
    }

    private function normalizeImageUrls(mixed $value): array
    {
        if (is_array($value)) return array_values(array_filter(array_map(static fn($url): string => trim((string)$url), $value)));
        $text = trim((string)$value);
        if ($text === '') return [];
        $decoded = json_decode($text, true);
        $urls = is_array($decoded) ? $decoded : (preg_split('/[,\r\n]+/', $text) ?: []);
        return array_values(array_filter(array_map(static fn($url): string => trim((string)$url), $urls)));
    }

    public function adjustCost(int $id, float $afterCost, string $reason = '', bool $syncPayable = true, string $requestId = '', string $costType = 'internal_adjust', array $context = []): bool
    {
        if ($afterCost <= 0) {
            throw new CommonException('调整后成本必须大于0');
        }
        $asset = $this->findAsset($id);
        $allowedStatuses = [ErpDict::ASSET_IN_STOCK, 'available_for_sale'];
        if ((string)$asset->status === ErpDict::ASSET_RETURNED) {
            throw new CommonException('设备已完成采购退货，不能再调整成本');
        }
        if (!in_array((string)$asset->status, $allowedStatuses, true)) {
            throw new CommonException('只有仍在库存中的设备可以调整成本');
        }
        $beforeCost = round((float)$asset->total_cost, 2);
        if ($costType === 'refurbish' && !empty($context['refurbish_items'])) {
            $items = [];
            $itemTotal = 0.0;
            foreach ((array)$context['refurbish_items'] as $rawItem) {
                if (!is_array($rawItem)) continue;
                $name = mb_substr(trim((string)($rawItem['name'] ?? '')), 0, 40);
                $amount = round((float)($rawItem['amount'] ?? 0), 2);
                if ($name === '' || $amount <= 0) throw new CommonException('请完整填写每一项整备项目和金额');
                $partyId = (int)($rawItem['party_id'] ?? 0);
                if ($partyId <= 0) throw new CommonException('请为每一项整备项目选择服务商');
                $items[] = ['name' => $name, 'amount' => $amount, 'party_id' => $partyId];
                $itemTotal = round($itemTotal + $amount, 2);
            }
            if ($items === [] || $itemTotal <= 0) throw new CommonException('请至少填写一项整备项目');
            $context['refurbish_items'] = $items;
            $context['expense_type_key'] = count($items) > 1 ? 'refurbish_mixed' : (trim((string)($context['expense_type_key'] ?? '')) ?: 'refurbish_mixed');
            $afterCost = round($beforeCost + $itemTotal, 2);
        }
        $delta = round($afterCost - $beforeCost, 2);
        if (abs($delta) <= 0) {
            throw new CommonException('调整金额不能为0');
        }
        if (!in_array($costType, ['purchase_adjust', 'refurbish', 'internal_adjust'], true)) {
            throw new CommonException('成本类型不正确');
        }
        if ($costType === 'refurbish') {
            if ((int)($context['workflow_complete'] ?? 0) !== 1) {
                throw new CommonException('整备费用必须在设备“整备完成”时登记，不能从普通成本调整直接录入');
            }
            return $this->addRefurbishCost($id, $afterCost, $reason, $requestId, $context);
        }
        $purchaseItemId = (int)$asset->purchase_item_id;
        if ($purchaseItemId <= 0) {
            $item = ErpPurchaseItem::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('采购明细不存在，无法调整成本');
            }
            $purchaseItemId = (int)$item->id;
        }

        return (new ErpPurchaseService())->adjustCost(
            $purchaseItemId,
            $delta,
            $reason !== '' ? $reason : '移动端成本调整',
            $costType === 'purchase_adjust',
            $requestId,
            $costType
        );
    }

    private function addRefurbishCost(int $id, float $afterCost, string $reason, string $requestId, array $context): bool
    {
        $requestId = ErpIdempotency::normalize($requestId);
        if ($requestId !== '' && ErpAssetLedger::where([
            ['site_id', '=', $this->site_id],
            ['request_id', '=', $requestId],
            ['action', '=', 'refurbish'],
        ])->count() > 0) {
            return true;
        }
        $categoryKey = trim((string)($context['expense_type_key'] ?? '')) ?: 'refurbish_labor';
        $category = (new ErpConfigService())->findFinanceCategory($categoryKey);
        if (!$category || (string)$category['direction'] !== 'expense' || (string)$category['scope'] !== 'refurbish' || (int)$category['affects_asset_cost'] !== 1) {
            throw new CommonException('请选择有效的整备支出类型');
        }
        $refurbishItems = (array)($context['refurbish_items'] ?? []);
        if ($refurbishItems === []) {
            throw new CommonException('请至少填写一项整备项目');
        }
        $partyIds = array_values(array_unique(array_map(static fn(array $item): int => (int)($item['party_id'] ?? 0), $refurbishItems)));
        if (in_array(0, $partyIds, true)) {
            throw new CommonException('请为每一项整备项目选择服务商');
        }
        $partyRows = ErpParty::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->whereIn('id', $partyIds)
            ->field('id,party_name')
            ->select()
            ->toArray();
        $partyMap = array_column($partyRows, 'party_name', 'id');
        foreach ($refurbishItems as &$refurbishItem) {
            $itemPartyId = (int)($refurbishItem['party_id'] ?? 0);
            if (!isset($partyMap[$itemPartyId])) {
                throw new CommonException('整备服务商不存在或已停用，请重新选择');
            }
            $refurbishItem['party_name'] = (string)$partyMap[$itemPartyId];
        }
        unset($refurbishItem);
        $refurbishTotal = round(array_sum(array_map(static fn(array $item): float => (float)($item['amount'] ?? 0), $refurbishItems)), 2);
        $result = (string)($context['result'] ?? 'success');
        $destination = (array)($context['destination'] ?? []);
        $voucherUrls = $context['voucher_urls'] ?? '';
        Db::transaction(function () use ($id, $afterCost, $reason, $requestId, $category, $refurbishItems, $refurbishTotal, $result, $destination, $voucherUrls) {
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
            if ($asset->isEmpty() || (string)$asset->status !== ErpDict::ASSET_IN_STOCK || !in_array((string)$asset->refurbish_status, ['pending', 'processing'], true)) {
                throw new CommonException('只有待整备或整备中的在库设备可以登记整备费用');
            }
            $beforeCost = round((float)$asset->total_cost, 2);
            $beforeLocation = [
                'warehouse_id' => (int)$asset->warehouse_id, 'warehouse_name' => (string)$asset->warehouse_name,
                'location_id' => (int)$asset->location_id, 'location_name' => (string)$asset->location_name,
            ];
            // 多项目整备以服务端明细合计为唯一事实；锁内基于最新成本累加，避免并发调整覆盖。
            $delta = $refurbishTotal > 0 ? $refurbishTotal : round($afterCost - $beforeCost, 2);
            if ($delta <= 0.0001) {
                throw new CommonException('整备费用只能增加成本；冲销整备费请走红字调整');
            }
            $completedAt = time();
            $asset->save(array_merge([
                'refurbish_cost' => round((float)$asset->refurbish_cost + $delta, 2),
                'total_cost' => round($beforeCost + $delta, 2),
                'refurbish_status' => $result === 'success' ? 'done' : 'failed',
                'refurbish_result' => $result,
                'refurbish_completed_at' => $completedAt,
                'refurbish_completed_uid' => (int)$this->uid,
                'refurbish_completed_name' => (string)$this->username,
                'refurbish_voucher_urls' => is_array($voucherUrls) ? json_encode($voucherUrls, JSON_UNESCAPED_UNICODE) : trim((string)$voucherUrls),
                'refurbish_remark' => $reason,
                'listing_status' => $result === 'success' ? $this->listingStatusAfterRefurbish($asset, $destination) : 'none',
                'update_at' => $completedAt,
            ], $destination));
            $ledger = new ErpLedgerService();
            $expenseNo = ErpLedgerService::makeNo('RF');
            $financeSourceService = new ErpFinanceSourceService();
            $uniquePartyIds = array_values(array_unique(array_column($refurbishItems, 'party_id')));
            $assetPartyId = count($uniquePartyIds) === 1 ? (int)$uniquePartyIds[0] : 0;
            $assetPartyName = count($uniquePartyIds) === 1 ? (string)$refurbishItems[0]['party_name'] : '多个整备服务商';
            $assetLedgerId = $ledger->asset([
                'asset_id' => $id,
                'request_id' => $requestId !== '' ? $requestId : null,
                'action' => 'refurbish',
                'before_status' => (string)$asset->status,
                'after_status' => 'in_stock/' . ($result === 'success' ? 'done' : 'failed'),
                'before_warehouse_id' => $beforeLocation['warehouse_id'],
                'before_warehouse_name' => $beforeLocation['warehouse_name'],
                'before_location_id' => $beforeLocation['location_id'],
                'before_location_name' => $beforeLocation['location_name'],
                'after_warehouse_id' => (int)($destination['warehouse_id'] ?? $beforeLocation['warehouse_id']),
                'after_warehouse_name' => (string)($destination['warehouse_name'] ?? $beforeLocation['warehouse_name']),
                'after_location_id' => (int)($destination['location_id'] ?? $beforeLocation['location_id']),
                'after_location_name' => (string)($destination['location_name'] ?? $beforeLocation['location_name']),
                'before_total_cost' => $beforeCost,
                'after_total_cost' => round($beforeCost + $delta, 2),
                'cost_delta' => $delta,
                'source_type' => 'refurbish',
                'party_id' => $assetPartyId,
                'party_name' => $assetPartyName,
                'source_id' => $id,
                'source_no' => $expenseNo,
                'remark' => $reason !== '' ? $reason : (string)$category['name'],
                'extra' => ['cost_type' => 'refurbish', 'expense_type_key' => $category['key'], 'expense_type_name' => $category['name'], 'source_plugin' => $category['source_plugin'], 'source_key' => $category['source_key'], 'refurbish_cost' => $delta, 'refurbish_items' => $refurbishItems, 'result' => $result, 'voucher_urls' => $voucherUrls, 'destination' => $destination],
            ]);
            foreach ($refurbishItems as $index => $refurbishItem) {
                $itemName = (string)$refurbishItem['name'];
                $itemAmount = round((float)$refurbishItem['amount'], 2);
                $itemPartyId = (int)$refurbishItem['party_id'];
                $itemPartyName = (string)$refurbishItem['party_name'];
                $itemExpenseNo = ErpLedgerService::makeNo('RF');
                $businessReason = '整备项目【' . $itemName . '】计入设备【' . (string)($asset->model ?: $asset->asset_no)
                    . ' / IMEI ' . (string)($asset->imei ?: '-') . '】成本，应付服务商【' . $itemPartyName . '】。';
                $refurbishSource = $financeSourceService->refurbish($category, [
                    'origin_no' => $itemExpenseNo,
                    'channel_code' => 'refurbish_service',
                    'channel_name' => '整备服务',
                    'business_reason' => $businessReason,
                ]);
                $ledger->account([
                    'biz_type' => 'refurbish',
                    'direction' => 'increase',
                    'amount' => $itemAmount,
                    'party_id' => $itemPartyId,
                    'party_name' => $itemPartyName,
                    'asset_id' => $id,
                    'source_type' => 'refurbish',
                    'source_id' => $assetLedgerId,
                    'source_no' => $itemExpenseNo,
                    'remark' => $itemName . '：' . ($reason !== '' ? $reason : '设备整备成本增加'),
                ]);
                if ((int)$category['creates_finance'] !== 1) continue;
                ErpPayable::create(array_merge([
                    'site_id' => $this->site_id,
                    'payable_no' => ErpLedgerService::makeNo('AP'),
                    'party_id' => $itemPartyId,
                    'party_name' => $itemPartyName,
                    'source_type' => 'refurbish',
                    'source_id' => $assetLedgerId,
                    'source_no' => $itemExpenseNo,
                    'asset_id' => $id,
                    'amount' => $itemAmount,
                    'settled_amount' => 0,
                    'status' => ErpDict::STATUS_PENDING,
                    'occurred_at' => time(),
                    'remark' => $itemName . '：' . (string)($asset->model ?: $asset->asset_no) . ' / IMEI ' . (string)($asset->imei ?: '-'),
                    'create_at' => time(),
                    'update_at' => time(),
                ], $financeSourceService->persistable($refurbishSource)));
            }
        });
        return true;
    }

    private function findAsset(int $id): ErpAsset
    {
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('设备不存在');
        }
        return $asset;
    }

}
