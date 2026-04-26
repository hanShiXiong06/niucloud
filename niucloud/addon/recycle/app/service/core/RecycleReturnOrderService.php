<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core;

use addon\recycle\app\dict\order\RecycleReturnOrderDict;
use addon\recycle\app\model\RecycleReturnOrder;
use addon\recycle\app\model\RecycleReturnDevice;
use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrder;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 退回订单核心服务
 * Class RecycleReturnOrderService
 * @package addon\recycle\app\service\core
 */
class RecycleReturnOrderService extends BaseCoreService
{
    /**
     * @var RecycleReturnOrder
     */
    protected $model;
    
    /**
     * @var int
     */
    protected $site_id = 0;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleReturnOrder();
    }

    /**
     * 获取退回订单列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getList(array $where, int $page = 1, int $limit = 10): array
    {
        $field = 'id, site_id, order_id, order_no, status, express_company, express_no, return_address, comment, create_at, update_at, over_at, operator_uid, operator_name, member_id, member_name, member_mobile';
        
        // 使用标准分页查询
        $list = $this->model->where($where)
            ->field($field)
            ->with(['returnDevices.device', 'member'])
            ->page($page, $limit)
            ->order('create_at desc')
            ->select()
            ->toArray();
        
        $count = $this->model->where($where)->count();
        
        return success(['list' => $list, 'count' => $count]);
    }

    /**
     * 获取退回订单详情
     * @param int $id
     * @param int $siteId
     * @return array
     */
    public function detail(int $id, int $siteId): array
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])
        ->with(['returnDevices.device', 'memberAddress'])
        ->find();


      
        
        if (empty($info)) {
            throw new CommonException('退回订单不存在');
        }

        return$info->toArray();
    }

    /**
     * 创建退回订单
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        // 验证订单ID
        $order_id = $data['order_id'] ?? 0;
        if (empty($order_id)) {
            return ['code' => -1, 'msg' => '原订单ID不能为空', 'data' => []];
        }
        
        // 验证设备ID
        $device_ids = $data['device_ids'] ?? [];
        if (empty($device_ids)) {
            return ['code' => -1, 'msg' => '退回设备不能为空', 'data' => []];
        }
        
        // 获取站点ID
        $site_id = $data['site_id'] ?? 0;
        if (empty($site_id)) {
            return ['code' => -1, 'msg' => '站点ID不能为空', 'data' => []];
        }
        
        // 验证原订单是否存在
        $order = (new RecycleOrder())->where([
            ['id', '=', $order_id],
            ['site_id', '=', $site_id]
        ])->find();
        
        if (empty($order)) {
            return ['code' => -1, 'msg' => '原订单不存在', 'data' => []];
        }
        
        // 验证设备是否存在且属于该订单，放宽对状态的限制
        $devices = (new RecycleDevice())->where([
            ['id', 'in', $device_ids],
            ['order_id', '=', $order_id]
        ])->select();
        
        if (empty($devices) || count($devices) == 0) {
            return ['code' => -1, 'msg' => '没有找到可退回的设备', 'data' => []];
        }
        
        // 检查是否有设备不属于订单
        $found_device_ids = [];
        foreach ($devices as $device) {
            $found_device_ids[] = $device['id'];
        }
        
        $missing_device_ids = array_diff($device_ids, $found_device_ids);
        if (!empty($missing_device_ids)) {
            Log::warning('部分设备不存在或不属于订单: ' . implode(',', $missing_device_ids));
        }
        
        // 只处理找到的设备
        if (empty($found_device_ids)) {
            return ['code' => -1, 'msg' => '所有设备都不存在或不属于该订单', 'data' => []];
        }
        
       
        return true;

        // 开始事务
        Db::startTrans();
        try {
            // 创建退回订单
            $order_no = 'RT' . date('YmdHis') . rand(1000, 9999);
            $return_order = [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'order_no' => $order_no,
                'status' => RecycleReturnOrderDict::ORDER_STATUS_PENDING,
                'express_company' => $data['express_company'] ?? '',
                'express_no' => $data['express_no'] ?? '',
                'return_address' => $data['return_address'] ?? '',
                'comment' => $data['comment'] ?? '',
                'operator_uid' => $data['operator_id'] ?? 0,
                'operator_name' => $data['operator_name'] ?? '',
                'member_id' => $order['member_id'],
                'member_name' => $order['customer_name'] ?? '',
                'member_mobile' => $order['customer_phone'] ?? '',
            ];
            
            $return_order_id = $this->model->insertGetId($return_order);
            
            // 创建退回设备关联
            $return_devices = [];
            foreach ($devices as $device) {
                $return_devices[] = [
                    'return_order_id' => $return_order_id,
                    'device_id' => $device['id'],
                    'status' => 0, // 待退货状态
                    'create_at' => time()
                ];
                
                // 如果设备状态还不是待退回，则更新
                if ($device->status != RecycleReturnOrderDict::DEVICE_STATUS_PENDING &&
                    $device->status != RecycleReturnOrderDict::DEVICE_STATUS_RETURNING &&
                    $device->status != RecycleReturnOrderDict::DEVICE_STATUS_RETURNED) {
                    
                    $device->status = RecycleReturnOrderDict::DEVICE_STATUS_PENDING;
                    $device->save();
                }
            }
            
            (new RecycleReturnDevice())->insertAll($return_devices);
            
            Db::commit();
            
            return [
                'code' => 0, 
                'msg' => '创建成功', 
                'data' => [
                    'id' => $return_order_id,
                    'order_no' => $order_no,
                    'status' => RecycleReturnOrderDict::ORDER_STATUS_PENDING
                ]
            ];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -1, 'msg' => '创建退货订单失败：' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 更新退回订单状态
     * @param int $id
     * @param int $status
     * @param array $data
     * @return array
     */
    public function updateStatus(int $id, int $status, array $data = []): array
    {
        $comment = $data['comment'] ?? '';
        $site_id = $data['site_id'];


        // 获取订单信息
        $order = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $site_id]
        ])->find();
        
        if (empty($order)) {
            throw new CommonException('退回订单不存在');
        }
        
        // 验证状态转换是否有效
        $valid_transitions = RecycleReturnOrderDict::getValidStatusTransitions();
        if (!in_array($status, $valid_transitions[$order['status']] ?? [])) {
            throw new CommonException('无效的状态转换');
        }

       
        
        // 开始事务
        Db::startTrans();
        try {

            // 如果status == 2 直接sava 就可以了
            if ($status == 2) {
                
                $data['operator_uid'] = $data['operator_id'] ?? 0;
                $data['operator_name'] = $data['operator_name'] ?? '';
                unset($data['operator_id']);

                $this->model->where('id', $id)->update($data);
               
            }else{

                // 更新订单状态
                $update_data = [
                    'status' => $status,
                    'comment' => $comment ? $order['comment'] . "\n" . $comment : $order['comment'],
                    'operator_uid' => $data['operator_uid'] ?? 0,
                    'operator_name' => $data['operator_name'] ?? '',
                    'express_no' => $data['express_no'] ?? '',
                    'express_company' => $data['express_company'] ?? '',
                    'member_mobile' => $data['member_mobile'] ?? '',
                    'member_name' => $data['member_name'] ?? '',
                    'return_address' => $data['return_address'] ?? '',
                    'remark'=> $data['remark'] ?? '',
                ];
                
                // 如果状态为已完成，设置完成时间
                if ($status == RecycleReturnOrderDict::ORDER_STATUS_COMPLETED) {
                    $update_data['over_at'] = date('Y-m-d H:i:s');
                }
                
                
                $this->model->where('id', $id)->update($update_data);
                
                // 更新关联设备状态
                $device_status = $this->getDeviceStatusByOrderStatus($status);
                if ($device_status !== false) {
                    $device_ids = (new RecycleReturnDevice())->where('return_order_id', $id)
                        ->column('device_id');
                    
                    if (!empty($device_ids)) {
                        (new RecycleDevice())->whereIn('id', $device_ids)
                            ->update(['status' => $device_status]);
                    }
                }
            }
            Db::commit();
            
            return [
                'code' => 0, 
                'msg' => '更新成功', 
                'data' => [
                    'id' => $id,
                    'status' => $status,
                    'status_name' => RecycleReturnOrderDict::getOrderStatus($status)['name'] ?? ''
                ]
            ];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -1, 'msg' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 确认退回订单
     * @param int $id
     * @param int $siteId
     * @return array
     */
    public function confirm(int $id, int $siteId , array $data = []): array
    {
        return $this->updateStatus($id, RecycleReturnOrderDict::ORDER_STATUS_RETURNING, [
            'site_id' => $siteId,
            'operator_uid' => request()->uid(),
            'operator_name' => request()->username(),
            'express_no' => $data['express_no'] ?? '',
            'express_company' => $data['express_company'] ?? '',
            'remark' => $data['remark'] ?? '',
            'member_mobile' => $data['member_mobile'] ?? '',
            'member_name' => $data['member_name'] ?? '',
            'return_address' => $data['return_address'] ?? '',

        ]);
    }

    /**
     * 取消退回订单
     * @param int $id
     * @param int $siteId
     * @param string $comment
     * @return array
     */
    public function cancel(int $id, int $siteId, string $comment = ''): array
    {
        return $this->updateStatus($id, RecycleReturnOrderDict::ORDER_STATUS_CANCELLED, [
            'comment' => $comment, 
            'site_id' => $siteId,
            'operator_uid' => request()->uid(),
            'operator_name' => request()->username(),

        ]);
    }

    /**
     * 删除退回订单
     * @param int $id
     * @param int $siteId
     * @return array
     */
    public function delete(int $id, int $siteId): array
    {
        // 获取订单信息
        $order = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->find();
        
        if (empty($order)) {
            throw new CommonException('退回订单不存在');
        }
        
        // 验证订单状态是否允许删除
        $delete_allowed = RecycleReturnOrderDict::getStatusActionPermissions()['DELETE'] ?? [];
        if (!in_array($order['status'], $delete_allowed)) {
            throw new CommonException('当前状态不允许删除');
        }
        
        // 软删除订单
        $this->model->where('id', $id)->update(['delete_at' => time()]);
        
        return ['code' => 0, 'msg' => '删除成功', 'data' => ['id' => $id]];
    }

    /**
     * 获取退回订单状态统计
     * @param int $siteId
     * @return array
     */
    public function getStatusCount(int $siteId): array
    {
        $result = [];
        $status_list = RecycleReturnOrderDict::getOrderStatusList();
        
        foreach ($status_list as $status => $info) {
            $count = $this->model->where([
                ['site_id', '=', $siteId],
                ['status', '=', $status],
                ['delete_at', '=', 0]
            ])->count();
            
            $result[] = [
                'status' => $status,
                'name' => $info['name'],
                'count' => $count
            ];
        }
        
        // 添加总数
        $total = $this->model->where([
            ['site_id', '=', $siteId],
            ['delete_at', '=', 0]
        ])->count();
        
        $result[] = [
            'status' => 'all',
            'name' => '全部',
            'count' => $total
        ];
        
        return $result;
    }

    /**
     * 根据订单状态获取对应的设备状态
     * @param int $order_status
     * @return int|bool
     */
    private function getDeviceStatusByOrderStatus(int $order_status)
    {
        $status_map = [
            RecycleReturnOrderDict::ORDER_STATUS_PENDING => RecycleReturnOrderDict::DEVICE_STATUS_PENDING,
            RecycleReturnOrderDict::ORDER_STATUS_RETURNING => RecycleReturnOrderDict::DEVICE_STATUS_RETURNING,
            RecycleReturnOrderDict::ORDER_STATUS_COMPLETED => RecycleReturnOrderDict::DEVICE_STATUS_RETURNED,
            RecycleReturnOrderDict::ORDER_STATUS_CANCELLED => RecycleReturnOrderDict::DEVICE_STATUS_CANCELLED
        ];
        
        return $status_map[$order_status] ?? false;
    }

    /**
     * 批量创建退回订单
     * @param array $data
     * @return array
     */
    public function batchCreate(array $data): array
    {
        // 验证订单数据
        if (empty($data['orders']) || !is_array($data['orders'])) {
            return ['code' => -1, 'msg' => '退回订单数据不能为空', 'data' => []];
        }
        
        // 开始事务
        Db::startTrans();
        try {
            $order_list = [];
            $errors = [];
            
            foreach ($data['orders'] as $key => $order_data) {
                try {
                    $result = $this->create($order_data);
                    // 判断返回值是否成功
                    if (isset($result['code']) && $result['code'] != 0) {
                        $errors[] = "订单 {$key}: " . $result['msg'];
                        continue;
                    }
                    $order_list[] = $result['data'];
                } catch (\Exception $e) {
                    $errors[] = "订单 {$key}: " . $e->getMessage();
                }
            }
            
            // 如果所有订单都失败，则回滚事务
            if (empty($order_list) && !empty($errors)) {
                Db::rollback();
                return ['code' => -1, 'msg' => '批量创建退回订单失败：' . implode('; ', $errors), 'data' => []];
            }
            
            // 记录部分失败的错误信息
            if (!empty($errors)) {
                Log::warning('部分退回订单创建失败: ' . implode('; ', $errors));
            }
            
            Db::commit();
            return [
                'code' => 0, 
                'msg' => '批量创建成功', 
                'data' => [
                    'success_count' => count($order_list),
                    'error_count' => count($errors),
                    'orders' => $order_list
                ]
            ];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -1, 'msg' => '批量创建退回订单失败：' . $e->getMessage(), 'data' => []];
        }
    }
} 