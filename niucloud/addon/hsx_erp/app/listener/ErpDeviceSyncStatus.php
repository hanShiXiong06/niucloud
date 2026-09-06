<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\service\admin\ErpRecycleDeviceIdentityService;
use core\exception\CommonException;

/** 回收设备与 ERP 资产的状态回查，关联键存放在资产 spec_json.source_device_id。 */
class ErpDeviceSyncStatus
{
    public function handle(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $deviceIds = (array)($event['device_ids'] ?? $event['source_device_ids'] ?? []);
        if (isset($event['source_device_id'])) $deviceIds[] = $event['source_device_id'];
        if ($siteId <= 0 || empty($deviceIds)) return [];

        $identities = $this->identityService()->deviceAssets($siteId, $deviceIds);
        $deviceIds = array_keys($identities);
        $map = [];
        foreach ($identities as $sourceDeviceId => $identity) {
            if (!$identity['has_asset']) continue;
            if ($identity['ambiguous']) {
                $map[$sourceDeviceId] = ['has_asset' => true, 'ambiguous' => true,
                    'asset_id' => 0, 'asset_ids' => $identity['asset_ids'], 'inventory_status' => '',
                    'reason' => '来源设备关联待核对，存在多个或冲突的ERP资产关联'];
                continue;
            }
            $row = $identity['assets'][0];
            $map[$sourceDeviceId] = [
                'has_asset' => true,
                'ambiguous' => false,
                'asset_ids' => $identity['asset_ids'],
                'source_device_id' => $sourceDeviceId,
                'asset_id' => (int)$row['id'],
                'asset_no' => (string)$row['asset_no'],
                'inventory_status' => (string)$row['status'],
                'warehouse_id' => (int)$row['warehouse_id'],
                'warehouse_name' => (string)$row['warehouse_name'],
                'location_id' => (int)$row['location_id'],
                'location_name' => (string)$row['location_name'],
                'update_at' => (int)$row['update_at'],
            ];
        }
        if (isset($event['source_device_id'])) {
            $item = $map[(int)$event['source_device_id']] ?? null;
            $refreshed = false;
            if (!empty($item['ambiguous'])) {
                throw new CommonException('来源设备关联待核对，未执行重新同步或快照更新');
            }
            if ($item !== null && !empty($event['device']) && is_array($event['device'])) {
                $refreshed = $this->refreshDeviceSnapshot($siteId, $item, $event['device']);
            }
            return [
                'has_asset' => $item !== null,
                'asset_id' => (int)($item['asset_id'] ?? 0),
                'snapshot_refreshed' => $refreshed,
                'flushed' => 0,
                'still_failed' => 0,
            ];
        }
        if (isset($event['source_device_ids'])) {
            $health = [];
            foreach ($deviceIds as $deviceId) {
                $item = $map[$deviceId] ?? null;
                $health[$deviceId] = [
                    'stuck' => $item === null || !empty($item['ambiguous']),
                    'has_asset' => $item !== null,
                    'ambiguous' => !empty($item['ambiguous']),
                    'asset_ids' => $item['asset_ids'] ?? [],
                    'pending' => 0,
                    'failed' => 0,
                    'reason' => $item === null ? 'ERP 尚未建立对应资产' : (string)($item['reason'] ?? ''),
                ];
            }
            return $health;
        }
        return $map;
    }

    protected function identityService(): ErpRecycleDeviceIdentityService
    {
        return new ErpRecycleDeviceIdentityService();
    }

    /** 仅刷新来源描述，不触碰成本、应付、库存状态和仓库位置。 */
    private function refreshDeviceSnapshot(int $siteId, array $item, array $device): bool
    {
        $assetId = (int)($item['asset_id'] ?? 0);
        if ($assetId <= 0) return false;

        $asset = ErpAsset::where([
            ['site_id', '=', $siteId],
            ['id', '=', $assetId],
            ['source_plugin', '=', 'hsx_recycle'],
        ])->findOrEmpty();
        if ($asset->isEmpty()) return false;

        $capacity = trim((string)($device['capacity'] ?? ''));
        $color = trim((string)($device['color'] ?? ''));
        $model = trim((string)($device['model'] ?? ''));
        $spec = implode(' ', array_values(array_filter([$capacity, $color])));
        $specJson = json_decode((string)($asset->spec_json ?? ''), true);
        if (!is_array($specJson)) $specJson = [];
        // 关联可能刚由采购明细快照补证；刷新规格时必须保留这份唯一身份，不能反向抹掉明细关联。
        $sourceDeviceId = (int)($item['source_device_id'] ?? 0);
        if ($sourceDeviceId <= 0) throw new CommonException('来源设备关联待核对，未更新设备快照');
        $specJson['source_plugin'] = 'hsx_recycle';
        $specJson['source_device_id'] = $sourceDeviceId;
        $specJson['capacity'] = $capacity;
        $specJson['capacity_value'] = $device['capacity_value'] ?? $capacity;
        $specJson['color'] = $color;
        $specJson['color_value'] = $device['color_value'] ?? $color;
        if (array_key_exists('payment_methods', $device)) {
            $specJson['payee_methods'] = array_values(array_filter((array)$device['payment_methods'], 'is_array'));
        }

        $updates = [
            'spec' => $spec,
            'spec_json' => json_encode($specJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            'color' => $color,
            'update_at' => time(),
        ];
        if ($model !== '') $updates['model'] = $model;
        $asset->save($updates);

        ErpPurchaseItem::where([
            ['site_id', '=', $siteId],
            ['asset_id', '=', $assetId],
        ])->update($updates);
        return true;
    }

    public function __invoke(array $event): array
    {
        return $this->handle($event);
    }
}
