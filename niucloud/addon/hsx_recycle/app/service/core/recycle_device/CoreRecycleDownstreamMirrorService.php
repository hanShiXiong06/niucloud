<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_device;

use addon\hsx_recycle\app\model\order\RecycleDevice;
use think\facade\Log;

/**
 * 下游流转回流镜像服务
 *
 * 由回收侧的下游事件监听器调用，把 ERP/数据中台回流的生命周期阶段
 * 写到 recycle_device 的 downstream_* 镜像字段上。
 *
 * 设计红线：
 * - 只写回收自己的表，绝不读写下游插件的表（插件独立）。
 * - 只前进、不回退（幂等）；同一事件重复投递自动跳过。
 * - 全程故障隔离：任何异常都吞掉并记日志，绝不回抛影响下游主流程。
 *
 * Class CoreRecycleDownstreamMirrorService
 * @package addon\hsx_recycle\app\service\core\recycle_device
 */
class CoreRecycleDownstreamMirrorService
{
    /**
     * 应用一条下游阶段回流
     * @param int $deviceId 回收设备ID（下游统一称 source_device_id）
     * @param int $stage 目标阶段（见 RecycleDownstreamDict）
     * @param array $extra 快照字段：erp_asset_id / sale_price
     * @param string $eventId 事件ID（幂等键）
     * @return array
     */
    public function applyStage(int $deviceId, int $stage, array $extra = [], string $eventId = ''): array
    {
        try {
            if ($deviceId <= 0 || $stage <= 0) {
                return ['skipped' => true, 'reason' => 'invalid_args'];
            }

            $device = RecycleDevice::where([['id', '=', $deviceId]])->findOrEmpty();
            if ($device->isEmpty()) {
                return ['skipped' => true, 'reason' => 'device_not_found'];
            }

            // 幂等：同一事件已应用过则跳过
            if ($eventId !== '' && (string)$device->downstream_event_id === $eventId) {
                return ['skipped' => true, 'reason' => 'duplicate_event'];
            }

            $current = (int)$device->downstream_stage;
            $data = [];

            // 只前进：高于当前阶段才推进主阶段，避免乱序投递造成回退
            if ($stage > $current) {
                $data['downstream_stage'] = $stage;
                $data['downstream_stage_at'] = time();
            }

            // 快照字段：到达即补充，便于前端展示
            if (!empty($extra['erp_asset_id'])) {
                $data['downstream_erp_asset_id'] = (int)$extra['erp_asset_id'];
            }
            if (isset($extra['sale_price']) && (float)$extra['sale_price'] > 0) {
                $data['downstream_sale_price'] = (float)$extra['sale_price'];
            }
            if ($eventId !== '') {
                $data['downstream_event_id'] = $eventId;
            }

            if (empty($data)) {
                return ['skipped' => true, 'reason' => 'no_change'];
            }

            $device->save($data);
            return ['updated' => true, 'device_id' => $deviceId, 'stage' => max($stage, $current)];
        } catch (\Throwable $e) {
            try {
                Log::error('[hsx_recycle] downstream mirror failed: ' . $e->getMessage());
            } catch (\Throwable $ignore) {
            }
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
