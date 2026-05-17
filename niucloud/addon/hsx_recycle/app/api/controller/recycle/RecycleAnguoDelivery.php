<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\api\controller\recycle;

use addon\hsx_recycle\app\service\core\recycle_order\RecycleAnguoDeliveryService;
use core\base\BaseApiController;
use think\Response;

/**
 * 安果快递控制器
 * Class RecycleAnguoDelivery
 */
class RecycleAnguoDelivery extends BaseApiController
{
    /**
     * 获取可用预约时间
     * @return Response
     */
    public function getPickupTimes(): Response
    {
       
        try {
            $service = new RecycleAnguoDeliveryService();
            $result = $service->getAvailablePickupTimes();

            return success($result);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

    /**
     * 创建快递订单
     * @return Response
     */
    public function create(): Response
    {
        try {
            $data = $this->request->post();

            // 参数校验
            if (empty($data['order_id'])) {
                return fail('订单ID不能为空');
            }

            if (empty($data['sender_address'])) {
                return fail('寄件地址不能为空');
            }

            if (empty($data['pickup_time'])) {
                return fail('预约时间不能为空');
            }

            $service = new RecycleAnguoDeliveryService();

            $result = $service->createDeliveryOrder(
                (int)$data['order_id'],
                $data['sender_address'],
                $data['pickup_time'],
                (float)($data['weight'] ?? 1.0)
            );

            return success($result, '快递下单成功');
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

    /**
     * 取消快递订单
     * @return Response
     */
    public function cancel(): Response
    {
        try {
            $data = $this->request->post();

            if (empty($data['order_id'])) {
                return fail('订单ID不能为空');
            }

            $service = new RecycleAnguoDeliveryService();
            $service->cancelDelivery((int)$data['order_id']);

            return success([], '取消成功');
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

    /**
     * 同步快递状态
     * @return Response
     */
    public function syncStatus(): Response
    {
        try {
            $data = $this->request->post();

            if (empty($data['order_id'])) {
                return fail('订单ID不能为空');
            }

            $service = new RecycleAnguoDeliveryService();
            $result = $service->syncDeliveryStatus((int)$data['order_id']);

            return success($result, '同步成功');
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
}
