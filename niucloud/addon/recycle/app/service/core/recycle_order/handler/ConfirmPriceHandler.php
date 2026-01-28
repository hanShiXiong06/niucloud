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

use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrder;
use core\exception\CommonException;

/**
 * 用户确认价格处理器
 *
 * 处理用户确认价格业务逻辑：
 * 1. 计算订单总价
 * 2. 记录用户确认时间
 * 3. 更新订单状态
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class ConfirmPriceHandler extends BaseFlowHandler
{
    /**
     * 处理确认价格操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 1. 计算订单总价
        $devices = RecycleDevice::where('order_id', $order['id'])->select()->toArray();
        
        if (empty($devices)) {
            throw new CommonException('订单没有设备信息');
        }

        $totalPrice = 0;
        foreach ($devices as $device) {
            $price = $device['final_price'] ?? $device['initial_price'] ?? 0;
            $totalPrice += floatval($price);
        }

        // 2. 记录确认信息
        $confirmInfo = [
            'price_confirm_time' => time(),
            'member_id' => $this->getOperatorId($context),
            'total_price' => $totalPrice,
            'remark' => $data['remark'] ?? ''
        ];

        // 3. 更新订单信息
        RecycleOrder::where('id', $order['id'])->update([
            'confirm_time' => $confirmInfo['price_confirm_time'],
            'update_at' => time()
        ]);

        return $this->success('已确认价格', [
            'confirm_info' => $confirmInfo,
            'total_price' => $totalPrice
        ]);
    }
}
