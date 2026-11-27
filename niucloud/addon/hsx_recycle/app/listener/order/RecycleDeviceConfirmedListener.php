<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\order;

use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
use think\facade\Log;

/**
 * 用户确认报价后的下游适配器。
 *
 * Core 只发布回收域事实；这里复用现有统一编排入口，把 ERP 入库、财务应付
 * 和待办分配接到事件上。后续新增商城、绩效等消费者无需修改 API 或 Core。
 */
class RecycleDeviceConfirmedListener
{
    public function handle(array $event): array
    {
        if ((string)($event['event_name'] ?? '') !== 'recycle.device.confirmed.v1') {
            return ['skipped' => true, 'reason' => 'unsupported_event'];
        }
        if (empty($event['accepted'])) {
            return ['skipped' => true, 'reason' => 'not_accepted'];
        }

        $siteId = (int)($event['site_id'] ?? 0);
        $currentSiteId = (int)request()->siteId();
        if ($siteId <= 0 || $currentSiteId !== $siteId) {
            return ['error' => true, 'message' => '确认事件站点与当前请求站点不一致'];
        }
        $deviceIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)($event['device_ids'] ?? [])
        ))));
        if ($deviceIds === []) {
            return ['error' => true, 'message' => '确认事件缺少设备'];
        }

        try {
            (new RecycleDeviceService())->dispatchAfterRecycle($deviceIds);
            return ['success' => true, 'device_ids' => $deviceIds];
        } catch (\Throwable $e) {
            Log::error('用户确认报价后的下游编排失败', [
                'site_id' => $siteId,
                'device_ids' => $deviceIds,
                'message' => $e->getMessage(),
            ]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
