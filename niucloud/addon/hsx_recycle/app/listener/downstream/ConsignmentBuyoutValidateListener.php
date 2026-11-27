<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\model\order\RecycleDevice;

/** ERP 代卖买断前置校验：只读，不改变回收插件任何状态。 */
class ConsignmentBuyoutValidateListener
{
    public function handle(array $payload): array
    {
        $siteId = (int)($payload['site_id'] ?? 0);
        $deviceId = (int)($payload['source_device_id'] ?? 0);
        if ($siteId <= 0 || $deviceId <= 0) {
            return ['handled' => 1, 'allowed' => 0, 'message' => '代卖设备缺少回收来源信息'];
        }
        $device = RecycleDevice::where([
            ['site_id', '=', $siteId],
            ['id', '=', $deviceId],
        ])->findOrEmpty();
        if ($device->isEmpty()) {
            return ['handled' => 1, 'allowed' => 0, 'message' => '回收插件中找不到对应代卖设备'];
        }
        $consignment = RecycleConsignmentOrder::where([
            ['site_id', '=', $siteId],
            ['source_device_id', '=', $deviceId],
        ])->order('id desc')->findOrEmpty();
        if ($consignment->isEmpty()) {
            return ['handled' => 1, 'allowed' => 1];
        }
        if (in_array((int)$consignment->status, [
            RecycleConsignmentDict::STATUS_SOLD,
            RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
            RecycleConsignmentDict::STATUS_SETTLED,
        ], true)) {
            return ['handled' => 1, 'allowed' => 0, 'message' => '该代卖设备已成交或进入结算，不能转为自有'];
        }
        return ['handled' => 1, 'allowed' => 1];
    }
}
