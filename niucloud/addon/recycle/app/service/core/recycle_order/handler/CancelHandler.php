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

use addon\recycle\app\model\order\RecycleOrder;
use addon\recycle\app\service\core\express\RecycleExpressService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 订单取消处理器
 *
 * 处理订单取消业务逻辑：
 * 1. 记录取消原因
 * 2. 更新取消时间
 * 3. 记录操作者信息
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class CancelHandler extends BaseFlowHandler
{
    /**
     * 处理取消操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - reason: 取消原因
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 1. 验证取消原因
        if (empty($data['reason'])) {
            throw new CommonException('请填写取消原因');
        }

        // 2. 如果订单有平台快递，自动取消快递
        $this->cancelExpressIfNeeded($order, $context);

        // 3. 记录取消信息
        $cancelInfo = [
            'cancel_time' => time(),
            'cancel_reason' => $data['reason'],
            'operator_id' => $this->getOperatorId($context),
            'remark' => $data['remark'] ?? ''
        ];

        // 4. 更新订单取消信息
        RecycleOrder::where('id', $order['id'])->update([
            'cancel_time' => $cancelInfo['cancel_time'],
            'cancel_reason' => $cancelInfo['cancel_reason'],
            'update_at' => time()
        ]);

        return $this->success('订单已取消', [
            'cancel_info' => $cancelInfo
        ]);
    }

    /**
     * 取消关联的平台快递（如果有）
     *
     * @param array $order 订单信息
     * @param array $context 上下文信息
     * @return void
     */
    private function cancelExpressIfNeeded(array $order, array $context): void
    {
        // 没有快递单号 或 快递状态不允许取消（已签收=3、已取消=4），跳过
        if (empty($order['express_no']) || empty($order['delivery_status']) || $order['delivery_status'] >= 3) {
            return;
        }

        try {
            $siteId = $order['site_id'] ?? 0;
            $expressService = new RecycleExpressService();

            $operatorInfo = [
                'uid' => 0,
                'username' => '',
                'source' => $context['flow_type'] ?? 'user',
                'member_id' => $this->getOperatorId($context),
            ];

            $expressService->cancelOrder($siteId, (int)$order['id'], $operatorInfo);

            Log::info("订单{$order['id']}取消时自动取消快递成功，运单号：{$order['express_no']}");

        } catch (\Exception $e) {
            // 快递取消失败不阻断订单取消，仅记录日志
            Log::error("订单{$order['id']}自动取消快递失败：" . $e->getMessage());
        }
    }
}
