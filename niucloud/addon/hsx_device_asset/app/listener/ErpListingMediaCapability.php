<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\listener;

use addon\hsx_device_asset\app\service\core\DeviceAssetErpEventService;
use app\model\site\Site;
use app\service\core\site\CoreSiteService;

/** 通过事件向 ERP 暴露拍照中台能力，ERP 无需依赖本插件类。 */
class ErpListingMediaCapability
{
    public function handle(array $request): array
    {
        $siteId = (int)($request['site_id'] ?? 0);
        if (!$this->enabledForSite($siteId)) {
            return ['provider' => 'device_asset', 'name' => '标准化拍照中台', 'available' => 0, 'reason' => 'addon_not_enabled_for_site'];
        }
        if ((string)($request['action'] ?? 'describe') === 'describe') {
            return [
                'provider' => 'device_asset',
                'name' => '标准化拍照中台',
                'available' => 1,
                'features' => ['guided_photo', 'industrial_camera', 'image_review', 'sale_price'],
            ];
        }

        $asset = (array)($request['asset'] ?? []);
        $assetId = (int)($request['asset_id'] ?? $asset['id'] ?? 0);
        $event = [
            'event_name' => 'erp.asset.ready_for_photo.v1',
            'event_id' => 'erp-listing-media-' . $siteId . '-' . $assetId,
            'site_id' => $siteId,
            'aggregate_id' => $assetId,
            'operator' => (array)($request['operator'] ?? []),
            'payload' => array_merge($asset, ['asset_id' => $assetId]),
        ];
        $result = (new DeviceAssetErpEventService())->createFromReadyForPhotoEvent($event);
        $middleAssetId = (int)($result['asset_id'] ?? 0);
        if ($middleAssetId <= 0) {
            return ['provider' => 'device_asset', 'available' => 1, 'prepared' => false, 'error' => true, 'message' => '拍照任务创建失败'];
        }
        return [
            'provider' => 'device_asset',
            'available' => 1,
            'prepared' => true,
            'middle_asset_id' => $middleAssetId,
            'mobile_path' => '/addon/hsx_device_asset/pages/photo/capture?id=' . $middleAssetId,
            'message' => !empty($result['created']) ? '标准化拍照任务已创建' : '已找到该设备的拍照任务',
        ];
    }

    private function enabledForSite(int $siteId): bool
    {
        if ($siteId <= 0) return false;
        $addons = (array)(new CoreSiteService())->getAddonKeysBySiteId($siteId);
        $site = (new Site())->where('site_id', $siteId)->findOrEmpty();
        $apps = !$site->isEmpty() && is_array($site->app) ? $site->app : [];
        return in_array('hsx_device_asset', array_unique(array_merge($addons, $apps)), true);
    }
}
