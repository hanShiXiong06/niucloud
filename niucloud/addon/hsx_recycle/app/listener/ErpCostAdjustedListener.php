<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener;

use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDeviceLogService;
use think\facade\Log;

/**
 * 承接 ERP 的"设备成本已调整"事件，在回收设备上留一条日志。
 *
 * 解耦：ERP 只发事件，不写回收的表；回收这边自己决定怎么承接(此处=记日志留痕)。
 * 场景：未打款时 ERP 调了成本，回收侧操作人能在设备日志里看到提醒，决定是否同步回收价。
 * 故障隔离：处理失败只记日志，不回抛。
 */
class ErpCostAdjustedListener
{
    public function handle($event)
    {
        try {
            $p = is_array($event) ? $event : (array)$event;
            $deviceId = (int)($p['source_device_id'] ?? 0);
            if ($deviceId <= 0) {
                return;
            }
            $before = number_format((float)($p['before_cost'] ?? 0), 2);
            $after  = number_format((float)($p['after_cost'] ?? 0), 2);
            $reason = trim((string)($p['reason'] ?? ''));
            $msg = "ERP 调整成本：¥{$before} → ¥{$after}" . ($reason !== '' ? "（{$reason}）" : '');
            (new CoreRecycleDeviceLogService())->addDeviceLog([
                'device_id'      => $deviceId,
                'operation_type' => 'erp_cost_adjust',
                'custom_message' => $msg,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[recycle] 承接ERP调成本事件失败: ' . $e->getMessage());
        }
    }
}
