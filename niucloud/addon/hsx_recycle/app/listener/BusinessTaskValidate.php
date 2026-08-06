<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\stat\RecycleTaskClaim;

/** 企业微信真正发件前校验任务仍然有效且责任人未变化。 */
final class BusinessTaskValidate
{
    public function handle(array $event): array
    {
        if ((string)($event['source_plugin'] ?? '') !== 'hsx_recycle') return [];

        $siteId = (int)($event['site_id'] ?? 0);
        $sourceId = (int)($event['source_id'] ?? 0);
        $stage = (string)($event['stage_key'] ?? '');
        $assigneeUid = (int)($event['assignee_uid'] ?? 0);
        if ($siteId <= 0 || $sourceId <= 0 || $stage === '' || $assigneeUid <= 0) {
            return ['valid' => false, 'reason' => '任务数据不完整，已停止通知'];
        }

        $claim = RecycleTaskClaim::where([
            ['site_id', '=', $siteId], ['device_id', '=', $sourceId], ['stage_key', '=', $stage],
        ])->findOrEmpty();
        if ($claim->isEmpty() || (int)$claim->assignee_uid !== $assigneeUid) {
            return ['valid' => false, 'reason' => '任务责任人已变化，无需向原责任人通知'];
        }

        if (RecycleStageDict::isOrderStage($stage)) {
            $order = RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', $sourceId]])->findOrEmpty();
            if ($order->isEmpty() || (int)$order->status !== RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
                return ['valid' => false, 'reason' => '订单已签收或已结束，无需继续通知'];
            }
            if ($stage === RecycleStageDict::STAGE_PICKUP
                && (int)$order->delivery_type !== (int)RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE) {
                return ['valid' => false, 'reason' => '订单已不属于物流车取货任务'];
            }
            return ['valid' => true];
        }

        $device = RecycleDevice::where([['site_id', '=', $siteId], ['id', '=', $sourceId]])->findOrEmpty();
        if ($device->isEmpty() || RecycleStageDict::stageOf((int)$device->status, (int)($device->pay_status ?? 0)) !== $stage) {
            return ['valid' => false, 'reason' => '设备已离开当前环节，无需继续通知'];
        }
        return ['valid' => true];
    }
}
