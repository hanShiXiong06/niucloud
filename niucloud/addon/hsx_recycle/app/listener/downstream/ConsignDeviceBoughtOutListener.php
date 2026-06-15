<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use think\facade\Log;

/**
 * 代卖转回收回流监听器（事件 ErpConsignDeviceBoughtOut）。
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
            $siteId = (int)($event['site_id'] ?? 0);
            $deviceId = (int)($event['source_device_id'] ?? 0);
            $buyout = round((float)($event['buyout_amount'] ?? 0), 2);
            if ($siteId <= 0 || $deviceId <= 0) {
                return ['skipped' => true, 'reason' => 'invalid_event'];
            }

            $device = RecycleDevice::where([
                ['site_id', '=', $siteId],
                ['id', '=', $deviceId],
            ])->findOrEmpty();
            if ($device->isEmpty()) {
                return ['skipped' => true, 'reason' => 'device_not_found'];
            }
            if ((string)$device->dispose_type === RecycleOrderDict::DISPOSE_TYPE_RECYCLE) {
                return ['skipped' => true, 'reason' => 'already_recycle'];
            }

            $oldDispose = (string)$device->dispose_type;
            $update = [
                'dispose_type' => RecycleOrderDict::DISPOSE_TYPE_RECYCLE,
                'update_at' => time(),
            ];
            // 买断价作为回收成本（成本转移到我方）；原代卖参考价不再适用
            if ($buyout > 0) {
                $update['final_price'] = $buyout;
            }
            $device->save($update);

            try {
                RecycleDeviceLog::create([
                    'site_id' => $siteId,
                    'device_id' => $deviceId,
                    'order_id' => (int)$device->order_id,
                    'operator_id' => (int)($event['operator']['id'] ?? 0),
                    'operator_name' => (string)($event['operator']['name'] ?? '系统'),
                    'operation_type' => 'consign_to_recycle',
                    'action' => 'consign_to_recycle',
                    'old_status' => (int)$device->status,
                    'new_status' => (int)$device->status,
                    'remark' => sprintf('代卖转回收：买断价 %.2f 计入回收成本（原处置类型 %s）', $buyout, $oldDispose),
                    'create_at' => time(),
                ]);
            } catch (\Throwable $ignore) {
            }

            return ['converted' => true, 'device_id' => $deviceId];
        } catch (\Throwable $e) {
            Log::warning('[hsx_recycle] 代卖转回收回流失败: ' . $e->getMessage());
            return ['skipped' => true, 'reason' => 'exception'];
        }
    }
}
