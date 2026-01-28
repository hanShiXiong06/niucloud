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
 * 回收订单定价服务
 * Class RecycleOrderPriceService
 * @package addon\recycle\app\service\admin\order
 */
class RecycleOrderPriceService extends BaseAdminService
{
    /**
     * 确认价格
     * @param int $orderId 订单ID
     * @param array $data 定价数据
     * @return bool
     * @throws CommonException
     */
    public function confirmPrice(int $orderId, array $data): bool
    {
        Db::startTrans();
        try {
            // 1. 参数验证
            $this->validatePriceData($orderId, $data);

            // 2. 获取订单信息
            $order = RecycleOrder::findOrEmpty($orderId);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 3. 验证订单状态
            if ($order->status != RecycleOrderDict::ORDER_STATUS_CHECKED) {
                throw new CommonException('ORDER_STATUS_ERROR');
            }

            // 4. 验证设备状态
            $this->validateDevicesForPricing($orderId);

            // 5. 处理设备定价
            $this->handleDevicePricing($orderId, $data['devices'] ?? []);

            // 6. 如果有付款凭证，直接完成交易
            if (!empty($data['pay_remark']) || !empty($data['pay_url'])) {
                return $this->processPayment($orderId, $data);
            }

            // 7. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM, [
                'remark' => $data['remark'] ?? '价格确认，等待用户确认',
                'operator_id' => $this->uid,
                'devices' => $data['devices'] ?? []
            ]);

            // 8. 更新订单支付信息
            $this->updateOrderPaymentInfo($order, $data);

            // 9. 触发定价后事件
            CoreRecycleOrderEventService::orderPriceAfter([
                'order_id' => $orderId,
                'site_id' => $this->site_id,
                'operator_id' => $this->uid,
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
     * 处理支付
     * @param int $orderId
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    private function processPayment(int $orderId, array $data): bool
    {
        $paymentService = new RecycleOrderPaymentService();
        return $paymentService->payment($orderId, $data);
    }

    /**
     * 验证定价数据
     * @param int $orderId
     * @param array $data
     * @throws CommonException
     */
    private function validatePriceData(int $orderId, array $data): void
    {
        if ($orderId <= 0) {
            throw new CommonException('INVALID_ORDER_ID');
        }

        // 验证设备定价数据
        if (!empty($data['devices'])) {
            foreach ($data['devices'] as $device) {
                if (isset($device['final_price']) && $device['final_price'] < 0) {
                    throw new CommonException('INVALID_DEVICE_PRICE');
                }
            }
        }
    }

    /**
     * 验证设备是否可以定价
     * @param int $orderId
     * @throws CommonException
     */
    private function validateDevicesForPricing(int $orderId): void
    {
        $devices = RecycleDevice::where('order_id', $orderId)->select()->toArray();
        
        if (empty($devices)) {
            throw new CommonException('NO_DEVICES_FOUND');
        }

        // 检查是否有设备未完成质检
        foreach ($devices as $device) {
            if (!in_array($device['status'], [
                RecycleOrderDict::DEVICE_STATUS_CHECKED,
                RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM
            ])) {
                throw new CommonException('DEVICE_NOT_INSPECTED');
            }
        }
    }

    /**
     * 处理设备定价
     * @param int $orderId
     * @param array $devices
     * @throws CommonException
     */
    private function handleDevicePricing(int $orderId, array $devices): void
    {
        if (empty($devices)) {
            return;
        }

        $deviceService = new RecycleDeviceService();

        foreach ($devices as $device) {
            if (isset($device['id']) && isset($device['final_price'])) {
                $priceData = [
                    'final_price' => $device['final_price'],
                    'price_remark' => $device['price_remark'] ?? '',
                    'price_uid' => $this->uid
                ];
                
                $deviceService->updatePrice($device['id'], $priceData);
            }
        }
    }

    /**
     * 更新订单支付信息
     * @param RecycleOrder $order
     * @param array $data
     */
    private function updateOrderPaymentInfo(RecycleOrder $order, array $data): void
    {
        $updateData = [];

        if (isset($data['pay_account'])) {
            $updateData['pay_account'] = $data['pay_account'];
        }
        if (isset($data['pay_type'])) {
            $updateData['pay_type'] = $data['pay_type'];
        }
        if (isset($data['pay_name'])) {
            $updateData['pay_name'] = $data['pay_name'];
        }
        if (isset($data['pay_remark'])) {
            $updateData['pay_remark'] = $data['pay_remark'];
        }

        if (!empty($updateData)) {
            $updateData['pay_status'] = 0; // 0-未付款
            $order->save($updateData);
        }
    }
} 