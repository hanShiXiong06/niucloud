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

/**
 * 订单流程处理器基类
 *
 * 所有业务处理器都应继承此基类，实现 handle 方法
 *
 * 处理器职责：
 * 1. 执行具体的业务逻辑
 * 2. 返回标准化的处理结果
 * 3. 不负责状态转换（由流程引擎统一处理）
 * 4. 不负责事件触发（由流程引擎统一处理）
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
abstract class BaseFlowHandler
{
    /**
     * 处理订单流程操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据
     * @param array $context 上下文信息（如：operator_id, site_id等）
     * @return array 处理结果
     */
    abstract public function handle(array $order, array $data, array $context): array;

    /**
     * 获取操作者ID
     *
     * @param array $context 上下文信息
     * @return int 操作者ID
     */
    protected function getOperatorId(array $context): int
    {
        return $context['operator_id'] ?? 0;
    }

    /**
     * 获取站点ID
     *
     * @param array $context 上下文信息
     * @return int 站点ID
     */
    protected function getSiteId(array $context): int
    {
        return $context['site_id'] ?? 0;
    }

    /**
     * 返回成功结果
     *
     * @param string $message 消息
     * @param array $data 数据
     * @return array 结果
     */
    protected function success(string $message = '操作成功', array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
}
