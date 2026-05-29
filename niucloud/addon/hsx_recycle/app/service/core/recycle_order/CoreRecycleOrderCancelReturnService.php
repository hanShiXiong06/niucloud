<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\order\RecycleOrderLog;
use addon\hsx_recycle\app\model\order\RecycleReturnDevice;
use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 主订单取消后的设备退回同步。
 */
class CoreRecycleOrderCancelReturnService extends BaseCoreService
{
    /**
     * 主订单取消后，将仍需退回给客户的设备创建退回单。
     *
     * @param int $orderId
     * @param array $data
     * @param array $context
     * @return void
     * @throws CommonException
     */
    public function sync(int $orderId, array $data = [], array $context = []): void
    {
        [$deviceIds, $skippedDevices, $beforeStatusMap] = $this->collectReturnableDevices($orderId);
        if (empty($deviceIds)) {
            $this->addOrderSystemLog($orderId, '主订单取消后未发现需要自动退回的设备', $context);
            $this->addSkippedDeviceLogs($skippedDevices, $context);
            return;
        }

        $remark = $this->buildRemark($data);

        try {
            (new RecycleDeviceService())->batchReturn($deviceIds, $remark);
            $this->keepOrderCancelled($orderId, $data);
            $this->addOrderSystemLog($orderId, "系统已为主订单取消自动创建退回处理，设备ID：" . implode(',', $deviceIds), $context);
            $this->addReturnedDeviceLogs($orderId, $deviceIds, $beforeStatusMap, $remark, $context);
            $this->addSkippedDeviceLogs($skippedDevices, $context);
        } catch (\Exception $e) {
            Log::error("订单{$orderId}取消后自动创建退回单失败：" . $e->getMessage(), [
                'order_id' => $orderId,
                'device_ids' => $deviceIds,
                'context' => $context
            ]);
            throw new CommonException($e->getMessage());
        }
    }

    private function collectReturnableDevices(int $orderId): array
    {
        $devices = RecycleDevice::where('order_id', $orderId)->select();
        if ($devices->isEmpty()) {
            return [[], [], []];
        }

        $deviceIds = [];
        $beforeStatusMap = [];
        $skippedDevices = [];
        foreach ($devices as $device) {
            $skipReason = $this->getSkipReason($device);
            if ($skipReason !== '') {
                $skippedDevices[] = [
                    'id' => (int)$device->id,
                    'order_id' => (int)$device->order_id,
                    'status' => (int)$device->status,
                    'reason' => $skipReason
                ];
                continue;
            }
            $deviceIds[] = (int)$device->id;
            $beforeStatusMap[(int)$device->id] = (int)$device->status;
        }

        if (empty($deviceIds)) {
            return [[], $skippedDevices, []];
        }

        $existingReturnDeviceIds = RecycleReturnDevice::whereIn('device_id', $deviceIds)->column('device_id');
        if (empty($existingReturnDeviceIds)) {
            return [$deviceIds, $skippedDevices, $beforeStatusMap];
        }

        $existingReturnDeviceIds = array_map('intval', $existingReturnDeviceIds);
        foreach ($existingReturnDeviceIds as $deviceId) {
            $skippedDevices[] = [
                'id' => $deviceId,
                'order_id' => $orderId,
                'status' => 0,
                'reason' => '已存在退回单关联'
            ];
        }

        foreach ($existingReturnDeviceIds as $deviceId) {
            unset($beforeStatusMap[$deviceId]);
        }

        return [array_values(array_diff($deviceIds, $existingReturnDeviceIds)), $skippedDevices, $beforeStatusMap];
    }

    private function getSkipReason($device): string
    {
        $status = (int)($device->status ?? 0);
        $payStatus = (int)($device->pay_status ?? 0);
        $payAmount = (float)($device->pay_amount ?? 0);
        $returnOrderId = (int)($device->return_order_id ?? 0);
        $consignmentOrderId = (int)($device->consignment_order_id ?? 0);
        $disposeType = (string)($device->dispose_type ?? '');

        if ($returnOrderId > 0) {
            return '设备已关联退回单';
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_RETURNED) {
            return '设备已退回';
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_CONSIGNED || $disposeType === RecycleOrderDict::DISPOSE_TYPE_CONSIGN || $consignmentOrderId > 0) {
            return '设备已转代卖';
        }
        if ($payStatus === RecycleOrderDict::PAY_STATUS_PAID || $payAmount > 0) {
            return '设备已打款';
        }

        return '';
    }

    private function buildRemark(array $data): string
    {
        $reason = trim((string)($data['reason'] ?? $data['cancel_reason'] ?? ''));
        $remark = trim((string)($data['remark'] ?? ''));

        if ($reason !== '' && $remark !== '') {
            return "主订单取消自动退回 | 原因：{$reason} | 备注：{$remark}";
        }
        if ($reason !== '') {
            return "主订单取消自动退回 | 原因：{$reason}";
        }
        if ($remark !== '') {
            return "主订单取消自动退回 | 备注：{$remark}";
        }
        return '主订单取消自动退回';
    }

    private function keepOrderCancelled(int $orderId, array $data): void
    {
        $update = [
            'status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
            'update_at' => time()
        ];

        $reason = trim((string)($data['reason'] ?? $data['cancel_reason'] ?? ''));
        if ($reason !== '') {
            $update['cancel_reason'] = $reason;
        }
        $update['cancel_time'] = time();

        RecycleOrder::where('id', $orderId)->update($update);
    }

    private function addReturnedDeviceLogs(int $orderId, array $deviceIds, array $beforeStatusMap, string $remark, array $context): void
    {
        foreach ($deviceIds as $deviceId) {
            $this->addDeviceSystemLog(
                $deviceId,
                $orderId,
                (int)($beforeStatusMap[$deviceId] ?? 0),
                RecycleOrderDict::DEVICE_STATUS_RETURNED,
                "系统操作：主订单取消后自动生成设备退回处理 | {$remark}",
                $context
            );
        }
    }

    private function addSkippedDeviceLogs(array $skippedDevices, array $context): void
    {
        foreach ($skippedDevices as $device) {
            $this->addDeviceSystemLog(
                (int)$device['id'],
                (int)$device['order_id'],
                (int)$device['status'],
                (int)$device['status'],
                '系统操作：主订单取消后跳过自动退回 | 原因：' . $device['reason'],
                $context
            );
        }
    }

    private function addDeviceSystemLog(int $deviceId, int $orderId, int $oldStatus, int $newStatus, string $remark, array $context): void
    {
        $log = new RecycleDeviceLog();
        $log->save([
            'site_id' => (int)($context['site_id'] ?? 0),
            'device_id' => $deviceId,
            'order_id' => $orderId,
            'operator_id' => 0,
            'operator_name' => '系统',
            'operation_type' => 'order_cancel_auto_return',
            'action' => 'order_cancel_auto_return',
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remark' => $remark,
            'create_at' => time()
        ]);
    }

    private function addOrderSystemLog(int $orderId, string $remark, array $context): void
    {
        $log = new RecycleOrderLog();
        $log->save([
            'site_id' => (int)($context['site_id'] ?? 0),
            'order_id' => $orderId,
            'operator_id' => 0,
            'operator_name' => '系统',
            'action' => 'order_cancel_auto_return',
            'old_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
            'new_status' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
            'remark' => $remark,
            'create_at' => time()
        ]);
    }
}
