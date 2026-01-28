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

use addon\recycle\app\model\RecycleOrder;
use core\exception\CommonException;

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

        // 2. 记录取消信息
        $cancelInfo = [
            'cancel_time' => time(),
            'cancel_reason' => $data['reason'],
            'operator_id' => $this->getOperatorId($context),
            'remark' => $data['remark'] ?? ''
        ];

        // 3. 更新订单取消信息
        RecycleOrder::where('id', $order['id'])->update([
            'cancel_time' => $cancelInfo['cancel_time'],
            'cancel_reason' => $cancelInfo['cancel_reason'],
            'update_at' => time()
        ]);

        return $this->success('订单已取消', [
            'cancel_info' => $cancelInfo
        ]);
    }
}
