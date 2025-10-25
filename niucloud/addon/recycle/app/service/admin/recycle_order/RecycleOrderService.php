<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\recycle_order;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrderLog;
use core\base\BaseAdminService;
use app\service\core\notice\NoticeService;
use core\exception\CommonException;
use addon\recycle\app\service\admin\order\RecycleOrderSignService;
use addon\recycle\app\service\admin\order\RecycleOrderCloseService;
use addon\recycle\app\service\admin\order\RecycleOrderCheckService;
use addon\recycle\app\service\admin\order\RecycleOrderPriceService;
use addon\recycle\app\service\admin\order\RecycleOrderPaymentService;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use addon\recycle\app\service\admin\recycle_order\RecycleDeviceService;

use think\facade\Db;
use think\facade\Log;

/**
 * 二手机回收报价订单服务层
 * Class RecycleOrderService
 * @package addon\recycle\app\service\admin\recycle_order
 */
class RecycleOrderService extends BaseAdminService
{
    /**
     * @var RecycleOrder
     */
    protected $model;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleOrder();
    }

    /**
     * 代下单
     * @param array $data
     * @return array
     * @throws CommonException
     */
    public function create(array $data): array
    {
        $data['site_id'] = $this->site_id;
        $coreService = new CoreRecycleOrderService();
        $order = $coreService->create($data);
        return ['id' => $order->id, 'order_no' => $order->order_no];
    }


    /**
     * 获取二手机回收报价订单列表
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
    public function getPage(array $where = []): array
    {
        $field = empty($field) ? '*' : $field;
        $order = empty($order) ? 'create_at desc' : $order;
        
        // 处理前端传递的参数映射
        $searchParams = [];
        
        // 订单号搜索 - 修复参数映射
        if (!empty($where['order_no'])) {
            $searchParams['order_no'] = $where['order_no'];
        }
        
        // 订单ID搜索
        if (!empty($where['order_id'])) {
            $searchParams['id'] = $where['order_id'];
        }
        
        // 快递单号搜索
        if (!empty($where['express_no'])) {
            $searchParams['express_no'] = $where['express_no'];
        }
        
        // 备注搜索
        if (!empty($where['remark'])) {
            $searchParams['remark'] = $where['remark'];
        }
        
        // 创建时间搜索 - 支持多种时间参数格式
        if (!empty($where['create_time_start']) && !empty($where['create_time_end'])) {
            $searchParams['create_at'] = [$where['create_time_start'], $where['create_time_end']];
        } elseif (!empty($where['create_at']) && is_array($where['create_at']) && count($where['create_at']) == 2) {
            $searchParams['create_at'] = $where['create_at'];
        }
        
        // 状态搜索
        if (isset($where['status']) && $where['status'] !== '') {
            if (is_string($where['status']) && strpos($where['status'], ',') !== false) {
                $searchParams['status'] = explode(',', $where['status']);
            } else {
                $searchParams['status'] = $where['status'];
            }
        }
        
        // 配送方式搜索
        if (isset($where['delivery_type']) && $where['delivery_type'] !== '') {
            if (is_string($where['delivery_type']) && strpos($where['delivery_type'], ',') !== false) {
                $searchParams['delivery_type'] = explode(',', $where['delivery_type']);
            } else {
                $searchParams['delivery_type'] = $where['delivery_type'];
            }
        }
        
        // 设备IMEI搜索
        if (!empty($where['device_imei'])) {
            $searchParams['imei'] = $where['device_imei'];
        }
        
        // 设备型号搜索
        if (!empty($where['device_model'])) {
            $searchParams['device_model'] = $where['device_model'];
        }
        
        // 用户昵称搜索
        if (!empty($where['user_nickname'])) {
            $searchParams['customer_name'] = $where['user_nickname'];
        }
        
        // 用户手机搜索
        if (!empty($where['user_mobile'])) {
            $searchParams['customer_phone'] = $where['user_mobile'];
        }
        
        // 关键词综合搜索（支持订单号、用户昵称、手机号、IMEI）
        if (!empty($where['keyword'])) {
            $searchParams['keyword'] = $where['keyword'];
        }
        
        // 通用搜索
        if (!empty($where['search'])) {
            $searchParams['search'] = $where['search'];
        }
        
        if (!empty($where['member_id'])) {
            $searchParams['member_id'] = $where['member_id'];
        }

        
        $search_model = (new RecycleOrder())
            ->withSearch([
                'id', 'order_no', 'express_no', 'customer_name', 'customer_phone', 
                'status', 'delivery_type', 'create_at', 'remark', 'imei', 
                'device_model', 'search', 'keyword','member_id'
            ], $searchParams)
            ->where([['site_id', '=', $this->site_id], ['delete_at', '=', 0]])
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,imei,model,initial_price,status,category_id,final_price')
                        ->append(['status_name','category_name']);
                },
                'member' => function($query) {
                    $query->field('member_id,username,nickname,mobile,headimg');
                },
                'recycleUserAddress' => function($query) {
                    $query->field('id,member_id,name,mobile,address');
                }
            ])
            ->field($field)
            ->order($order)
            ->append(['status_name', 'delivery_type_name']);
            
        // 获取分页数据
        $result = $this->pageQuery($search_model);
        
        // 计算各个状态的数量（基于当前搜索条件，但不包含状态筛选）
        $statusCounts = $this->getStatusCounts($where);
        
        // 将状态统计添加到返回结果中
        $result['status_counts'] = $statusCounts;
            
        return $result;
    }

    /**
     * 获取二手机回收报价订单数量
     * @param array $where
     * @return int
     */
    public function getCount(array $where = []): int
    {
        // 处理前端传递的参数映射（与getPage方法保持一致）
        $searchParams = [];
        
        // 订单号搜索
        if (!empty($where['order_no'])) {
            $searchParams['order_no'] = $where['order_no'];
        }
        
        // 订单ID搜索
        if (!empty($where['order_id'])) {
            $searchParams['id'] = $where['order_id'];
        }
        
        if (!empty($where['express_no'])) {
            $searchParams['express_no'] = $where['express_no'];
        }
        
        if (!empty($where['remark'])) {
            $searchParams['remark'] = $where['remark'];
        }
        
        if (!empty($where['create_time_start']) && !empty($where['create_time_end'])) {
            $searchParams['create_at'] = [$where['create_time_start'], $where['create_time_end']];
        } elseif (!empty($where['create_at']) && is_array($where['create_at']) && count($where['create_at']) == 2) {
            $searchParams['create_at'] = $where['create_at'];
        }
        
        if (isset($where['status']) && $where['status'] !== '') {
            if (is_string($where['status']) && strpos($where['status'], ',') !== false) {
                $searchParams['status'] = explode(',', $where['status']);
            } else {
                $searchParams['status'] = $where['status'];
            }
        }
        
        if (isset($where['delivery_type']) && $where['delivery_type'] !== '') {
            if (is_string($where['delivery_type']) && strpos($where['delivery_type'], ',') !== false) {
                $searchParams['delivery_type'] = explode(',', $where['delivery_type']);
            } else {
                $searchParams['delivery_type'] = $where['delivery_type'];
            }
        }
        
        if (!empty($where['device_imei'])) {
            $searchParams['imei'] = $where['device_imei'];
        }
        
        if (!empty($where['device_model'])) {
            $searchParams['device_model'] = $where['device_model'];
        }
        
        if (!empty($where['user_nickname'])) {
            $searchParams['customer_name'] = $where['user_nickname'];
        }
        
        if (!empty($where['user_mobile'])) {
            $searchParams['customer_phone'] = $where['user_mobile'];
        }
        
        if (!empty($where['keyword'])) {
            $searchParams['keyword'] = $where['keyword'];
        }
        
        if (!empty($where['search'])) {
            $searchParams['search'] = $where['search'];
        }
             if (!empty($where['member_id'])) {
            $searchParams['member_id'] = $where['member_id'];
        }

        
        return (new RecycleOrder())
            ->withSearch([
                'id', 'order_no', 'express_no', 'customer_name', 'customer_phone', 
                'status', 'delivery_type', 'create_at', 'remark', 'imei', 
                'device_model', 'search', 'keyword','member_id'
            ], $searchParams)
            ->where([['site_id', '=', $this->site_id], ['delete_at', '=', 0]])
            ->count();
    }

    /**
     * 获取二手机回收报价订单信息
     * @param int $id
     * @param array $field
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws CommonException
     */
    public function getInfo(int $id, array $field = []): array
    {
        $info = (new RecycleOrder())->where([['id', '=', $id]])
            ->field($field)
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,imei,model,initial_price, category_id , status,check_result,final_price')
                        ->append(['status_name','category_name']);
                },
                'member' => function($query) {
                    $query->field('member_id,username,nickname,mobile,headimg');
                }
            ])
            ->append(['status_name', 'delivery_type_name'])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('ORDER_NOT_FOUND');
        }

        return $info;
    }

    /**
     * 添加订单
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        // 开启事务
        Db::startTrans();
        try {
            // 创建订单
            $order = new RecycleOrder();
            $order->save([
                'order_no' => $this->generateOrderNo(),
                'member_id' => $data['member_id'] ?? 0,
                'delivery_type' => $data['delivery_type'] ?? RecycleOrderDict::DELIVERY_TYPE_EXPRESS,
                'express_no' => $data['express_no'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'customer_phone' => $data['customer_phone'] ?? '',
                'remark' => $data['remark'] ?? '',
                'status' => RecycleOrderDict::ORDER_STATUS_PENDING_SIGN,
                'site_id' => $this->site_id,
                'create_at' => time(),
                'update_at' => time()
            ]);

            // 添加设备
            if (!empty($data['devices'])) {
                $deviceService = new RecycleDeviceService();
                foreach ($data['devices'] as $device) {
                    $device['order_id'] = $order->id;
                    $device['member_id'] = $data['member_id'] ?? 0;
                    $device['site_id'] = $this->site_id;
                   
                    $deviceService->add($device);
                }
            }
            
            // 添加订单日志
            $this->addOrderLog($order->id, 0, RecycleOrderDict::ORDER_STATUS_PENDING_SIGN, '创建订单');
            
            // 触发订单下单通知
            (new \addon\recycle\app\service\core\recycle_order\CoreRecycleOrderNotifyService())->orderAddNotify(['order_id' => $order->id , 'site_id'=>$this->site_id]);
            
            // 触发订单下单后事件
            \addon\recycle\app\service\core\order\CoreRecycleOrderEventService::orderAddAfter(['order_id' => $order->id, 'site_id' => $this->site_id, 'member_id' => $data['member_id'] ?? 0]);
            
            Db::commit();
            return $order->id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

  
   /**
     * 签收订单
     * @param int $id
     * @param array $devices
     * @param string $remark
     * @return bool
     * @throws CommonException
     */
    public function sign(int $id, array $devices = [], string $remark = ''): bool
    {
        $signService = new RecycleOrderSignService();
        return $signService->sign($id, [
            'devices' => $devices,
            'remark' => $remark
        ]);
    }

    /**
     * 开始质检
     * @param int $id
     * @param array $devices
     * @param string $remark
     * @return bool
     * @throws CommonException
     */
    public function startCheck(int $id, array $devices = [], string $remark = ''): bool
    {
        $checkService = new RecycleOrderCheckService();
        return $checkService->startCheck($id, [
            'devices' => $devices,
            'remark' => $remark
        ]);
    }

    /**
     * 质检订单
     * @param int $id
     * @param array $devices
     * @param string $remark
     * @return bool
     * @throws CommonException
     */
    public function check(int $id, array $devices = [], string $remark = ''): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $this->updateStatus($id, RecycleOrderDict::ORDER_STATUS_CHECKED, $remark, $devices);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 完成质检
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function completeCheck(int $id, array $data): bool
    {
        $checkService = new RecycleOrderCheckService();
        return $checkService->completeCheck($id, $data);
    }

    /**
     * 确认价格
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function confirmPrice(int $id, array $data): bool
    {
        $priceService = new RecycleOrderPriceService();
        return $priceService->confirmPrice($id, $data);
    }

    /**
     * 支付订单
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function payment(int $id, array $data): bool
    {
        $paymentService = new RecycleOrderPaymentService();
        return $paymentService->payment($id, $data);
    }

    /**
     * 完成订单
     * @param int $id
     * @param array $devices
     * @param string $remark
     * @return bool
     * @throws CommonException
     */
    public function complete(int $id, array $devices = [], string $remark = ''): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $this->updateStatus($id, RecycleOrderDict::ORDER_STATUS_COMPLETED, $remark, $devices);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 确认打款
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function paymentConfirm(int $id, array $data): bool
    {
        $paymentService = new RecycleOrderPaymentService();
        return $paymentService->confirmPayment($id, $data);
    }

    /**
     * 关闭订单
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function close(int $id, array $data): bool
    {
        $closeService = new RecycleOrderCloseService();
        return $closeService->close($id, $data);
    }

    /**
     * 取消订单
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function cancel(int $id, array $data): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $updateData = [
                'cancel_reason' => $data['cancel_reason'] ?? ''
            ];
            
            $this->updateStatus($id, RecycleOrderDict::ORDER_STATUS_CANCELLED, $data['remark'] ?? '', $updateData);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 删除订单
     * @param int $id
     * @return bool
     * @throws CommonException
     */
    public function delete(int $id): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 检查订单状态
            $order = $this->getInfo($id);
            if (!in_array($order['status'], [
                RecycleOrderDict::ORDER_STATUS_COMPLETED,
                RecycleOrderDict::ORDER_STATUS_CLOSED,
                RecycleOrderDict::ORDER_STATUS_CANCELLED
            ])) {
                throw new CommonException('CANNOT_DELETE_ORDER');
            }
            
            
            // 更新订单状态为已删除
            $this->updateStatus($id, RecycleOrderDict::ORDER_STATUS_DELETE, '删除订单');
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 更新订单状态
     * @param int $id 订单ID
     * @param int $status 目标状态
     * @param string $remark 备注
     * @param array $devices 设备列表
     * @return bool
     * @throws CommonException
     */
    public function updateStatus(int $id, int $status, string $remark = '', array $devices = []): bool
    {   
       
        // 开启事务
        Db::startTrans();
        try {

        // 获取订单信息
        $order = RecycleOrder::findOrEmpty($id);
        if ($order->isEmpty()) {
            throw new CommonException('ORDER_NOT_FOUND');
        }
        
        // 检查状态流转是否合法
        if (!RecycleOrderDict::isValidStatusTransition($order->status, $status, 'order')) {
            throw new CommonException('INVALID_STATUS_TRANSITION');
        }
        
        
        // 更新订单状态
        $updateData = [
            'status' => $status,
            'update_at' => time()
        ];
        
        // 如果 状态==10则追加datele_at
        if ($status == 10) {
            $updateData['delete_at'] = time();
        }
        
        
        $order->save($updateData);
        
        // 记录订单状态变更日志
        $this->addOrderLog($id, $order->status, $status, $remark);
        
        // 订单状态到设备状态的映射
        $orderToDeviceStatusMap = [
            RecycleOrderDict::ORDER_STATUS_PENDING_SIGN => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            RecycleOrderDict::ORDER_STATUS_SIGNED => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            RecycleOrderDict::ORDER_STATUS_CHECKING => RecycleOrderDict::DEVICE_STATUS_CHECKING,
            RecycleOrderDict::ORDER_STATUS_CHECKED => RecycleOrderDict::DEVICE_STATUS_CHECKED,
            RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT => RecycleOrderDict::DEVICE_STATUS_RECYCLED,
            RecycleOrderDict::ORDER_STATUS_COMPLETED => RecycleOrderDict::DEVICE_STATUS_RECYCLED,
        ];
        
        // 获取对应的设备状态
        $deviceStatus = $orderToDeviceStatusMap[$status] ?? $status;
        

       
        // 如果有设备列表，更新设备状态和基本信息
        if (!empty($devices)) {
            // 创建设备服务对象，用于记录日志
            $deviceService = new RecycleDeviceService();
            
            // 如果devices是关联数组（非索引数组），将其转换为索引数组
            if (isset($devices['id'])) {
                $devices = [$devices];
            }
            
            // 提取所有设备ID
            $deviceIds = [];
            foreach ($devices as $device) {
                if (isset($device['id'])) {
                    $deviceIds[] = $device['id'];
                }
            }
            
            // 批量查询该订单下的指定设备，避免N+1查询
            if (!empty($deviceIds)) {
                $deviceModels = RecycleDevice::where('order_id', $id)
                    ->whereIn('id', $deviceIds)
                    ->select()
                    ->keyBy('id');
                
                foreach ($devices as $device) {
                    if (isset($device['id']) && isset($deviceModels[$device['id']])) {
                        $deviceModel = $deviceModels[$device['id']];
                        
                        // 记录原始状态
                        $originalStatus = $deviceModel->status;
                        
                        // 检查设备当前状态，如果已经是退回状态，则不更新其状态
                        if ($originalStatus == RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                            // 记录日志，表明此设备由于已退回而跳过更新
                            $deviceService->addDeviceLog(
                                $deviceModel->id, 
                                $originalStatus, 
                                $originalStatus, 
                                "设备已处于退回状态，订单状态变更为" . RecycleOrderDict::ORDER_STATUS_TEXT[$status] . "时保持不变", 
                                $id
                            );
                            continue; // 跳过此设备的状态更新
                        }
                        
                        // 更新设备状态
                        $deviceData = [
                            'status' => $deviceStatus
                        ];
                        
                        
                        // 保存设备基本信息
                        if (isset($device['imei'])) {
                            $deviceData['imei'] = $device['imei'];
                        }
                        if (isset($device['model'])) {
                            $deviceData['model'] = $device['model'];
                        }
                        if (isset($device['initial_price'])) {
                            $deviceData['initial_price'] = $device['initial_price'];
                        }
                        if (isset($device['final_price'])) {
                            $deviceData['final_price'] = $device['final_price'];
                            $deviceData['price_uid'] = $this->uid;

                        }
                        
                        // 只有当状态发生变化时才记录日志
                        if ($originalStatus != $deviceStatus) {
                            // 记录设备状态变更日志
                            $deviceService->addDeviceLog(
                                $deviceModel->id, 
                                $originalStatus, 
                                $deviceStatus, 
                                "订单状态变更为" . RecycleOrderDict::ORDER_STATUS_TEXT[$status] . "，设备状态从" . 
                                RecycleOrderDict::getDeviceStatus($originalStatus) . "更新为" . 
                                RecycleOrderDict::getDeviceStatus($deviceStatus), 
                                $id
                            );
                        }
                        
                        // 保存设备更新
                        $deviceModel->save($deviceData);
                    }
                }
            }
        }
        Db::commit();
        return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 添加订单状态变更日志
     * @param int $order_id 订单ID
     * @param int $old_status 旧状态
     * @param int $new_status 新状态
     * @param string $remark 备注
     * @return bool
     */
    protected function addOrderLog(int $order_id, int $old_status, int $new_status, string $remark = ''): bool
    {
        $log = new RecycleOrderLog();
        $log->save([
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'remark' => $remark,
            'operator_id' => $this->uid ?? 0,
            'operator_name' => '',
            'create_at' => time()
        ]);
        return true;
    }

    /**
     * 获取订单状态列表
     * @return array
     */
    public function getStatus(): array
    {
        return RecycleOrderDict::getOrderStatus();
    }

    /**
     * 获取订单设备列表
     * @param int $id
     * @return array
     * @throws CommonException
     */
    public function devices(int $id): array
    {
        $order = $this->getInfo($id);
        return $order['devices'] ?? [];
    }

    /**
     * 更新订单信息
     * @param int $id 订单ID
     * @param array $data 更新数据
     * @return bool
     * @throws CommonException
     */
    public function update(int $id, array $data): bool
    {
        $order = $this->getInfo($id);
        if (!$order) {
            throw new CommonException('订单不存在');
        }

        // 处理特殊操作
        if (isset($data['action'])) {
           
            switch ($data['action']) {
                case 'order_sign':
                   
                    return $this->sign($id, $data['devices'] ?? [], $data['remark'] ?? '');
                case 'order_check':
                    return $this->check($id, $data['devices'] ?? [], $data['remark'] ?? '');
                case 'order_complete':
                    return $this->complete($id,$data['devices'] ?? [], $data['remark'] ?? '');
                case 'order_cancel':
                    return $this->cancel($id, $data);
                case 'order_payment_confirm':
                    return $this->paymentConfirm($id, $data);
                case 'batch_recycle':
                    return $this->batchRecycleDevices($id, $data);
                case 'batch_return':
                    return $this->batchReturnDevices($id, $data);
                default:
                    break;
            }
        }

        // 更新订单基本信息
        $update_data = [];
        if (isset($data['remark'])) {
            $update_data['remark'] = $data['remark'];
        }
        if (isset($data['express_no'])) {
            $update_data['express_no'] = $data['express_no'];
        }
        if (isset($data['express_company'])) {
            $update_data['express_company'] = $data['express_company'];
        }
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
        }

        if (!empty($update_data)) {
            $this->model->where([['id', '=', $id]])->update($update_data);
        }

        return true;
    }

    /**
     * 批量回收设备
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function batchRecycleDevices(int $id, array $data): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 检查订单状态
            $order = $this->getInfo($id);
            if (!$order) {
                throw new CommonException('订单不存在');
            }
            
            // 批量回收设备
            $deviceService = new RecycleDeviceService();
            
            if (!empty($data['device_ids'])) {
                $deviceService->batchRecycle($data['device_ids'], $data['remark'] ?? '');
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
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function batchReturnDevices(int $id, array $data): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 检查订单状态
            $order = $this->getInfo($id);
            if (!$order) {
                throw new CommonException('订单不存在');
            }
            
            // 批量退回设备
            $deviceService = new RecycleDeviceService();
            
            if (!empty($data['device_ids'])) {
                $deviceService->batchReturn($data['device_ids'], $data['remark'] ?? '');
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 推送订单确认通知
     * @param int $id 订单ID
     * @return array
     * @throws CommonException
     */
    public function pushOrderNotify(int $id): array
    {
        try {
            // 获取订单信息
            $order = $this->getInfo($id);
            if (!$order) {
                throw new CommonException('订单不存在');
            }

            // 验证订单状态 - 只有待确认状态的订单才能推送通知
            if ($order['status'] != RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM) {
                throw new CommonException('只有待确认状态的订单才能推送通知');
            }

            // 验证订单是否有用户
            if (empty($order['member_id'])) {
                throw new CommonException('订单没有关联用户，无法推送通知');
            }

            // 调用通知服务推送OrderAgree通知
            $noticeService = new NoticeService();
            $result = $noticeService->send($this->site_id, 'recycle_order_agree', [
                'order_id' => $id,
                'order_no' => $order['order_no'],
                'time' => date('Y-m-d H:i:s'),
                'status' => '待确认'
            ]);

            // 记录推送日志
            $this->addOrderLog($id, $order['status'], $order['status'], '手动推送订单确认通知');

            return [
                'success' => true,
                'message' => '推送通知已发送',
                'order_no' => $order['order_no'],
                'push_time' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            Log::record('推送订单通知失败：' . $e->getMessage(), 'error');
            throw new CommonException('推送通知失败：' . $e->getMessage());
        }
    }

    /**
     * 生成订单编号
     * @return string
     */
    protected function generateOrderNo(): string
    {
        return 'RO' . date('YmdHis') . mt_rand(1000, 9999);
    }

    /**
     * 获取各个状态的订单数量
     * @param array $where 搜索条件（不包含状态筛选）
     * @return array
     */
    protected function getStatusCounts(array $where = []): array
    {
        // 移除状态筛选条件，保留其他搜索条件
        $countWhere = $where;
        unset($countWhere['status']);
        
        // 处理前端传递的参数映射（与getPage方法保持一致，但不包含状态）
        $searchParams = [];
        
        if (!empty($countWhere['order_id'])) {
            $searchParams['id'] = $countWhere['order_id'];
        }
        
        if (!empty($countWhere['express_no'])) {
            $searchParams['express_no'] = $countWhere['express_no'];
        }
        
        if (!empty($countWhere['remark'])) {
            $searchParams['remark'] = $countWhere['remark'];
        }
        
        if (!empty($countWhere['create_time_start']) && !empty($countWhere['create_time_end'])) {
            $searchParams['create_at'] = [$countWhere['create_time_start'], $countWhere['create_time_end']];
        }
        
        if (isset($countWhere['delivery_type']) && $countWhere['delivery_type'] !== '') {
            if (is_string($countWhere['delivery_type']) && strpos($countWhere['delivery_type'], ',') !== false) {
                $searchParams['delivery_type'] = explode(',', $countWhere['delivery_type']);
            } else {
                $searchParams['delivery_type'] = $countWhere['delivery_type'];
            }
        }
        
        if (!empty($countWhere['device_imei'])) {
            $searchParams['imei'] = $countWhere['device_imei'];
        }
        
        if (!empty($countWhere['device_model'])) {
            $searchParams['device_model'] = $countWhere['device_model'];
        }
        
        if (!empty($countWhere['user_nickname'])) {
            $searchParams['customer_name'] = $countWhere['user_nickname'];
        }
        
        if (!empty($countWhere['user_mobile'])) {
            $searchParams['customer_phone'] = $countWhere['user_mobile'];
        }
        
        if (!empty($countWhere['keyword'])) {
            $searchParams['keyword'] = $countWhere['keyword'];
        }
        
        if (!empty($countWhere['search'])) {
            $searchParams['search'] = $countWhere['search'];
        }
        
        // 获取基础查询模型（不包含状态筛选）
        $baseQuery = (new RecycleOrder())
            ->withSearch([
                'id', 'order_no', 'express_no', 'customer_name', 'customer_phone', 
                'delivery_type', 'create_at', 'remark', 'imei', 
                'device_model', 'search', 'keyword'
            ], $searchParams)
            ->where([['site_id', '=', $this->site_id], ['delete_at', '=', 0]]);
        
        // 获取所有状态的定义
        $allStatuses = RecycleOrderDict::getOrderStatus();
        
        // 初始化状态计数
        $statusCounts = [
            'all' => 0  // 总数
        ];
        
        // 计算总数
        $statusCounts['all'] = (clone $baseQuery)->count();
        
        // 计算各个状态的数量
        foreach ($allStatuses as $statusKey => $statusInfo) {
            $statusCounts[$statusKey] = (clone $baseQuery)->where('status', $statusKey)->count();
        }
        
        // 添加一些特殊的状态统计
        $statusCounts['pending'] = $statusCounts[RecycleOrderDict::ORDER_STATUS_PENDING_SIGN] ?? 0; // 待处理
        $statusCounts['processing'] = ($statusCounts[RecycleOrderDict::ORDER_STATUS_SIGNED] ?? 0) + 
                                     ($statusCounts[RecycleOrderDict::ORDER_STATUS_CHECKING] ?? 0) + 
                                     ($statusCounts[RecycleOrderDict::ORDER_STATUS_CHECKED] ?? 0); // 处理中
        $statusCounts['finished'] = ($statusCounts[RecycleOrderDict::ORDER_STATUS_COMPLETED] ?? 0) + 
                                   ($statusCounts[RecycleOrderDict::ORDER_STATUS_CLOSED] ?? 0); // 已完成
        
        return $statusCounts;
    }

    /**
     * 获取商户的收款信息
     * getMerchantPayInfo
     * @param int $id 商户ID
     * @return array
    */
    public function getMerchantPayInfo(int $id )
    {
       // 通过$id 查询商户的收款信息
       $payment_service = new \addon\recycle\app\service\admin\payment\PaymentService();
       return $payment_service->getList($id);
    }
}
