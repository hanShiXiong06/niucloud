<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\order;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderStatusService;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderEventService;
use addon\recycle\app\service\admin\recycle_order\RecycleDeviceService;
use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\model\RecycleDevice;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 回收订单质检服务
 * Class RecycleOrderCheckService
 * @package addon\recycle\app\service\admin\order
 */
class RecycleOrderCheckService extends BaseAdminService
{
    /**
     * 开始质检
     * @param int $orderId 订单ID
     * @param array $data 质检数据
     * @return bool
     * @throws CommonException
     */
    public function startCheck(int $orderId, array $data = []): bool
    {
        Db::startTrans();
        try {
            // 1. 参数验证
            $this->validateCheckData($orderId, $data);

            // 2. 获取订单信息
            $order = RecycleOrder::findOrEmpty($orderId);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 3. 验证订单状态
            if ($order->status != RecycleOrderDict::ORDER_STATUS_SIGNED) {
                throw new CommonException('ORDER_STATUS_ERROR');
            }

            // 4. 处理设备质检
            $this->handleDeviceCheck($orderId, $data['devices'] ?? [], 'start');

            // 5. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_CHECKING, [
                'remark' => $data['remark'] ?? '开始质检',
                'operator_id' => $this->uid,
                'devices' => $data['devices'] ?? []
            ]);

            // 6. 触发质检开始事件
            CoreRecycleOrderEventService::orderCheckAfter([
                'order_id' => $orderId,
                'site_id' => $this->site_id,
                'operator_id' => $this->uid,
                'action' => 'start_check',
                'data' => $data
            ]);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 完成质检
     * @param int $orderId 订单ID
     * @param array $data 质检数据
     * @return bool
     * @throws CommonException
     */
    public function completeCheck(int $orderId, array $data): bool
    {
        Db::startTrans();
        try {
            // 1. 参数验证
            $this->validateCheckData($orderId, $data);

            // 2. 获取订单信息
            $order = RecycleOrder::findOrEmpty($orderId);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 3. 验证订单状态
            if ($order->status != RecycleOrderDict::ORDER_STATUS_CHECKING) {
                throw new CommonException('ORDER_STATUS_ERROR');
            }

            // 4. 处理设备质检结果
            $this->handleDeviceCheck($orderId, $data['devices'] ?? [], 'complete');

            // 5. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_CHECKED, [
                'remark' => $data['remark'] ?? '质检完成',
                'operator_id' => $this->uid,
                'devices' => $data['devices'] ?? []
            ]);

            // 6. 触发质检完成事件
            CoreRecycleOrderEventService::orderCheckAfter([
                'order_id' => $orderId,
                'site_id' => $this->site_id,
                'operator_id' => $this->uid,
                'action' => 'complete_check',
                'data' => $data
            ]);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 验证质检数据
     * @param int $orderId
     * @param array $data
     * @throws CommonException
     */
    private function validateCheckData(int $orderId, array $data): void
    {
        if ($orderId <= 0) {
            throw new CommonException('INVALID_ORDER_ID');
        }
    }

    /**
     * 处理设备质检
     * @param int $orderId
     * @param array $devices
     * @param string $action
     * @throws CommonException
     */
    private function handleDeviceCheck(int $orderId, array $devices, string $action): void
    {
        $deviceService = new RecycleDeviceService();

        if (empty($devices)) {
            // 如果没有指定设备，则处理所有设备
            $allDevices = RecycleDevice::where('order_id', $orderId)->select();
            foreach ($allDevices as $device) {
                if ($action === 'start') {
                    $deviceService->startCheck($device->id, '开始质检');
                } elseif ($action === 'complete') {
                    $deviceService->completeCheck($device->id, [], '质检完成');
                }
            }
        } else {
            // 只处理指定设备
            foreach ($devices as $device) {
                if (isset($device['id'])) {
                    if ($action === 'start') {
                        $deviceService->startCheck($device['id'], $device['remark'] ?? '开始质检');
                    } elseif ($action === 'complete') {
                        $checkData = [
                            'check_result' => $device['check_result'] ?? '',
                            'check_images' => $device['check_images'] ?? '',
                        ];
                        $deviceService->completeCheck($device['id'], $checkData, $device['remark'] ?? '质检完成');
                    }
                }
            }
        }
    }
} 