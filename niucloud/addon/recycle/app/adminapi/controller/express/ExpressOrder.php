<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\express;

use addon\recycle\app\service\core\ExpressOrderService;
use addon\recycle\app\service\core\express\RecycleExpressService;
use core\base\BaseAdminController;

/**
 * 快递下单控制器
 * Class ExpressOrder
 * @package addon\recycle\app\adminapi\controller\express
 */
class ExpressOrder extends BaseAdminController
{
    /**
     * 获取快递报价
     * @return \think\Response
     */
    public function quote()
    {
        $data = $this->request->params([
            ['senderProvince', ''],
            ['senderCity', ''],
            ['senderDistrict', ''],
            ['senderAddress', ''],
            ['receiveProvince', ''],
            ['receiveCity', ''],
            ['receiveDistrict', ''],
            ['receiveAddress', ''],
            ['weight', 1],
            ['packageCount', 1],
            ['vloumLong', 0],
            ['vloumWidth', 0],
            ['vloumHeight', 0],
            ['guaranteeValueAmount', 0],
            ['customerType', ''],
        ]);

        $service = new ExpressOrderService();
        $result = $service->getQuote($this->site_id, $data);

        return success($result);
    }

    /**
     * 创建快递订单
     * @return \think\Response
     */
    public function create()
    {
        $data = $this->request->params([
            ['deliveryType', ''],           // 快递类型
            ['senderName', ''],
            ['senderMobile', ''],
            ['senderProvince', ''],
            ['senderCity', ''],
            ['senderDistrict', ''],
            ['senderAddress', ''],
            ['receiveName', ''],
            ['receiveMobile', ''],
            ['receiveProvince', ''],
            ['receiveCity', ''],
            ['receiveDistrict', ''],
            ['receiveAddress', ''],
            ['goods', ''],                  // 物品名称
            ['weight', 1],
            ['packageCount', 1],
            ['vloumLong', 0],
            ['vloumWidth', 0],
            ['vloumHeight', 0],
            ['guaranteeValueAmount', 0],    // 保价金额
        ]);

        $service = new ExpressOrderService();
        $result = $service->createOrder($this->site_id, $data);

        return success($result, '下单成功');
    }

    /**
     * 取消快递订单
     * @return \think\Response
     */
    public function cancel()
    {
        $orderNo = $this->request->param('order_no', '');

        if (empty($orderNo)) {
            return fail('订单号不能为空');
        }

        $service = new ExpressOrderService();
        $service->cancelOrder($this->site_id, $orderNo);

        return success([], '取消成功');
    }

    /**
     * 查询快递轨迹
     * @return \think\Response
     */
    public function track()
    {
        $deliveryId = $this->request->param('delivery_id', '');

        if (empty($deliveryId)) {
            return fail('运单号不能为空');
        }

        $service = new ExpressOrderService();
        $result = $service->trackOrder($this->site_id, $deliveryId);

        return success($result);
    }

    /**
     * 查询账户余额
     * @return \think\Response
     */
    public function balance()
    {
        $service = new ExpressOrderService();
        $balance = $service->getBalance($this->site_id);

        return success(['balance' => $balance]);
    }

    // ==================== 统一快递服务接口（基于RecycleExpressService） ====================

    /**
     * 为回收订单创建快递单（管理员操作）
     * @return \think\Response
     */
    public function createForOrder()
    {
        $data = $this->request->params([
            ['recycle_order_id', 0],
            ['sender_name', ''],
            ['sender_mobile', ''],
            ['sender_province', ''],
            ['sender_city', ''],
            ['sender_district', ''],
            ['sender_address', ''],
            ['product_code', ''],
            ['weight', 1.0],
            ['package_count', 1],
            ['pickup_time', ''],
            ['estimated_cost', 0],
        ]);

        if (empty($data['recycle_order_id'])) {
            return fail('缺少回收订单ID');
        }

        if (empty($data['sender_name']) || empty($data['sender_mobile'])) {
            return fail('请填写寄件人信息');
        }

        $expressService = new RecycleExpressService();

        $operatorInfo = [
            'uid' => $this->uid,
            'username' => $this->request->adminInfo()['username'] ?? '',
            'source' => 'admin',
            'member_id' => 0,
        ];

        $expressConfig = [
            'sender_name' => $data['sender_name'],
            'sender_mobile' => $data['sender_mobile'],
            'sender_province' => $data['sender_province'],
            'sender_city' => $data['sender_city'],
            'sender_district' => $data['sender_district'],
            'sender_address' => $data['sender_address'],
            'product_code' => $data['product_code'],
            'weight' => $data['weight'],
            'package_count' => $data['package_count'],
            'pickup_time' => $data['pickup_time'],
            'estimated_cost' => $data['estimated_cost'],
        ];

        $result = $expressService->createOrder(
            $this->site_id,
            (int)$data['recycle_order_id'],
            $expressConfig,
            $operatorInfo
        );

        return success($result, '下单成功');
    }

    /**
     * 取消回收订单的快递单（管理员操作）
     * @return \think\Response
     */
    public function cancelForOrder()
    {
        $recycleOrderId = $this->request->param('recycle_order_id', 0);

        if (empty($recycleOrderId)) {
            return fail('缺少回收订单ID');
        }

        $expressService = new RecycleExpressService();

        $operatorInfo = [
            'uid' => $this->uid,
            'username' => $this->request->adminInfo()['username'] ?? '',
            'source' => 'admin',
            'member_id' => 0,
        ];

        $expressService->cancelOrder(
            $this->site_id,
            (int)$recycleOrderId,
            $operatorInfo
        );

        return success([], '取消成功');
    }

    /**
     * 查询回收订单的快递轨迹（管理员操作）
     * @return \think\Response
     */
    public function trackForOrder()
    {
        $recycleOrderId = $this->request->param('recycle_order_id', 0);

        if (empty($recycleOrderId)) {
            return fail('缺少回收订单ID');
        }

        $expressService = new RecycleExpressService();

        $result = $expressService->trackOrder(
            $this->site_id,
            (int)$recycleOrderId
        );

        return success($result);
    }

    /**
     * 获取统一快递报价（管理员操作）
     * @return \think\Response
     */
    public function unifiedQuote()
    {
        $data = $this->request->params([
            ['sender_name', ''],
            ['sender_mobile', ''],
            ['sender_province', ''],
            ['sender_city', ''],
            ['sender_district', ''],
            ['sender_address', ''],
            ['weight', 1.0],
            ['package_count', 1],
        ]);

        if (empty($data['sender_province']) || empty($data['sender_city']) || empty($data['sender_address'])) {
            return fail('请填写完整的寄件地址');
        }

        $expressService = new RecycleExpressService();

        $senderAddress = [
            'name' => $data['sender_name'],
            'mobile' => $data['sender_mobile'],
            'province' => $data['sender_province'],
            'city' => $data['sender_city'],
            'district' => $data['sender_district'],
            'address' => $data['sender_address'],
        ];

        $result = $expressService->getQuote(
            $this->site_id,
            $senderAddress,
            (float)$data['weight'],
            (int)$data['package_count']
        );

        return success($result);
    }
}
