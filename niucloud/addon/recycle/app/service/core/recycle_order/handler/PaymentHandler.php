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
use addon\recycle\app\model\order\RecycleOrder;
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
     *   - pay_type: 支付方式
     *   - account: 收款账号
     *   - payment_images: 打款凭证图片
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

        // 2. 记录打款信息
        $requestPaymentInfo = is_array($data['payment_info'] ?? null) ? $data['payment_info'] : [];
        $paymentInfo = [
            'pay_time' => time(),
            'pay_account' => $totalAmount,
            'pay_type' => $requestPaymentInfo['pay_type'] ?? $data['pay_type'] ?? '',
            'account' => $requestPaymentInfo['account'] ?? $data['account'] ?? '',
            'payment_images' => $requestPaymentInfo['payment_images'] ?? $data['payment_images'] ?? '',
            'operator_id' => $this->getOperatorId($context),
            'remark' => $requestPaymentInfo['remark'] ?? $data['remark'] ?? ''
        ];

        // 3. 更新订单打款信息
        RecycleOrder::where('id', $order['id'])->update([
            'pay_time' => $paymentInfo['pay_time'],
            'total_amount' => $paymentInfo['pay_account'],
            'pay_type' => $paymentInfo['pay_type'],
            'pay_account' => $paymentInfo['account'],
            'payment_images' => $paymentInfo['payment_images'],
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
