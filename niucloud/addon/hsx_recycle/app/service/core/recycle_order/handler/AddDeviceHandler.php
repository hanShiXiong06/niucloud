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

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
use core\exception\CommonException;

/**
 * 添加设备处理器
 *
 * 处理添加设备业务逻辑：
 * 1. 验证设备信息
 * 2. 添加新设备到订单
 * 3. 设置设备初始状态
 *
 * @package addon\hsx_recycle\app\service\core\recycle_order\handler
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
        $siteId = $this->getSiteId($context);

        foreach ($devices as $device) {
            $categoryId = (int)($device['category_id'] ?? 0);
            $categoryPath = DeviceSummaryHelper::normalizeCategoryPath($device['category_path'] ?? null, $categoryId);
            // 统一摘要契约：summary = { field_key: value }
            $summary = DeviceSummaryHelper::normalizeSummary($device['summary'] ?? []);
            $cols = DeviceSummaryHelper::reservedColumns($summary, $device);

            // 添加新设备
            $deviceData = [
                'order_id' => $order['id'],
                'imei' => $device['imei'] ?? '',
                'user_sn' => $device['user_sn'] ?? ($device['imei'] ?? ''),
                'imei2' => $device['imei2'] ?? '',
                'sn' => $device['serial_number'] ?? '',
                'model' => $device['model'] ?? '',
                'initial_price' => $device['initial_price'] ?? 0,
                'category_id' => $categoryId,
                'check_template_id' => (int)($device['check_template_id'] ?? 0),
                'color' => $cols['color'],
                'capacity' => $cols['capacity'],
                'system_version' => $cols['system_version'],
                'warranty_info' => $cols['warranty_info'],
                'info' => DeviceSummaryHelper::buildInfo([], $categoryPath, $summary, $device, $siteId),
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
