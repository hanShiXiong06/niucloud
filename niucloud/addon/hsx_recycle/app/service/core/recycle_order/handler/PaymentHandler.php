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

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 订单打款处理器
 *
 * 处理订单打款业务逻辑：
 * 1. 计算打款金额
 * 2. 记录打款信息
 * 3. 更新订单状态
 *
 * @package addon\hsx_recycle\app\service\core\recycle_order\handler
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
        // 即使绕过流程引擎直接调用，也必须在事务中重新读取并锁定真实订单。
        $siteId = self::positiveId($context['site_id'] ?? $order['site_id'] ?? 0);
        $orderId = self::positiveId($order['id'] ?? 0);
        if ($siteId <= 0 || $orderId <= 0 || self::positiveId($order['site_id'] ?? $siteId) !== $siteId) {
            throw new CommonException('付款订单或站点不正确');
        }
        return Db::transaction(function () use ($siteId, $orderId, $data, $context): array {
            $lockedOrder = RecycleOrder::where([
                ['site_id', '=', $siteId], ['id', '=', $orderId], ['delete_at', '=', 0],
            ])->lock(true)->findOrEmpty();
            if ($lockedOrder->isEmpty()) throw new CommonException('订单不存在或不属于当前站点');
            $devices = RecycleDevice::where([
                ['site_id', '=', $siteId], ['order_id', '=', $orderId],
            ])->order('id asc')->lock(true)->select()->toArray();
            $plan = $this->preparePayment($lockedOrder->toArray(), $devices, $data);
            return $this->recordPayment($siteId, $orderId, $plan, $data, $context);
        });
    }

    private function recordPayment(int $siteId, int $orderId, array $plan, array $data, array $context): array
    {
        $totalAmount = $plan['total_amount'];

        // 2. 记录打款信息（统一把可能为数组的字段转成字符串，避免存字符串列时 Array to string conversion）
        $requestPaymentInfo = is_array($data['payment_info'] ?? null) ? $data['payment_info'] : [];
        $toStr = static function ($v): string {
            return is_array($v) ? implode(',', array_map('strval', $v)) : (string)$v;
        };
        $paymentInfo = [
            'pay_time' => time(),
            'pay_account' => $totalAmount,
            'pay_type' => $toStr($requestPaymentInfo['pay_type'] ?? $data['pay_type'] ?? ''),
            'account' => $toStr($requestPaymentInfo['account'] ?? $data['account'] ?? ''),
            'payment_images' => $toStr($requestPaymentInfo['payment_images'] ?? $data['payment_images'] ?? ''),
            'operator_id' => $this->getOperatorId($context),
            'remark' => $toStr($requestPaymentInfo['remark'] ?? $data['remark'] ?? '')
        ];

        // 最终归属闸门必须位于本次事务、所有校验之后和付款事实写入之前。
        (new RecycleErpCapabilityService())->assertLocalPaymentAllowed($siteId, $orderId, $plan['device_ids']);

        // 3. 订单与设备付款事实一并保存，防止切换为设备模式后重复支付。
        foreach ($plan['amounts'] as $deviceId => $amount) {
            RecycleDevice::where([
                ['site_id', '=', $siteId], ['order_id', '=', $orderId], ['id', '=', $deviceId],
            ])->update([
                'pay_status' => RecycleOrderDict::PAY_STATUS_PAID,
                'pay_amount' => $amount,
                'pay_time' => $paymentInfo['pay_time'],
                'pay_uid' => $paymentInfo['operator_id'],
            ]);
        }
        RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', $orderId]])->update([
            'pay_time' => $paymentInfo['pay_time'],
            'pay_status' => 1,
            'pay_uid' => $paymentInfo['operator_id'],
            'total_amount' => $paymentInfo['pay_account'],
            'pay_type' => $paymentInfo['pay_type'],
            'pay_account' => $paymentInfo['account'],
            'payment_images' => $paymentInfo['payment_images'],
            'update_at' => time()
        ]);

        return $this->success('打款成功', [
            'payment_info' => $paymentInfo,
            'total_amount' => $totalAmount,
            'device_ids' => $plan['device_ids'],
        ]);
    }

    /** 纯校验：必须对锁定的全单设备执行，不得默默扣除已付设备后完成整单。 */
    protected function preparePayment(array $order, array $devices, array $data): array
    {
        if ((int)($order['pay_status'] ?? 0) !== RecycleOrderDict::PAY_STATUS_UNPAID
            || (int)($order['pay_time'] ?? 0) > 0) {
            throw new CommonException('订单已有付款事实，请勿重复整单打款');
        }
        if ((int)($order['status'] ?? 0) !== RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT) {
            throw new CommonException('只有待打款订单可以确认打款');
        }
        $amounts = [];
        foreach ($devices as $device) {
            if ((int)($device['site_id'] ?? 0) !== (int)$order['site_id']
                || (int)($device['order_id'] ?? 0) !== (int)$order['id']) {
                throw new CommonException('付款设备不属于当前站点订单');
            }
            if ((int)($device['pay_status'] ?? 0) !== RecycleOrderDict::PAY_STATUS_UNPAID
                || (float)($device['pay_amount'] ?? 0) > 0 || (int)($device['pay_time'] ?? 0) > 0) {
                throw new CommonException('订单内已有设备付款事实，禁止重复整单打款，请按设备核对');
            }
            if (in_array((int)($device['status'] ?? 0), [RecycleOrderDict::DEVICE_STATUS_RETURNED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED], true)
                || (int)($device['confirm_status'] ?? 0) === RecycleOrderDict::CONFIRM_STATUS_REJECTED
                || in_array((string)($device['dispose_type'] ?? ''), [RecycleOrderDict::DISPOSE_TYPE_RETURN, RecycleOrderDict::DISPOSE_TYPE_CONSIGN], true)) continue;
            $deviceId = self::positiveId($device['id'] ?? 0);
            if ($deviceId <= 0 || isset($amounts[$deviceId])) throw new CommonException('付款设备关联不正确');
            // 保留旧整单金额语义：存在最终价（即使为0）时不回退初始价。
            $price = $device['final_price'] ?? $device['initial_price'] ?? 0;
            $amount = round((float)$price, 2);
            if (!is_finite($amount) || $amount <= 0) throw new CommonException('设备金额必须大于0，无法确认整单打款');
            $amounts[$deviceId] = $amount;
        }
        if ($amounts === []) throw new CommonException('订单没有可打款设备');
        ksort($amounts, SORT_NUMERIC);
        $deviceIds = array_keys($amounts);
        if (array_key_exists('device_ids', $data)) {
            if (!is_array($data['device_ids']) || $data['device_ids'] === []) throw new CommonException('付款设备列表不正确');
            $selected = [];
            foreach ($data['device_ids'] as $rawId) {
                $id = self::positiveId($rawId);
                if ($id <= 0) throw new CommonException('付款设备ID必须是正整数，已拒绝整批付款');
                $selected[$id] = $id;
            }
            ksort($selected, SORT_NUMERIC);
            if (array_values($selected) !== $deviceIds) throw new CommonException('整单打款必须包含全部可付款设备，请使用按设备打款处理子集');
        }
        return ['device_ids' => $deviceIds, 'amounts' => $amounts, 'total_amount' => round(array_sum($amounts), 2)];
    }

    private static function positiveId($value): int
    {
        if (is_int($value)) return max(0, $value);
        if (!is_string($value) || !preg_match('/^[0-9]+$/', $value)) return 0;
        $value = ltrim($value, '0');
        $max = (string)PHP_INT_MAX;
        if ($value === '' || strlen($value) > strlen($max)
            || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) return 0;
        return (int)$value;
    }
}
