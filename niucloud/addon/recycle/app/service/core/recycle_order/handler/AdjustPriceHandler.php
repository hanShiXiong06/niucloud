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

namespace addon\recycle\app\service\core\recycle_order\handler;

use addon\recycle\app\model\order\RecycleDevice;
use core\exception\CommonException;

/**
 * 调整价格处理器
 *
 * 处理价格调整业务逻辑（用于议价后的价格调整）：
 * 1. 验证设备信息
 * 2. 更新设备调整后的价格
 * 3. 记录调价原因和时间
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class AdjustPriceHandler extends BaseFlowHandler
{
    /**
     * 处理调整价格操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - devices: 设备价格信息数组 [['device_id' => 1, 'final_price' => 120], ...]
     *   - adjust_reason: 调价原因（可选）
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
                    'adjust_reason' => $data['adjust_reason'] ?? '议价调整',
                    'adjust_time' => time(),
                    'update_at' => time()
                ]);

            $updatedDevices[] = [
                'device_id' => $deviceData['device_id'],
                'final_price' => $finalPrice
            ];
            $totalPrice += floatval($finalPrice);
        }

        return $this->success('价格调整成功', [
            'updated_devices' => $updatedDevices,
            'total_price' => $totalPrice,
            'adjust_reason' => $data['adjust_reason'] ?? '议价调整',
            'operator_id' => $this->getOperatorId($context)
        ]);
    }
}
