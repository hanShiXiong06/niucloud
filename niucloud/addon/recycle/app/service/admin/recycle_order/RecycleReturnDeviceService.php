<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2023~2024 Njucloud DE Team
// +----------------------------------------------------------------------
// | Author: 尼古拉斯 <515343908@qq.com>
// +----------------------------------------------------------------------
namespace addon\recycle\app\service\admin\recycle_order;

use addon\recycle\app\dict\RecycleOrderDict;
use addon\recycle\app\model\recycle_order\RecycleDevice;
use addon\recycle\app\model\recycle_order\RecycleDeviceLog;
use addon\recycle\app\model\recycle_order\RecycleReturnDevice;
use addon\recycle\app\model\recycle_order\RecycleReturnOrder;
use app\service\admin\BaseAdminService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收退回设备服务
 * Class RecycleReturnDeviceService
 * @package addon\recycle\app\service\admin\recycle_order
 */
class RecycleReturnDeviceService extends BaseAdminService
{
    /**
     * 构造函数
     * @param int $site_id
     */
    public function __construct(int $site_id = 0)
    {
        parent::__construct($site_id);
        $this->model = new RecycleReturnDevice();
    }

    /**
     * 获取退回设备信息
     * @param int $id
     * @param array $field
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getInfo(int $id, array $field = []): array
    {
        
    }

    /**
     * 获取设备列表
     * @param array $where
     * @param string $field
     * @param string $order
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getList(array $where = [], string $field = '*', string $order = ''): array
    {
        
        $condition = [
            ['site_id', '=', $this->site_id]
        ];
        if (!empty($where)) {
            $condition = array_merge($condition, $where);
        }
        $list = $this->model->field($field)->where($condition)->order($order)->with(['device', 'order'])->select();
        if ($list->isEmpty()) {
            return [];
        }
        return $list->toArray();
    }

    /**
     * 获取退回设备分页
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param string $field
     * @param string $order
     * @return array
     */
    public function getPage(array $where = [], int $page = 1, int $limit = 10, string $field = '*', string $order = ''): array
    {
       
        $condition = [
            ['site_id', '=', $this->site_id]
        ];
        if (!empty($where)) {
            $condition = array_merge($condition, $where);
        }
        $count = $this->model->where($condition)->count();
        $list = $this->model->field($field)->where($condition)->order($order)->with(['device', 'order'])->page($page, $limit)->select();
        return ['count' => $count, 'list' => $list->toArray()];
    }

    /**
     * 添加退回设备
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        $data['site_id'] = $this->site_id;
        $this->model->startTrans();
        try {
            $id = $this->model->save($data);
            $this->model->commit();
            return $id;
        } catch (\Exception $e) {
            $this->model->rollback();
            Log::error("添加退回设备失败：" . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取指定退货单下的设备列表
     * @param int $returnOrderId
     * @return array
     */
    public function getDevicesByReturnOrderId(int $returnOrderId): array
    {
        if (empty($returnOrderId)) {
            return [];
        }
        
        $condition = [
            ['site_id', '=', $this->site_id],
            ['return_order_id', '=', $returnOrderId]
        ];
        
        $list = $this->model->where($condition)
            ->with(['device' => function($query) {
                $query->field('id,imei,model,status,initial_price,final_price,create_at');
            }])
            ->select();
            
        if ($list->isEmpty()) {
            return [];
        }
        
        return $list->toArray();
    }
    
    /**
     * 获取指定设备的退货记录
     * @param int $deviceId
     * @return array
     */
    public function getReturnDevicesByDeviceId(int $deviceId): array
    {
        if (empty($deviceId)) {
            return [];
        }
        
        $condition = [
            ['site_id', '=', $this->site_id],
            ['device_id', '=', $deviceId]
        ];
        
        $list = $this->model->where($condition)
            ->with(['order' => function($query) {
                $query->field('id,order_no,status,create_time');
            }])
            ->select();
            
        if ($list->isEmpty()) {
            return [];
        }
        
        return $list->toArray();
    }
    
    /**
     * 批量添加退回设备
     * @param array $deviceIds
     * @param int $returnOrderId
     * @return bool
     */
    public function batchAdd(array $deviceIds, int $returnOrderId): bool
    {
        if (empty($deviceIds) || empty($returnOrderId)) {
            return false;
        }
        
        // 查询退货单信息
        $returnOrderModel = new RecycleReturnOrder();
        $returnOrder = $returnOrderModel->where([
            ['id', '=', $returnOrderId],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($returnOrder)) {
            return false;
        }
        
        // 查询设备信息
        $deviceModel = new RecycleDevice();
        $devices = $deviceModel->where([
            ['id', 'in', $deviceIds],
            ['site_id', '=', $this->site_id]
        ])->select();
        
        if ($devices->isEmpty()) {
            return false;
        }
        
        $this->model->startTrans();
        try {
            $data = [];
            foreach ($devices as $device) {
                $data[] = [
                    'site_id' => $this->site_id,
                    'return_order_id' => $returnOrderId,
                    'order_id' => $returnOrder['order_id'],
                    'device_id' => $device['id'],
                    'create_time' => time()
                ];
                
                // 更新设备状态为已退回
                $device->status = RecycleOrderDict::DEVICE_STATUS_RETURNED;
                $device->save();
                
                // 添加设备日志
                $deviceLogModel = new RecycleDeviceLog();
                $deviceLogModel->save([
                    'site_id' => $this->site_id,
                    'device_id' => $device['id'],
                    'old_status' => $device['status'],
                    'new_status' => RecycleOrderDict::DEVICE_STATUS_RETURNED,
                    'remark' => '设备已退回，退货单号：' . $returnOrder['order_no'],
                    'create_time' => time()
                ]);
            }
            
            // 批量添加退回设备
            $this->model->saveAll($data);
            
            $this->model->commit();
            return true;
        } catch (\Exception $e) {
            $this->model->rollback();
            Log::error("批量添加退回设备失败：" . $e->getMessage());
            return false;
        }
    }
} 