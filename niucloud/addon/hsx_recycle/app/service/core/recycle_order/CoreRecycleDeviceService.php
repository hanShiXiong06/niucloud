<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use core\base\BaseCoreService;
use core\exception\AdminException;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收设备核心服务层
 * Class CoreRecycleDeviceService
 * @package addon\hsx_recycle\app\service\core\recycle_order
 */
class CoreRecycleDeviceService extends BaseCoreService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDevice();
    }

    /**
     * 添加设备
     * @param array $data
     * @return RecycleDevice
     */
    public function add(array $data)
    {
        try {
            Db::startTrans();

            // 检查IMEI是否重复
            if (!empty($data['imei'])) {
                $exists = $this->model->where([
                    ['imei', '=', $data['imei']],
                    ['site_id', '=', $data['site_id']]
                ])->find();
                if (!empty($exists)) {
                    throw new AdminException('该IMEI已存在');
                }
            }

            // 创建设备
            $device = $this->model->create([
                'site_id' => $data['site_id'],
                'order_id' => $data['order_id'],
                'imei' => $data['imei'] ?? '',
                'model' => $data['model'] ?? '',
                'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                'initial_price' => $data['initial_price'] ?? 0,
                'remark' => $data['remark'] ?? '',
            ]);

            // 更新订单设备数量
            RecycleOrder::where('id', $data['order_id'])->inc('device_count')->update();

            Db::commit();
            return $device;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('添加回收设备失败：' . $e->getMessage());
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 编辑设备
     * @param int $id
     * @param array $data
     * @return void
     */
    public function edit(int $id, array $data)
    {
        try {
            Db::startTrans();

            $device = $this->model->findOrFail($id);

            // 如果修改了IMEI，检查是否重复
            if (!empty($data['imei']) && $data['imei'] !== $device['imei']) {
                $exists = $this->model->where([
                    ['imei', '=', $data['imei']],
                    ['site_id', '=', $device['site_id']],
                    ['id', '<>', $id]
                ])->find();
                if (!empty($exists)) {
                    throw new AdminException('该IMEI已存在');
                }
            }

            // 更新设备信息
            $device->save([
                'imei' => $data['imei'] ?? $device['imei'],
                'model' => $data['model'] ?? $device['model'],
                'remark' => $data['remark'] ?? $device['remark'],
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('编辑回收设备失败：' . $e->getMessage());
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 删除设备
     * @param int $id
     * @return void
     */
    public function delete(int $id)
    {
        try {
            Db::startTrans();

            $device = $this->model->findOrFail($id);
            $order_id = $device['order_id'];

            // 删除设备
            $device->delete();

            // 更新订单设备数量
            RecycleOrder::where('id', $order_id)->dec('device_count')->update();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('删除回收设备失败：' . $e->getMessage());
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 开始质检
     * @param int $id
     * @return void
     */
    public function startCheck(int $id)
    {
        try {
            $device = $this->model->findOrFail($id);
            if ($device['status'] != RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK) {
                throw new AdminException('设备状态不正确');
            }

            $device->save([
                'status' => RecycleOrderDict::DEVICE_STATUS_CHECKING,
                'check_status' => 1,
            ]);
        } catch (\Exception $e) {
            Log::error('开始质检失败：' . $e->getMessage());
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 完成质检
     * @param int $id
     * @param array $data
     * @return void
     */
    public function completeCheck(int $id, array $data)
    {
        try {
            $device = $this->model->findOrFail($id);
            if ($device['status'] != RecycleOrderDict::DEVICE_STATUS_CHECKING) {
                throw new AdminException('设备状态不正确');
            }

            $device->save([
                'status' => RecycleOrderDict::DEVICE_STATUS_CHECKED,
                'check_status' => 2,
                'check_result' => $data['check_result'] ?? '',
                'check_images' => $data['check_images'] ?? [],
                'check_at' => time(),
            ]);
        } catch (\Exception $e) {
            Log::error('完成质检失败：' . $e->getMessage());
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 设置价格
     * @param int $id
     * @param array $data
     * @return void
     */
    public function setPrice(int $id, array $data)
    {
        try {
            $device = $this->model->findOrFail($id);
            if ($device['status'] != RecycleOrderDict::DEVICE_STATUS_CHECKED) {
                throw new AdminException('设备状态不正确');
            }

            $device->save([
                'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
                'final_price' => $data['final_price'],
                'price_remark' => $data['price_remark'] ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error('设置价格失败：' . $e->getMessage());
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 用户确认最终回收报价。
     *
     * API 层只传递当前用户身份；设备归属校验、状态机、日志和订单汇总
     * 均在 Core 的同一事务中完成。事务提交后才发布扩展事件，ERP 或其他
     * 插件通过监听器消费，不能反向污染用户确认事实。
     */
    public function confirmMemberPrice(int $id, int $siteId, int $memberId, bool $accept): array
    {
        if ($id <= 0 || $siteId <= 0 || $memberId <= 0) {
            throw new CommonException('确认报价参数不完整');
        }

        $result = [];
        try {
            Db::startTrans();

            $device = RecycleDevice::where([
                ['id', '=', $id],
                ['site_id', '=', $siteId],
            ])->lock(true)->findOrEmpty();
            if ($device->isEmpty()) {
                throw new CommonException('设备不存在或不属于当前站点');
            }

            $order = RecycleOrder::where([
                ['id', '=', (int)$device->order_id],
                ['site_id', '=', $siteId],
                ['member_id', '=', $memberId],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) {
                throw new CommonException('设备不存在或不属于当前用户订单');
            }
            if ((float)($device->final_price ?? 0) <= 0) {
                throw new CommonException('商家尚未完成定价，请等待最终报价后再操作');
            }

            $oldStatus = (int)$device->status;
            $targetStatus = $accept
                ? RecycleOrderDict::DEVICE_STATUS_RECYCLED
                : RecycleOrderDict::DEVICE_STATUS_RETURNED;

            // 同一请求重复提交只返回既有结果，不重复写日志、不重复发布下游事实。
            if ($oldStatus === $targetStatus) {
                Db::commit();
                return [
                    'success' => true,
                    'changed' => false,
                    'device_id' => $id,
                    'order_id' => (int)$order->id,
                    'status' => $targetStatus,
                    'accepted' => $accept,
                ];
            }
            if (in_array($oldStatus, [
                RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                RecycleOrderDict::DEVICE_STATUS_RETURNED,
                RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
            ], true)) {
                throw new CommonException('设备已完成处置，不能重复更改确认结果');
            }
            if ($oldStatus !== RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
                throw new CommonException('设备当前不是待确认状态，不能确认报价');
            }

            $now = time();
            $action = $accept ? '用户接受报价' : '用户拒绝报价';
            $remark = $accept ? '用户接受回收报价' : '用户拒绝回收报价';
            $device->save([
                'status' => $targetStatus,
                'confirm_status' => $accept
                    ? RecycleOrderDict::CONFIRM_STATUS_CONFIRMED
                    : RecycleOrderDict::CONFIRM_STATUS_REJECTED,
                'confirm_time' => $now,
                'confirm_member_id' => $memberId,
                'confirm_remark' => $remark,
                'update_at' => $now,
            ]);
            RecycleDeviceLog::create([
                'site_id' => $siteId,
                'device_id' => $id,
                'order_id' => (int)$order->id,
                'operator_id' => 0,
                'operator_name' => '用户',
                'action' => $action,
                'old_status' => $oldStatus,
                'new_status' => $targetStatus,
                'remark' => $remark,
                'create_at' => $now,
            ]);

            $this->syncConfirmedOrderStatus((int)$order->id, $siteId, $now);
            Db::commit();

            $result = [
                'success' => true,
                'changed' => true,
                'device_id' => $id,
                'order_id' => (int)$order->id,
                'status' => $targetStatus,
                'accepted' => $accept,
            ];
        } catch (CommonException $e) {
            Db::rollback();
            throw $e;
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('用户确认回收报价失败', [
                'site_id' => $siteId,
                'member_id' => $memberId,
                'device_id' => $id,
                'message' => $e->getMessage(),
            ]);
            throw new CommonException($e->getMessage(), 0, $e);
        }

        if ($accept && !empty($result['changed'])) {
            try {
                event('RecycleDeviceConfirmed', [
                    'event_name' => 'recycle.device.confirmed.v1',
                    'site_id' => $siteId,
                    'member_id' => $memberId,
                    'order_id' => (int)$result['order_id'],
                    'device_ids' => [$id],
                    'accepted' => true,
                    'occurred_at' => time(),
                ]);
            } catch (\Throwable $e) {
                // 下游属于可独立重试的扩展能力，不能把已提交的客户确认伪装成失败。
                Log::error('用户确认报价后的扩展事件处理失败', [
                    'site_id' => $siteId,
                    'device_id' => $id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /** 全部设备进入终态后，统一汇总回收订单状态。 */
    private function syncConfirmedOrderStatus(int $orderId, int $siteId, int $now): void
    {
        $statuses = array_map('intval', RecycleDevice::where([
            ['order_id', '=', $orderId],
            ['site_id', '=', $siteId],
        ])->column('status'));
        if ($statuses === []) {
            RecycleOrder::where([['id', '=', $orderId], ['site_id', '=', $siteId]])->update([
                'status' => RecycleOrderDict::ORDER_STATUS_CLOSED,
                'update_at' => $now,
            ]);
            return;
        }

        $terminalStatuses = [
            RecycleOrderDict::DEVICE_STATUS_RECYCLED,
            RecycleOrderDict::DEVICE_STATUS_RETURNED,
            RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
        ];
        foreach ($statuses as $status) {
            if (!in_array($status, $terminalStatuses, true)) {
                return;
            }
        }

        $allReturned = count(array_filter($statuses, static fn (int $status): bool =>
            $status === RecycleOrderDict::DEVICE_STATUS_RETURNED
        )) === count($statuses);
        RecycleOrder::where([['id', '=', $orderId], ['site_id', '=', $siteId]])->update([
            'status' => $allReturned
                ? RecycleOrderDict::ORDER_STATUS_CLOSED
                : RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
            'update_at' => $now,
        ]);
    }
}
