<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\recycle_order;

use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\dict\order\RecycleOrderDict;
use core\base\BaseCoreService;
use core\exception\AdminException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收设备核心服务层
 * Class CoreRecycleDeviceService
 * @package addon\recycle\app\service\core\recycle_order
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
     * 确认价格
     * @param int $id
     * @param bool $accept
     * @return void
     */
    public function confirmPrice(int $id, bool $accept)
    {
        try {
            $device = $this->model->findOrFail($id);
            
            // 如果设备已经是终态（已回收或已退回），直接返回成功
            if ($device['status'] == RecycleOrderDict::DEVICE_STATUS_RECYCLED || 
                $device['status'] == RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                
                // 如果当前状态与用户要求的状态一致，直接返回成功
                $expectedStatus = $accept ? RecycleOrderDict::DEVICE_STATUS_RECYCLED : RecycleOrderDict::DEVICE_STATUS_RETURNED;
                if ($device['status'] == $expectedStatus) {
                    Log::info('设备已经处于目标状态，无需更新：ID=' . $id . ', 当前状态=' . $device['status']);
                    return;
                }
                
                // 如果状态不一致，但已经是终态，记录日志并继续执行（允许状态转换）
                Log::warning('设备已处于终态但状态不一致，将进行状态转换：ID=' . $id . 
                    ', 当前状态=' . $device['status'] . ', 目标状态=' . $expectedStatus);
            }
            
            // 检查设备状态，只允许从待确认状态转换到终态
            if ($device['status'] != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM &&
                $device['status'] != RecycleOrderDict::DEVICE_STATUS_RECYCLED &&
                $device['status'] != RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                throw new AdminException('设备状态不正确，只有待确认的设备才能进行确认操作');
            }

            // 更新设备状态
            $device->save([
                'status' => $accept ? 
                    RecycleOrderDict::DEVICE_STATUS_RECYCLED : 
                    RecycleOrderDict::DEVICE_STATUS_RETURNED,
                'update_at' => time()
            ]);
            
            Log::info('设备状态更新成功：ID=' . $id . ', 新状态=' . ($accept ? 
                RecycleOrderDict::DEVICE_STATUS_RECYCLED : 
                RecycleOrderDict::DEVICE_STATUS_RETURNED));
                
        } catch (\Exception $e) {
            Log::error('确认价格失败：' . $e->getMessage());
            throw new AdminException($e->getMessage());
        }
    }
}
