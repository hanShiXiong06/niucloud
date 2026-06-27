<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\service\core;

use addon\hsx_device_asset\app\dict\DeviceAssetDict;
use addon\hsx_device_asset\app\model\DeviceAssetItem;
use addon\hsx_device_asset\app\model\DeviceAssetOperationLog;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use think\facade\Db;

class DeviceAssetErpEventService
{
    public function createFromReadyForPhotoEvent(array $event): array
    {
        $payload = (array)($event['payload'] ?? []);
        $siteId = (int)($event['site_id'] ?? 0);
        $erpAssetId = (int)($payload['asset_id'] ?? $event['aggregate_id'] ?? 0);
        if ($siteId <= 0 || $erpAssetId <= 0) {
            return ['skipped' => true, 'reason' => 'invalid_event'];
        }

        $erpAsset = ErpAsset::where([
            ['site_id', '=', $siteId],
            ['id', '=', $erpAssetId],
        ])->findOrEmpty();
        if ($erpAsset->isEmpty()) {
            return ['skipped' => true, 'reason' => 'erp_asset_not_found'];
        }

        $sourceDeviceId = (int)$erpAsset->source_device_id;
        if ($sourceDeviceId <= 0) {
            return ['skipped' => true, 'reason' => 'missing_source_device_id'];
        }

        $now = time();
        $existing = DeviceAssetItem::where([
            ['site_id', '=', $siteId],
            ['device_id', '=', $sourceDeviceId],
        ])->findOrEmpty();
        if (!$existing->isEmpty()) {
            // 幂等：已存在则不重复创建，但若库位回显为空则顺手回填（重新同步可修复历史空白数据）
            $this->backfillLocation($existing, $siteId, $erpAsset, $now);
            return ['created' => false, 'asset_id' => (int)$existing->id];
        }

        Db::startTrans();
        try {
            $asset = DeviceAssetItem::create($this->buildAssetData($siteId, $erpAsset, $event, $now));
            $asset->save(['asset_no' => $this->makeAssetNo($siteId, (int)$asset->id)]);
            DeviceAssetOperationLog::create([
                'site_id' => $siteId,
                'asset_id' => (int)$asset->id,
                'device_id' => $sourceDeviceId,
                'action' => DeviceAssetDict::ACTION_IMPORT,
                'action_name' => DeviceAssetDict::actionName(DeviceAssetDict::ACTION_IMPORT),
                'operator_uid' => (int)($event['operator']['id'] ?? 0),
                'operator_name' => (string)($event['operator']['name'] ?? ''),
                'payload' => [
                    'source' => 'erp.asset.ready_for_photo.v1',
                    'erp_asset_id' => $erpAssetId,
                    'event_id' => (string)($event['event_id'] ?? ''),
                ],
                'ip' => '',
                'create_at' => $now,
            ]);
            Db::commit();
            return ['created' => true, 'asset_id' => (int)$asset->id];
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    private function buildAssetData(int $siteId, ErpAsset $erpAsset, array $event, int $now): array
    {
        $source = (array)$erpAsset->source_snapshot;
        $check = (array)$erpAsset->check_snapshot;
        $loc = $this->resolveLocation($siteId, $erpAsset);

        return [
            'site_id' => $siteId,
            'asset_no' => '',
            'device_id' => (int)$erpAsset->source_device_id,
            'recycle_order_id' => (int)($source['source_id'] ?? 0),
            'imei' => (string)$erpAsset->imei,
            'imei2' => (string)$erpAsset->imei2,
            'sn' => (string)$erpAsset->sn,
            'model' => (string)$erpAsset->model,
            'category_id' => (int)$erpAsset->category_id,
            'warehouse_id' => $loc['warehouse_id'],
            'warehouse_name' => $loc['warehouse_name'],
            'location_id' => $loc['location_id'],
            'location_name' => $loc['location_name'],
            'source_status' => 0,
            'recycle_final_price' => round((float)$erpAsset->purchase_cost, 2),
            'check_summary' => $this->buildCheckSummary($erpAsset, $check),
            'status' => DeviceAssetDict::STATUS_WAIT_PHOTO,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_WAIT,
            'price_status' => DeviceAssetDict::PRICE_STATUS_WAIT,
            'export_status' => DeviceAssetDict::EXPORT_STATUS_PENDING,
            'imported_by' => (int)($event['operator']['id'] ?? 0),
            'imported_at' => $now,
            'ext_json' => [
                'erp_asset_id' => (int)$erpAsset->id,
                'erp_cycle_id' => (int)$erpAsset->cycle_id,
                'asset_no' => (string)$erpAsset->asset_no,
                'capacity' => (string)$erpAsset->capacity,
                'color' => (string)$erpAsset->color,
                'current_cost' => round((float)$erpAsset->current_cost, 2),
                'sale_destination' => (string)($source['sale_destination'] ?? ''),
                'source_event_id' => (string)($event['event_id'] ?? ''),
            ],
            'create_at' => $now,
            'update_at' => $now,
        ];
    }

    /**
     * 解析资产的库位归属：以 ERP 资产已确认入库的 warehouse_id/location_id 为权威，
     * 名称从 ERP 仓库/库位表查；查不到时回退用回收带过来的目标仓位名/ID，避免中台库位回显空白。
     */
    private function resolveLocation(int $siteId, ErpAsset $erpAsset): array
    {
        $source = (array)$erpAsset->source_snapshot;
        $warehouseId = (int)$erpAsset->warehouse_id;
        $locationId = (int)$erpAsset->location_id;
        $warehouseName = '';
        $locationName = '';
        if ($warehouseId > 0) {
            $warehouseName = (string)ErpWarehouse::where([
                ['site_id', '=', $siteId], ['id', '=', $warehouseId],
            ])->value('warehouse_name');
        }
        if ($locationId > 0) {
            $locationName = (string)ErpWarehouseLocation::where([
                ['site_id', '=', $siteId], ['id', '=', $locationId],
            ])->value('location_name');
        }
        if ($warehouseName === '') {
            $warehouseName = (string)($source['target_warehouse_name'] ?? '');
        }
        if ($locationName === '') {
            $locationName = (string)($source['target_location_name'] ?? '');
        }
        if ($warehouseId <= 0) {
            $warehouseId = (int)($source['target_warehouse_id'] ?? 0);
        }
        if ($locationId <= 0) {
            $locationId = (int)($source['target_location_id'] ?? 0);
        }
        return [
            'warehouse_id' => $warehouseId,
            'warehouse_name' => $warehouseName,
            'location_id' => $locationId,
            'location_name' => $locationName,
        ];
    }

    /**
     * 已存在的中台资产若库位回显为空，则回填（供「重新同步」修复历史空白）。
     */
    private function backfillLocation(DeviceAssetItem $asset, int $siteId, ErpAsset $erpAsset, int $now): void
    {
        $needWarehouse = (int)$asset->warehouse_id <= 0 || (string)$asset->warehouse_name === '';
        $needLocation = (int)$asset->location_id <= 0 || (string)$asset->location_name === '';
        if (!$needWarehouse && !$needLocation) {
            return;
        }
        $loc = $this->resolveLocation($siteId, $erpAsset);
        $update = ['update_at' => $now];
        if ($needWarehouse && ($loc['warehouse_id'] > 0 || $loc['warehouse_name'] !== '')) {
            $update['warehouse_id'] = $loc['warehouse_id'];
            $update['warehouse_name'] = $loc['warehouse_name'];
        }
        if ($needLocation && ($loc['location_id'] > 0 || $loc['location_name'] !== '')) {
            $update['location_id'] = $loc['location_id'];
            $update['location_name'] = $loc['location_name'];
        }
        if (count($update) > 1) {
            $asset->save($update);
        }
    }

    private function buildCheckSummary(ErpAsset $erpAsset, array $check): array
    {
        return array_filter([
            '卖家质检' => (string)($check['check_result_seller'] ?? ''),
            '买家质检' => (string)($check['check_result_buyer'] ?? ''),
            '容量' => (string)$erpAsset->capacity,
            '颜色' => (string)$erpAsset->color,
        ], fn ($value) => $value !== '');
    }

    private function makeAssetNo(int $siteId, int $id): string
    {
        return 'DA' . date('Ymd') . str_pad((string)$siteId, 3, '0', STR_PAD_LEFT) . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }
}
