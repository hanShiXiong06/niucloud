<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\service\api\recycle_order;

use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\model\RecycleDevice;
use app\model\member\Member;
use addon\recycle\app\dict\order\RecycleOrderDict;
use core\base\BaseApiService;
use core\exception\ApiException;

/**
 * 二手机回收报价订单服务层
 * Class RecycleOrderService
 * @package addon\recycle\app\service\admin\recycle_order
 */
class RecycleOrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleOrder();
    }

    /**
     * 获取二手机回收报价订单列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,order_no,site_id,member_id,delivery_type,express_no,count,customer_name,customer_phone,remark,status,create_at,update_at';
        $order = 'create_at desc';
    
        // 如果 state = all
        if (!empty($where['status']) && $where['status'] == 'all') {
            unset($where['status']);
        }

        // 构建基础查询模型
        $search_model = $this->buildBaseQuery($where);
        
        // 处理搜索关键词
        if (!empty($where['search']) && trim($where['search']) !== '') {
            $search_model = $this->buildSearchQuery($search_model, $where['search']);
        }
        
        // 设置查询字段、关联、排序和附加属性
        $search_model = $search_model
            ->field($field)
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,imei,model,initial_price,status,final_price')
                        ->append(['status_name']);
                }
            ])
            ->order($order)
            ->append(['status_name', 'delivery_type_name']);

        return $this->pageQuery($search_model);
    }
    
    /**
     * 构建基础查询条件
     * @param array $where
     * @return \think\db\Query
     */
    private function buildBaseQuery(array $where)
    {
        // 基础条件
        $conditions = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
             ['status', '<>', 10]
        ];
        
        // 动态添加查询条件
        $this->addSearchConditions($conditions, $where);
        
        return $this->model->where($conditions);
    }
    
    /**
     * 添加搜索条件
     * @param array &$conditions
     * @param array $where
     * @return void
     */
    private function addSearchConditions(array &$conditions, array $where)
    {
        // 订单号搜索
        if (!empty($where['order_no'])) {
            $conditions[] = ['order_no', 'like', "%{$where['order_no']}%"];
        }
        
        // 快递单号搜索
        if (!empty($where['express_no'])) {
            $conditions[] = ['express_no', 'like', "%{$where['express_no']}%"];
        }
        
        // 客户姓名搜索
        if (!empty($where['customer_name'])) {
            $conditions[] = ['customer_name', 'like', "%{$where['customer_name']}%"];
        }
        
        // 客户电话搜索
        if (!empty($where['customer_phone'])) {
            $conditions[] = ['customer_phone', 'like', "%{$where['customer_phone']}%"];
        }
        
        // 订单状态筛选
        if (isset($where['status']) && $where['status'] !== '') {
            $conditions[] = ['status', '=', $where['status']];
        }
        
        // 配送方式筛选
        if (isset($where['delivery_type']) && $where['delivery_type'] !== '') {
            $conditions[] = ['delivery_type', '=', $where['delivery_type']];
        }
        
        // 创建时间范围搜索
        if (!empty($where['create_at']) && is_array($where['create_at'])) {
            $start_time = strtotime($where['create_at'][0]);
            $end_time = strtotime($where['create_at'][1]);
            if ($start_time && $end_time) {
                $conditions[] = ['create_at', 'between', [$start_time, $end_time]];
            }
        }
        
        // 备注搜索
        if (!empty($where['remark'])) {
            $conditions[] = ['remark', 'like', "%{$where['remark']}%"];
        }
    }
    
    /**
     * 构建复合搜索查询（订单基本信息 + 设备IMEI和型号）
     * @param \think\db\Query $query
     * @param string $search
     * @return \think\db\Query
     */
    private function buildSearchQuery($query, string $search)
    {
        return $query->where(function($subQuery) use ($search) {
            // 搜索订单基本信息
            $this->addOrderBasicSearch($subQuery, $search);
            
            // 搜索关联设备信息
            $this->addDeviceSearch($subQuery, $search);
        });
    }
    
    /**
     * 添加订单基本信息搜索
     * @param \think\db\Query $query
     * @param string $search
     * @return void
     */
    private function addOrderBasicSearch($query, string $search)
    {
        $query->whereOr([
            ['id', 'like', "%{$search}%"],
            ['express_no', 'like', "%{$search}%"],
            ['order_no', 'like', "%{$search}%"],
        ]);
    }
    
    /**
     * 添加设备信息搜索（IMEI和型号）
     * @param \think\db\Query $query
     * @param string $search
     * @return void
     */
    private function addDeviceSearch($query, string $search)
    {
        $query->whereOr(function($deviceQuery) use ($search) {
            $deviceQuery->whereExists(function($existsQuery) use ($search) {
                $deviceModel = new RecycleDevice();
                $existsQuery->table($deviceModel->getTable())
                           ->whereColumn($deviceModel->getTable() . '.order_id', $this->model->getTable() . '.id')
                           ->where(function($deviceCondition) use ($search) {
                               $deviceCondition->whereOr([
                                   ['imei', 'like', "%{$search}%"],
                                   ['model', 'like', "%{$search}%"]
                               ]);
                           });
            });
        });
    }


    /**
     * 获取二手机回收报价订单信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        
        $field = 'id,order_no,site_id,member_id,delivery_type,express_no,customer_name,customer_phone,remark,status,create_at,update_at';

        $info = $this->model
            ->where([
                ['id', "=", $id], 
                ['site_id', "=", $this->site_id],
                ['member_id', "=", $this->member_id]
            ])
            ->field($field)
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,imei,model,initial_price,status,final_price,remark,check_images,check_result,price_remark');
                },
                'member' => function($query) {
                    $query->field('member_id,nickname,mobile');
                }
            ])
            ->append(['status_name', 'delivery_type_name'])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new ApiException('订单不存在');
        }

        return $info;
    }

    /**
     * 添加二手机回收报价订单
     * @param array $data
     * @return array
     * @throws \Exception
     */

     
    public function add(array $data)
    {
        try {
            // 添加必要的字段
            $data['site_id'] = $this->site_id;
            $data['member_id'] = $this->member_id;
            $data['status'] = RecycleOrderDict::ORDER_STATUS_PENDING_SIGN;
            $data['order_no'] = $this->generateOrderNo();
            // 创建订单
           
            $order = $this->model->create($data);

            // 如果有设备列表，创建设备
            if (!empty($data['devices'])) {
                $devices = [];
                foreach ($data['devices'] as $device) {
                    $devices[] = [
                        'site_id' => $this->site_id,
                        'order_id' => $order->id,
                        'category_id' => $device['category_id'] ?? 1,
                        'imei' => $device['imei'] ?? '',
                        'model' => $device['model'] ?? '',
                        'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                        'initial_price' => $device['initial_price'] ?? 0,
                        'member_id' => $this->member_id
                    ];
                }
                (new RecycleDevice())->insertAll($devices);
            }

            return $order->toArray();
        } catch (\Exception $e) {
            throw new ApiException('创建订单失败：' . $e->getMessage());
        }
    }

    /**
     * 二手机回收报价订单编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        // 根据 edit 
        //["action", ""], 客户要执行的操作
        //["status", ""]  客户当前的状态

        $data['action'] = $data['action'] ?? '';
        $data['status'] = $data['status'] ?? '';
        

      
        if ( $data['action'] == 'cancel' && $data['status'] !== RecycleOrderDict::ORDER_STATUS_PENDING_SIGN ) {
            throw new ApiException('取消订单失败：当前订单状态不支持取消');
        }
        // 客户要删除订单 只有 status == 8 || 9 才允许删除
        if ( $data['action'] == 'delete' && $data['status'] == RecycleOrderDict::ORDER_STATUS_CLOSED || $data['status'] == RecycleOrderDict::ORDER_STATUS_CANCELLED) {
            throw new ApiException('删除订单失败：当前订单状态不支持删除');
        } 
        // 只有 status == 5 的时候才能一键确认
        if ( $data['action'] == 'confirm' && $data['status'] == RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM) {
            throw new ApiException('确认订单失败：当前订单状态不支持确认');
        }

        $data['update_at'] = time();
        
        // 讲 status = 客户要执行的操作id 和 dict 中的状态对应
// var_dump($data['action']);
        $data['status'] = RecycleOrderDict::getOrderStatusId($data['action']);
        
        // 判断客户是否一键确认 如果不是  则只能修改订单状态
        
        if ($data['action'] != 'confirm') {
            unset($data['action']);
            $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
            return true;
        }
        unset($data['action']);
 
        // 开启事务
        $this->model->startTrans();
        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        // 如果
        // 将关联的设备状态修改为 已回收
        (new RecycleDevice())->where([['order_id', '=', $id],['site_id', '=', $this->site_id]])->update(['status' => RecycleOrderDict::DEVICE_STATUS_RECYCLED]);
        // 提交事务
        $this->model->commit();

        return true;
    }

    /**
     * 删除二手机回收报价订单
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
    public function getMemberAll(){
       $memberModel = new Member();
       return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

    
   
    /**
     * 更新设备验机信息
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateDeviceCheck(int $id, array $data)
    {
        try {
            $deviceModel = new RecycleDevice();
            
            // 获取设备信息
            $device = $deviceModel->where('id', $id)->find();
            if (!$device) {
                throw new \Exception('设备不存在');
            }
            
            // 更新设备信息
            $updateData = [
                'check_status' => $data['check_status'],
                'check_result' => $data['check_result'],
                'initial_price' => $data['initial_price'],
                'final_price' => $data['final_price'],
                'price_remark' => $data['price_remark'],
                'update_at' => time(),
                'member_id' => $this->member_id
            ];
            
            if ($data['check_status'] == 2) { // 验机完成
                $updateData['check_at'] = time();
            }
            
            $result = $deviceModel->where('id', $id)->update($updateData);
            if (!$result) {
                throw new \Exception('更新设备信息失败');
            }
            
            // 如果验机完成，更新订单状态
            if ($data['check_status'] == 2) {
                $order = $this->model->where('id', $device['order_id'])->find();
                if ($order) {
                    // 检查该订单所有设备是否都验机完成
                    $unfinishedCount = $deviceModel->where([
                        ['order_id', '=', $device['order_id']],
                        ['check_status', '<>', 2]
                    ])->count();
                    
                    if ($unfinishedCount == 0) {
                        // 所有设备都验机完成，更新订单状态
                        $this->model->where('id', $device['order_id'])->update([
                            'status' => 4, // 质检完成
                            'update_at' => time()
                        ]);
                    }
                }
            }
            
            return true;
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 获取设备详情
     * @param int $id
     * @return array
     */
    public function getDeviceInfo(int $id)
    {
        $deviceModel = new RecycleDevice();
        $info = $deviceModel->where('id', $id)->find();
        if (!$info) {
            throw new \Exception('设备不存在');
        }
        return $info->toArray();
    }

    /**
     * 获取订单下的所有设备
     * @param int $orderId
     * @return array
     */
    public function getOrderDevices(int $orderId)
    {
        
        $deviceModel = new RecycleDevice();
        return $deviceModel->where('order_id', $orderId)->select()->toArray();
    }
    /**
     * 获取订单状态统计
     * @return array
     */
    // RecycleOrderService 服务层
// RecycleOrderService.php



    public function getStatusCount()
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];
        
        $counts = $this->model->where($where)
            ->group('status')
            ->column('count(*)', 'status');
        
        // 构建状态列表数据
        $statusList = [];
        
        // 添加"全部"选项
        $statusList[] = [
            'key' => 'all',           // 改用key而不是value
            'text' => '全部',         // 改用text而不是label
            'count' => array_sum($counts)
        ];
        
        // 添加各状态数据
        foreach (RecycleOrderDict::ORDER_STATUS_TEXT as $status => $text) {
            $statusList[] = [
                'key' => (string)$status,  // 状态值作为key
                'text' => $text,           // 状态文本作为text
                'count' => $counts[$status] ?? 0,
                'actions' => RecycleOrderDict::ORDER_STATUS_FLOW[$status] ?? []
            ];
        }

        return [
            'list' => $statusList,
            'status_dict' => [
                'device' => RecycleOrderDict::DEVICE_STATUS_TEXT,
                'order' => RecycleOrderDict::ORDER_STATUS_TEXT
            ]
        ];
    }

    public static function getStatus($status)
    {
        return [
            'actions' => self::ORDER_STATUS_FLOW[$status] ?? []
        ];
    }
    // updateStatus
    public function updateStatus(int $id, array $data)
    {
        // 优先判断是否是删除订单
        if($data['action'] == 'delete'){
            return $this->deleteOrder($id);
        }
        try {
            // 开启事务
            $this->model->startTrans();
            
            \think\facade\Log::info('updateStatus 开始 - 参数:', ['id' => $id, 'data' => $data]);
            
            // 更新订单状态
            switch ($data['action']) {
                case 'cancel':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['CANCELLED']; // 7
                    break;
                
                case 'start_check':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['CHECKING']; // 2
                    break;
                
                case 'complete_check':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['CHECKED']; // 3
                    break;
                
                case 'confirm':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['PAYING']; // 4
                    break;
                
                case 'pay':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['PAYED']; // 5
                    break;
                
                case 'complete':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['COMPLETED']; // 6
                    break;
                
                case 'reject':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['RETURNING']; // 8
                    break;
                
                case 'return_confirm':
                    $data['status'] = RecycleOrderDict::ORDER_STATUS['RETURNED']; // 9
                    break;
                
               
            }

            // 更新订单状态
            if (isset($data['status'])) {
                $order_result = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
                    'status' => $data['status'],
                    'update_at' => time()
                ]);
                \think\facade\Log::info('更新订单状态结果:', ['status' => $data['status'], 'result' => $order_result]);
                
                if (!$order_result) {
                    throw new \Exception('更新订单状态失败');
                }
            }

            // 如果客户点击了一键确认，则需要将设备的状态改为已确认
            if ($data['action'] == 'confirm') {
                $deviceModel = new RecycleDevice();
                
                // 检查是否有可以更新的设备
                $devices_to_update = $deviceModel->where([
                    ['order_id', '=', $id], 
                    ['site_id', '=', $this->site_id],
                    ['status', '=', RecycleOrderDict::DEVICE_STATUS['CHECKED']] // 只更新已质检的设备
                ])->count();
                
                \think\facade\Log::info('待更新设备数量:', ['count' => $devices_to_update]);
                
                if ($devices_to_update > 0) {
                    // 只更新状态为已质检(3)的设备
                    $device_result = $deviceModel->where([
                        ['order_id', '=', $id], 
                        ['site_id', '=', $this->site_id],
                        ['status', '=', RecycleOrderDict::DEVICE_STATUS['CHECKED']] // 只更新已质检的设备
                    ])->update([
                        'status' => RecycleOrderDict::DEVICE_STATUS['CONFIRMED'],
                        'update_at' => time()
                    ]);
                    
                    \think\facade\Log::info('更新设备状态结果:', [
                        'order_id' => $id,
                        'old_status' => RecycleOrderDict::DEVICE_STATUS['CHECKED'],
                        'new_status' => RecycleOrderDict::DEVICE_STATUS['CONFIRMED'],
                        'result' => $device_result
                    ]);
                    
                    if (!$device_result) {
                        throw new \Exception('更新设备状态失败');
                    }
                } else {
                    \think\facade\Log::info('没有需要更新的设备');
                }
            }
            
            // 提交事务
            $this->model->commit();
            \think\facade\Log::info('updateStatus 完成');
            return true;
            
        } catch (\Exception $e) {
            // 回滚事务
            $this->model->rollback();
            \think\facade\Log::error('updateStatus 异常:', ['message' => $e->getMessage()]);
            throw new ApiException($e->getMessage());
        }
    }

    // 单台确认
    public function deviceConfirm($device_id)
    {
        try {
            $deviceModel = new RecycleDevice();
            
            // 获取设备信息
            $device = $deviceModel->where([['id', '=', $device_id]])->find();
            if (empty($device)) {
                throw new \Exception('设备不存在');
            }
            
            // 检查设备状态是否为已质检(3)
            if ($device['status'] != 3) {
                throw new \Exception('设备状态不正确，无法确认');
            }
            
            // 开启事务
            $deviceModel->startTrans();
            
            try {
                // 更新设备状态为已确认(4)
                $result = $deviceModel->where([['id', '=', $device_id]])->update([
                    'status' => RecycleOrderDict::DEVICE_STATUS['CONFIRMED'],
                    'update_at' => time()
                ]);
                
                if (!$result) {
                    throw new \Exception('更新设备状态失败');
                }
                
                // 检查订单下所有设备是否都已确认
                $unconfirmed = $deviceModel->where([
                    ['order_id', '=', $device['order_id']],
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['CONFIRMED']],
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['RETURNED']] // 排除已退回的设备
                ])->count();
                
                // 如果所有设备都已确认，更新订单状态为已确认(4)
                if ($unconfirmed == 0) {
                    $order_result = $this->model->where([['id', '=', $device['order_id']]])->update([
                        'status' => RecycleOrderDict::ORDER_STATUS['PAYING'],
                        'update_at' => time()
                    ]);
                    
                    if (!$order_result) {
                        throw new \Exception('更新订单状态失败');
                    }
                }
                
                // 提交事务
                $deviceModel->commit();
                return true;
                
            } catch (\Exception $e) {
                // 回滚事务
                $deviceModel->rollback();
                throw new ApiException($e->getMessage());
            }
            
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    // 单台取消
    public function deviceCancle($device_id)
    {
        try {
            $deviceModel = new RecycleDevice();
            
            // 获取设备信息
            $device = $deviceModel->where([['id', '=', $device_id]])->find();
            if (empty($device)) {
                throw new \Exception('设备不存在');
            }
            
            // 检查设备状态是否为已质检(3)
            if ($device['status'] != 3) {
                throw new \Exception('设备状态不正确，无法取消');
            }
            
            // 开启事务
            $deviceModel->startTrans();
            
            try {
                // 更新设备状态为已退回(6)
                $result = $deviceModel->where([['id', '=', $device_id]])->update([
                    'status' => RecycleOrderDict::DEVICE_STATUS['RETURNED'],
                    'update_at' => time()
                ]);
                
                if (!$result) {
                    throw new \Exception('更新设备状态失败');
                }
                
                // 检查订单下是否还有未处理的设备
                $pending_devices = $deviceModel->where([
                    ['order_id', '=', $device['order_id']],
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['CONFIRMED']], // 不是已确认
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['RETURNED']]  // 不是已退回
                ])->count();
                
                // 如果没有未处理的设备，检查是否有确认的设备
                if ($pending_devices == 0) {
                    $confirmed_devices = $deviceModel->where([
                        ['order_id', '=', $device['order_id']],
                        ['status', '=', RecycleOrderDict::DEVICE_STATUS['CONFIRMED']]
                    ])->count();
                    
                    // 更新订单状态
                    // 有确认的设备则为已确认(4)，否则为已取消(7)
                    $new_status = $confirmed_devices > 0 ? 
                        RecycleOrderDict::ORDER_STATUS['PAYING'] : 
                        RecycleOrderDict::ORDER_STATUS['CANCELLED'];
                    
                    $order_result = $this->model->where([['id', '=', $device['order_id']]])->update([
                        'status' => $new_status,
                        'update_at' => time()
                    ]);
                    
                    if (!$order_result) {
                        throw new \Exception('更新订单状态失败');
                    }
                }
                
                // 提交事务
                $deviceModel->commit();
                return true;
                
            } catch (\Exception $e) {
                // 回滚事务
                $deviceModel->rollback();
                throw new ApiException($e->getMessage());
            }
            
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }
    
    // deleteOrder  
    protected function deleteOrder($id)
    {
        // 开启事务
        $this->model->startTrans();
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
        $deviceModel = new RecycleDevice();
        $deviceModel->where([['order_id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
        // 提交事务
        $this->model->commit();
        return true;
    }
    //  取消设备回收 deviceCancel
    
    protected function generateOrderNo(): string
    {
        return 'RO' . date('YmdHis') . mt_rand(1000, 9999);
    }
}
