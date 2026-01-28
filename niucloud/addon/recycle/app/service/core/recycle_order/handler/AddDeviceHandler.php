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
use addon\recycle\app\service\admin\recycle_order\RecycleDeviceService;
use core\exception\CommonException;

/**
 * 添加设备处理器
 *
 * 处理添加设备业务逻辑：
 * 1. 验证设备信息
 * 2. 添加新设备到订单
 * 3. 设置设备初始状态
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class AddDeviceHandler extends BaseFlowHandler
{
    /**
     * 处理添加设备操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - devices: 设备信息数组
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        if (empty($data['devices'])) {
            throw new CommonException('请提供设备信息');
        }

        // 如果devices是关联数组，转换为索引数组
        $devices = isset($data['devices']['imei']) ? [$data['devices']] : $data['devices'];

        $deviceService = new RecycleDeviceService();
        $deviceIds = [];

        foreach ($devices as $device) {
            // 添加新设备
            $deviceData = [
                'order_id' => $order['id'],
                'imei' => $device['imei'] ?? '',
                'model' => $device['model'] ?? '',
                'initial_price' => $device['initial_price'] ?? 0,
                'category_id' => $device['category_id'] ?? 1,
                'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK, // 1-待质检
                'create_at' => time(),
                'update_at' => time(),
                'member_id' => $order['member_id'],
                'site_id' => $this->getSiteId($context)
            ];
            $newDeviceId = $deviceService->add($deviceData);
            $deviceIds[] = $newDeviceId;
        }

        return $this->success('设备添加成功', [
            'device_ids' => $deviceIds,
            'device_count' => count($deviceIds)
        ]);
    }
}
