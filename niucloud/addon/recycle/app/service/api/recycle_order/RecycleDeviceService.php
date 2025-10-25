<?php
declare(strict_types=1);

namespace addon\recycle\app\service\api\recycle_order;

use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\service\core\recycle_order\CoreRecycleDeviceService;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Log;
use addon\recycle\app\model\RecycleDeviceLog;

/**
 * 回收订单设备服务层 - 接口端
 */
class RecycleDeviceService extends BaseApiService
{
    private $core_service;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDevice();
        $this->core_service = new CoreRecycleDeviceService();
    }

    /**
     * 获取设备列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
      
        $field = 'id,order_id,imei,model,initial_price,final_price,price_remark,status,check_status,check_result,check_images,check_at,remark,create_at,update_at';
        $order = 'create_at desc';
        
        $search_model = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->withSearch(['order_id', 'imei', 'model', 'status'], $where)
            ->with(['order' => function($query) {
                $query->field('id,order_no,customer_name,customer_phone');
            }])
            ->field($field)
            ->order($order);
        
        $list = $this->pageQuery($search_model);

        // 处理列表数据
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                $item['status_name'] = RecycleOrderDict::getDeviceStatusName($item['status']);
                $item['check_status_name'] = RecycleOrderDict::getDeviceCheckStatusName($item['check_status']);
            }
        }

        return $list;
    }

    /**
     * 获取设备详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->with([
            'order' => function($query) {
                $query->field('id,order_no,customer_name,customer_phone,status')
                    ->append(['status_name']);
            }
        ])->findOrFail();

        if (empty($info)) {
            throw new ApiException('设备不存在');
        }

        $info['status_name'] = RecycleOrderDict::getDeviceStatusName($info['status']);
        $info['check_status_name'] = RecycleOrderDict::getDeviceCheckStatusName($info['check_status']);

        return $info;
    }

    /**
     * 获取订单下的设备列表
     * @param int $order_id
     * @return array
     */
    public function getOrderDevices(int $order_id)
    {
        
        // 先检查订单是否属于当前用户
        $order = RecycleOrder::where([
            ['id', '=', $order_id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->findOrFail();

        if (empty($order)) {
            throw new ApiException('订单不存在');
        }

        $list = $this->model->where([
            ['order_id', '=', $order_id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->select()->toArray();

        foreach ($list as &$item) {
            $item['status_name'] = RecycleOrderDict::getDeviceStatusName($item['status']);
            $item['check_status_name'] = RecycleOrderDict::getDeviceCheckStatusName($item['check_status']);
        }

        return $list;
    }

    /**
     * 确认价格（接受或拒绝）
     * @param int $id
     * @param bool $accept
     * @return void
     * @throws ApiException
     */
    public function confirmPrice(int $id, bool $accept)
    {
       
		Log::write(sprintf('[RecycleDeviceService][confirmPrice] Start. Device ID: %d, Accept: %s, Member ID: %s, Site ID: %s', $id, $accept ? 'true' : 'false', $this->member_id, $this->site_id));
        // 开启事务
        $this->model->startTrans();
        try {
            // 先检查设备是否属于当前用户
            $device = $this->model->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])->with(['order' => function($query) {
                $query->where('member_id', $this->member_id);
            }])->findOrEmpty(); // Use findOrEmpty to allow checking before throwing exception

            if ($device->isEmpty() || empty($device['order'])) {
                Log::write(sprintf('[RecycleDeviceService][confirmPrice] Device not found or order not associated for current member. Device ID: %d', $id));
                $this->model->rollback(); // Rollback if device not found
                throw new ApiException('设备不存在或不属于当前用户订单');
            }
			
			$device_data_for_log = $device->toArray();
            $order_id = $device_data_for_log['order_id']; // Get order_id after ensuring device is found
            $old_status = $device_data_for_log['status']; // Get old status before update
            Log::write(sprintf('[RecycleDeviceService][confirmPrice] Device found. Device ID: %d, Order ID: %d, Old Status: %d', $device_data_for_log['id'], $order_id, $old_status));

            // 确认价格，更新设备状态 (core_service handles the actual status update)
            $this->core_service->confirmPrice($id, $accept);
            Log::write(sprintf('[RecycleDeviceService][confirmPrice] core_service->confirmPrice called for Device ID: %d, Accept: %s', $id, $accept ? 'true' : 'false'));

            // --- Add Device Log Record --- 
            $new_status = 0; // Initialize
            $action_text = '';
            $remark_text = '';

            if ($accept) {
                $new_status = RecycleOrderDict::DEVICE_STATUS_RECYCLED; // Assuming status 5 for accepted price
                $action_text = '用户接受报价';
                $remark_text = '用户接受回收报价';
            } else {
                // IMPORTANT: Assuming status 4 (Pending Confirm) if price is rejected.
                // Adjust if CoreRecycleDeviceService sets a different status on rejection.
                $new_status = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM; 
                $action_text = '用户拒绝报价';
                $remark_text = '用户拒绝回收报价，等待最终处理方式确认';
            }

            if ($new_status != 0) { // Only log if a valid new status is determined
                $log_data = [
                    'site_id' => $this->site_id,
                    'device_id' => $id,
                    'order_id' => $order_id,
                    'operator_id' => 0, // 用户端操作
                    'operator_name' => '用户',
                    'action' => $action_text,
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                    'remark' => $remark_text,
                    'create_at' => time()
                ];
                $log_model = new RecycleDeviceLog();
                $log_model->save($log_data);
                Log::write(sprintf('[RecycleDeviceService][confirmPrice] Device log inserted for Device ID: %d. Action: %s, Old Status: %d, New Status: %d', $id, $action_text, $old_status, $new_status));
            } else {
                Log::write(sprintf('[RecycleDeviceService][confirmPrice] Device log skipped for Device ID: %d. Could not determine new status reliably after core_service call.', $id));
            }
            // --- End Add Device Log Record --- 

            // 检查设备是否全部确认
            $is_all_confirmed = $this->checkDeviceAllConfirm($order_id);
            // var_dump(!$is_all_confirmed);
            if ($is_all_confirmed) {
                // 更新订单状态
                $this->updateOrderStatus($order_id);
            }

            
            // 提交事务
            $this->model->commit();
            Log::write(sprintf('[RecycleDeviceService][confirmPrice] Transaction committed for Device ID: %d, Order ID: %d', $id, $order_id));
        } catch (ApiException $e) {
            $this->model->rollback();
            Log::write(sprintf('[RecycleDeviceService][confirmPrice] ApiException Caught. Transaction rolled back. Device ID: %d, Order ID: %s. Message: %s', $id, isset($order_id) ? $order_id : 'N/A', $e->getMessage()));
            throw $e; // Re-throw the ApiException
        } catch (\Exception $e) {
            // 回滚事务
            $this->model->rollback();
            Log::write(sprintf('[RecycleDeviceService][confirmPrice] General Exception Caught. Transaction rolled back. Device ID: %d, Order ID: %s. Message: %s\nTrace: %s', $id, isset($order_id) ? $order_id : 'N/A', $e->getMessage(), $e->getTraceAsString()));
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 确认设备处理方式（出售或退回）
     * @param int $id 设备ID
     * @param bool $is_sell 是否出售
     * @param string $remark 备注
     * @return bool
     */
    public function confirm(int $id, bool $is_sell, string $remark = '')
    {
       
        // 开启事务
        $this->model->startTrans();
        try {
            // 获取设备信息
            $device = $this->model->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                

            ])->findOrFail();

            if (empty($device)) {
                throw new ApiException('设备不存在');
            }

            // 检查设备状态是否为已质检
            if ($device['status'] != RecycleOrderDict::DEVICE_STATUS_CHECKED) {
                throw new ApiException('设备未完成质检，不能确认');
            }

            // 更新设备状态
            $new_status = $is_sell ? RecycleOrderDict::DEVICE_STATUS_RECYCLED : RecycleOrderDict::DEVICE_STATUS_RETURNED;
            $device->save([
                'status' => $new_status,
                'remark' => $remark,
                'update_at' => time(),
                'final_status' => 1
            ]);

            // 更新订单状态
            $this->updateOrderStatus($device['order_id']);

            $this->model->commit();
            return true;
        } catch (\Exception $e) {
            $this->model->rollback();
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 更新订单状态
     * @param int $order_id
     * @return bool
     */
    protected function updateOrderStatus(int $order_id)
    {
        // 获取订单下所有设备
        $devices = $this->model->where([
            ['order_id', '=', $order_id],
            ['site_id', '=', $this->site_id]
        ])->select()->toArray();

        // 如果没有设备，订单状态设为已关闭
        if (empty($devices)) {
            RecycleOrder::where([
                ['id', '=', $order_id],
                ['site_id', '=', $this->site_id]
            ])->update([
                'status' => RecycleOrderDict::ORDER_STATUS_CLOSED,
                'update_at' => time()
            ]);
            return true;
        }

        // 检查设备状态
        $has_pending = false;        // 是否有待确认的设备
        $has_recycled = false;       // 是否有已回收的设备
        $all_returned = true;        // 是否全部退回

        foreach ($devices as $device) {
            if ($device['status'] == RecycleOrderDict::DEVICE_STATUS_CHECKED) {
                $has_pending = true;
            } elseif ($device['status'] == RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
                $has_recycled = true;
                $all_returned = false;
            } elseif ($device['status'] != RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                $all_returned = false;
            }
        }

        // 更新订单状态
        $order_status = RecycleOrderDict::ORDER_STATUS_CHECKED;  // 默认已质检

        if ($has_pending) {
            $order_status = RecycleOrderDict::ORDER_STATUS_CHECKED;  // 有待确认的，保持已质检状态
        } elseif ($all_returned) {
            $order_status = RecycleOrderDict::ORDER_STATUS_CLOSED;   // 全部退回，订单关闭
        } elseif ($has_recycled) {
            $order_status = RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;  // 有回收的，进入待打款状态
        }

        // 更新订单状态
        RecycleOrder::where([
            ['id', '=', $order_id],
            ['site_id', '=', $this->site_id]
        ])->update([
            'status' => $order_status,
            'update_at' => time()
        ]);

        return true;
    }

    /**
     * 获取指定状态的设备数量
     * @param int $status 设备状态
     * @return int
     */
    public function getCountByStatus(int $status)
    {
        return $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', '=', $status]
        ])->count();
    }

    /**
     * 获取设备状态数量统计
     * @return array
     */
    public function getCount(){
        // 获取各状态设备数量
        $pending_inspection = $this->getCountByStatus(1); // 待质检
        $processing = $this->getCountByStatus(2);        // 处理中
        $inspected = $this->getCountByStatus(3);         // 已质检
        $pending_confirm = $this->getCountByStatus(4);    // 待确认
        
        return [
            'pending_inspection' => $pending_inspection,
            'processing' => $processing,
            'inspected' => $inspected,
            'pending_confirm' => $pending_confirm
        ];
    }

    /**
     * 批量确认设备
     * @param array $device_ids_array
     * @return bool
     */
    public function deviceAllConfirm(array $device_ids_array)
    {
        if (empty($device_ids_array)) {
            Log::write('[RecycleDeviceService][deviceAllConfirm] Device IDs array is empty.');
            return true; // Or throw an exception, depending on desired behavior
        }

        Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Start. Device IDs: %s, Member ID: %s, Site ID: %s', implode(',', $device_ids_array), $this->member_id, $this->site_id));

        // 开启事务
        $this->model->startTrans();
        try {
            // 1. 获取设备更新前的状态和order_id，确保它们属于当前用户和站点
            $devices_to_log = $this->model->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])
                                        ->whereIn('id', $device_ids_array)
                                        ->field('id, order_id, status')
                                        ->select();

            if ($devices_to_log->isEmpty()) {
                Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] No valid devices found for IDs: %s, Member ID: %s, Site ID: %s', implode(',', $device_ids_array), $this->member_id, $this->site_id));
                // No rollback needed yet as no db changes made within this specific try block part
                // But if called from somewhere else that has started a transaction, this might be an issue.
                // For now, assume this is the primary transaction control point for this operation.
                $this->model->rollback(); // Rollback if no devices are found to avoid empty commit
                throw new ApiException('未找到任何有效设备进行确认');
            }
            
            $actual_device_ids_to_update = $devices_to_log->column('id');
            Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Devices found for update and logging: %s', json_encode($devices_to_log->toArray())));

            // 2. 批量更新设备状态
            $new_device_status = RecycleOrderDict::DEVICE_STATUS_RECYCLED;
            $update_device_result = $this->model->whereIn('id', $actual_device_ids_to_update)->update([
                'status' => $new_device_status,
                'update_at' => time()
            ]);
            Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Device statuses updated to %d. Rows affected: %d', $new_device_status, $update_device_result));

            // 3. 准备并插入设备操作日志
            $device_logs = [];
            $current_time = time();
            $processed_order_ids = []; // To store unique order_ids for later processing

            foreach ($devices_to_log as $device_info) {
                $device_logs[] = [
                    'site_id' => $this->site_id,
                    'device_id' => $device_info['id'],
                    'order_id' => $device_info['order_id'],
                    'operator_id' => 0, // 用户端操作
                    'operator_name' => '用户', // 或 '系统用户'
                    'action' => '用户批量确认回收',
                    'old_status' => $device_info['status'],
                    'new_status' => $new_device_status,
                    'remark' => '批量确认设备回收',
                    'create_at' => $current_time
                ];
                if (!in_array($device_info['order_id'], $processed_order_ids)) {
                    $processed_order_ids[] = $device_info['order_id'];
                }
            }

            if (!empty($device_logs)) {
                $log_model = new RecycleDeviceLog();
                $log_model->saveAll($device_logs);
                Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] %d device logs inserted.', count($device_logs)));
            }

            // 4. 检查并更新涉及到的每个订单的状态
            foreach ($processed_order_ids as $order_id) {
                Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Checking order status for Order ID: %d', $order_id));
                $devices_in_order = $this->model->where([
                    ['order_id', '=', $order_id],
                    ['site_id', '=', $this->site_id]
                ])->select()->toArray();
                
                $confirm_count = $this->model->where([
                    ['order_id', '=', $order_id],
                    ['site_id', '=', $this->site_id],
                    ['status', '>=', RecycleOrderDict::DEVICE_STATUS_RECYCLED] // 确保是已回收或更高状态 (如已退回等)
                ])->count();

                Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Order ID: %d, Total devices: %d, Confirmed devices (status >= 5): %d', $order_id, count($devices_in_order), $confirm_count));

                if ($confirm_count == count($devices_in_order) && count($devices_in_order) > 0) {
                   Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] All devices in Order ID %d confirmed. Attempting to update order status to PENDING_PAYMENT.', $order_id));
                   $order_update_result = RecycleOrder::where([
                        ['id', '=', $order_id],
                        ['site_id', '=', $this->site_id]
                   ])->update([
                        'status' => RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
                        'update_at' => time()
                   ]);
                   Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Order status update result for Order ID %d: %s', $order_id, $order_update_result ? 'success (' . $order_update_result . ' rows affected)' : 'failure'));
                } else {
                    Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Not all devices in Order ID %d are confirmed. Order status not updated.', $order_id));
                }
            }

            $this->model->commit();
            Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] Transaction committed for Device IDs: %s', implode(',', $actual_device_ids_to_update)));
            return true;
        } catch (ApiException $e) {
            $this->model->rollback();
            Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] ApiException Caught. Transaction rolled back. Device IDs: %s. Message: %s', implode(',', $device_ids_array), $e->getMessage()));
            throw $e;
        } catch (\Exception $e) {
            $this->model->rollback();
            Log::write(sprintf('[RecycleDeviceService][deviceAllConfirm] General Exception Caught. Transaction rolled back. Device IDs: %s. Message: %s\nTrace: %s', implode(',', $device_ids_array), $e->getMessage(), $e->getTraceAsString()));
            throw new ApiException($e->getMessage());
        }
    }

    // 检测设备是否全部确认
    public function checkDeviceAllConfirm(int $order_id)
    {
        $devices = $this->model->where([
            ['order_id', '=', $order_id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', '<', RecycleOrderDict::DEVICE_STATUS_RECYCLED]
        ])->select()->toArray();



        if (empty($devices)) {
            return true;
        }

        return false;

    }
}
