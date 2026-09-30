<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 监听中台定价完成事件，落"待上架货源"
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\listener\intake;

use addon\phone_shop\app\service\core\intake\CoreDeviceIntakeService;
use app\service\core\site\CoreSiteService;
use app\model\site\Site;
use think\facade\Log;

/**
 * 监听数据中台定价完成事件 DeviceAssetPriceCompleted（device_asset.price.completed.v1）。
 * 与 hsx_recycle 共享该事件：回收侧回写设备状态，商城侧落货源待上架。
 * 故障隔离：失败只记日志，绝不打断中台定价主流程。
 */
class DeviceAssetPricedListener
{
    public function handle(array $event): array
    {
        $consumer = 'phone_shop.device_asset_priced';
        try {
            if ((string) ($event['event_name'] ?? '') !== 'device_asset.price.completed.v1') {
                return [ 'consumer' => $consumer, 'status' => 'ignored', 'skipped' => true ];
            }
            $siteId = (int)($event['site_id'] ?? 0);
            $addons = $siteId > 0 ? (array)(new CoreSiteService())->getAddonKeysBySiteId($siteId) : [];
            $site = $siteId > 0 ? (new Site())->where('site_id', $siteId)->findOrEmpty() : null;
            $siteApp = $site && !$site->isEmpty() && is_array($site->app) ? $site->app : [];
            $addons = array_unique(array_merge($addons, $siteApp));
            if (!in_array('phone_shop', (array)$addons, true)) {
                return [ 'consumer' => $consumer, 'status' => 'ignored', 'skipped' => true, 'reason' => 'addon_not_enabled_for_site' ];
            }
            // 带 ERP 资产 ID 的设备必须先回写 ERP，由 ERP 的渠道策略决定直发还是
            // 交给商城运营。商城不能再旁路创建货源，否则同一设备会出现两条上架链路。
            if ((int)($event['payload']['erp_asset_id'] ?? 0) > 0 && in_array('hsx_erp', (array)$addons, true)) {
                return [ 'consumer' => $consumer, 'status' => 'ignored', 'skipped' => true, 'reason' => 'erp_owned_workflow' ];
            }
            $result = (new CoreDeviceIntakeService())->saveFromEvent($event);
            if (!empty($result['skipped'])) return array_merge([ 'consumer' => $consumer, 'status' => 'rejected' ], $result);
            return array_merge([ 'consumer' => $consumer, 'status' => !empty($result['created']) ? 'processed' : 'duplicate' ], $result);
        } catch (\Throwable $e) {
            Log::write('[phone_shop] 货源入库失败: ' . $e->getMessage());
            return [ 'consumer' => $consumer, 'status' => 'failed', 'error' => true, 'message' => $e->getMessage() ];
        }
    }
}
