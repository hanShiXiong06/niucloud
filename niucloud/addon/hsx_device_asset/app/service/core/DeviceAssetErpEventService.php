<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\service\core;

use addon\hsx_device_asset\app\dict\DeviceAssetDict;
use addon\hsx_device_asset\app\model\DeviceAssetItem;
use addon\hsx_device_asset\app\model\DeviceAssetOperationLog;
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

        $snapshot = $this->eventSnapshot($siteId, $erpAssetId, $payload);
        if ($snapshot === []) {
            return ['skipped' => true, 'reason' => 'asset_snapshot_missing'];
        }
        $sourceDeviceId = $this->resolveSourceDeviceId($snapshot);

        $now = time();
        $existing = $this->findExistingAsset($siteId, $sourceDeviceId, $snapshot);
        if (!$existing->isEmpty()) {
            // 幂等：回收设备可能早于 ERP 已进入中台。重新同步时除了库位，还必须补绑
            // ERP 资产 ID；否则后续定价完成事件无法把结果交给商城。
            $this->backfillErpLink($existing, $snapshot, $event, $now);
            return ['created' => false, 'asset_id' => (int)$existing->id];
        }

        Db::startTrans();
        try {
            $asset = DeviceAssetItem::create($this->buildAssetData($siteId, $snapshot, $event, $now));
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

    private function buildAssetData(int $siteId, array $snapshot, array $event, int $now): array
    {
        $loc = $this->resolveLocation($snapshot);
        $sourceDeviceId = $this->resolveSourceDeviceId($snapshot);

        return [
            'site_id' => $siteId,
            'asset_no' => '',
            'device_id' => $sourceDeviceId,
            'recycle_order_id' => 0,
            'imei' => (string)($snapshot['imei'] ?? ''),
            'imei2' => '',
            'sn' => (string)($snapshot['sn'] ?? ''),
            'model' => (string)($snapshot['model'] ?? ''),
            'category_id' => (int)($snapshot['category_id'] ?? 0),
            'warehouse_id' => $loc['warehouse_id'],
            'warehouse_name' => $loc['warehouse_name'],
            'location_id' => $loc['location_id'],
            'location_name' => $loc['location_name'],
            'source_status' => 0,
            'recycle_final_price' => round((float)($snapshot['purchase_cost'] ?? $snapshot['current_cost'] ?? 0), 2),
            'check_summary' => $this->buildCheckSummary($snapshot),
            'status' => DeviceAssetDict::STATUS_WAIT_PHOTO,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_WAIT,
            'price_status' => DeviceAssetDict::PRICE_STATUS_WAIT,
            'export_status' => DeviceAssetDict::EXPORT_STATUS_PENDING,
            'imported_by' => (int)($event['operator']['id'] ?? 0),
            'imported_at' => $now,
            'ext_json' => [
                'erp_asset_id' => (int)$snapshot['asset_id'],
                'erp_source_uid' => $this->erpSourceUid($siteId, $snapshot),
                'asset_no' => (string)($snapshot['asset_no'] ?? ''),
                'capacity' => (string)($snapshot['spec'] ?? ''),
                'color' => (string)($snapshot['color'] ?? ''),
                'current_cost' => round((float)($snapshot['current_cost'] ?? 0), 2),
                'sale_destination' => (string)($snapshot['sale_target'] ?? ''),
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
    private function resolveLocation(array $snapshot): array
    {
        return [
            'warehouse_id' => (int)($snapshot['warehouse_id'] ?? 0),
            'warehouse_name' => (string)($snapshot['warehouse_name'] ?? ''),
            'location_id' => (int)($snapshot['location_id'] ?? 0),
            'location_name' => (string)($snapshot['location_name'] ?? ''),
        ];
    }

    /**
     * 已存在的中台资产若库位回显为空，则回填（供「重新同步」修复历史空白）。
     */
    private function backfillErpLink(DeviceAssetItem $asset, array $snapshot, array $event, int $now): void
    {
        $needWarehouse = (int)$asset->warehouse_id <= 0 || (string)$asset->warehouse_name === '';
        $needLocation = (int)$asset->location_id <= 0 || (string)$asset->location_name === '';
        $loc = $this->resolveLocation($snapshot);
        $update = ['update_at' => $now];
        if ($needWarehouse && ($loc['warehouse_id'] > 0 || $loc['warehouse_name'] !== '')) {
            $update['warehouse_id'] = $loc['warehouse_id'];
            $update['warehouse_name'] = $loc['warehouse_name'];
        }
        if ($needLocation && ($loc['location_id'] > 0 || $loc['location_name'] !== '')) {
            $update['location_id'] = $loc['location_id'];
            $update['location_name'] = $loc['location_name'];
        }
        $ext = is_array($asset->ext_json) ? $asset->ext_json : [];
        $linkedExt = array_merge($ext, [
            'erp_asset_id' => (int)$snapshot['asset_id'],
            'erp_source_uid' => $this->erpSourceUid((int)($snapshot['site_id'] ?? $asset->site_id), $snapshot),
            'asset_no' => (string)($snapshot['asset_no'] ?? ''),
            'capacity' => (string)($snapshot['spec'] ?? ''),
            'color' => (string)($snapshot['color'] ?? ''),
            'current_cost' => round((float)($snapshot['current_cost'] ?? 0), 2),
            'sale_destination' => (string)($snapshot['sale_target'] ?? ''),
            'source_event_id' => (string)($event['event_id'] ?? ''),
        ]);
        if ($linkedExt !== $ext) {
            $update['ext_json'] = $linkedExt;
        }
        if (count($update) > 1) {
            $asset->save($update);
        }
    }

    private function buildCheckSummary(array $snapshot): array
    {
        return array_filter([
            '规格' => (string)($snapshot['spec'] ?? ''),
            '颜色' => (string)($snapshot['color'] ?? ''),
            '电池' => (int)($snapshot['battery'] ?? 0) > 0 ? (int)$snapshot['battery'] . '%' : '',
            '质检备注' => (string)($snapshot['quality_remark'] ?? ''),
        ], fn ($value) => $value !== '');
    }

    private function resolveSourceDeviceId(array $snapshot): int
    {
        if ((string)($snapshot['source_plugin'] ?? '') === 'hsx_recycle' && is_numeric((string)($snapshot['source_id'] ?? ''))) {
            $id = (int)$snapshot['source_id'];
            if ($id > 0) return $id;
        }
        // ERP 重装后自增 asset_id 可能从 1 重新开始，不能再用 -asset_id 作为跨插件唯一键。
        // 资产号是业务快照中的稳定标识，用其生成负数占位并在 ext_json 再保存完整 UID。
        $siteId = (int)($snapshot['site_id'] ?? 0);
        $uid = $this->erpSourceUid($siteId, $snapshot);
        for ($salt = 0; $salt < 20; $salt++) {
            $hash = (int)sprintf('%u', crc32($uid . ($salt > 0 ? ':' . $salt : '')));
            $candidate = -(1 + ($hash % 2147483000));
            $row = DeviceAssetItem::where([['site_id', '=', $siteId], ['device_id', '=', $candidate]])->findOrEmpty();
            if ($row->isEmpty()) return $candidate;
            $ext = is_array($row->ext_json) ? $row->ext_json : [];
            if ((string)($ext['erp_source_uid'] ?? '') === $uid
                || ((string)($ext['erp_source_uid'] ?? '') === '' && (string)($ext['asset_no'] ?? '') === (string)($snapshot['asset_no'] ?? ''))
            ) return $candidate;
        }
        throw new \RuntimeException('ERP资产来源键发生多次冲突，请联系管理员处理历史数据');
    }

    /**
     * 新事件直接使用 ERP 写入的不可变快照；仅为兼容历史精简事件，才回退读取 ERP 当前表。
     * 这样事件失败后重放不会因资产后来被修改而改变结果。
     */
    private function eventSnapshot(int $siteId, int $erpAssetId, array $payload): array
    {
        $snapshot = $payload;
        $snapshot['site_id'] = $siteId;
        $snapshot['asset_id'] = $erpAssetId;
        $requiredPresent = (string)($snapshot['model'] ?? '') !== ''
            || (string)($snapshot['imei'] ?? '') !== ''
            || (string)($snapshot['asset_no'] ?? '') !== '';
        if ($requiredPresent) return $snapshot;

        $erpAssetClass = '\\addon\\hsx_erp\\app\\model\\ErpAsset';
        if (!class_exists($erpAssetClass)) return [];
        $record = $erpAssetClass::where([
            ['site_id', '=', $siteId],
            ['id', '=', $erpAssetId],
        ])->findOrEmpty();
        if ($record->isEmpty()) return [];
        return array_merge($record->toArray(), $snapshot, ['asset_id' => $erpAssetId]);
    }

    /** 优先按完整来源UID匹配；旧 device_id 恰好复用但资产号不同的记录绝不能被重新绑定。 */
    private function findExistingAsset(int $siteId, int $sourceDeviceId, array $snapshot): DeviceAssetItem
    {
        $expectedUid = $this->erpSourceUid($siteId, $snapshot);
        $byDeviceId = DeviceAssetItem::where([['site_id', '=', $siteId], ['device_id', '=', $sourceDeviceId]])->findOrEmpty();
        if (!$byDeviceId->isEmpty()) {
            if ((string)($snapshot['source_plugin'] ?? '') === 'hsx_recycle') return $byDeviceId;
            $ext = is_array($byDeviceId->ext_json) ? $byDeviceId->ext_json : [];
            $storedUid = (string)($ext['erp_source_uid'] ?? '');
            $storedAssetNo = (string)($ext['asset_no'] ?? '');
            if (($storedUid !== '' && $storedUid === $expectedUid)
                || ($storedUid === '' && $storedAssetNo !== '' && $storedAssetNo === (string)($snapshot['asset_no'] ?? ''))
            ) return $byDeviceId;
        }

        // 兼容旧版 -asset_id 数据：按 ext_json 中的资产号/UID 找回原记录，避免升级后重复建档。
        $candidates = DeviceAssetItem::where([['site_id', '=', $siteId], ['device_id', '<', 0]])->select();
        foreach ($candidates as $candidate) {
            $ext = is_array($candidate->ext_json) ? $candidate->ext_json : [];
            if ((string)($ext['erp_source_uid'] ?? '') === $expectedUid
                || ((string)($ext['erp_source_uid'] ?? '') === '' && (string)($ext['asset_no'] ?? '') === (string)($snapshot['asset_no'] ?? ''))
            ) return $candidate;
        }
        return DeviceAssetItem::where([['site_id', '=', $siteId], ['id', '=', 0]])->findOrEmpty();
    }

    private function erpSourceUid(int $siteId, array $snapshot): string
    {
        $assetNo = trim((string)($snapshot['asset_no'] ?? ''));
        if ($assetNo === '') $assetNo = 'legacy-id-' . (int)($snapshot['asset_id'] ?? 0);
        return 'hsx_erp:' . $siteId . ':' . $assetNo;
    }

    private function makeAssetNo(int $siteId, int $id): string
    {
        return 'DA' . date('Ymd') . str_pad((string)$siteId, 3, '0', STR_PAD_LEFT) . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }
}
