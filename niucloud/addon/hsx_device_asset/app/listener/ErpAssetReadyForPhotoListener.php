<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\listener;

use addon\hsx_device_asset\app\service\core\DeviceAssetErpEventService;
use app\service\core\site\CoreSiteService;
use app\model\site\Site;

class ErpAssetReadyForPhotoListener
{
    public function handle(array $event): array
    {
        $consumer = 'hsx_device_asset.ready_for_photo';
        if ((string)($event['event_name'] ?? '') !== 'erp.asset.ready_for_photo.v1') {
            return ['consumer' => $consumer, 'status' => 'ignored', 'skipped' => true];
        }
        $siteId = (int)($event['site_id'] ?? 0);
        $addons = $siteId > 0 ? (array)(new CoreSiteService())->getAddonKeysBySiteId($siteId) : [];
        $site = $siteId > 0 ? (new Site())->where('site_id', $siteId)->findOrEmpty() : null;
        $siteApp = $site && !$site->isEmpty() && is_array($site->app) ? $site->app : [];
        $addons = array_unique(array_merge($addons, $siteApp));
        if (!in_array('hsx_device_asset', (array)$addons, true)) {
            return ['consumer' => $consumer, 'status' => 'ignored', 'skipped' => true, 'reason' => 'addon_not_enabled_for_site'];
        }
        try {
            $result = (new DeviceAssetErpEventService())->createFromReadyForPhotoEvent($event);
            if (!empty($result['skipped'])) {
                return array_merge(['consumer' => $consumer, 'status' => 'rejected'], $result);
            }
            return array_merge([
                'consumer' => $consumer,
                'status' => !empty($result['created']) ? 'processed' : 'duplicate',
            ], $result);
        } catch (\Throwable $e) {
            return ['consumer' => $consumer, 'status' => 'failed', 'error' => true, 'message' => $e->getMessage()];
        }
    }
}
