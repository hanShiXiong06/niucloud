<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentLog;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use think\facade\Db;
use think\facade\Log;

/**
 * 代卖转回收回流监听器。
 *
 * ERP 把代卖仓设备买断为自有（consignment → 二手机仓）时发本事件，
 * 回收侧把该设备由"代卖(consign)"标记为"回收(recycle)"，买断价作为回收成本入账，
 * 让"由代卖转回收"在回收业务里可见（应付已由 ERP 财务侧生成，此处只翻转回收侧状态并留痕）。
 *
 * 幂等：已是 recycle 的设备不再处理。监听器绝不抛异常，避免影响 ERP 调拨主流程。
 */
class ConsignDeviceBoughtOutListener
{
    public function handle(array $event): array
    {
        try {
            $eventName = (string)($event['event_name'] ?? '');
            $isDomainEvent = $eventName !== '';
            if ($isDomainEvent && $eventName !== 'erp.asset.consignment_bought_out.v1') {
                return ['skipped' => true];
            }
            $payload = $isDomainEvent ? (array)($event['payload'] ?? []) : $event;
            $siteId = (int)($event['site_id'] ?? $payload['site_id'] ?? 0);
            $deviceId = (int)($payload['source_device_id'] ?? 0);
            $buyout = round((float)($payload['buyout_amount'] ?? 0), 2);
            if ($siteId <= 0 || $deviceId <= 0) {
                return ['consumer' => 'hsx_recycle', 'status' => 'failed', 'error' => true, 'message' => '代卖买断事件缺少站点或回收设备ID'];
            }

            $device = RecycleDevice::where([
                ['site_id', '=', $siteId],
                ['id', '=', $deviceId],
            ])->findOrEmpty();
            if ($device->isEmpty()) {
                return ['consumer' => 'hsx_recycle', 'status' => 'failed', 'error' => true, 'message' => '对应的回收设备不存在'];
            }
            $consignment = RecycleConsignmentOrder::where([
                ['site_id', '=', $siteId],
                ['source_device_id', '=', $deviceId],
            ])->order('id desc')->findOrEmpty();
            if (!$consignment->isEmpty() && in_array((int)$consignment->status, [
                RecycleConsignmentDict::STATUS_SOLD,
                RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
                RecycleConsignmentDict::STATUS_SETTLED,
            ], true)) {
                return [
                    'consumer' => 'hsx_recycle', 'status' => 'failed', 'error' => true,
                    'message' => '代卖订单已成交或进入结算，不能转为回收，请先处理代卖订单',
                ];
            }
            if ((string)$device->dispose_type === RecycleOrderDict::DISPOSE_TYPE_RECYCLE
                && ($consignment->isEmpty() || (int)$consignment->status === RecycleConsignmentDict::STATUS_CANCELLED)) {
                return ['consumer' => 'hsx_recycle', 'status' => 'duplicate', 'device_id' => $deviceId];
            }

            $oldDispose = (string)$device->dispose_type;
            $operatorId = (int)($event['operator']['id'] ?? $payload['operator_id'] ?? 0);
            $operatorName = (string)($event['operator']['name'] ?? $payload['operator_name'] ?? '系统');
            $now = time();
            Db::transaction(function () use (
                $device, $consignment, $siteId, $deviceId, $buyout, $oldDispose,
                $operatorId, $operatorName, $payload, $now
            ) {
                if ((string)$device->dispose_type !== RecycleOrderDict::DISPOSE_TYPE_RECYCLE) {
                    $update = [
                        'dispose_type' => RecycleOrderDict::DISPOSE_TYPE_RECYCLE,
                        'update_at' => $now,
                    ];
                    // 买断价作为回收成本（成本转移到我方）；原代卖参考价不再适用。
                    if ($buyout > 0) $update['final_price'] = $buyout;
                    $device->save($update);
                }

                if (!$consignment->isEmpty() && (int)$consignment->status !== RecycleConsignmentDict::STATUS_CANCELLED) {
                    $before = $consignment->toArray();
                    $remark = sprintf(
                        'ERP 代卖买断转自有；回收价 %.2f；采购单 %s；应付单 %s',
                        $buyout,
                        (string)($payload['purchase_no'] ?? '-'),
                        (string)($payload['payable_no'] ?? '-')
                    );
                    $consignment->save([
                        'status' => RecycleConsignmentDict::STATUS_CANCELLED,
                        'cancel_time' => $now,
                        'operator_id' => $operatorId,
                        'remark' => $remark,
                        'update_time' => $now,
                    ]);
                    RecycleConsignmentLog::create([
                        'site_id' => $siteId,
                        'consignment_id' => (int)$consignment->id,
                        'source_order_id' => (int)$consignment->source_order_id,
                        'source_device_id' => $deviceId,
                        'operator_id' => $operatorId,
                        'operator_name' => $operatorName,
                        'action' => 'erp_buyout',
                        'old_status' => (int)($before['status'] ?? 0),
                        'new_status' => RecycleConsignmentDict::STATUS_CANCELLED,
                        'before_data' => $before,
                        'after_data' => $consignment->toArray(),
                        'remark' => $remark,
                        'create_time' => $now,
                    ]);
                }

                RecycleDeviceLog::create([
                    'site_id' => $siteId,
                    'device_id' => $deviceId,
                    'order_id' => (int)$device->order_id,
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                    'operation_type' => 'consign_to_recycle',
                    'action' => 'consign_to_recycle',
                    'old_status' => (int)$device->status,
                    'new_status' => (int)$device->status,
                    'remark' => sprintf('代卖转回收：买断价 %.2f 计入回收成本（原处置类型 %s）', $buyout, $oldDispose),
                    'create_at' => $now,
                ]);
            });

            return [
                'consumer' => 'hsx_recycle', 'status' => 'processed', 'converted' => true,
                'device_id' => $deviceId, 'consignment_closed' => !$consignment->isEmpty(),
            ];
        } catch (\Throwable $e) {
            Log::warning('[hsx_recycle] 代卖转回收回流失败: ' . $e->getMessage());
            return ['consumer' => 'hsx_recycle', 'status' => 'failed', 'error' => true, 'message' => $e->getMessage()];
        }
    }
}
