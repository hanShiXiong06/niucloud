<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\yisu;

use addon\recycle\app\service\core\ExpressOrderService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 易速快递控制器
 * Class Yisu
 * @package addon\recycle\app\adminapi\controller\yisu
 */
class Yisu extends BaseAdminController
{
    /**
     * 创建快递订单
     * @return Response
     */
    public function createOrder()
    {
        $params = $this->request->param();

        // 参数验证
        $requiredFields = ['product_code', 'sender_name', 'sender_mobile', 'sender_address',
                          'receiver_name', 'receiver_mobile', 'receiver_address'];

        foreach ($requiredFields as $field) {
            if (empty($params[$field])) {
                return fail("缺少必填参数: {$field}");
            }
        }

        try {
            // 构建订单参数
            $orderParams = [
                'deliveryType' => $params['product_code'],
                'senderName' => $params['sender_name'],
                'senderMobile' => $params['sender_mobile'],
                'senderProvince' => $params['sender_province'] ?? '',
                'senderCity' => $params['sender_city'] ?? '',
                'senderDistrict' => $params['sender_district'] ?? '',
                'senderAddress' => $params['sender_address'],
                'receiveName' => $params['receiver_name'],
                'receiveMobile' => $params['receiver_mobile'],
                'receiveProvince' => $params['receiver_province'] ?? '',
                'receiveCity' => $params['receiver_city'] ?? '',
                'receiveDistrict' => $params['receiver_district'] ?? '',
                'receiveAddress' => $params['receiver_address'],
                'goods' => $params['goods'] ?? '回收设备',
                'weight' => $params['weight'] ?? 1,
                'packageCount' => $params['package_count'] ?? 1,
                'guaranteeValueAmount' => $params['guarantee_value'] ?? 0,
                'vloumLong' => $params['volume_long'] ?? 0,
                'vloumWidth' => $params['volume_width'] ?? 0,
                'vloumHeight' => $params['volume_height'] ?? 0,
                'remark' => $params['remark'] ?? '',
                'recycle_order_id' => $params['recycle_order_id'] ?? 0,
                'recycle_device_id' => $params['recycle_device_id'] ?? 0,
            ];

            $service = new ExpressOrderService();
            $result = $service->createOrder($this->siteId, $orderParams);

            return success([
                'express_no' => $result['deliveryId'] ?? '',
                'order_no' => $result['orderNo'] ?? '',
            ], '快递订单创建成功');
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
}
