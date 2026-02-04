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

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\model\order\RecycleDevice;
use core\exception\CommonException;

/**
 * 开始质检处理器
 *
 * 处理开始质检业务逻辑：
 * 1. 验证订单是否有设备
 * 2. 更新所有设备状态为"质检中"
 * 3. 记录质检开始时间
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class StartCheckHandler extends BaseFlowHandler
{
    /**
     * 处理开始质检操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 1. 获取订单所有设备
        $devices = RecycleDevice::where('order_id', $order['id'])->select()->toArray();

        if (empty($devices)) {
            throw new CommonException('订单没有设备信息，无法开始质检');
        }

        // 2. 更新所有设备状态为"质检中"（状态2）
        RecycleDevice::where('order_id', $order['id'])
            ->where('status', RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK) // 只更新待质检的设备
            ->update([
                'status' => RecycleOrderDict::DEVICE_STATUS_CHECKING, // 2-质检中
                'check_start_time' => time(),
                'update_at' => time()
            ]);

        // 3. 记录质检开始信息
        $checkInfo = [
            'check_start_time' => time(),
            'operator_id' => $this->getOperatorId($context),
            'device_count' => count($devices),
            'remark' => $data['remark'] ?? ''
        ];

        return $this->success('已开始质检', [
            'check_info' => $checkInfo,
            'devices' => $devices
        ]);
    }
}
