<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\recycle_order;

use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\model\RecycleDeviceLog;
use addon\recycle\app\model\RecycleReturnOrder;
use addon\recycle\app\model\RecycleReturnDevice;
use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\dict\order\RecycleReturnOrderDict;
use core\exception\CommonException;
use core\base\BaseAdminService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use app\service\core\notice\NoticeService;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderNotifyService;
use addon\recycle\app\service\admin\printer\RecyclePrinterTemplateService;
use addon\recycle\app\service\core\recycle_device\CoreRecycleDeviceLogService;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收设备服务
 */
class RecycleDeviceService extends BaseAdminService
{
    /**
     * 构造函数
     * @param int $site_id 站点ID
     */
    public function __construct(int $site_id = 0)
    {
        parent::__construct();
        $this->model = new RecycleDevice();
        $this->notifyService = new CoreRecycleOrderNotifyService();
        $this->logService = new CoreRecycleDeviceLogService();
    }
    /**
     * 获取设备列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param string $field
     * @param string $order
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getPage(array $where = [], int $page = 1, int $limit = 10, string $field = '*', string $order = ''): array
    {
        $search_model = new RecycleDevice();
        $search_model = $search_model->withSearch(['order_id', 'device_name', 'imei', 'model', 'status', 'create_at'], $where)
            ->with(['order'])
            ->field($field)
            ->order(empty($order) ? 'create_at desc' : $order)
            ->append(['status_name']);
            
        return $this->pageQuery($search_model);
    }


    /**
     * 获取设备列表数量
     * @param array $where
     * @return int
     */
    public function getCount(array $where = [])
    {
        $search_model = new RecycleDevice();
        return $search_model->withSearch(['order_id', 'device_name', 'imei', 'status', 'create_at'], $where)->count();
    }

    /**
     * 获取设备信息
     * @param int $id 设备ID
     * @param array $field 字段
     * @return array
     */
    public function getInfo(int $id, array $field = []): array
    {
        
        $info = (new RecycleDevice())->where([['id', '=', $id]])
        ->field($field)->with(['order','checkUser'])
        ->findOrEmpty()
        ->append(['status_name'])
        ->toArray();
       
        return $info;
    }


    /**
     * 添加设备
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        $data['status'] = RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
        
        // 确保分类字段有默认值
        if (!isset($data['category_id']) || empty($data['category_id'])) {
            $data['category_id'] = 1; // 默认为手机分类
        }
        
        $model = new RecycleDevice();
        $model->save($data);
        return $model->id;
    }


    /**
     * sign更新设备信息
     * @param int $id 设备ID
     * @param array $data 更新数据
     * @return bool
     */
    public function signUpdate(int $id, array $data): bool
    {
        $model = RecycleDevice::find($id);
        if (empty($model)) {
            return false;
        }
        return $model->save($data);
    }

    /**
     * 更新设备价格
     * @param int $id 设备ID
     * @param array $data 价格数据
     * @return bool
     * @throws CommonException
     */
    public function updatePrice(int $id, array $data): bool
    {
        try {
            $device = RecycleDevice::findOrEmpty($id);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }

            // 记录原始状态
            $originalStatus = $device->status;

            // 更新设备价格信息
            $updateData = [
                'final_price' => $data['final_price'],
                'price_uid' => $data['price_uid'] ?? $this->uid,
                'price_at' => time(),
                'update_at' => time()
            ];

            if (isset($data['price_remark'])) {
                $updateData['price_remark'] = $data['price_remark'];
            }

            // 如果设备当前状态允许，更新为待确认状态
            if (in_array($originalStatus, [
                RecycleOrderDict::DEVICE_STATUS_CHECKED,
                RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM
            ])) {
                $updateData['status'] = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
            }

            $device->save($updateData);

            // 记录设备定价日志
            $priceData = [
                'old_status' => $originalStatus,
                'initial_price' => $device->initial_price,
                'final_price' => $data['final_price'],
                'price_reason' => $data['price_remark'] ?? '',
                'order_id' => $device->order_id
            ];
            $this->logService->logDevicePrice($device->id, $priceData, $data['price_remark'] ?? '');

            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }
    /**
     * 更新设备信息
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function update(int $id, array $data): bool
    {
        
       
        // 开启事务
        Db::startTrans();
        try {
            $device = RecycleDevice::findOrEmpty($id);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }
            
            $currentStatus = $device->status;
            $targetStatus = $data['status'] ?? $currentStatus;
            
            // 检查状态流转是否合法
            if ($currentStatus != $targetStatus && !RecycleOrderDict::isValidStatusTransition($currentStatus, $targetStatus, 'device')) {
                throw new CommonException('INVALID_STATUS_TRANSITION');
            }


           
            
           // 处理质检和定价逻辑
            if (isset($data['final_price']) && $data['final_price'] > 0) {

            
                // 如果设置了最终价格，且当前状态是质检中或已质检，则自动将状态更新为待确认
                if ($currentStatus == RecycleOrderDict::DEVICE_STATUS_CHECKING || 
                    $currentStatus == RecycleOrderDict::DEVICE_STATUS_CHECKED) {
                    $data['status'] = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
                    $targetStatus = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
                
            
                    // 获取设备所属的订单ID
                    $orderId = $device['order_id'];
            
                    // 查询订单下所有设备的数量
                    $totalDevices = $this->model->where([
                        ['order_id', '=', $orderId],
                    ])->count();
                    
                    // 查询订单下状态为待确认(4)的设备数量
                    $pendingConfirmDevices = $this->model->where([
                        ['order_id', '=', $orderId],
                        ['status', '=', RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM],
                    ])->count();
                    
                    // 查询订单下状态为已回收(5)的设备数量
                    $recycledDevices = $this->model->where([
                        ['order_id', '=', $orderId],
                        ['status', '=', RecycleOrderDict::DEVICE_STATUS_RECYCLED],
                    ])->count();
                    
                    // 统计当前设备之外的非待确认设备数量
                    $nonPendingConfirmDevices = $totalDevices - $pendingConfirmDevices;
                    
                    // 当前设备要更新为待确认状态，所以非待确认设备数量减1
                    if ($currentStatus != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
                        $nonPendingConfirmDevices -= 1;
                    }
                    
                    // 计算待确认和已回收的设备总数
                    $confirmedAndRecycledDevices = $pendingConfirmDevices + $recycledDevices;
                    // 如果当前设备将更新为待确认，则数量加1
                    if ($currentStatus != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
                        $confirmedAndRecycledDevices += 1;
                    }
                    
                    // 如果该设备更新为待确认后，所有设备都将是待确认状态
                    // 或者所有设备都是待确认或已回收状态
                    if ($nonPendingConfirmDevices <= 0 || $confirmedAndRecycledDevices >= $totalDevices) {
                        try {
                            // 查询当前订单状态
                            $currentOrder = RecycleOrder::findOrEmpty($orderId);
                            $currentOrderStatus = $currentOrder->isEmpty() ? 0 : $currentOrder->status;
                            
                            // 更新订单状态为待确认
                            $orderModel = new RecycleOrder();
                            $updateResult = $orderModel->where([
                                ['id', '=', $orderId],
                                ['site_id', '=', $this->site_id]
                            ])->update([
                                'status' => RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,
                                'update_at' => time()
                            ]);
                            
                        // 提示: 用户处理已完成定价的设备
                            Log::record('【确认通知】Order->handle 获取订单信息: '.$orderId , 'notice');
                        // 触发订单同意通知
                            $this->notifyService->orderAgreeNotify(['order_id' => $orderId, "site_id"=>$this->site_id]);
                            
                            
                        } catch (\Exception $e) {
                        Log::record('更新订单状态异常: ' . $e->getMessage(), 'error');
                        }
                    } else {
                        // 记录设备状态统计，方便调试
                    Log::record('设备状态: 非待确认数=' . $nonPendingConfirmDevices . 
                            ', 待确认和已回收总数=' . $confirmedAndRecycledDevices . 
                            ', 总设备数=' . $totalDevices, 'debug');
                    }
                } 
            
            } else {
                    
                    Log::record('未设置最终价格，跳过状态更新逻辑 - 设备ID: ' . $id, 'debug');
            }
            
            // 记录状态变更日志
            if (isset($data['status'])) {
                $this->addDeviceLog($device->id, $currentStatus, $data['status'], $data['remark'] ?? '', $device->order_id);
            }
            // 判断之前是否完成了定价 也就是 final_price > 0 
            // 如果是重新定价 需要将之前的定价的数据 存到 before_price 字段中 并能完成数据的更新
            $isRepricing = false;
            if (!empty($device->final_price) && $device->final_price > 0) {
                $isRepricing = true;
                // 如果before_price字段为空或为0，才保存原始价格
                if (empty($device->before_price) || $device->before_price == 0) {
                    $data['before_price'] = $device->final_price;
                }else{
                    $data['before_price'] =  $device->before_price.','.$device->final_price;
                }
                // 如果不等于 0 则把 before_price之前的值和  $device->final_price 以 , 拼接
               
            }
            

            // 重新定价会把重新定价的uid 追加到price_uid
            if (isset($data['final_price'])) {
                $data['price_uid'] = $this->uid;
                $data['price_at'] = time(); // 添加定价时间
            }
            
            // 更新设备信息
            $device->save($data);
            
            // 同步更新订单状态（如果需要）
            if (isset($data['status']) || isset($data['final_price'])) {
                $this->syncOrderStatus($device->order_id, $targetStatus);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 删除设备
     * @param int $id
     * @return bool
     */
    public function delete(int $id)
    {
        
        return (new RecycleDevice())->where([['id', '=', $id]])->delete();
    }

    /**
     * 批量删除设备
     * @param array $ids
     * @return bool
     */
    public function deleteByIds(array $ids): bool
    {
        return (new RecycleDevice())->whereIn('id', $ids)->delete();
    }

    /**
     * 开始质检设备
     * @param int $id 设备ID
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function startCheck(int $id, string $remark = ''): bool
    {
        // 获取设备信息
        $device = RecycleDevice::findOrEmpty($id);
        if ($device->isEmpty()) {
            throw new CommonException('DEVICE_NOT_FOUND');
        }
        
        // 获取订单信息
        $order = RecycleOrder::findOrEmpty($device->order_id);
        if ($order->isEmpty()) {
            throw new CommonException('ORDER_NOT_FOUND');
        }
        
        // 检查订单状态，确保只有已签收的订单才能进行质检
        if ($order->status == RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
            throw new CommonException('请先签收订单后再进行质检');
        }
        
        // 更新设备状态为质检中
        $device->status = RecycleOrderDict::DEVICE_STATUS_CHECKING;
        $device->check_uid = $this->uid;
        $device->save();
        
        // 记录质检开始日志
        if (!isset($this->logService)) {
            $this->logService = new CoreRecycleDeviceLogService();
        }
        $this->logService->logDeviceCheckStart($id, $remark);
        
        return true;
    }

    /**
     * 完成质检设备
     * @param int $id 设备ID
     * @param array $checkData 质检数据
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function completeCheck(int $id, array $checkData, string $remark = '')
    {

        
        // 开启事务
        Db::startTrans();
        try {
            $device = RecycleDevice::findOrEmpty($id);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }
            
            // 获取订单信息
            $order = RecycleOrder::findOrEmpty($device->order_id);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }
            
            // 检查订单状态，确保只有已签收的订单才能进行质检
            if ($order->status == RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
                throw new CommonException('请先签收订单后再进行质检');
            }
            
            // 检查当前状态，如果不是质检中状态，则自动开始质检
            if ($device->status != RecycleOrderDict::DEVICE_STATUS_CHECKING) {
                // 如果是待质检状态，则自动开始质检
                if ($device->status == RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK) {
                    // 先将设备状态更新为质检中
                    $device->status = RecycleOrderDict::DEVICE_STATUS_CHECKING;
                    $device->save();
                    
                    // 记录质检开始日志
                    if (!isset($this->logService)) {
                        $this->logService = new CoreRecycleDeviceLogService();
                    }
                    $this->logService->logDeviceCheckStart($device->id, '自动开始质检');
                } else {
                    throw new CommonException('DEVICE_STATUS_ERROR');
                }
            }
            
            // 根据check_status确定目标状态
            $targetStatus = RecycleOrderDict::DEVICE_STATUS_CHECKED;
            if (isset($checkData['check_status'])) {
                if ($checkData['check_status'] == 2) { // 假设2表示退回
                    $targetStatus = RecycleOrderDict::DEVICE_STATUS_RETURNED;
                } else if (isset($checkData['final_price']) && $checkData['final_price'] > 0) {
                    $targetStatus = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
                   
                }
                // 移除check_status，避免保存到数据库
                unset($checkData['check_status']);
            } else if (isset($checkData['final_price']) && $checkData['final_price'] > 0) {
                $targetStatus = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
            }

        
            
            // 更新设备质检信息
            $updateData = [
                'status' => $targetStatus,
                'check_uid'=>  $this->uid,
                'check_at'=> time(),
                'remark' => $remark,
            ];
            // if model
            if (isset($checkData['model'])) {
                $updateData['model'] = $checkData['model'];
            }
           
            // if info
            
          
            // 如果有最终价格 则 更新 'price_uid'=>  $this->uid,
            if (isset($checkData['final_price']) && $checkData['final_price'] > 0) {
                $updateData['price_uid'] =  $this->uid;
            }
            
            // 合并质检数据
            if (!empty($checkData)) {
                $updateData = array_merge($updateData, $checkData);
            }
            if (isset($checkData['info'])) {
                // 将info 转换为 json 字符串
                $updateData['info'] = json_encode($checkData['info']);
            }
            
            $device->save($updateData);
            
            // 记录质检完成日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }
            $this->logService->logDeviceCheckComplete($device->id, $checkData, $remark);
            
            // 同步更新订单状态
            try {
                $this->syncOrderStatus($device->order_id, $targetStatus);
            } catch (\Exception $e) {
              Log::record('【调试日志】completeCheck同步订单状态异常: ' . $e->getMessage(), 'error');
            }
            
            // 触发质检完成事件（通过事件处理自动打印）
            try {
                $eventData = [
                    'device_id' => $id,
                    'site_id' => $this->site_id,
                    'status' => $targetStatus,
                    'order_id' => $device->order_id,
                    'uid' => $this->uid
                ];
                event('AfterDeviceCheckComplete', $eventData);
                Log::record('【质检完成】已触发质检完成事件: ' . json_encode($eventData), 'info');
            } catch (\Exception $e) {
                Log::record('【质检完成】触发事件异常: ' . $e->getMessage(), 'error');
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 确认设备价格
     * @param int $id 设备ID
     * @param float $price 价格
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function confirmPrice(int $id, float $price, string $remark = ''): bool
    {
       
     
        // 开启事务
        Db::startTrans();
        try {
            $device = $this->model->find($id);
            if (empty($device)) {
                throw new CommonException('设备不存在');
            }
            
            // 检查当前状态是否允许确认价格
            if (
                $device->status != RecycleOrderDict::DEVICE_STATUS_CHECKING &&
                $device->status != RecycleOrderDict::DEVICE_STATUS_CHECKED &&
                $device->status != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM
            ) {
                throw new CommonException('当前设备状态不允许确认价格');
            }
            
            

            $old_status = $device->status;
            $oldPrice = $device->final_price;
            $device->final_price = $price;
            $device->remark = $remark;
            $device->price_uid = $this->uid;
            $device->price_at = time(); // 添加定价时间
            $device->status = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
            $device->update_time = time();
            
            // 检查是否为重新定价
            $isRepricing = !empty($oldPrice) && $oldPrice != $price;
            if ($isRepricing) {
                $device->reprice_time = time();
            }
            $device->save();
            
            // 记录价格确认日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }
            $priceData = [
                'old_status' => $old_status,
                'initial_price' => $device->initial_price,
                'final_price' => $price,
                'old_price' => $oldPrice,
                'price_change' => $price - ($oldPrice ?? 0),
                'is_repricing' => $isRepricing,
                'order_id' => $device->order_id
            ];
            $this->logService->logDevicePrice($id, $priceData, $remark);
            
            // 尝试同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM);
            
            // 检查该订单下的所有设备是否都已确认价格
            $allDevices = $this->model->where('order_id', $device->order_id)->select();
            $allConfirmed = true;
            
            foreach ($allDevices as $dev) {
                if ($dev->status != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM && 
                    $dev->status != RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
                    $allConfirmed = false;
                    break;
                }
            }
            
            // 如果所有设备都已确认价格，触发通知
            if ($allConfirmed) {
                $this->notifyService->orderAgreeNotify(['order_id' => $device->order_id, 'site_id' => $this->site_id]);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 回收设备
     * @param int $id
     * @param string $remark
     * @return bool
     */
    public function recycle(int $id, string $remark = ''): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            $device = $this->model->find($id);
            if (empty($device)) {
                throw new CommonException('设备不存在');
            }
            
            // 只有待确认状态的设备可以回收
            if ($device->status != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
                throw new CommonException('当前设备状态不允许回收');
            }
            
            $old_status = $device->status;
            $device->status = RecycleOrderDict::DEVICE_STATUS_RECYCLED;
            $device->update_time = time();
            $device->save();
            
            // 记录回收日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }
            $this->logService->logDeviceRecycle($id, $remark);
            
            // 尝试同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleOrderDict::DEVICE_STATUS_RECYCLED);
            
           
            
            
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * getList
     * 获取设备列表（不分页）
     * @param array $where 查询条件
     * @param string $field 查询字段
     * @param string $order 排序规则
     * @return array
     */
    public function getList(array $where = [], string $field = '*', string $order = ''): array
    {
        $search_model = new RecycleDevice();
        return $search_model->withSearch(['order_id', 'device_name', 'imei', 'model', 'status', 'create_at'], $where)
            ->field($field)
            ->order(empty($order) ? 'create_at desc' : $order)
            ->append(['status_name'])
            ->select()
            ->toArray();
    }

    /**
     * 退回单个设备（原有方法修改，返回退货单ID）
     * 
     * @param int $id 设备ID
     * @param string $remark 备注信息 
     * @return array 包含return_order_id的数组
     */
    public function returnDevice(int $id, string $remark = ''): array
    {
        Db::startTrans();
        try {
            // 获取设备信息
            $device = RecycleDevice::where('id', $id)->find();
            if (!$device) {
                throw new CommonException('设备不存在');
            }
            
            // 确保设备有订单关联
            if (empty($device->order_id)) {
                throw new CommonException('设备未关联订单');
            }
            
            // 获取订单信息
            $order = RecycleOrder::where('id', $device->order_id)->find();
            if (!$order) {
                throw new CommonException('关联的订单不存在');
            }
            
            // 保存设备原始状态用于日志记录
            $oldStatus = $device->status;
            
            // 检查是否已经存在退货单
            $existingReturnOrder = RecycleReturnOrder::where('order_id', $device->order_id)->find();
            if ($existingReturnOrder) {
                // 如果已经存在退货单，直接使用
                $returnOrderId = $existingReturnOrder->id;
                Log::info("找到现有退货单ID: {$returnOrderId} 关联订单ID: {$device->order_id}");
            } else {
                // 创建新的退货单
                $returnOrderData = [
                    'order_id' => $device->order_id,
                    'order_no' => $order->order_no,
                    'site_id' => $order->site_id,
                    'member_id' => $order->member_id,
                    'status' => RecycleReturnOrderDict::ORDER_STATUS_PENDING, // 使用RecycleReturnOrderDict中的常量
                    'create_at' => time(),
                    
                ];
                
                $returnOrderModel = new RecycleReturnOrder();
                $returnOrderId = $returnOrderModel->insertGetId($returnOrderData);
                
                if (!$returnOrderId) {
                    throw new CommonException('创建退货单记录失败');
                }
                
                Log::info("创建新退货单ID: {$returnOrderId} 关联订单ID: {$device->order_id}");
            }
            
            // 更新设备状态为退回中
            $device->status = RecycleReturnOrderDict::DEVICE_STATUS_RETURNING; // 使用RecycleOrderDict中的常量
            $device->return_order_id = $returnOrderId;
            $device->return_time = time();
            $device->return_remark = $remark;
            
            if (!$device->save()) {
                throw new CommonException('更新设备状态失败');
            }
            
            // 记录设备退回日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }
            $this->logService->logDeviceReturn($id, $remark, $remark);
            
            // 添加设备到退货单关联表
            $returnDeviceModel = new RecycleReturnDevice();
            $returnDeviceData = [
                'return_order_id' => $returnOrderId,
                'device_id' => $id,
                'create_time' => time(),
                'remark' => $remark,
            ];
            
            if (!$returnDeviceModel->save($returnDeviceData)) {
                throw new CommonException('关联设备到退货单失败');
            }
            
            // 同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleReturnOrderDict::DEVICE_STATUS_RETURNING);
            
            Db::commit();
            return ['return_order_id' => $returnOrderId];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("退回设备失败（设备ID: {$id}）: " . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 更新设备状态
     * @param int $id 设备ID
     * @param int $status 状态
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function updateStatus(int $id, int $status, string $remark = ''): bool
    {
        Db::startTrans();
        try {
            $device = RecycleDevice::findOrEmpty($id);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }
            
            // 检查状态流转是否合法
            if (!RecycleOrderDict::isValidStatusTransition($device->status, $status, 'device')) {
                throw new CommonException('INVALID_STATUS_TRANSITION');
            }
            
            // 更新设备状态
            $device->status = $status;
            $device->final_status = 1;
            $device->save();
            
            // 记录日志
            $this->addDeviceLog($id, $status, $remark);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量回收设备
     * @param array $ids 设备ID数组
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function batchRecycle(array $ids, string $remark = ''): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            foreach ($ids as $id) {
                $this->recycle((int)$id, $remark);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量退回设备
     * @param array $ids 设备ID数组
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function batchReturn(array $ids, string $remark = ''): bool
    {
        if (empty($ids)) {
            throw new CommonException('请选择要退回的设备');
        }
        
        // 开启事务
        Db::startTrans();
        try {
            $success = 0;
            $errors = [];
            
            // 按订单ID分组处理设备
            $devicesByOrder = [];
            
            // 1. 先获取所有设备信息并按订单ID分组
            $devices = RecycleDevice::whereIn('id', $ids)->select();
            foreach ($devices as $device) {
                $deviceId = $device->id;
                $orderId = $device->order_id;
                
                if (empty($orderId)) {
                    $errors[] = "设备ID: {$deviceId}, 错误: 设备未关联订单";
                    continue;
                }
                
                if (!isset($devicesByOrder[$orderId])) {
                    $devicesByOrder[$orderId] = [];
                }
                $devicesByOrder[$orderId][] = $device;
            }
            
            // 2. 检查每个订单是否已有退货单
            $returnOrderByOrderId = [];
            $orderIds = array_keys($devicesByOrder);
            
            if (!empty($orderIds)) {
                // 查询已存在的退货单
                $existingReturnOrders = RecycleReturnOrder::whereIn('order_id', $orderIds)->select();
                foreach ($existingReturnOrders as $returnOrder) {
                    $returnOrderByOrderId[$returnOrder->order_id] = $returnOrder;
                }
            }
            
            // 3. 按订单处理设备退回
            foreach ($devicesByOrder as $orderId => $orderDevices) {
                try {
                    // 检查此订单是否已有退货单
                    $returnOrderId = null;
                    if (isset($returnOrderByOrderId[$orderId])) {
                        // 使用已存在的退货单
                        $returnOrderId = $returnOrderByOrderId[$orderId]->id;
                        Log::info("使用已存在的退货单 ID: {$returnOrderId} 处理订单 ID: {$orderId} 的设备");
                    }
                    
                    // 处理当前订单下的所有设备
                    foreach ($orderDevices as $device) {
                        if ($returnOrderId) {
                            // 添加到已有退货单
                            $this->appendDeviceToReturnOrder($device->id, $returnOrderId, $remark);
                        } else {
                            // 创建新的退货单
                            $returnOrderId = $this->createReturnOrderForDevice($device->id, $remark);
                            // 记录新创建的退货单，以便后续设备使用
                            if ($returnOrderId) {
                                $returnOrderByOrderId[$orderId] = (object)['id' => $returnOrderId];
                            }
                        }
                        $success++;
                    }
                } catch (\Exception $e) {
                    // 记录订单级别的处理失败，但继续处理其他订单
                    $errors[] = "订单ID: {$orderId}, 错误: " . $e->getMessage();
                    Log::error("订单退回失败: 订单ID {$orderId}, 错误: " . $e->getMessage());
                }
            }
            
            // 如果所有设备都失败，则抛出异常
            if ($success === 0 && !empty($errors)) {
                $errorMsg = '所有设备退回失败: ' . implode('; ', $errors);
                Log::error($errorMsg);
                throw new CommonException($errorMsg);
            }
            
            // 如果部分成功，记录日志
            if (!empty($errors)) {
                $warningMsg = '部分设备退回失败: ' . implode('; ', $errors);
                Log::warning($warningMsg);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量更新设备状态
     * @param array $ids 设备ID数组
     * @param int $status 目标状态
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function batchUpdateStatus(array $ids, int $status, string $remark = ''): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            foreach ($ids as $id) {
                $this->updateStatus((int)$id, $status, $remark);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 添加设备日志
     * @param int $deviceId 设备ID
     * @param int $fromStatus 原状态
     * @param int $toStatus 新状态
     * @param string $remark 备注
     * @param int $orderId 订单ID
     * @return int 日志ID
     */
    public function addDeviceLog(int $deviceId, int $fromStatus, int $toStatus, string $remark = '', int $orderId = 0): int
    {

        $log = new RecycleDeviceLog();
        $log->save([
            'site_id' => $this->site_id,
            'device_id' => $deviceId,
            'order_id' => $orderId,
            'operator_id' => $this->uid ?? 0,
            'operator_name' => '',
            'action' => 'status_change',
            'old_status' => $fromStatus,
            'new_status' => $toStatus,
            'remark' => $remark ?? '',
            'create_at' => time()
        ]);
        return $log->id;
    }

    /**
     * 同步更新订单状态
     * @param int $orderId 订单ID
     * @param int $deviceStatus 设备状态
     * @return bool
     */
    protected function syncOrderStatus(int $orderId, int $deviceStatus): bool
    {
        
        // 获取订单信息
        $order = RecycleOrder::findOrEmpty($orderId);
        if ($order->isEmpty()) {
            return false;
        }
        
        // 获取订单下所有设备
        $devices = RecycleDevice::where('order_id', $orderId)->select();
        if ($devices->isEmpty()) {
            return false;
        }
        
        // 统计各状态设备数量
        $deviceStatusCounts = [
            RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK => 0,
            RecycleOrderDict::DEVICE_STATUS_CHECKING => 0,
            RecycleOrderDict::DEVICE_STATUS_CHECKED => 0,
            RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM => 0,
            RecycleOrderDict::DEVICE_STATUS_RECYCLED => 0,
            RecycleOrderDict::DEVICE_STATUS_RETURNED => 0
        ];
        
        $allDevicesPriced = true; // 检查所有设备是否都已定价
        $allDevicesChecked = true; // 检查所有设备是否都已完成质检
        $allDevicesInFinalState = true; // 检查所有设备是否都处于终态（回收或退回）
        
        foreach ($devices as $device) {
            // 统计各状态设备数量
            if (isset($deviceStatusCounts[$device->status])) {
                $deviceStatusCounts[$device->status]++;
            }
            
            // 检查非退回设备是否都已定价
            if ($device->status != RecycleOrderDict::DEVICE_STATUS_RETURNED && empty($device->final_price)) {
                $allDevicesPriced = false;
            }
            
            // 检查设备是否都已完成质检（已质检、待确认、已回收或已退回的设备视为已完成质检）
            if ($device->status == RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK || 
                $device->status == RecycleOrderDict::DEVICE_STATUS_CHECKING) {
                $allDevicesChecked = false;
            }
            
            // 检查设备是否处于终态（已回收或已退回）
            if ($device->status != RecycleOrderDict::DEVICE_STATUS_RECYCLED && 
                $device->status != RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                $allDevicesInFinalState = false;
            }
        }
        
        // 计算待确认和已回收的设备总数
        $confirmedAndRecycledDevices = $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] + 
                                      $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED];
                                      
        // 记录日志
      Log::record('syncOrderStatus - 订单ID: ' . $orderId . ', 设备总数: ' . $devices->count() . 
            ', 待确认设备数: ' . $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] . 
            ', 已回收设备数: ' . $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] . 
            ', 待确认和已回收总数: ' . $confirmedAndRecycledDevices, 'debug');
        
        // 记录设备状态统计
        $statusSummary = "设备状态统计: ";
        foreach ($deviceStatusCounts as $status => $count) {
            if ($count > 0) {
                $statusSummary .= RecycleOrderDict::getDeviceStatus($status) . ": {$count}台, ";
            }
        }
        $this->addDeviceLog(0, 0, 0, rtrim($statusSummary, ", "), $orderId);
        
        // 计算订单应处于的状态
        $orderStatus = $order->status; // 默认保持当前状态
        
        // 检查是否所有设备都是待确认或已回收状态
        if ($confirmedAndRecycledDevices == $devices->count()) {
            // 如果所有设备都是待确认或已回收状态，将订单状态更新为待确认
            if ($order->status != RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM && 
                $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] > 0) {
                $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
                $this->addDeviceLog(0, 0, 0, "所有设备都已处于待确认或已回收状态，订单进入待确认状态", $orderId);
                // 直接更新订单状态并返回
                $order->status = $orderStatus;
                $order->save();
               
              // 提示: 用户处理已完成定价的设备
                 Log::record('【确认通知】Order->handle 获取订单信息: '.$orderId , 'notice');
              // 触发订单同意通知
                $this->notifyService->orderAgreeNotify(['order_id' => $orderId, "site_id"=>$this->site_id]);
                return true;
            }
        }
        
        // 优先处理所有设备都处于终态的情况
        if ($allDevicesInFinalState) {
            // 如果所有设备都已退回，订单进入已关闭状态
            if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED] == $devices->count()) {
                $orderStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
                $this->addDeviceLog(0, 0, 0, "所有设备都已退回，订单进入已关闭状态", $orderId);
                
                // 尝试创建退货订单（如果还没有为这些设备创建过）
                $this->createReturnOrderForAllDevices($orderId, "所有设备退回，系统自动创建退货单");
            } 
            // 如果部分设备已回收，部分已退回，订单进入待打款状态
            else if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] > 0) {
                $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
                $this->addDeviceLog(0, 0, 0, "所有设备都处于终态，有{$deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED]}台设备已回收，订单进入待打款状态", $orderId);
            }
            
            // 直接更新订单状态并返回，不再执行后续的逻辑
            if ($orderStatus != $order->status) {
                $order->status = $orderStatus;
                $order->update_time = time();
                $order->save();
                $this->addDeviceLog(0, 0, 0, "订单状态从 " . RecycleOrderDict::ORDER_STATUS_TEXT[$order->status] . " 变更为 " . RecycleOrderDict::ORDER_STATUS_TEXT[$orderStatus], $orderId);
                return true;
            }
        }
        
        // 根据业务流程处理订单状态更新
        switch ($deviceStatus) {
            case RecycleOrderDict::DEVICE_STATUS_CHECKING:
                // 如果有一个设备进入质检中，订单就进入质检中状态
                if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKING) {
                    $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKING;
                    $this->addDeviceLog(0, 0, 0, "有设备进入质检中状态，订单进入质检中状态", $orderId);
                }
                break;
                
            case RecycleOrderDict::DEVICE_STATUS_CHECKED:
            case RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM:
                // 如果还有设备未完成质检，订单保持质检中状态
                if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK] > 0 || 
                    $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_CHECKING] > 0) {
                    if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKING) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKING;
                        $this->addDeviceLog(0, 0, 0, "存在未完成质检的设备，订单进入质检中状态", $orderId);
                    }
                    break;
                }
                
                // 所有设备都已完成质检
                if ($allDevicesChecked) {
                    // 如果当前订单状态低于已质检，更新为已质检
                    if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKED) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKED;
                        $this->addDeviceLog(0, 0, 0, "所有设备已完成质检，订单进入已质检状态", $orderId);
                        break;
                    }
                    
                    // 如果所有设备都已定价，检查是否可以进入待确认状态
                    if ($allDevicesPriced) {
                        // 计算非退回设备数量
                        $nonReturnedDevices = $devices->count() - $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED];
                        
                        // 只有当所有非退回设备都处于待确认状态时，订单才能进入待确认状态
                        if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] == $nonReturnedDevices) {
                            // 如果当前订单状态是已质检，可以直接过渡到待确认状态
                            if ($order->status == RecycleOrderDict::ORDER_STATUS_CHECKED) {
                                $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
                                $this->addDeviceLog(0, 0, 0, "所有非退回设备都已定价并处于待确认状态，订单进入待确认状态", $orderId);
                                
                                // 提示: 用户处理已完成定价的设备
                                  Log::record('【确认通知】Order->handle 获取订单信息: '.$orderId , 'notice');
                              // 触发订单同意通知
                                $this->notifyService->orderAgreeNotify(['order_id' => $orderId, "site_id"=>$this->site_id]);
                            }
                        }
                    }
                }
                break;
                
            case RecycleOrderDict::DEVICE_STATUS_RECYCLED:
                // 检查是否所有设备都已回收或已退回
                $totalConfirmedDevices = $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] + 
                                        $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED];
                
                // 如果所有设备都已确认（回收或退回）
                if ($totalConfirmedDevices == $devices->count()) {
                    // 如果至少有一个设备是已回收状态，订单进入待打款状态
                    if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] > 0) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
                        $this->addDeviceLog(0, 0, 0, "所有设备都已确认，且至少有一个设备已回收，订单进入待打款状态", $orderId);
                    } else {
                        // 如果所有设备都已退回，没有已回收的设备，订单进入已关闭状态
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
                        $this->addDeviceLog(0, 0, 0, "所有设备都已退回，订单进入已关闭状态", $orderId);
                    }
                }
                break;
                
            case RecycleOrderDict::DEVICE_STATUS_RETURNED:
                // 检查是否所有设备都已退回
                if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED] == $devices->count()) {
                    // 如果所有设备都已退回，订单进入已关闭状态
                    $orderStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
                    $this->addDeviceLog(0, 0, 0, "所有设备都已退回，订单进入已关闭状态", $orderId);
                    
                    // 为所有设备创建退货订单
                   $this->createReturnOrderForAllDevices($orderId, "所有设备退回，系统自动创建退货单");
                } else {
                    // 如果不是所有设备都已退回，需要检查其他设备的状态
                    // 如果所有非退回设备都已完成质检

                    if ($allDevicesChecked) {
                        // 如果当前订单状态低于已质检，更新为已质检
                        if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKED) {
                            $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKED;
                            $this->addDeviceLog(0, 0, 0, "所有非退回设备已完成质检，订单进入已质检状态", $orderId);
                        }
                    }
                }
                break;
        }
        
        // 如果订单状态需要更新
        if ($orderStatus != $order->status) {
            // 记录订单状态变更
            $this->addDeviceLog(0, $order->status, $orderStatus, "订单状态从 " . RecycleOrderDict::ORDER_STATUS_TEXT[$order->status] . " 变更为 " . RecycleOrderDict::ORDER_STATUS_TEXT[$orderStatus], $orderId);
            
            // 更新订单状态
            $order->status = $orderStatus;
            $order->update_time = time();
            $order->save();
        }
        
        return true;
    }
    
    /**
     * 为订单的所有设备创建退货订单
     * @param int $orderId 订单ID
     * @param string $remark 备注
     * @return bool
     */
    protected function createReturnOrderForAllDevices(int $orderId, string $remark = '',): bool
    {

        try {
            // 获取订单下的所有设备
            $deviceIds = RecycleDevice::where('order_id', $orderId)
                ->column('id');
            $member_id = RecycleDevice::where('order_id', $orderId)
            ->column('member_id');
                
                
            if (empty($deviceIds)) {
                return false;
            }
            
            // 检查是否已经为这些设备创建了退货单
            $existingReturnDevices = (new RecycleReturnDevice())
                ->whereIn('device_id', $deviceIds)
                ->count();
                
            // 如果已经有退货单，不再重复创建
            if ($existingReturnDevices > 0) {
                $this->addDeviceLog(0, 0, 0, "系统检测到已存在退货单，不再重复创建", $orderId);
                return true;
            }
            
            // 创建退货订单
            $returnOrderService = new \addon\recycle\app\service\admin\RecycleReturnOrderService();
            $returnOrderData = [
                'site_id' => $this->site_id,
                'order_id' => $orderId,
                'device_ids' => $deviceIds,
                'comment' => $remark,
                'return_address' => '',
                'operator_id' => $this->uid,
                'operator_name' => $this->username,
                'member_id' => $member_id,
            ];
            
            $result = $returnOrderService->create($returnOrderData);
            if (!$result || (isset($result['code']) && $result['code'] != 0)) {
                // 如果创建退货订单失败，记录日志但不影响流程
                $errorMsg = isset($result['msg']) ? $result['msg'] : '未知错误';
                Log::error("为订单 {$orderId} 的所有设备创建退货单失败: {$errorMsg}");
                return false;
            }
            
            $this->addDeviceLog(0, 0, 0, "成功为订单 {$orderId} 的所有设备创建退货单", $orderId);
            return true;
        } catch (\Exception $e) {
            Log::error("创建退货单异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 将设备添加到已有退货单
     * 
     * @param int $deviceId 设备ID
     * @param int $returnOrderId 退货单ID
     * @param string $remark 备注信息
     * @return bool
     */
    protected function appendDeviceToReturnOrder(int $deviceId, int $returnOrderId, string $remark = ''): bool
    {
        Db::startTrans();
        try {
            // 获取设备信息
            $device = RecycleDevice::where('id', $deviceId)->find();
            if (!$device) {
                throw new CommonException('设备不存在');
            }
            
            // 保存设备原始状态用于日志记录
            $oldStatus = $device->status;
            
            // 更新设备状态
            $device->status = RecycleReturnOrderDict::DEVICE_STATUS_RETURNING;
            $device->return_order_id = $returnOrderId;
            $device->return_time = time();
            $device->return_remark = $remark;

            
            if (!$device->save()) {
                throw new CommonException('更新设备状态失败');
            }
            
            // 记录设备状态变更日志
            $this->addDeviceLog($deviceId, $oldStatus, RecycleReturnOrderDict::DEVICE_STATUS_RETURNING, $remark, $device->order_id);
            
            // 添加设备到退货单关联表
            $returnDeviceModel = new RecycleReturnDevice();
            $returnDeviceData = [
                'return_order_id' => $returnOrderId,
                'device_id' => $deviceId,
                'create_at' => time(),
                
            ];
            
            if (!$returnDeviceModel->save($returnDeviceData)) {
                throw new CommonException('关联设备到退货单失败');
            }
            
            // 同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleReturnOrderDict::DEVICE_STATUS_RETURNING);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("添加设备到退货单失败（设备ID: {$deviceId}, 退货单ID: {$returnOrderId}）: " . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }
    
    /**
     * 为设备创建新的退货单
     * 
     * @param int $deviceId 设备ID
     * @param string $remark 备注信息
     * @return int 新创建的退货单ID
     */
    protected function createReturnOrderForDevice(int $deviceId, string $remark = ''): int
    {
        try {
            // 先获取设备信息，检查是否已有关联的退货单
            $device = RecycleDevice::where('id', $deviceId)->find();
            if (!$device) {
                throw new CommonException('设备不存在');
            }
            
            // 如果设备已经关联了退货单，直接返回该退货单ID
            if (!empty($device->return_order_id)) {
                Log::info("设备ID: {$deviceId} 已关联退货单ID: {$device->return_order_id}，直接使用");
                return $device->return_order_id;
            }
            
            // 获取订单信息
            $order = RecycleOrder::where('id', $device->order_id)->find();
            if (!$order) {
                throw new CommonException("关联的订单不存在，订单ID: {$device->order_id}");
            }
            
            // 调用returnDevice创建退货单
            $result = $this->returnDevice($deviceId, $remark);
            
            if (empty($result) || !isset($result['return_order_id'])) {
                Log::error("创建退货单失败，结果: " . json_encode($result));
                throw new CommonException('创建退货单失败，无法获取退货单ID');
            }
            
            Log::info("成功为设备ID: {$deviceId} 创建退货单ID: {$result['return_order_id']}");
            return $result['return_order_id'];
        } catch (\Exception $e) {
            Log::error("为设备创建退货单失败（设备ID: {$deviceId}）: " . $e->getMessage());
            throw new CommonException('创建退货单失败: ' . $e->getMessage());
        }
    }
    // getImeiInfo
    /**
     * 查询IMEI/SN信息
     * @param string $imei IMEI或序列号
     * @return array
     */
    public function getImeiInfo(string $imei)
    {

   
        try {
            // 使用新的设备查询服务
            $queryService = new \addon\recycle\app\service\admin\DeviceQueryService();
         
            $result = $queryService->queryDevice($imei  , $this->site_id);
        
            // var_dump($result);
            // die;
  
            if ($result['success']  && !empty($result['data'])) {

             
                $data = $result['data'];

          
                $name = '';
                
                // 组合设备名称
                if (isset($data['model'])) {
                    $name = $data['model'];
                }
                
                if (isset($data['fmi'])) {
                    $name .= ' ' . $data['fmi'];
                }
                
                return ['name' => trim($name)];
            } else {
                return ['name' => ''];
            }
        } catch (\Exception $e) {
            // 如果新服务出错，回退到原来的查询方式
            return $this->getImeiInfoFallback($imei);
        }
    }

    /**
     * 获取IMEI信息（使用配置化查询服务）
     * @param string $imei
     * @return array
     */
    private function getImeiInfoFallback(string $imei)
    {
        try {
            // 使用新的设备查询服务
            $deviceQueryService = new \addon\recycle\app\service\admin\DeviceQueryService();
            $result = $deviceQueryService->queryDeviceInfo($imei, 'imei');
            
           

            if ($result && isset($result['query_result'])) {
                $data = $result['query_result'];
                
                // 构建设备名称
                $name = '';
                if (!empty($data['model'])) {
                    $name = $data['model'];
                    if (!empty($data['capacity'])) {
                        $name .= ' ' . $data['capacity'];
                    }
                    if (!empty($data['color'])) {
                        $name .= ' ' . $data['color'];
                    }
                }
                
                return ['name' => $name ?: ''];
            }
            
            return ['name' => ''];
        } catch (\Exception $e) {
            // 记录错误日志
            trace('IMEI查询失败: ' . $e->getMessage());
            return ['name' => ''];
        }
    }


    // printDeviceLabel
    public function printDeviceLabel(int $id)
    {
        // 查询和这个设备相关的所有信息
        $device = RecycleDevice::where('id', $id) 
        ->with('checkUser')
        ->find();
        if (!$device) {
            throw new CommonException('设备不存在');
        }
        
        // 使用简化版打印服务
        $printerService = new \addon\recycle\app\service\admin\printer\RecyclePrinterService();
        return $printerService->printDeviceLabel($id);
    }

}
