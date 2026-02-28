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
use addon\recycle\app\service\admin\order\RecycleDeviceService;
use core\exception\CommonException;

/**
 * 订单签收处理器
 *
 * 处理订单签收业务逻辑：
 * 1. 录入设备信息
 * 2. 更新设备状态
 * 3. 记录签收时间
 *
 * @package addon\recycle\app\service\core\recycle_order\handler
 */
class SignHandler extends BaseFlowHandler
{
    /**
     * 处理签收操作
     *
     * @param array $order 订单信息
     * @param array $data 操作数据，包含：
     *   - devices: 设备信息数组
     *   - remark: 备注（可选）
     * @param array $context 上下文信息
     * @return array 处理结果
     * @throws CommonException
     */
    public function handle(array $order, array $data, array $context): array
    {
        // 1. 处理设备信息
        $deviceIds = [];
        if (!empty($data['devices'])) {
            $deviceIds = $this->handleDevices(
                $order['id'],
                $data['devices'],
                $order['member_id'],
                $this->getSiteId($context)
            );
        }

        // 2. 记录签收信息
        $signInfo = [
            'sign_time' => time(),
            'operator_id' => $this->getOperatorId($context),
            'device_count' => count($deviceIds),
            'remark' => $data['remark'] ?? ''
        ];

        return $this->success('签收成功', [
            'device_ids' => $deviceIds,
            'sign_info' => $signInfo
        ]);
    }

    /**
     * 处理设备信息
     *
     * @param int $orderId 订单ID
     * @param array $devices 设备数据
     * @param int $memberId 会员ID
     * @param int $siteId 站点ID
     * @return array 设备ID列表
     * @throws CommonException
     */
    private function handleDevices(int $orderId, array $devices, int $memberId, int $siteId): array
    {
        // 如果devices是关联数组（非索引数组），将其转换为索引数组
        if (isset($devices['imei'])) {
            $devices = [$devices];
        }

        // 获取当前订单已有的设备记录
        $existingDevices = (new RecycleDevice())
            ->where([['order_id', '=', $orderId]])
            ->column('*', 'id');

        $deviceService = new RecycleDeviceService();
        $deviceIds = [];

        foreach ($devices as $device) {
            $categoryId = (int)($device['category_id'] ?? 1);
            $categoryPath = $this->normalizeCategoryPath($device['category_path'] ?? null, $categoryId);

            // 检查是否有设备ID并且该ID是否在现有设备中
            if (!empty($device['id']) && isset($existingDevices[$device['id']])) {
                // 设备已存在，执行更新操作
                $deviceData = [
                    'imei' => $device['imei'] ?? '',
                    'model' => $device['model'] ?? '',
                    'initial_price' => $device['initial_price'] ?? 0,
                    'category_id' => $categoryId,
                    'info' => $this->buildDeviceInfo($existingDevices[$device['id']]['info'] ?? [], $categoryPath),
                    'update_at' => time()
                ];
                $deviceService->signUpdate((int)$device['id'], $deviceData);
                $deviceIds[] = $device['id'];
            } else {
                // 设备不存在，添加新设备
                $deviceData = [
                    'order_id' => $orderId,
                    'imei' => $device['imei'] ?? '',
                    'model' => $device['model'] ?? '',
                    'initial_price' => $device['initial_price'] ?? 0,
                    'category_id' => $categoryId,
                    'info' => $this->buildDeviceInfo([], $categoryPath),
                    'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                    'create_at' => time(),
                    'update_at' => time(),
                    'member_id' => $memberId,
                    'site_id' => $siteId
                ];
                $newDeviceId = $deviceService->add($deviceData);
                $deviceIds[] = $newDeviceId;
            }
        }

        return $deviceIds;
    }

    /**
     * 规范化分类路径，统一存储为字符串数组
     * @param mixed $categoryPath
     * @param int $categoryId
     * @return array
     */
    private function normalizeCategoryPath($categoryPath, int $categoryId): array
    {
        if (is_string($categoryPath) && $categoryPath !== '') {
            $decoded = json_decode($categoryPath, true);
            if (is_array($decoded)) {
                $categoryPath = $decoded;
            } else {
                $categoryPath = array_filter(array_map('trim', explode(',', $categoryPath)));
            }
        }

        if (!is_array($categoryPath) || empty($categoryPath)) {
            $categoryPath = [ $categoryId ];
        }

        return array_values(array_map('strval', $categoryPath));
    }

    /**
     * 合并设备info信息，补充商城分类路径
     * @param mixed $originInfo
     * @param array $categoryPath
     * @return array
     */
    private function buildDeviceInfo($originInfo, array $categoryPath): array
    {
        if (is_string($originInfo) && $originInfo !== '') {
            $decoded = json_decode($originInfo, true);
            $originInfo = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($originInfo)) {
            $originInfo = [];
        }

        $originInfo['goods_category'] = $categoryPath;
        return $originInfo;
    }
}
