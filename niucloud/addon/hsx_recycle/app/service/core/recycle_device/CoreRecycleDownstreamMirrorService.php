<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_device;

use addon\hsx_recycle\app\dict\order\RecycleDownstreamDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use think\facade\Db;
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
    /** ERP 采购退货完成后，同步回收设备业务状态；已付款事实保持不变。 */
    public function applyPurchaseReturn(int $deviceId, array $extra = [], string $eventId = ''): array
    {
        try {
            return Db::transaction(function () use ($deviceId, $extra, $eventId) {
                if ($deviceId <= 0) return ['skipped' => true, 'reason' => 'invalid_args'];

                $conditions = [['id', '=', $deviceId]];
                $siteId = (int)($extra['site_id'] ?? 0);
                if ($siteId > 0) $conditions[] = ['site_id', '=', $siteId];

                $device = RecycleDevice::where($conditions)->lock(true)->findOrEmpty();
                if ($device->isEmpty()) return ['skipped' => true, 'reason' => 'device_not_found'];
                if ($eventId !== '' && (string)$device->downstream_event_id === $eventId) {
                    return ['skipped' => true, 'reason' => 'duplicate_event'];
                }

                $now = time();
                $oldStatus = (int)$device->status;
                $returnNo = trim((string)($extra['return_no'] ?? ''));
                $remark = 'ERP采购退货已确认，设备已退出ERP库存';
                if ($returnNo !== '') $remark .= '，退货单：' . $returnNo;

                $device->save([
                    'status' => RecycleOrderDict::DEVICE_STATUS_RETURNED,
                    'confirm_status' => RecycleOrderDict::CONFIRM_STATUS_REJECTED,
                    'confirm_remark' => $remark,
                    'settlement_mode' => RecycleOrderDict::DISPOSE_TYPE_RETURN,
                    'dispose_type' => RecycleOrderDict::DISPOSE_TYPE_RETURN,
                    'dispose_status' => RecycleOrderDict::DISPOSE_STATUS_RETURNED,
                    'return_time' => $now,
                    'return_remark' => $remark,
                    'downstream_stage' => max((int)$device->downstream_stage, RecycleDownstreamDict::STAGE_PURCHASE_RETURN_PENDING),
                    'downstream_stage_at' => $now,
                    'downstream_erp_asset_id' => (int)($extra['erp_asset_id'] ?? $device->downstream_erp_asset_id ?? 0),
                    'downstream_event_id' => $eventId,
                    'update_at' => $now,
                ]);

                if ($oldStatus !== RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                    RecycleDeviceLog::create([
                        'site_id' => (int)$device->site_id,
                        'device_id' => (int)$device->id,
                        'order_id' => (int)$device->order_id,
                        'operator_id' => 0,
                        'operator_name' => 'ERP同步',
                        'operation_type' => 'erp_purchase_return',
                        'action' => 'erp_purchase_return',
                        'old_status' => $oldStatus,
                        'new_status' => RecycleOrderDict::DEVICE_STATUS_RETURNED,
                        'remark' => $remark,
                        'create_at' => $now,
                    ]);
                }

                $this->closeOrderWhenAllReturned((int)$device->order_id, (int)$device->site_id, $now);

                return [
                    'updated' => true,
                    'device_id' => $deviceId,
                    'order_id' => (int)$device->order_id,
                    'stage' => (int)$device->downstream_stage,
                    'status' => RecycleOrderDict::DEVICE_STATUS_RETURNED,
                ];
            });
        } catch (\Throwable $e) {
            try {
                Log::error('[hsx_recycle] purchase return mirror failed: ' . $e->getMessage());
            } catch (\Throwable $ignore) {
            }
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function closeOrderWhenAllReturned(int $orderId, int $siteId, int $now): void
    {
        if ($orderId <= 0 || $siteId <= 0) return;

        $conditions = [
            ['site_id', '=', $siteId],
            ['order_id', '=', $orderId],
        ];
        $total = (int)RecycleDevice::where($conditions)->count();
        $returned = (int)RecycleDevice::where($conditions)
            ->where('status', RecycleOrderDict::DEVICE_STATUS_RETURNED)
            ->count();
        if ($total <= 0 || $returned < $total) return;

        RecycleOrder::where([
            ['site_id', '=', $siteId],
            ['id', '=', $orderId],
            ['delete_at', '=', 0],
        ])->update([
            'status' => RecycleOrderDict::ORDER_STATUS_CLOSED,
            'complete_at' => $now,
            'close_time' => $now,
            'close_reason' => 'ERP采购退货完成，全部设备已退回客户',
            'update_at' => $now,
        ]);
    }

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

            $conditions = [['id', '=', $deviceId]];
            if ((int)($extra['site_id'] ?? 0) > 0) {
                $conditions[] = ['site_id', '=', (int)$extra['site_id']];
            }
            $device = RecycleDevice::where($conditions)->findOrEmpty();
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
