<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\order;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderStatusService;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderEventService;
use addon\recycle\app\model\RecycleOrder;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 回收订单关闭服务
 * Class RecycleOrderCloseService
 * @package addon\recycle\app\service\admin\order
 */
class RecycleOrderCloseService extends BaseAdminService
{
    /**
     * 关闭订单
     * @param int $orderId 订单ID
     * @param array $data 关闭数据
     * @return bool
     * @throws CommonException
     */
    public function close(int $orderId, array $data = []): bool
    {
        try {
            // 1. 参数验证
            $this->validateCloseData($orderId, $data);

            // 2. 获取订单信息
            $order = RecycleOrder::findOrEmpty($orderId);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 3. 验证订单状态
            if (!in_array($order->status, [
                RecycleOrderDict::ORDER_STATUS_PENDING_SIGN,
                RecycleOrderDict::ORDER_STATUS_SIGNED,
                RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM
            ])) {
                throw new CommonException('ORDER_STATUS_ERROR');
            }

            // 4. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_CLOSED, [
                'remark' => $data['remark'] ?? '订单关闭',
                'operator_id' => $this->uid,
                'close_reason' => $data['close_reason'] ?? ''
            ]);

            // 5. 触发关闭后事件
            CoreRecycleOrderEventService::orderCloseAfter([
                'order_id' => $orderId,
                'site_id' => $this->site_id,
                'operator_id' => $this->uid,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 验证关闭数据
     * @param int $orderId
     * @param array $data
     * @throws CommonException
     */
    private function validateCloseData(int $orderId, array $data): void
    {
        if ($orderId <= 0) {
            throw new CommonException('INVALID_ORDER_ID');
        }
    }
} 