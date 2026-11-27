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

use addon\hsx_recycle\app\model\order\RecycleOrder;
use core\exception\CommonException;

/**
 * 订单关闭处理器
 *
 * 处理订单关闭业务逻辑：
 * 1. 记录关闭原因
 * 2. 更新关闭时间
 * 3. 记录操作者信息
 *
 * 注：关闭与取消的区别：
 * - 取消：用户或管理员主动取消，订单未完成
 * - 关闭：管理员因特殊原因关闭订单（如异常订单、违规订单等）
 *
 * @package addon\hsx_recycle\app\service\core\recycle_order\handler
 */
class CloseHandler extends BaseFlowHandler
{
    /**
     * 处理关闭操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - reason: 关闭原因
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 1. 验证关闭原因
        if (empty($data['reason'])) {
            throw new CommonException('请填写关闭原因');
        }

        // 2. 记录关闭信息
        $closeInfo = [
            'close_time' => time(),
            'close_reason' => $data['reason'],
            'operator_id' => $this->getOperatorId($context),
            'remark' => $data['remark'] ?? ''
        ];

        // 3. 更新订单关闭信息
        RecycleOrder::where('id', $order['id'])->update([
            'close_time' => $closeInfo['close_time'],
            'close_reason' => $closeInfo['close_reason'],
            'update_at' => time()
        ]);

        return $this->success('订单已关闭', [
            'close_info' => $closeInfo
        ]);
    }
}
