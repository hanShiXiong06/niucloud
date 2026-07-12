<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpAsset;

/** 回收设备与 ERP 资产的状态回查，关联键存放在资产 spec_json.source_device_id。 */
class ErpDeviceSyncStatus
{
    public function handle(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $deviceIds = $event['device_ids'] ?? $event['source_device_ids'] ?? [];
        if (isset($event['source_device_id'])) $deviceIds[] = (int)$event['source_device_id'];
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', (array)$deviceIds))));
        if ($siteId <= 0 || empty($deviceIds)) return [];

        $wanted = array_fill_keys($deviceIds, true);
        $rows = ErpAsset::where([
            ['site_id', '=', $siteId],
            ['source_plugin', '=', 'hsx_recycle'],
        ])->field('id,asset_no,status,warehouse_id,warehouse_name,location_id,location_name,spec_json,update_at')
            ->order('id desc')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $snapshot = json_decode((string)($row['spec_json'] ?? ''), true);
            $sourceDeviceId = (int)($snapshot['source_device_id'] ?? 0);
            if ($sourceDeviceId <= 0 || !isset($wanted[$sourceDeviceId]) || isset($map[$sourceDeviceId])) continue;
            $map[$sourceDeviceId] = [
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
            return [
                'has_asset' => $item !== null,
                'asset_id' => (int)($item['asset_id'] ?? 0),
                'flushed' => 0,
                'still_failed' => 0,
            ];
        }
        if (isset($event['source_device_ids'])) {
            $health = [];
            foreach ($deviceIds as $deviceId) {
                $item = $map[$deviceId] ?? null;
                $health[$deviceId] = [
                    'stuck' => $item === null,
                    'has_asset' => $item !== null,
                    'pending' => 0,
                    'failed' => 0,
                    'reason' => $item === null ? 'ERP 尚未建立对应资产' : '',
                ];
            }
            return $health;
        }
        return $map;
    }

    public function __invoke(array $event): array
    {
        return $this->handle($event);
    }
}
