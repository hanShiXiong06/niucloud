<?php
declare(strict_types=1);

namespace addon\recycle\app\service\api\recycle_order;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\model\order\RecycleDevice;
use addon\recycle\app\model\order\RecycleOrder;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\ApiException;
use core\exception\CommonException;

/**
 * 回收订单服务（API端 - 使用流程引擎）
 *
 * 用户端订单操作入口，所有操作通过流程引擎执行
 *
 * @package addon\recycle\app\service\api\recycle_order
 */
class RecycleOrderService extends BaseApiService
{
    /**
     * 流程引擎实例
     * @var CoreRecycleOrderFlowService
     */
    private $flowService;

    /**
     * @var RecycleOrder
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->flowService = new CoreRecycleOrderFlowService();
        $this->model = new RecycleOrder();
    }

    /**
     * 执行订单操作
     *
     * @param int $orderId 订单ID
     * @param string $action 操作名称
     * @param array $data 操作数据
     * @return array 执行结果
     * @throws CommonException
     */
    private function execute(int $orderId, string $action, array $data = []): array
    {
        return $this->flowService->execute(
            $orderId,
            $action,
            $data,
            CoreRecycleOrderFlowService::FLOW_TYPE_API,
            [
                'operator_id' => $this->member_id,
                'site_id' => $this->site_id
            ]
        );
    }

    /**
     * 取消订单
     *
     * @param int $orderId 订单ID
     * @param array $data 取消数据（包含reason）
     * @return bool
     * @throws CommonException
     */
    public function cancel(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'cancel', $data);
        return $result['success'];
    }

    /**
     * 确认收货
     *
     * @param int $orderId 订单ID
     * @param array $data 确认数据
     * @return bool
     * @throws CommonException
     */
    public function confirmReceipt(int $orderId, array $data = []): bool
    {
        $result = $this->execute($orderId, 'confirm_receipt', $data);
        return $result['success'];
    }

    /**
     * 确认价格
     *
     * @param int $orderId 订单ID
     * @param array $data 确认数据
     * @return bool
     * @throws CommonException
     */
    public function confirmPrice(int $orderId, array $data = []): bool
    {
        $result = $this->execute($orderId, 'confirm_price', $data);
        return $result['success'];
    }

    /**
     * 议价
     *
     * @param int $orderId 订单ID
     * @param array $data 议价数据（包含expected_price, reason等）
     * @return bool
     * @throws CommonException
     */
    public function negotiate(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'negotiate', $data);
        return $result['success'];
    }

    // ==================== CRUD 和工具方法 ====================

    /**
     * 获取订单列表
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
        // 查询的订单状态不能是 10
        $search_model = $search_model->where('status', '<>', 10);

        // 设置查询字段、关联、排序和附加属性
        $search_model = $search_model
            ->field($field)
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,site_id,imei,model,initial_price,status,final_price,check_images,check_images_seller')
                        ->append(['status_name', 'check_images_seller_thumb_small']);
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
            ['status', '<>', 10],
            ['delete_at', '=', 0]
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
     * 获取订单信息
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
                    $query->field('id,order_id,site_id,imei,model,initial_price,status,final_price,remark,check_images,check_images_seller,check_result,check_result_seller,price_remark')
                        ->append(['status_name', 'check_images_seller_thumb_small']);
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
     * 添加订单
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

            // ========== 统一快递服务（亿速）==========
            if (!empty($data['use_express']) && !empty($data['express_config'])) {
                // 先创建回收订单
                $order = $this->model->create($data);

                try {
                    // 调用统一快递服务下单
                    $expressService = new \addon\recycle\app\service\core\express\RecycleExpressService();

                    $operatorInfo = [
                        'uid' => 0,
                        'username' => '',
                        'source' => 'user',
                        'member_id' => $this->member_id,
                    ];

                    $expressResult = $expressService->createOrder(
                        $this->site_id,
                        $order->id,
                        $data['express_config'],
                        $operatorInfo
                    );

                    // 刷新订单数据（快递服务内部已更新了订单字段）
                    $order->refresh();

                } catch (\Exception $e) {
                    // 快递下单失败，删除已创建的订单
                    $order->delete();
                    throw new ApiException('快递下单失败：' . $e->getMessage());
                }

            } else {
                // 不使用平台快递，直接创建订单（自填快递号 或 自送到店）
                $order = $this->model->create($data);
            }

            // 如果有设备列表，创建设备
            if (!empty($data['devices'])) {
                $devices = [];
                foreach ($data['devices'] as $device) {
                    $categoryId = (int)($device['category_id'] ?? 1);
                    $categoryPath = $device['category_path'] ?? [];
                    if (!is_array($categoryPath) || empty($categoryPath)) {
                        $categoryPath = [ $categoryId ];
                    }

                    $devices[] = [
                        'site_id' => $this->site_id,
                        'order_id' => $order->id,
                        'category_id' => $categoryId,
                        'info' => [
                            'goods_category' => array_values(array_map('strval', $categoryPath))
                        ],
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
     * 编辑订单
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data['action'] = $data['action'] ?? '';
        $data['status'] = $data['status'] ?? '';

        // 客户要删除订单 只有 status == 8（已关闭） || 9（已取消） 才允许软删除
        if ($data['action'] == 'delete') {
            if (!in_array((int)$data['status'], [RecycleOrderDict::ORDER_STATUS_CLOSED, RecycleOrderDict::ORDER_STATUS_CANCELLED])) {
                throw new ApiException('删除订单失败：当前订单状态不支持删除');
            }
            // 软删除：设置 delete_at 时间戳，列表查询已通过 delete_at=0 过滤
            $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
                'delete_at' => time(),
                'update_at' => time(),
            ]);
            return true;
        }
        // 只有 status == 5 的时候才能一键确认
        if ( $data['action'] == 'confirm' && $data['status'] == RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM) {
            throw new ApiException('确认订单失败：当前订单状态不支持确认');
        }

        // 取消订单走流程引擎，以触发快递拦截等业务逻辑
        if ($data['action'] == 'cancel') {
            return $this->cancel($id, [
                'reason' => $data['reason'] ?? '用户取消订单',
                'remark' => $data['remark'] ?? '',
            ]);
        }

        $data['update_at'] = time();

        // 讲 status = 客户要执行的操作id 和 dict 中的状态对应
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
        // 将关联的设备状态修改为 已回收
        (new RecycleDevice())->where([['order_id', '=', $id],['site_id', '=', $this->site_id]])->update(['status' => RecycleOrderDict::DEVICE_STATUS_RECYCLED]);
        // 提交事务
        $this->model->commit();

        return true;
    }

    /**
     * 删除订单
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($model)) {
            throw new CommonException('ORDER_NOT_FOUND');
        }

        // 已完成的订单不允许删除
        if ($model['status'] == RecycleOrderDict::ORDER_STATUS_COMPLETED) {
            throw new CommonException('已完成的订单不允许删除');
        }

        // 只有已关闭或已取消的订单才能删除
        if (!in_array($model['status'], [
            RecycleOrderDict::ORDER_STATUS_CLOSED,
            RecycleOrderDict::ORDER_STATUS_CANCELLED
        ])) {
            throw new CommonException('当前订单状态不允许删除');
        }

        // 软删除：标记删除时间，更新状态
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
            'status' => RecycleOrderDict::ORDER_STATUS_DELETE,
            'delete_at' => time(),
            'update_at' => time()
        ]);

        return true;
    }

    /**
     * 获取所有会员
     * @return array
     */
    public function getMemberAll(){
       $memberModel = new Member();
       return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

    /**
     * 获取订单状态列表
     * @return array
     */
    public function getStatus()
    {
        return RecycleOrderDict::getOrderStatus();
    }

    /**
     * 获取订单状态统计
     * @return array
     */
    public function getStatusCount()
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['delete_at', '=', 0]
        ];

        $counts = $this->model->where($where)
            ->group('status')
            ->column('count(*)', 'status');

        // 构建状态列表数据
        $statusList = [];

        // 添加"全部"选项
        $statusList[] = [
            'key' => 'all',
            'text' => '全部',
            'count' => array_sum($counts)
        ];

        // 添加各状态数据
        foreach (RecycleOrderDict::ORDER_STATUS_TEXT as $status => $text) {
            $statusList[] = [
                'key' => (string)$status,
                'text' => $text,
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

    /**
     * 单台确认
     * @param int $device_id
     * @return bool
     */
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

    /**
     * 单台取消
     * @param int $device_id
     * @return bool
     */
    public function deviceCancel($device_id)
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

    /**
     * 生成订单编号
     * @return string
     */
    protected function generateOrderNo(): string
    {
        return 'RO' . date('YmdHis') . mt_rand(1000, 9999);
    }
}
