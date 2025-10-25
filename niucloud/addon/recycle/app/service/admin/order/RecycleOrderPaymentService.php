<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\order;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderStatusService;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderEventService;
use addon\recycle\app\model\RecycleOrder;
use app\service\core\pay\CoreTransferService;
use app\service\core\site\CoreSiteAccountService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单支付服务
 * Class RecycleOrderPaymentService
 * @package addon\recycle\app\service\admin\order
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
            $order = RecycleOrder::findOrEmpty($orderId);
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

            // 4. 更新订单支付信息
            $this->updateOrderPaymentInfo($order, $data);

            // 5. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_COMPLETED, [
                'remark' => $data['remark'] ?? '订单支付完成',
                'operator_id' => $this->uid
            ]);

            // 6. 创建财务转账记录
            $this->createTransferRecord($order, $data, '订单支付');

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
        } catch (\Exception $e) {
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
            $order = RecycleOrder::findOrEmpty($orderId);
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

            // 4. 更新订单支付信息
            $this->updateOrderPaymentInfo($order, $data);

            // 5. 调用核心状态服务进行状态流转
            $statusService = new CoreRecycleOrderStatusService();
            $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_COMPLETED, [
                'remark' => $data['remark'] ?? '确认打款完成',
                'operator_id' => $this->uid
            ]);

            // 6. 创建财务转账记录
            $this->createTransferRecord($order, $data, '确认打款');

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
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('【回收打款】confirmPayment方法执行失败', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 创建财务转账记录
     * @param RecycleOrder $order 订单信息
     * @param array $data 打款数据
     * @param string $action 操作类型
     * @return void
     */
    private function createTransferRecord(RecycleOrder $order, array $data, string $action): void
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
            
            $devices = $order->devices;
            Log::info('【转账记录】订单设备信息', [
                'devices_count' => $devices ? $devices->count() : 0,
                'devices_data' => $devices ? $devices->toArray() : []
            ]);
            
            $totalAmount = $order->devices()->sum('final_price');
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