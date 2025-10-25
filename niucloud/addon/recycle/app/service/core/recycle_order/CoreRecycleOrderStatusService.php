<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\recycle_order;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrderLog;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单状态管理核心服务
 * Class CoreRecycleOrderStatusService
 * @package addon\recycle\app\service\core\recycle_order
 */
class CoreRecycleOrderStatusService extends BaseCoreService
{
    /**
     * 订单状态流转
     * @param int $orderId 订单ID
     * @param int $targetStatus 目标状态
     * @param array $data 附加数据
     * @return bool
     * @throws CommonException
     */
    public function transition(int $orderId, int $targetStatus, array $data = []): bool
    {
        Db::startTrans();
        try {
            // 获取订单信息
            $order = RecycleOrder::findOrEmpty($orderId);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 验证状态流转是否合法
            if (!$this->validateTransition($order->status, $targetStatus)) {
                throw new CommonException('INVALID_STATUS_TRANSITION');
            }

            $oldStatus = $order->status;

            // 更新订单状态
            $updateData = [
                'status' => $targetStatus,
                'update_at' => time()
            ];

            // 特殊状态处理
            if ($targetStatus == RecycleOrderDict::ORDER_STATUS_DELETE) {
                $updateData['delete_at'] = time();
            }

            $order->save($updateData);

            // 记录状态变更日志
            $this->addOrderLog($orderId, $oldStatus, $targetStatus, $data['remark'] ?? '', $data['operator_id'] ?? 0);

            // 同步设备状态
            if (!empty($data['devices'])) {
                $this->updateDevicesStatus($orderId, $data['devices'], $targetStatus);
            } else {
                // 如果没有指定设备，则更新所有设备状态
                $this->updateAllDevicesStatus($orderId, $targetStatus);
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('订单状态流转失败：' . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量设备状态更新
     * @param int $orderId 订单ID
     * @param array $devices 设备列表
     * @param int $orderStatus 订单状态
     * @return bool
     */
    public function updateDevicesStatus(int $orderId, array $devices, int $orderStatus): bool
    {
        // 订单状态到设备状态的映射
        $statusMap = $this->getOrderToDeviceStatusMap();
        $deviceStatus = $statusMap[$orderStatus] ?? null;

        if ($deviceStatus === null) {
            return true; // 如果没有对应的设备状态，则不更新
        }

        foreach ($devices as $device) {
            if (!isset($device['id'])) {
                continue;
            }

            $deviceModel = RecycleDevice::findOrEmpty($device['id']);
            if ($deviceModel->isEmpty() || $deviceModel->order_id != $orderId) {
                continue;
            }

            // 检查设备当前状态，如果已经是退回状态，则不更新
            if ($deviceModel->status == RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                continue;
            }

            $updateData = ['status' => $deviceStatus];

            // 更新设备基本信息
            if (isset($device['imei'])) {
                $updateData['imei'] = $device['imei'];
            }
            if (isset($device['model'])) {
                $updateData['model'] = $device['model'];
            }
            if (isset($device['initial_price'])) {
                $updateData['initial_price'] = $device['initial_price'];
            }
            if (isset($device['final_price'])) {
                $updateData['final_price'] = $device['final_price'];
            }

            $deviceModel->save($updateData);
        }

        return true;
    }

    /**
     * 更新所有设备状态
     * @param int $orderId 订单ID
     * @param int $orderStatus 订单状态
     * @return bool
     */
    public function updateAllDevicesStatus(int $orderId, int $orderStatus): bool
    {
        $statusMap = $this->getOrderToDeviceStatusMap();
        $deviceStatus = $statusMap[$orderStatus] ?? null;

        if ($deviceStatus === null) {
            return true;
        }

        // 批量更新设备状态（排除已退回的设备）
        RecycleDevice::where('order_id', $orderId)
            ->where('status', '<>', RecycleOrderDict::DEVICE_STATUS_RETURNED)
            ->update(['status' => $deviceStatus]);

        return true;
    }

    /**
     * 状态流转验证
     * @param int $currentStatus 当前状态
     * @param int $targetStatus 目标状态
     * @return bool
     */
    public function validateTransition(int $currentStatus, int $targetStatus): bool
    {
        return RecycleOrderDict::isValidStatusTransition($currentStatus, $targetStatus, 'order');
    }

    /**
     * 获取订单状态到设备状态的映射
     * @return array
     */
    private function getOrderToDeviceStatusMap(): array
    {
        return [
            RecycleOrderDict::ORDER_STATUS_PENDING_SIGN => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            RecycleOrderDict::ORDER_STATUS_SIGNED => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            RecycleOrderDict::ORDER_STATUS_CHECKING => RecycleOrderDict::DEVICE_STATUS_CHECKING,
            RecycleOrderDict::ORDER_STATUS_CHECKED => RecycleOrderDict::DEVICE_STATUS_CHECKED,
            RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT => RecycleOrderDict::DEVICE_STATUS_RECYCLED,
            RecycleOrderDict::ORDER_STATUS_COMPLETED => RecycleOrderDict::DEVICE_STATUS_RECYCLED,
        ];
    }

    /**
     * 添加订单状态变更日志
     * @param int $orderId 订单ID
     * @param int $oldStatus 旧状态
     * @param int $newStatus 新状态
     * @param string $remark 备注
     * @param int $operatorId 操作员ID
     * @return bool
     */
    private function addOrderLog(int $orderId, int $oldStatus, int $newStatus, string $remark = '', int $operatorId = 0): bool
    {
        $log = new RecycleOrderLog();
        $log->save([
            'order_id' => $orderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remark' => $remark,
            'operator_id' => $operatorId,
            'operator_name' => '',
            'create_at' => time()
        ]);
        return true;
    }
} 