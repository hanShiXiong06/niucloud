<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;

class DeviceSyncStatusListener
{
    public function handle(array $params): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        $deviceIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)($params['device_ids'] ?? [])
        ))));
        if ($siteId <= 0 || empty($deviceIds)) {
            return [];
        }

        $cycles = ErpAssetCycle::where([
            ['site_id', '=', $siteId],
            ['source_plugin', '=', 'hsx_recycle'],
            ['source_type', '=', 'recycle_device'],
        ])->whereIn('source_device_id', $deviceIds)
            ->field('id,source_device_id')
            ->order('id desc')
            ->select()
            ->toArray();
        if (empty($cycles)) {
            return [];
        }

        $cycleDeviceMap = [];
        foreach ($cycles as $cycle) {
            $cycleDeviceMap[(int)$cycle['id']] = (int)$cycle['source_device_id'];
        }

        $assets = ErpAsset::where([['site_id', '=', $siteId]])
            ->whereIn('cycle_id', array_keys($cycleDeviceMap))
            ->field('id,asset_no,cycle_id,source_device_id,inventory_status,create_at,stock_in_at')
            ->order('id desc')
            ->select()
            ->toArray();

        $statusMap = [];
        foreach ($assets as $asset) {
            $deviceId = $cycleDeviceMap[(int)$asset['cycle_id']]
                ?? (int)$asset['source_device_id'];
            if (!isset($statusMap[$deviceId])) {
                $statusMap[$deviceId] = $asset;
            }
        }

        return $statusMap;
    }
}
