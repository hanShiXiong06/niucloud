<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderStatusService;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderEventService;
use addon\hsx_recycle\app\service\admin\recycle_order\RecycleDeviceService;
use addon\hsx_recycle\app\model\RecycleOrder;
use addon\hsx_recycle\app\model\RecycleDevice;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 回收订单签收服务
 * Class RecycleOrderSignService
 * @package addon\hsx_recycle\app\service\admin\order
 */
class RecycleOrderSignService extends BaseAdminService
{
    /**
     * 签收订单
     * @param int $orderId 订单ID
     * @param array $data 签收数据
     * @return bool
     * @throws CommonException
     */
    public function sign(int $orderId, array $data): bool
    {
        Db::startTrans();
        try {
            // 1. 参数验证
            $this->validateSignData($orderId, $data);

            // 2. 获取订单信息
            $order = RecycleOrder::findOrEmpty($orderId);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 3. 验证订单状态 - 允许在多种状态下添加设备
            $allowedStatuses = [
                RecycleOrderDict::ORDER_STATUS_PENDING_SIGN,  // 待签收
                RecycleOrderDict::ORDER_STATUS_SIGNED,        // 已签收
                RecycleOrderDict::ORDER_STATUS_CHECKING,      // 质检中
                RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM, // 待确认
            ];
            
            if (!in_array($order->status, $allowedStatuses)) {
                throw new CommonException('ORDER_STATUS_ERROR');
            }

            // 4. 处理设备信息
            if (!empty($data['devices'])) {
                $this->handleDevices($orderId, $data['devices'], $order->member_id);
            }

            // 5. 只有待签收状态的订单才需要进行状态流转
            if ($order->status == RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
                // 调用核心状态服务进行状态流转
                $statusService = new CoreRecycleOrderStatusService();
                $statusService->transition($orderId, RecycleOrderDict::ORDER_STATUS_SIGNED, [
                    'remark' => $data['remark'] ?? '订单签收',
                    'operator_id' => $this->uid,
                    'devices' => $data['devices'] ?? []
                ]);

                // 触发签收后事件
                CoreRecycleOrderEventService::orderSignAfter([
                    'order_id' => $orderId,
                    'site_id' => $this->site_id,
                    'operator_id' => $this->uid,
                    'data' => $data
                ]);
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 验证签收数据
     * @param int $orderId
     * @param array $data
     * @throws CommonException
     */
    private function validateSignData(int $orderId, array $data): void
    {
        if ($orderId <= 0) {
            throw new CommonException('INVALID_ORDER_ID');
        }

        // 验证设备数据
        if (!empty($data['devices'])) {
            foreach ($data['devices'] as $device) {
                if (empty($device['imei']) && empty($device['model'])) {
                    throw new CommonException('DEVICE_INFO_REQUIRED');
                }
            }
        }
    }

    /**
     * 处理设备信息
     * @param int $orderId
     * @param array $devices
     * @param int $memberId
     * @throws CommonException
     */
    private function handleDevices(int $orderId, array $devices, int $memberId): void
    {
        // 如果devices是关联数组（非索引数组），将其转换为索引数组
        if (isset($devices['imei'])) {
            $devices = [$devices];
        }

        // 获取当前订单已有的设备记录
        $existingDevices = (new RecycleDevice())->where([['order_id', '=', $orderId]])->column('*', 'id');
        
        $deviceService = new RecycleDeviceService();
        
        foreach ($devices as $device) {
            // 型号触发的质检模板与摘要字段（最多 5 个），归一化为 { field_key: value }
            $summaryValues = $this->normalizeSummaryValues($device['summary'] ?? []);
            $checkTemplateId = (int)($device['check_template_id'] ?? 0);

            // 检查是否有设备ID并且该ID是否在现有设备中
            if (!empty($device['id']) && isset($existingDevices[$device['id']])) {
                // 设备已存在，执行更新操作。
                // 注意：不要用 null 覆盖 info，否则会清空代客下单录入时保存的分类/摘要等信息。
                $deviceData = [
                    'imei' => $device['imei'] ?? '',
                    'model' => $device['model'] ?? '',
                    'initial_price' => $device['initial_price'] ?? 0,
                    'category_id' => (int)($device['category_id'] ?? 0),
                    'update_at' => time(),
                ];
                if ($checkTemplateId > 0) {
                    $deviceData['check_template_id'] = $checkTemplateId;
                }
                $mergedInfo = $this->mergeDeviceInfo($existingDevices[$device['id']]['info'] ?? null, $summaryValues);
                if ($mergedInfo !== null) {
                    $deviceData['info'] = $mergedInfo;
                }
                $deviceService->signUpdate((int)$device['id'], $deviceData);
            } else {
                // 设备不存在，添加新设备
                $deviceData = [
                    'order_id' => $orderId,
                    'imei' => $device['imei'] ?? '',
                    'model' => $device['model'] ?? '',
                    'initial_price' => $device['initial_price'] ?? 0,
                    'category_id' => (int)($device['category_id'] ?? 0),
                    'check_template_id' => $checkTemplateId,
                    'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                    'create_at' => time(),
                    'update_at' => time(),
                    'member_id' => $memberId,
                    'site_id' => $this->site_id,
                    'info' => $this->mergeDeviceInfo(null, $summaryValues) ?? [],
                ];
                $deviceService->add($deviceData);
            }
        }
    }

    /**
     * 合并设备 info：在原有 info 基础上写入摘要字段值，不丢失既有数据。
     * @param mixed $existingInfo 原 info（JSON 字符串或数组）
     * @param array $summaryValues 归一化后的 { field_key: value }
     * @return array|null 有变更时返回合并后的数组；无任何信息时返回 null（调用方据此决定是否写库）
     */
    private function mergeDeviceInfo($existingInfo, array $summaryValues): ?array
    {
        $info = [];
        if (is_string($existingInfo) && $existingInfo !== '') {
            $decoded = json_decode($existingInfo, true);
            $info = is_array($decoded) ? $decoded : [];
        } elseif (is_array($existingInfo)) {
            $info = $existingInfo;
        }

        if (!empty($summaryValues)) {
            foreach ($summaryValues as $fieldKey => $val) {
                $info[$fieldKey] = $val;
            }
            $info['sign_summary'] = $summaryValues;
        }

        if (empty($info)) {
            return null;
        }
        return $info;
    }

    /**
     * 归一化质检摘要字段值，兼容 { field_key: value } 与 [ {field_key, value} ] 两种形态。
     * @param mixed $summary
     * @return array
     */
    private function normalizeSummaryValues($summary): array
    {
        if (is_string($summary) && $summary !== '') {
            $decoded = json_decode($summary, true);
            $summary = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($summary) || empty($summary)) {
            return [];
        }

        $result = [];
        foreach ($summary as $key => $item) {
            if (is_array($item) && isset($item['field_key'])) {
                $fieldKey = (string)$item['field_key'];
                $value = $item['value'] ?? '';
            } else {
                $fieldKey = (string)$key;
                $value = $item;
            }
            if ($fieldKey === '') {
                continue;
            }
            if (is_array($value)) {
                $value = array_values(array_filter($value, static fn($v) => $v !== '' && $v !== null));
                if (empty($value)) {
                    continue;
                }
            } elseif ($value === '' || $value === null) {
                continue;
            }
            $result[$fieldKey] = $value;
        }
        return $result;
    }
} 
