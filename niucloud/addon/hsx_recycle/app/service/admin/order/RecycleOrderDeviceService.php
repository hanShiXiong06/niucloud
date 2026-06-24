<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDeviceLogService;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 回收订单设备管理服务
 * Class RecycleOrderDeviceService
 * @package addon\hsx_recycle\app\service\admin\order
 */
class RecycleOrderDeviceService extends BaseAdminService
{
    /**
     * @var CoreRecycleDeviceLogService
     */
    protected $logService;
    /**
     * 向现有订单添加设备
     * @param int $orderId 订单ID
     * @param array $deviceData 设备数据
     * @return int 设备ID
     * @throws CommonException
     */
    public function addDeviceToOrder(int $orderId, array $deviceData): int
    {
        $this->logService = new CoreRecycleDeviceLogService();
        
        Db::startTrans();
        try {
            // 1. 验证订单是否存在
            $order = RecycleOrder::findOrEmpty($orderId);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 2. 验证订单状态 - 允许在多种状态下添加设备
            $allowedStatuses = [
                RecycleOrderDict::ORDER_STATUS_PENDING_SIGN,    // 待签收
                RecycleOrderDict::ORDER_STATUS_SIGNED,          // 已签收
                RecycleOrderDict::ORDER_STATUS_CHECKING,        // 质检中
                RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM, // 待确认
            ];
            
            if (!in_array($order->status, $allowedStatuses)) {
                throw new CommonException('ORDER_STATUS_NOT_ALLOW_ADD_DEVICE');
            }

            // 3. 验证IMEI是否已存在
            // if (!empty($deviceData['imei'])) {
            //     $existingDevice = (new RecycleDevice())->where([
            //         ['imei', '=', $deviceData['imei']],
            //         ['order_id', '<>', $orderId]
            //     ])->findOrEmpty();
                
            //     if (!$existingDevice->isEmpty()) {
            //         throw new CommonException('DEVICE_IMEI_EXISTS');
            //     }
            // }

            // 4. 组装设备数据（统一摘要契约：summary = { field_key: value }，与签收/Handler 同口径）
            $categoryId = (int)($deviceData['category_id'] ?? 0);
            $categoryPath = DeviceSummaryHelper::normalizeCategoryPath($deviceData['category_path'] ?? null, $categoryId);
            $summary = DeviceSummaryHelper::normalizeSummary($deviceData['summary'] ?? []);
            $cols = DeviceSummaryHelper::reservedColumns($summary, $deviceData);

            $data = [
                'order_id' => $orderId,
                'imei' => $deviceData['imei'] ?? '',
                'model' => $deviceData['model'] ?? '',
                'initial_price' => $deviceData['initial_price'] ?? 0,
                'category_id' => $categoryId,
                'check_template_id' => (int)($deviceData['check_template_id'] ?? 0),
                'color' => $cols['color'],
                'capacity' => $cols['capacity'],
                'system_version' => $cols['system_version'],
                'warranty_info' => $cols['warranty_info'],
                'info' => DeviceSummaryHelper::buildInfo([], $categoryPath, $summary, $deviceData),
                'status' => $this->getInitialDeviceStatus($order->status),
                'member_id' => $order->member_id,
                'site_id' => $this->site_id,
                'create_at' => time(),
                'update_at' => time(),
                'remark' => $deviceData['remark'] ?? '后续添加的设备'
            ];

            // 5. 添加设备
            $deviceService = new RecycleDeviceService();
            $deviceId = $deviceService->add($data);
            (new RecycleDeviceModelDictService())->ensureFromModelName((string)($data['model'] ?? ''), $this->site_id);

            // 6. 记录设备添加日志
            $this->logService->logDeviceAdd($deviceId, $data);
            $this->syncOrderDeviceCount($orderId);

            Db::commit();

            // 7. 设备录入保存后按打印场景配置触发（如即时打印设备标签）。
            // 必须在事务提交后执行，确保打印框架能读到已落库的设备数据；失败不影响录入主流程。
            try {
                (new \addon\hsx_recycle\app\service\admin\printer\RecyclePrintTriggerService())
                    ->auto('device.sign.saved', [
                        'device_id' => $deviceId,
                        'order_id'  => $orderId,
                        'biz_id'    => $deviceId,
                    ]);
            } catch (\Throwable $e) {
                \think\facade\Log::error('设备签收保存后自动打印触发失败：' . $e->getMessage(), ['device_id' => $deviceId]);
            }

            return $deviceId;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量向订单添加设备
     * @param int $orderId 订单ID
     * @param array $devices 设备数组
     * @return array 添加的设备ID数组
     * @throws CommonException
     */
    public function batchAddDevicesToOrder(int $orderId, array $devices): array
    {
        $deviceIds = [];
        
        foreach ($devices as $deviceData) {
            $deviceIds[] = $this->addDeviceToOrder($orderId, $deviceData);
        }
        
        return $deviceIds;
    }

    /**
     * 根据订单状态获取设备初始状态
     * @param int $orderStatus 订单状态
     * @return int 设备状态
     */
    private function getInitialDeviceStatus(int $orderStatus): int
    {
        switch ($orderStatus) {
            case RecycleOrderDict::ORDER_STATUS_PENDING_SIGN:
                return RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
            case RecycleOrderDict::ORDER_STATUS_SIGNED:
                return RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
            case RecycleOrderDict::ORDER_STATUS_CHECKING:
                return RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
            case RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM:
                return RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
            default:
                return RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
        }
    }

    /**
     * 从订单中移除设备
     * @param int $deviceId 设备ID
     * @param string $reason 移除原因
     * @return bool
     * @throws CommonException
     */
    public function removeDeviceFromOrder(int $deviceId, string $reason = ''): bool
    {
        $this->logService = new CoreRecycleDeviceLogService();
        
        Db::startTrans();
        try {
            // 1. 获取设备信息
            $device = RecycleDevice::findOrEmpty($deviceId);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }

            // 2. 验证设备状态 - 只允许在特定状态下移除
            $allowedStatuses = [
                RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                RecycleOrderDict::DEVICE_STATUS_CHECKING,
            ];
            
            if (!in_array($device->status, $allowedStatuses)) {
                throw new CommonException('DEVICE_STATUS_NOT_ALLOW_REMOVE');
            }

            // 3. 记录移除日志
            $this->logService->logDeviceRemove($deviceId, $reason ?: '管理员操作');

            // 4. 删除设备
            $orderId = (int)$device->order_id;
            $device->delete();
            $this->syncOrderDeviceCount($orderId);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    private function syncOrderDeviceCount(int $orderId): void
    {
        $count = (new RecycleDevice())->where([['order_id', '=', $orderId]])->count();
        (new RecycleOrder())->where([['id', '=', $orderId]])->update([
            'count' => $count,
            'device_count' => $count,
            'update_at' => time(),
        ]);
    }

}
