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

use addon\recycle\app\model\order\RecycleDevice;
use core\exception\CommonException;

/**
 * 质检完成处理器
 *
 * 处理质检完成业务逻辑：
 * 1. 验证所有设备已质检
 * 2. 更新质检完成时间
 * 3. 记录质检结果
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class CompleteCheckHandler extends BaseFlowHandler
{
    /**
     * 处理质检完成操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - remark: 质检备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 1. 获取订单所有设备
        $devices = RecycleDevice::where('order_id', $order['id'])->select()->toArray();

        if (empty($devices)) {
            throw new CommonException('订单没有设备信息');
        }

        // 2. 检查所有设备是否已质检（状态为3-已质检）
        $uncheckedDevices = array_filter($devices, function($device) {
            return $device['status'] != 3; // 3-已质检
        });

        if (!empty($uncheckedDevices)) {
            throw new CommonException('还有设备未完成质检，无法完成订单质检');
        }

        // 3. 记录质检完成信息
        $checkInfo = [
            'check_complete_time' => time(),
            'operator_id' => $this->getOperatorId($context),
            'device_count' => count($devices),
            'remark' => $data['remark'] ?? ''
        ];

        return $this->success('质检完成', [
            'check_info' => $checkInfo,
            'devices' => $devices
        ]);
    }
}
