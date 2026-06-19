<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener;

use addon\hsx_recycle\app\service\admin\order\RecycleDeviceCostAdjustmentService;
use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDeviceLogService;
use think\facade\Log;

/**
 * 承接 ERP 的"设备成本已调整"事件，按增量同步回收设备的库存成本。
 *
 * 解耦：ERP 只发事件，不直接写回收的表；回收这边自己决定怎么承接(此处=改 final_price + 落调整记录 + 设备日志)。
 * 防死循环：调用 applyExternalDelta 只改数据、不再回发 RecycleDeviceCostAdjusted。
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
            // 优先用事件携带的增量；老事件没有 delta 时用 after-before 兜底
            $delta = array_key_exists('delta', $p)
                ? round((float)$p['delta'], 2)
                : round((float)($p['after_cost'] ?? 0) - (float)($p['before_cost'] ?? 0), 2);
            $reason   = trim((string)($p['reason'] ?? ''));
            $operator = trim((string)($p['operator'] ?? ''));

            if (abs($delta) < 0.001) {
                // 没有金额变化也留一条提醒痕迹
                $before = number_format((float)($p['before_cost'] ?? 0), 2);
                $after  = number_format((float)($p['after_cost'] ?? 0), 2);
                (new CoreRecycleDeviceLogService())->addDeviceLog([
                    'device_id'      => $deviceId,
                    'operation_type' => 'erp_cost_adjust',
                    'custom_message' => "ERP 调整成本：¥{$before} → ¥{$after}" . ($reason !== '' ? "（{$reason}）" : ''),
                ]);
                return;
            }

            (new RecycleDeviceCostAdjustmentService())->applyExternalDelta($deviceId, $delta, $reason, $operator);
        } catch (\Throwable $e) {
            Log::warning('[recycle] 承接ERP调成本事件失败: ' . $e->getMessage());
        }
    }
}
