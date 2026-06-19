<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpAssetService;
use think\facade\Log;

/**
 * 承接"回收侧成本调整"事件：按增量同步 ERP 资产的库存成本，并记一笔成本流水(操作人=回收操作人)。
 *
 * 解耦：回收只发事件，不直接写 ERP 的表；ERP 这边自己决定怎么承接(此处=改 current_cost + 记 ErpCostLedger)。
 * 防死循环：本承接只改数据、不再回发 ErpAssetCostAdjusted(只有用户在 ERP 主动调成本才发那个事件)。
 * 故障隔离：处理失败只记日志，不回抛。
 */
class RecycleCostAdjustedListener
{
    public function handle($event)
    {
        try {
            $p = is_array($event) ? $event : (array)$event;
            $deviceId = (int)($p['source_device_id'] ?? 0);
            $delta    = round((float)($p['delta'] ?? 0), 2);
            if ($deviceId <= 0 || abs($delta) < 0.001) {
                return;
            }
            $reason   = trim((string)($p['reason'] ?? ''));
            $operator = trim((string)($p['operator'] ?? ''));
            (new ErpAssetService())->applyRecycleCostDelta($deviceId, $delta, $reason, $operator);
        } catch (\Throwable $e) {
            Log::warning('[erp] 承接回收调成本事件失败: ' . $e->getMessage());
        }
    }
}
