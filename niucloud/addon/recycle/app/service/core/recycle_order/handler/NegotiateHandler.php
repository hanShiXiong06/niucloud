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
 * 议价处理器
 *
 * 处理用户议价业务逻辑：
 * 1. 记录议价信息
 * 2. 记录用户期望价格
 * 3. 通知管理员处理
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class NegotiateHandler extends BaseFlowHandler
{
    /**
     * 处理议价操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - expected_price: 用户期望价格（可选）
     *   - reason: 议价原因（可选）
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 记录议价信息
        $negotiateInfo = [
            'negotiate_time' => time(),
            'member_id' => $this->getOperatorId($context),
            'expected_price' => $data['expected_price'] ?? 0,
            'reason' => $data['reason'] ?? '',
            'remark' => $data['remark'] ?? ''
        ];

        // 更新订单议价信息
        RecycleOrder::where('id', $order['id'])->update([
            'negotiate_time' => $negotiateInfo['negotiate_time'],
            'expected_price' => $negotiateInfo['expected_price'],
            'negotiate_reason' => $negotiateInfo['reason'],
            'is_negotiating' => 1, // 标记为议价中
            'update_at' => time()
        ]);

        return $this->success('议价申请已提交', [
            'negotiate_info' => $negotiateInfo
        ]);
    }
}
