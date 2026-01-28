<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\order;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrderLog;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService;
use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use addon\recycle\app\service\admin\recycle_order\RecycleDeviceService;
use app\service\core\notice\NoticeService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单管理服务（Admin端 - 使用流程引擎）
 *
 * 统一的订单操作入口，所有操作通过流程引擎执行
 *
 * @package addon\recycle\app\service\admin\order
 */
class RecycleOrderService extends BaseAdminService
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
            CoreRecycleOrderFlowService::FLOW_TYPE_ADMIN,
            [
                'operator_id' => $this->uid,
                'site_id' => $this->site_id
            ]
        );
    }

    /**
     * 签收订单
     *
     * @param int $orderId 订单ID
     * @param array $data 签收数据
     * @return bool
     * @throws CommonException
     */
    public function sign(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'sign', $data);
        return $result['success'];
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
     * 关闭订单
     *
     * @param int $orderId 订单ID
     * @param array $data 关闭数据（包含reason）
     * @return bool
     * @throws CommonException
     */
    public function close(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'close', $data);
        return $result['success'];
    }

    /**
     * 开始质检
     *
     * @param int $orderId 订单ID
     * @param array $data 质检数据
     * @return bool
     * @throws CommonException
     */
    public function startCheck(int $orderId, array $data = []): bool
    {
        $result = $this->execute($orderId, 'start_check', $data);
        return $result['success'];
    }

    /**
     * 完成质检
     *
     * @param int $orderId 订单ID
     * @param array $data 质检数据
     * @return bool
     * @throws CommonException
     */
    public function completeCheck(int $orderId, array $data = []): bool
    {
        $result = $this->execute($orderId, 'complete_check', $data);
        return $result['success'];
    }

    /**
     * 添加设备
     *
     * @param int $orderId 订单ID
     * @param array $data 设备数据（包含devices）
     * @return bool
     * @throws CommonException
     */
    public function addDevice(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'add_device', $data);
        return $result['success'];
    }

    /**
     * 设置价格
     *
     * @param int $orderId 订单ID
     * @param array $data 价格数据（包含devices）
     * @return bool
     * @throws CommonException
     */
    public function setPrice(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'set_price', $data);
        return $result['success'];
    }

    /**
     * 调整价格
     *
     * @param int $orderId 订单ID
     * @param array $data 价格数据（包含devices）
     * @return bool
     * @throws CommonException
     */
    public function adjustPrice(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'adjust_price', $data);
        return $result['success'];
    }

    /**
     * 强制确认
     *
     * @param int $orderId 订单ID
     * @param array $data 确认数据
     * @return bool
     * @throws CommonException
     */
    public function forceConfirm(int $orderId, array $data = []): bool
    {
        $result = $this->execute($orderId, 'force_confirm', $data);
        return $result['success'];
    }

    /**
     * 打款
     *
     * @param int $orderId 订单ID
     * @param array $data 打款数据（包含payment_info）
     * @return bool
     * @throws CommonException
     */
    public function payment(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'payment', $data);
        return $result['success'];
    }

    // ==================== CRUD 和工具方法 ====================

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
     * 获取订单分页列表
     * @param array $where 查询条件
     * @return array
     */
    public function getPage(array $where = []): array
    {
        $field = '*';
        $order = 'create_at desc';

        // 使用 Model 的参数映射方法
        $searchParams = RecycleOrder::mapSearchParams($where);

        // 构建查询
        $search_model = (new RecycleOrder())
            ->withSearch(RecycleOrder::getSearchFields(), $searchParams)
            ->where([['site_id', '=', $this->site_id], ['delete_at', '=', 0]])
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,imei,model,initial_price,status,category_id,final_price')
                        ->append(['status_name', 'category_name']);
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
     * 获取订单信息
     * @param int $id
     * @param array $field
     * @return array
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
            $this->model->where([['id', '=', $id]])->update([
                'delete_at' => time(),
                'update_at' => time()
            ]);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
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
                case 'order_cancel':
                    return $this->cancel($id, $data);
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

        if (!empty($update_data)) {
            $this->model->where([['id', '=', $id]])->update($update_data);
        }

        return true;
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
     * 获取各个状态的订单数量
     * @param array $where 搜索条件（不包含状态筛选）
     * @return array
     */
    protected function getStatusCounts(array $where = []): array
    {
        // 移除状态筛选条件，保留其他搜索条件
        $countWhere = $where;
        unset($countWhere['status']);

        // 处理前端传递的参数映射
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
        $statusCounts['pending'] = $statusCounts[RecycleOrderDict::ORDER_STATUS_PENDING_SIGN] ?? 0;
        $statusCounts['processing'] = ($statusCounts[RecycleOrderDict::ORDER_STATUS_SIGNED] ?? 0) +
                                     ($statusCounts[RecycleOrderDict::ORDER_STATUS_CHECKING] ?? 0) +
                                     ($statusCounts[RecycleOrderDict::ORDER_STATUS_CHECKED] ?? 0);
        $statusCounts['finished'] = ($statusCounts[RecycleOrderDict::ORDER_STATUS_COMPLETED] ?? 0) +
                                   ($statusCounts[RecycleOrderDict::ORDER_STATUS_CLOSED] ?? 0);

        return $statusCounts;
    }

    /**
     * 获取商户的收款信息
     * @param int $id 商户ID
     * @return array
    */
    public function getMerchantPayInfo(int $id)
    {
       // 通过$id 查询商户的收款信息
       $payment_service = new \addon\recycle\app\service\admin\payment\PaymentService();
       return $payment_service->getList($id);
    }
}
