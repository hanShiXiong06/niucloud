<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDownstreamMirrorService;
use addon\hsx_recycle\app\dict\order\RecycleDownstreamDict;

/**
 * 监听 ERP 域事件（ErpDomainEvent），把"已入库 / 转中台待拍照"回流到回收设备。
 *
 * ERP 所有域事件经 Outbox 统一以 event('ErpDomainEvent', $payload) 派发，
 * 这里按 event_name 过滤，只处理与回收设备相关的两类，其余跳过。
 * 监听器绝不抛异常，避免影响 Outbox 发布状态与其它插件的监听器。
 *
 * Class ErpAssetDownstreamListener
 * @package addon\hsx_recycle\app\listener\downstream
 */
class ErpAssetDownstreamListener
{
    public function handle(array $event): array
    {
        try {
            $name = (string)($event['event_name'] ?? '');
            if ($name !== 'erp.asset.stocked.v1' && $name !== 'erp.asset.ready_for_photo.v1') {
                return ['skipped' => true];
            }

            $payload = (array)($event['payload'] ?? []);
            $deviceId = (int)($payload['source_device_id'] ?? 0);
            if ($deviceId <= 0) {
                // 兜底：stocked 事件的 source.id 即回收设备ID（ready_for_photo 的 source 是入库单，不取）
                $src = (array)($event['source'] ?? []);
                if ((string)($src['type'] ?? '') !== 'stock_in') {
                    $deviceId = (int)($src['id'] ?? 0);
                }
            }

            $eventId = (string)($event['event_id'] ?? '');
            $extra = ['erp_asset_id' => (int)($payload['asset_id'] ?? $event['aggregate_id'] ?? 0)];

            $stage = $name === 'erp.asset.stocked.v1'
                ? RecycleDownstreamDict::STAGE_STOCKED
                : RecycleDownstreamDict::STAGE_READY_FOR_PHOTO;

            return (new CoreRecycleDownstreamMirrorService())->applyStage($deviceId, $stage, $extra, $eventId);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
