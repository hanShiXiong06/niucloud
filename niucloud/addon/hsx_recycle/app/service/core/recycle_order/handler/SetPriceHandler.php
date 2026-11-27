<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\hsx_recycle\app\service\core\recycle_order\handler;

use addon\hsx_recycle\app\model\order\RecycleDevice;
use core\exception\CommonException;

/**
 * 设置价格处理器
 *
 * 处理设置设备价格业务逻辑：
 * 1. 验证设备信息
 * 2. 更新设备最终价格
 * 3. 记录定价时间
 *
 * @package addon\hsx_recycle\app\service\core\recycle_order\handler
 */
class SetPriceHandler extends BaseFlowHandler
{
    /**
     * 处理设置价格操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - devices: 设备价格信息数组 [['device_id' => 1, 'final_price' => 100], ...]
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        if (empty($data['devices'])) {
            throw new CommonException('请提供设备价格信息');
        }

        $updatedDevices = [];
        $totalPrice = 0;

        foreach ($data['devices'] as $deviceData) {
            if (empty($deviceData['device_id'])) {
                continue;
            }

            $finalPrice = $deviceData['final_price'] ?? 0;
            
            // 更新设备价格
            RecycleDevice::where('id', $deviceData['device_id'])
                ->where('order_id', $order['id'])
                ->update([
                    'final_price' => $finalPrice,
                    'price_remark' => $deviceData['remark'] ?? '',
                    'price_time' => time(),
                    'update_at' => time()
                ]);

            $updatedDevices[] = [
                'device_id' => $deviceData['device_id'],
                'final_price' => $finalPrice
            ];
            $totalPrice += floatval($finalPrice);
        }

        return $this->success('价格设置成功', [
            'updated_devices' => $updatedDevices,
            'total_price' => $totalPrice,
            'operator_id' => $this->getOperatorId($context)
        ]);
    }
}
