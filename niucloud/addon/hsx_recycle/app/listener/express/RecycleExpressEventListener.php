<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\express;

use addon\hsx_recycle\app\model\order\RecycleOrderLog;
use think\facade\Log;

/**
 * 快递事件监听器。
 * 当前先保留统一扩展入口，后续可在这里接 ERP、通知、财务流水等模块。
 */
class RecycleExpressEventListener
{
    public function handle($params)
    {
        if (!is_array($params)) {
            return '';
        }

        $context = [
            'site_id' => $params['site_id'] ?? 0,
            'event_type' => $params['event_type'] ?? '',
            'push_type' => $params['push_type'] ?? 0,
            'record_id' => $params['record_id'] ?? 0,
            'order_no' => $params['order_no'] ?? '',
            'delivery_id' => $params['delivery_id'] ?? '',
            'recycle_order_id' => $params['recycle_order_id'] ?? 0,
        ];

        Log::info('回收快递事件触发', $context);

        if (in_array($params['event_type'] ?? '', ['status_synced', 'status_sync_ignored'], true)) {
            $this->recordStatusSyncNotice($params);
        }
        if (in_array($params['event_type'] ?? '', ['status_changed', 'billing_changed', 'courier_changed', 'waybill_changed'], true)) {
            $this->recordPushNotice($params);
        }

        return '';
    }

    private function recordPushNotice(array $params): void
    {
        if (empty($params['recycle_order_id'])) {
            return;
        }

        try {
            (new RecycleOrderLog())->addOrderLog([
                'site_id' => (int)($params['site_id'] ?? 0),
                'order_id' => (int)$params['recycle_order_id'],
                'operator_id' => 0,
                'operator_name' => 'system',
                'action' => 'express_push',
                'old_status' => $this->statusToDeliveryStatus((string)($params['old_order_status'] ?? '')),
                'new_status' => $this->statusToDeliveryStatus((string)($params['new_order_status'] ?? '')),
                'remark' => $this->buildPushRemark($params),
                'create_at' => time(),
            ]);
        } catch (\Exception $e) {
            Log::error('记录易速快递推送提醒失败：' . $e->getMessage(), [
                'site_id' => $params['site_id'] ?? 0,
                'record_id' => $params['record_id'] ?? 0,
                'recycle_order_id' => $params['recycle_order_id'] ?? 0,
            ]);
        }
    }

    private function recordStatusSyncNotice(array $params): void
    {
        if (empty($params['recycle_order_id'])) {
            return;
        }

        try {
            (new RecycleOrderLog())->addOrderLog([
                'site_id' => (int)($params['site_id'] ?? 0),
                'order_id' => (int)$params['recycle_order_id'],
                'operator_id' => 0,
                'operator_name' => 'system',
                'action' => 'express_status_sync',
                'old_status' => $this->statusToDeliveryStatus((string)($params['old_status'] ?? '')),
                'new_status' => $this->statusToDeliveryStatus((string)($params['new_status'] ?? '')),
                'remark' => $this->buildRemark($params),
                'create_at' => time(),
            ]);
        } catch (\Exception $e) {
            Log::error('记录快递状态同步提醒失败：' . $e->getMessage(), [
                'site_id' => $params['site_id'] ?? 0,
                'record_id' => $params['record_id'] ?? 0,
                'recycle_order_id' => $params['recycle_order_id'] ?? 0,
            ]);
        }
    }

    private function buildRemark(array $params): string
    {
        $notice = (string)($params['notice'] ?? '第三方运单状态与本地不一致');
        $oldStatus = (string)($params['old_status'] ?? '');
        $newStatus = (string)($params['new_status'] ?? '');
        $thirdStatusName = (string)($params['third_status_name'] ?? '');
        $deliveryId = (string)($params['delivery_id'] ?? '');
        $remark = sprintf('%s，运单号：%s，本地：%s，第三方：%s', $notice, $deliveryId ?: '-', $oldStatus ?: '-', $thirdStatusName ?: $newStatus);

        return function_exists('mb_substr') ? mb_substr($remark, 0, 255) : substr($remark, 0, 255);
    }

    private function buildPushRemark(array $params): string
    {
        $payload = is_array($params['payload'] ?? null) ? $params['payload'] : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $deliveryId = (string)($params['delivery_id'] ?? $payload['waybillNo'] ?? '');

        switch ((string)($params['event_type'] ?? '')) {
            case 'status_changed':
                $remark = sprintf('易速物流状态变更，运单号：%s，状态：%s', $deliveryId ?: '-', (string)($data['desc'] ?? '-'));
                break;
            case 'billing_changed':
                $remark = sprintf('易速物流计费变更，运单号：%s，实际费用：%s 元，实际重量：%s kg', $deliveryId ?: '-', (string)($data['totalFee'] ?? '-'), (string)($data['weightFinal'] ?? $data['weightFee'] ?? '-'));
                break;
            case 'courier_changed':
                $remark = sprintf('易速物流快递员变更，运单号：%s，电话：%s，信息：%s', $deliveryId ?: '-', (string)($data['courierPhone'] ?? '-'), (string)($data['courierInfo'] ?? '-'));
                break;
            case 'waybill_changed':
                $remark = sprintf('易速物流运单变更，原运单：%s，新运单：%s', (string)($payload['waybillNo'] ?? '-'), (string)($data['newWaybillNo'] ?? $deliveryId ?: '-'));
                break;
            default:
                $remark = sprintf('易速物流推送，运单号：%s，类型：%s', $deliveryId ?: '-', (string)($payload['pushType'] ?? '-'));
        }

        return function_exists('mb_substr') ? mb_substr($remark, 0, 255) : substr($remark, 0, 255);
    }

    private function statusToDeliveryStatus(string $status): int
    {
        $map = [
            'pending' => 1,
            'picked' => 2,
            'in_transit' => 2,
            'delivered' => 3,
            'cancelled' => 4,
            'exception' => 2,
        ];

        return $map[$status] ?? 0;
    }
}
