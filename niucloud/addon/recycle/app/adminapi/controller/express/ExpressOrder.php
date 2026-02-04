<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\express;

use addon\recycle\app\service\core\ExpressOrderService;
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
}
