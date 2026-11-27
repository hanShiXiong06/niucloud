<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderStatusService;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderEventService;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use addon\hsx_recycle\app\model\RecycleOrder;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use app\service\core\pay\CoreTransferService;
use app\service\core\site\CoreSiteAccountService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单支付服务
 * Class RecycleOrderPaymentService
 * @package addon\hsx_recycle\app\service\admin\order
 */
class RecycleOrderPaymentService extends BaseAdminService
{
    /**
     * 支付订单
     * @param int $orderId 订单ID
     * @param array $data 支付数据
     * @return bool
     * @throws CommonException
     */
    public function payment(int $orderId, array $data): bool
    {
        Log::info('【回收打款】开始执行payment方法', [
            'order_id' => $orderId,
            'data' => $data,
            'operator_uid' => $this->uid,
            'site_id' => $this->site_id
        ]);
        
        Db::startTrans();
        try {
            // 1. 参数验证
            $this->validatePaymentData($orderId, $data);

            // 2. 获取订单信息
            $order = RecycleOrder::where([
                ['site_id', '=', $this->site_id], ['id', '=', $orderId], ['delete_at', '=', 0],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 3. 验证订单状态
            if (!in_array($order->status, [
                RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,
                RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT
            ])) {
                throw new CommonException('ORDER_STATUS_ERROR');
            }

            $plan = $this->lockPaymentPlan($order, $data);
            // 全批校验后、首次付款写入前，在当前事务内确认真实付款归属。
            $this->assertLocalPaymentAllowed($orderId, $plan['device_ids']);
            // 4. 更新订单及实际付款设备的信息
            $this->updateOrderPaymentInfo($order, $data);
            $this->updateDevicePaymentInfo($order, $plan);

            // 5. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_COMPLETED, [
                'remark' => $data['remark'] ?? '订单支付完成',
                'operator_id' => $this->uid,
                'devices' => array_map(static fn(int $id): array => ['id' => $id], $plan['device_ids']),
            ]);

            // 6. 创建财务转账记录
            $this->createTransferRecord($order, $data, '订单支付', $plan);

            // 7. 触发支付后事件
            CoreRecycleOrderEventService::orderPayAfter([
                'order_id' => $orderId,
                'site_id' => $this->site_id,
                'operator_id' => $this->uid,
                'data' => $data
            ]);

            Db::commit();
            Log::info('【回收打款】payment方法执行成功', ['order_id' => $orderId]);
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('【回收打款】payment方法执行失败', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 确认打款
     * @param int $orderId 订单ID
     * @param array $data 确认数据
     * @return bool
     * @throws CommonException
     */
    public function confirmPayment(int $orderId, array $data): bool
    {
        Log::info('【回收打款】开始执行confirmPayment方法', [
            'order_id' => $orderId,
            'data' => $data,
            'operator_uid' => $this->uid,
            'site_id' => $this->site_id
        ]);
        
        Db::startTrans();
        try {
            // 1. 参数验证
            $this->validatePaymentData($orderId, $data);

            // 2. 获取订单信息
            $order = RecycleOrder::where([
                ['site_id', '=', $this->site_id], ['id', '=', $orderId], ['delete_at', '=', 0],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            Log::info('【回收打款】订单信息获取成功', [
                'order_id' => $orderId,
                'order_no' => $order->order_no,
                'current_status' => $order->status,
                'customer_name' => $order->customer_name
            ]);

            // 3. 验证订单状态
            if ($order->status != RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT) {
                throw new CommonException('ORDER_STATUS_ERROR');
            }

            $plan = $this->lockPaymentPlan($order, $data);
            // 与直接付款共用最后闸门，确认接口不能绕过设备归属与重复付款检查。
            $this->assertLocalPaymentAllowed($orderId, $plan['device_ids']);
            // 4. 更新订单及实际付款设备的信息
            $this->updateOrderPaymentInfo($order, $data);
            $this->updateDevicePaymentInfo($order, $plan);

            // 5. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_COMPLETED, [
                'remark' => $data['remark'] ?? '确认打款完成',
                'operator_id' => $this->uid,
                'devices' => array_map(static fn(int $id): array => ['id' => $id], $plan['device_ids']),
            ]);

            // 6. 创建财务转账记录
            $this->createTransferRecord($order, $data, '确认打款', $plan);

            // 7. 触发支付后事件
            CoreRecycleOrderEventService::orderPayAfter([
                'order_id' => $orderId,
                'site_id' => $this->site_id,
                'operator_id' => $this->uid,
                'action' => 'confirm_payment',
                'data' => $data
            ]);

            Db::commit();
            Log::info('【回收打款】confirmPayment方法执行成功', ['order_id' => $orderId]);
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('【回收打款】confirmPayment方法执行失败', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new CommonException($e->getMessage());
        }
    }

    private function assertLocalPaymentAllowed(int $orderId, array $deviceIds): void
    {
        (new RecycleErpCapabilityService())->assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds);
    }

    private function lockPaymentPlan(RecycleOrder $order, array $data): array
    {
        $devices = RecycleDevice::where([
            ['site_id', '=', $this->site_id], ['order_id', '=', $order->id],
        ])->order('id asc')->lock(true)->select()->toArray();
        return $this->preparePayment($order->toArray(), $devices, $data);
    }

    /** 全单校验不扣除已付设备继续付款；退回和代卖不进入本次付款集合。 */
    protected function preparePayment(array $order, array $devices, array $data): array
    {
        if ((int)($order['pay_status'] ?? 0) !== RecycleOrderDict::PAY_STATUS_UNPAID
            || (int)($order['pay_time'] ?? 0) > 0) {
            throw new CommonException('订单已有付款事实，请勿重复整单打款');
        }
        if (!in_array((int)($order['status'] ?? 0), [RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM, RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT], true)) {
            throw new CommonException('订单状态不允许付款，请勿重复确认已完成订单');
        }
        $amounts = [];
        $paymentDevices = [];
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
            $deviceId = RecycleDevicePaymentService::normalizePaymentDeviceIds([$device['id'] ?? 0])[0];
            if (isset($amounts[$deviceId])) throw new CommonException('付款设备关联不正确');
            // 保留此入口原来按最终价生成真实转账记录的金额口径。
            $amount = round((float)($device['final_price'] ?? 0), 2);
            if (!is_finite($amount) || $amount <= 0) throw new CommonException('设备最终金额必须大于0，无法确认整单打款');
            $amounts[$deviceId] = $amount;
            $paymentDevices[$deviceId] = $device;
        }
        if ($amounts === []) throw new CommonException('订单没有可打款设备');
        ksort($amounts, SORT_NUMERIC);
        $deviceIds = array_keys($amounts);
        if (array_key_exists('device_ids', $data)
            && RecycleDevicePaymentService::normalizePaymentDeviceIds($data['device_ids']) !== $deviceIds) {
            throw new CommonException('整单打款必须包含全部可付款设备，请使用按设备打款处理子集');
        }
        return ['device_ids' => $deviceIds, 'amounts' => $amounts,
            'devices' => array_values($paymentDevices), 'total_amount' => round(array_sum($amounts), 2)];
    }

    private function updateDevicePaymentInfo(RecycleOrder $order, array $plan): void
    {
        foreach ($plan['amounts'] as $deviceId => $amount) {
            RecycleDevice::where([
                ['site_id', '=', $this->site_id], ['order_id', '=', $order->id], ['id', '=', $deviceId],
            ])->update([
                'pay_status' => RecycleOrderDict::PAY_STATUS_PAID,
                'pay_amount' => $amount,
                'pay_time' => $order->pay_time,
                'pay_uid' => $this->uid,
                'update_at' => time(),
            ]);
        }
    }

    /**
     * 创建财务转账记录
     * @param RecycleOrder $order 订单信息
     * @param array $data 打款数据
     * @param string $action 操作类型
     * @param array $plan 已锁定并确认的实际付款设备和金额
     * @return void
     */
    private function createTransferRecord(RecycleOrder $order, array $data, string $action, array $plan): void
    {
        Log::info('【转账记录】开始创建转账记录', [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'action' => $action,
            'data' => $data,
            'site_id' => $this->site_id,
            'operator_uid' => $this->uid
        ]);
        
        try {
            // 步骤1: 计算总金额
            Log::info('【转账记录】步骤1：计算设备总金额');
            
            Log::info('【转账记录】订单设备信息', [
                'devices_count' => count($plan['devices']),
                'devices_data' => $plan['devices']
            ]);
            
            $totalAmount = $plan['total_amount'];
            Log::info('【转账记录】金额计算结果', [
                'total_amount' => $totalAmount,
                'amount_type' => gettype($totalAmount)
            ]);
            
            if ($totalAmount <= 0) {
                Log::warning('【转账记录】金额为0，跳过转账记录创建', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'total_amount' => $totalAmount
                ]);
                return;
            }
            
            // 步骤2: 检查CoreTransferService类
            Log::info('【转账记录】步骤2：检查CoreTransferService类');
            
            if (!class_exists('\\app\\service\\core\\pay\\CoreTransferService')) {
                Log::error('【转账记录】CoreTransferService类不存在');
                throw new \Exception('CoreTransferService类不存在');
            }
            
            // 步骤3: 创建转账服务实例
            Log::info('【转账记录】步骤3：创建CoreTransferService实例');
            $transferService = new CoreTransferService();
            Log::info('【转账记录】CoreTransferService实例创建成功');
            
            // 步骤4: 构建转账备注
            Log::info('【转账记录】步骤4：构建转账备注');
            $remark = sprintf(
                '%s - 订单号：%s，收款人：%s，金额：%.2f元',
                $action,
                $order->order_no,
                $data['pay_name'] ?? $order->customer_name,
                $totalAmount
            );
            
            // 添加支付方式信息
            if (!empty($data['pay_type'])) {
                $remark .= '，支付方式：' . $data['pay_type'];
            }
            
            // 添加支付账号信息
            if (!empty($data['pay_account'])) {
                $remark .= '，支付账号：' . $data['pay_account'];
            }
            
            // 添加支付备注
            if (!empty($data['pay_remark'])) {
                $remark .= '，备注：' . $data['pay_remark'];
            }
            
            Log::info('【转账记录】转账备注构建完成', ['remark' => $remark]);
            
            // 步骤5: 调用create方法创建转账记录
            Log::info('【转账记录】步骤5：调用create方法', [
                'site_id' => $this->site_id,
                'main_type' => 'recycle_order',
                'main_id' => $order->id,
                'amount' => $totalAmount,
                'trade_type' => 'recycle_payment',
                'remark' => $remark
            ]);
            
            $transferNo = $transferService->create(
                $this->site_id,
                'recycle_order',        // 主业务类型
                $order->id,             // 主业务ID
                (float)$totalAmount,    // 转账金额，确保为float类型
                'recycle_payment',      // 业务场景类型
                $remark                 // 转账备注
            );
            
            Log::info('【转账记录】create方法调用完成', [
                'transfer_no' => $transferNo,
                'result_type' => gettype($transferNo)
            ]);
            
            if ($transferNo) {
                Log::info('【转账记录】转账记录创建成功！', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'transfer_no' => $transferNo,
                    'amount' => $totalAmount,
                    'action' => $action
                ]);
                
                // 步骤6: 创建转账流水记录
                Log::info('【转账记录】步骤6：创建转账流水记录');
                try {
                    $siteAccountService = new CoreSiteAccountService();
                    $accountLogId = $siteAccountService->addTransferLog($this->site_id, $transferNo);
                    
                    Log::info('【转账记录】转账流水记录创建成功！', [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'transfer_no' => $transferNo,
                        'account_log_id' => $accountLogId,
                        'site_id' => $this->site_id
                    ]);
                } catch (\Exception $e) {
                    Log::error('【转账记录】转账流水记录创建失败', [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'transfer_no' => $transferNo,
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine()
                    ]);
                }
            } else {
                Log::error('【转账记录】转账记录创建失败：返回值为空', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'transfer_no' => $transferNo
                ]);
            }
            
        } catch (\Exception $e) {
            // 转账记录创建失败不影响主流程，只记录日志
            Log::error('【转账记录】转账记录创建异常', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'action' => $action
            ]);
        }
    }

    /**
     * 验证支付数据
     * @param int $orderId
     * @param array $data
     * @throws CommonException
     */
    private function validatePaymentData(int $orderId, array $data): void
    {
        if ($orderId <= 0) {
            throw new CommonException('INVALID_ORDER_ID');
        }

        // 验证支付方式
        if (isset($data['pay_type']) && !in_array($data['pay_type'], [1, 2, 3, 4,'微信','支付宝','银行卡'])) {
            throw new CommonException('INVALID_PAY_TYPE');
        }
    }

    /**
     * 更新订单支付信息
     * @param RecycleOrder $order
     * @param array $data
     */
    private function updateOrderPaymentInfo(RecycleOrder $order, array $data): void
    {
        Log::info('【回收打款】更新订单支付信息', [
            'order_id' => $order->id,
            'pay_uid' => $this->uid,
            'data' => $data
        ]);
        
        $updateData = [
            'pay_status' => 1, // 1-已付款
            'pay_time' => time(),
            'pay_uid' => $this->uid // 记录打款操作人员
        ];

        if (isset($data['pay_account'])) {
            $updateData['pay_account'] = $data['pay_account'];
        }
        if (isset($data['pay_type'])) {
            $updateData['pay_type'] = $data['pay_type'];
        }
        if (isset($data['pay_name'])) {
            $updateData['pay_name'] = $data['pay_name'];
        }
        if (isset($data['pay_remark'])) {
            $updateData['pay_remark'] = $data['pay_remark'];
        }
        if (isset($data['pay_url'])) {
            $updateData['pay_url'] = $data['pay_url'];
        }

        $order->save($updateData);
        
        Log::info('【回收打款】订单支付信息更新完成', [
            'order_id' => $order->id,
            'update_data' => $updateData
        ]);
    }
}
