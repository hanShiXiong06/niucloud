<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDownstreamMirrorService;
use addon\hsx_recycle\app\dict\order\RecycleDownstreamDict;

/**
 * 监听数据中台定价完成事件（DeviceAssetPriceCompleted），把"已定价·可售"回流到回收设备。
 *
 * 该事件由中台直接 event('DeviceAssetPriceCompleted', $payload) 派发，
 * payload.device_id 即回收设备ID（中台资产以 source_device_id 落库）。
 * 该事件本身无 event_id，这里以设备+资产合成稳定幂等键。
 *
 * Class DeviceAssetPricedListener
 * @package addon\hsx_recycle\app\listener\downstream
 */
class DeviceAssetPricedListener
{
    public function handle(array $event): array
    {
        try {
            if ((string)($event['event_name'] ?? '') !== 'device_asset.price.completed.v1') {
                return ['skipped' => true];
            }

            $deviceId = (int)($event['device_id'] ?? 0);
            $payload = (array)($event['payload'] ?? []);
            $erpAssetId = (int)($payload['erp_asset_id'] ?? 0);
            $eventId = 'da-priced-' . $deviceId . '-' . $erpAssetId;
            $extra = [
                'erp_asset_id' => $erpAssetId,
                'sale_price' => (float)($payload['sale_price'] ?? 0),
            ];

            return (new CoreRecycleDownstreamMirrorService())
                ->applyStage($deviceId, RecycleDownstreamDict::STAGE_PRICED, $extra, $eventId);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
