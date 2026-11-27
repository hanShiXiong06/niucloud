<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_device;

use addon\hsx_recycle\app\dict\order\RecycleDownstreamDict;
use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentLog;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
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
    /** ERP售出代卖设备后，回写成交金额并把代卖单推进到待结算。 */
    public function applyConsignmentSale(int $deviceId, array $extra = [], string $eventId = ''): array
    {
        try {
            return Db::transaction(function () use ($deviceId, $extra, $eventId): array {
                $siteId = (int)($extra['site_id'] ?? 0);
                if ($deviceId <= 0 || $siteId <= 0) return ['skipped' => true, 'reason' => 'invalid_args'];
                $device = RecycleDevice::where([['site_id', '=', $siteId], ['id', '=', $deviceId]])->lock(true)->findOrEmpty();
                if ($device->isEmpty()) return ['skipped' => true, 'reason' => 'device_not_found'];
                if ($eventId !== '' && (string)$device->downstream_event_id === $eventId) {
                    return ['skipped' => true, 'reason' => 'duplicate_event'];
                }
                $consignment = RecycleConsignmentOrder::where([
                    ['site_id', '=', $siteId],
                    ['id', '=', (int)$device->consignment_order_id],
                    ['source_device_id', '=', (int)$device->id],
                ])->lock(true)->findOrEmpty();
                if ($consignment->isEmpty()) return ['error' => true, 'message' => '代卖设备未找到来源代卖单'];

                $salePrice = round((float)($extra['sale_price'] ?? 0), 2);
                $settlementAmount = round((float)($extra['consignment_settlement_amount'] ?? 0), 2);
                if ($salePrice <= 0 || $settlementAmount <= 0 || $settlementAmount > $salePrice) {
                    return ['error' => true, 'message' => 'ERP代卖成交金额快照不完整'];
                }
                $now = time();
                $before = $consignment->toArray();
                $consignment->save([
                    'sold_price' => $salePrice,
                    'settlement_amount' => $settlementAmount,
                    'service_fee' => round($salePrice - $settlementAmount, 2),
                    'status' => RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
                    'pay_status' => RecycleConsignmentDict::PAY_STATUS_UNPAID,
                    'sold_time' => (int)($extra['sold_at'] ?? $now),
                    'operator_id' => 0,
                    'update_time' => $now,
                ]);
                $device->save([
                    'sell_price' => $salePrice,
                    'final_price' => $settlementAmount,
                    'downstream_stage' => max((int)$device->downstream_stage, RecycleDownstreamDict::STAGE_SOLD),
                    'downstream_stage_at' => $now,
                    'downstream_sale_price' => $salePrice,
                    'downstream_erp_asset_id' => (int)($extra['erp_asset_id'] ?? 0),
                    'downstream_event_id' => $eventId,
                    'update_at' => $now,
                ]);
                RecycleConsignmentLog::create([
                    'site_id' => $siteId,
                    'consignment_id' => (int)$consignment->id,
                    'source_order_id' => (int)$consignment->source_order_id,
                    'source_device_id' => (int)$device->id,
                    'operator_id' => 0,
                    'operator_name' => 'ERP同步',
                    'action' => 'sold',
                    'old_status' => (int)($before['status'] ?? 0),
                    'new_status' => RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
                    'before_data' => $before,
                    'after_data' => $consignment->toArray(),
                    'remark' => sprintf('ERP销售出库：成交¥%.2f，应付客户¥%.2f，应付单%s', $salePrice, $settlementAmount, (string)($extra['consignment_payable_no'] ?? '')),
                    'create_time' => $now,
                ]);
                RecycleDeviceLog::create([
                    'site_id' => $siteId,
                    'device_id' => (int)$device->id,
                    'order_id' => (int)$device->order_id,
                    'operator_id' => 0,
                    'operator_name' => 'ERP同步',
                    'operation_type' => 'erp_consignment_sale',
                    'action' => 'erp_consignment_sale',
                    'old_status' => (int)$device->status,
                    'new_status' => (int)$device->status,
                    'remark' => sprintf('代卖成交 | ERP销售单:%s | 成交:%.2f | 待付客户:%.2f', (string)($extra['sale_no'] ?? ''), $salePrice, $settlementAmount),
                    'create_at' => $now,
                ]);
                return ['updated' => true, 'device_id' => $deviceId, 'consignment_id' => (int)$consignment->id];
            });
        } catch (\Throwable $e) {
            Log::error('[hsx_recycle] consignment sale mirror failed: ' . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** ERP撤销代卖设备销售后，撤回待付款事实并恢复为可继续代卖。 */
    public function applyConsignmentSaleCancellation(int $deviceId, array $extra = [], string $eventId = ''): array
    {
        try {
            return Db::transaction(function () use ($deviceId, $extra, $eventId): array {
                $siteId = (int)($extra['site_id'] ?? 0);
                if ($deviceId <= 0 || $siteId <= 0) return ['skipped' => true, 'reason' => 'invalid_args'];
                $device = RecycleDevice::where([
                    ['site_id', '=', $siteId],
                    ['id', '=', $deviceId],
                ])->lock(true)->findOrEmpty();
                if ($device->isEmpty()) return ['skipped' => true, 'reason' => 'device_not_found'];
                if ($eventId !== '' && (string)$device->downstream_event_id === $eventId) {
                    return ['skipped' => true, 'reason' => 'duplicate_event'];
                }
                $consignment = RecycleConsignmentOrder::where([
                    ['site_id', '=', $siteId],
                    ['id', '=', (int)$device->consignment_order_id],
                    ['source_device_id', '=', (int)$device->id],
                ])->lock(true)->findOrEmpty();
                if ($consignment->isEmpty()) return ['error' => true, 'message' => '代卖设备未找到来源代卖单'];
                if ((int)$consignment->pay_status === RecycleConsignmentDict::PAY_STATUS_PAID
                    || (int)$consignment->status === RecycleConsignmentDict::STATUS_SETTLED) {
                    return ['error' => true, 'message' => '代卖货款已经结清，不能直接撤销销售'];
                }

                $now = time();
                $before = $consignment->toArray();
                $restoredStatus = (float)$consignment->listing_price > 0
                    ? RecycleConsignmentDict::STATUS_SELLING
                    : RecycleConsignmentDict::STATUS_PENDING;
                $restoredSettlement = round((float)($consignment->min_settlement_price ?: $consignment->expected_price ?: 0), 2);
                $consignment->save([
                    'sold_price' => 0,
                    'settlement_amount' => 0,
                    'service_fee' => 0,
                    'status' => $restoredStatus,
                    'pay_status' => RecycleConsignmentDict::PAY_STATUS_UNPAID,
                    'sold_time' => 0,
                    'operator_id' => 0,
                    'update_time' => $now,
                ]);
                $device->save([
                    'sell_price' => (float)$consignment->listing_price,
                    'final_price' => $restoredSettlement,
                    'downstream_stage' => RecycleDownstreamDict::STAGE_STOCKED,
                    'downstream_stage_at' => $now,
                    'downstream_sale_price' => 0,
                    'downstream_erp_asset_id' => (int)($extra['erp_asset_id'] ?? $device->downstream_erp_asset_id ?? 0),
                    'downstream_event_id' => $eventId,
                    'update_at' => $now,
                ]);

                $reason = trim((string)($extra['return_reason'] ?? '')) ?: 'ERP撤销销售';
                RecycleConsignmentLog::create([
                    'site_id' => $siteId,
                    'consignment_id' => (int)$consignment->id,
                    'source_order_id' => (int)$consignment->source_order_id,
                    'source_device_id' => (int)$device->id,
                    'operator_id' => 0,
                    'operator_name' => 'ERP同步',
                    'action' => 'sale_cancel',
                    'old_status' => (int)($before['status'] ?? 0),
                    'new_status' => $restoredStatus,
                    'before_data' => $before,
                    'after_data' => $consignment->toArray(),
                    'remark' => sprintf('%s，代卖设备恢复可售 | ERP销售单:%s', $reason, (string)($extra['sale_no'] ?? '')),
                    'create_time' => $now,
                ]);
                RecycleDeviceLog::create([
                    'site_id' => $siteId,
                    'device_id' => (int)$device->id,
                    'order_id' => (int)$device->order_id,
                    'operator_id' => 0,
                    'operator_name' => 'ERP同步',
                    'operation_type' => 'erp_consignment_sale_cancel',
                    'action' => 'erp_consignment_sale_cancel',
                    'old_status' => (int)$device->status,
                    'new_status' => (int)$device->status,
                    'remark' => sprintf('%s | ERP销售单:%s', $reason, (string)($extra['sale_no'] ?? '')),
                    'create_at' => $now,
                ]);
                return ['updated' => true, 'device_id' => $deviceId, 'consignment_id' => (int)$consignment->id];
            });
        } catch (\Throwable $e) {
            Log::error('[hsx_recycle] consignment sale cancellation mirror failed: ' . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

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
