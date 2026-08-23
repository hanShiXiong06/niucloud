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

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderApiFlowDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderAdminFlowDict;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单流程引擎服务
 *
 * 核心职责：
 * 1. 统一管理订单状态流转
 * 2. 验证操作权限（基于配置）
 * 3. 调用对应的业务处理器
 * 4. 执行状态转换
 * 5. 触发事件通知
 *
 * 使用示例：
 * ```php
 * $flowService = new CoreRecycleOrderFlowService();
 * // 管理员签收订单
 * $flowService->execute($orderId, 'sign', $data, 'admin');
 * // 用户取消订单
 * $flowService->execute($orderId, 'cancel', $data, 'api');
 * ```
 *
 * @package addon\hsx_recycle\app\service\core\recycle_order
 */
class CoreRecycleOrderFlowService extends BaseCoreService
{
    /**
     * 流程类型：API端（用户端）
     */
    const FLOW_TYPE_API = 'api';

    /**
     * 流程类型：Admin端（管理端）
     */
    const FLOW_TYPE_ADMIN = 'admin';


    /**
     * 执行订单流程操作
     *
     * @param int $orderId 订单ID
     * @param string $action 操作名称（如：sign, cancel, payment等）
     * @param array $data 操作数据
     * @param string $flowType 流程类型（api/admin）
     * @param array $context 上下文信息（如：operator_id, site_id等）
     * @return array 执行结果
     * @throws CommonException
     */
    public function execute(int $orderId, string $action, array $data = [], string $flowType = self::FLOW_TYPE_ADMIN, array $context = []): array
    {
        // 开启事务
        Db::startTrans();
        try {
            // 1. 获取订单信息
            $order = $this->getOrderInfo($orderId);

            // 财务安全边界必须放在核心流程层。这样即使旧版移动端仍调用历史打款
            // 接口，或其他服务绕过控制器直接执行 payment，也无法在 ERP 接管后
            // 继续写入回收插件的本地付款事实。
            if ($action === 'payment') {
                $siteId = (int)($order['site_id'] ?? $context['site_id'] ?? 0);
                (new RecycleErpCapabilityService())->assertLocalPaymentAllowed($siteId);
            }

            // 2. 获取流程配置
            $flowConfig = $this->getFlowConfig($order['status'], $flowType);

            // 3. 验证操作权限
            $this->validateAction($order['status'], $action, $flowConfig);

            // 4. 获取转换配置
            $transitionConfig = $this->getTransitionConfig($order['status'], $action, $flowConfig);

            // 5. 验证必需数据
            $this->validateRequiredData($data, $transitionConfig);

            // 6. 执行验证规则
            $this->executeValidations($order, $data, $transitionConfig);

            // 7. 调用业务处理器
            $handlerResult = $this->executeHandler($order, $action, $data, $transitionConfig, $context);

            // 8. 执行状态转换（如果目标状态与当前状态不同）
            if ($transitionConfig['to_status'] != $order['status']) {
                $this->executeStatusTransition($orderId, $transitionConfig['to_status'], $data, $context);
            }

            // 取消主订单后，已签收/已入库设备需要同步生成退回处理。
            if ($action === 'cancel') {
                (new CoreRecycleOrderCancelReturnService())->sync($orderId, $data, $context);
            }

            // 9. 触发后置事件
            $this->triggerAfterEvent($orderId, $transitionConfig, $context);

            // 提交事务
            Db::commit();

            // 记录日志
            Log::info("订单流程执行成功", [
                'order_id' => $orderId,
                'action' => $action,
                'flow_type' => $flowType,
                'from_status' => $order['status'],
                'to_status' => $transitionConfig['to_status']
            ]);

            return [
                'success' => true,
                'message' => '操作成功',
                'data' => $handlerResult
            ];

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            // 记录错误日志
            Log::error("订单流程执行失败", [
                'order_id' => $orderId,
                'action' => $action,
                'flow_type' => $flowType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 获取订单信息
     *
     * @param int $orderId 订单ID
     * @return array 订单信息
     * @throws CommonException
     */
    private function getOrderInfo(int $orderId): array
    {
        $order = RecycleOrder::where('id', $orderId)->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('订单不存在');
        }
        return $order->toArray();
    }

    /**
     * 获取流程配置
     *
     * @param int $status 订单状态
     * @param string $flowType 流程类型
     * @return array 流程配置
     * @throws CommonException
     */
    private function getFlowConfig(int $status, string $flowType): array
    {
        $config = null;
        if ($flowType === self::FLOW_TYPE_API) {
            $config = RecycleOrderApiFlowDict::getFlowConfig($status);
        } elseif ($flowType === self::FLOW_TYPE_ADMIN) {
            $config = RecycleOrderAdminFlowDict::getFlowConfig($status);
        }

        if (empty($config)) {
            throw new CommonException('流程配置不存在');
        }

        return $config;
    }

    /**
     * 验证操作权限
     *
     * @param int $status 当前状态
     * @param string $action 操作名称
     * @param array $flowConfig 流程配置
     * @throws CommonException
     */
    private function validateAction(int $status, string $action, array $flowConfig): void
    {
        if (!in_array($action, $flowConfig['actions'])) {
            throw new CommonException("当前状态[{$flowConfig['status_name']}]不允许执行操作[{$action}]");
        }
    }

    /**
     * 获取转换配置
     *
     * @param int $status 当前状态
     * @param string $action 操作名称
     * @param array $flowConfig 流程配置
     * @return array 转换配置
     * @throws CommonException
     */
    private function getTransitionConfig(int $status, string $action, array $flowConfig): array
    {
        if (!isset($flowConfig['transitions'][$action])) {
            throw new CommonException("操作[{$action}]的转换配置不存在");
        }
        return $flowConfig['transitions'][$action];
    }

    /**
     * 验证必需数据
     *
     * @param array $data 操作数据
     * @param array $transitionConfig 转换配置
     * @throws CommonException
     */
    private function validateRequiredData(array $data, array $transitionConfig): void
    {
        if (empty($transitionConfig['require_data'])) {
            return;
        }

        foreach ($transitionConfig['require_data'] as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new CommonException("缺少必需参数：{$field}");
            }
        }
    }

    /**
     * 执行验证规则
     *
     * @param array $order 订单信息
     * @param array $data 操作数据
     * @param array $transitionConfig 转换配置
     * @throws CommonException
     */
    private function executeValidations(array $order, array $data, array $transitionConfig): void
    {
        if (empty($transitionConfig['validate'])) {
            return;
        }

        foreach ($transitionConfig['validate'] as $validateMethod) {
            // 调用验证方法（可以扩展为独立的验证器类）
            $this->$validateMethod($order, $data);
        }
    }

    /**
     * 执行业务处理器
     *
     * @param array $order 订单信息
     * @param string $action 操作名称
     * @param array $data 操作数据
     * @param array $transitionConfig 转换配置
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    private function executeHandler(array $order, string $action, array $data, array $transitionConfig, array $context): array
    {
        $handlerClass = "addon\\hsx_recycle\\app\\service\\core\\recycle_order\\handler\\{$transitionConfig['handler']}";

        if (!class_exists($handlerClass)) {
            throw new CommonException("处理器类不存在：{$handlerClass}");
        }

        $handler = new $handlerClass();
        return $handler->handle($order, $data, $context);
    }

    /**
     * 执行状态转换
     *
     * @param int $orderId 订单ID
     * @param int $toStatus 目标状态
     * @param array $data 操作数据
     * @param array $context 上下文信息
     * @throws CommonException
     */
    private function executeStatusTransition(int $orderId, int $toStatus, array $data, array $context): void
    {
        $statusService = new CoreRecycleOrderStatusService();
        $statusService->transition($orderId, $toStatus, array_merge($data, $context));
    }

    /**
     * 触发后置事件
     *
     * @param int $orderId 订单ID
     * @param array $transitionConfig 转换配置
     * @param array $context 上下文信息
     */
    private function triggerAfterEvent(int $orderId, array $transitionConfig, array $context): void
    {
        if (empty($transitionConfig['event_after'])) {
            return;
        }

        $eventMethod = $transitionConfig['event_after'];
        $eventData = array_merge([
            'order_id' => $orderId
        ], $context);

        // 调用事件服务
        if (method_exists(CoreRecycleOrderEventService::class, $eventMethod)) {
            CoreRecycleOrderEventService::$eventMethod($eventData);
        }
    }

    // ==================== 验证方法示例 ====================

    /**
     * 验证设备是否存在
     *
     * @param array $order 订单信息
     * @param array $data 操作数据
     * @throws CommonException
     */
    private function checkDeviceExists(array $order, array $data): void
    {
        // 这里可以添加具体的验证逻辑
        // 例如：检查订单是否有设备记录
    }

    /**
     * 验证所有设备是否已质检
     *
     * @param array $order 订单信息
     * @param array $data 操作数据
     * @throws CommonException
     */
    private function checkAllDevicesChecked(array $order, array $data): void
    {
        // 这里可以添加具体的验证逻辑
        // 例如：检查所有设备的质检状态
    }
}
