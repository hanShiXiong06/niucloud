<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\core\ErpReturnService;

/**
 * 监听回收"撤销回收"事件 → ERP 退货出库冲销。
 * 故障隔离：异常不回抛，绝不影响回收侧撤销。
 *
 * Class DeviceCancelledListener
 * @package addon\hsx_erp\app\listener
 */
class DeviceCancelledListener
{
    public function handle(array $event): array
    {
        try {
            if ((string)($event['event_name'] ?? '') !== 'recycle.device.cancelled.v1') {
                return ['skipped' => true];
            }
            return (new ErpReturnService())->returnByRecycleCancel($event);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
