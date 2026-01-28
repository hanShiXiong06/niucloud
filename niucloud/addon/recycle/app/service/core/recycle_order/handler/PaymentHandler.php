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
 * 订单打款处理器
 *
 * 处理订单打款业务逻辑：
 * 1. 计算打款金额
 * 2. 记录打款信息
 * 3. 更新订单状态
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class PaymentHandler extends BaseFlowHandler
{
    /**
     * 处理打款操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - payment_info: 打款信息（支付方式、交易号等）
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 1. 计算订单总金额
        $totalAmount = $this->calculateTotalAmount($order['id']);

        if ($totalAmount <= 0) {
            throw new CommonException('订单金额为0，无法打款');
        }

        // 2. 验证打款信息
        if (empty($data['payment_info'])) {
            throw new CommonException('缺少打款信息');
        }

        // 3. 记录打款信息
        $paymentInfo = [
            'payment_time' => time(),
            'payment_amount' => $totalAmount,
            'payment_method' => $data['payment_info']['method'] ?? 'transfer',
            'transaction_no' => $data['payment_info']['transaction_no'] ?? '',
            'operator_id' => $this->getOperatorId($context),
            'remark' => $data['remark'] ?? ''
        ];

        // 4. 更新订单打款信息（这里可以扩展为更新订单表的打款字段）
        RecycleOrder::where('id', $order['id'])->update([
            'payment_time' => $paymentInfo['payment_time'],
            'payment_amount' => $paymentInfo['payment_amount'],
            'update_at' => time()
        ]);

        return $this->success('打款成功', [
            'payment_info' => $paymentInfo,
            'total_amount' => $totalAmount
        ]);
    }

    /**
     * 计算订单总金额
     *
     * @param int $orderId 订单ID
     * @return float 总金额
     */
    private function calculateTotalAmount(int $orderId): float
    {
        // 获取订单所有设备的最终价格
        $devices = RecycleDevice::where('order_id', $orderId)->select()->toArray();

        $totalAmount = 0;
        foreach ($devices as $device) {
            // 优先使用最终价格，如果没有则使用初始价格
            $price = $device['final_price'] ?? $device['initial_price'] ?? 0;
            $totalAmount += floatval($price);
        }

        return $totalAmount;
    }
}
