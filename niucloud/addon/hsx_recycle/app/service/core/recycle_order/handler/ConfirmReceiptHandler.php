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

/**
 * 用户确认收货处理器
 *
 * 处理用户确认收货业务逻辑：
 * 1. 记录用户确认收货时间
 * 2. 更新订单状态
 *
 * @package addon\hsx_recycle\app\service\core\recycle_order\handler
 */
class ConfirmReceiptHandler extends BaseFlowHandler
{
    /**
     * 处理确认收货操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 记录确认收货信息
        $receiptInfo = [
            'receipt_confirm_time' => time(),
            'member_id' => $this->getOperatorId($context),
            'remark' => $data['remark'] ?? ''
        ];

        // 更新订单信息
        RecycleOrder::where('id', $order['id'])->update([
            'receipt_confirm_time' => $receiptInfo['receipt_confirm_time'],
            'update_at' => time()
        ]);

        return $this->success('已确认收货', [
            'receipt_info' => $receiptInfo
        ]);
    }
}
