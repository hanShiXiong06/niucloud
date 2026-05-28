<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\admin\dashboard\RecycleDashboardFilterService;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderNotifyService;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单管理服务（Admin端 - 使用流程引擎）
 *
 * 统一的订单操作入口，所有操作通过流程引擎执行
 *
 * @package addon\hsx_recycle\app\service\admin\order
 */
class RecycleOrderService extends BaseAdminService
{
    /**
     * 流程引擎实例
     * @var CoreRecycleOrderFlowService
     */
    private $flowService;

    private RecycleDevicePaymentService $devicePaymentService;
    private RecycleOrderFlowModeService $flowModeService;

    /**
     * @var RecycleOrder
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->flowService = new CoreRecycleOrderFlowService();
        $this->devicePaymentService = new RecycleDevicePaymentService();
        $this->flowModeService = new RecycleOrderFlowModeService();
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
        $data = $this->fillPaymentInfo($data);
        $result = $this->execute($orderId, 'payment', $data);
        return $result['success'];
    }

    /**
     * 兼容旧的扁平打款参数，流程配置要求 payment_info。
     * @param array $data
     * @return array
     */
    private function fillPaymentInfo(array $data): array
    {
        if (empty($data['payment_info']) || !is_array($data['payment_info'])) {
            $data['payment_info'] = [
                'pay_type' => $data['pay_type'] ?? '',
                'account' => $data['account'] ?? '',
                'payment_images' => $data['payment_images'] ?? '',
                'remark' => $data['remark'] ?? ''
            ];
        }

        return $data;
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
     * 获取当前操作员名称
     * @return string
     */
    private function getOperatorName(): string
    {
        try {
            $adminInfo = Db::name('sys_user')->where('uid', $this->uid)->find();
            return $adminInfo['real_name'] ?? $adminInfo['username'] ?? '客服';
        } catch (\Exception $e) {
            return '客服';
        }
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
        $filterKey = trim((string)($where['filter_key'] ?? ''));
        $viewMode = (string)($where['view_mode'] ?? '');
        $filterService = $filterKey !== '' ? new RecycleDashboardFilterService() : null;

        // 使用 Model 的参数映射方法
        $searchParams = RecycleOrder::mapSearchParams($where);

        // 构建查询
        $search_model = (new RecycleOrder())
            ->withSearch(RecycleOrder::getSearchFields(), $searchParams)
            ->where([['site_id', '=', $this->site_id], ['delete_at', '=', 0]])
            ->with([
                'devices' => function($query) use ($filterKey, $viewMode, $where, $filterService) {
                    $query->field('id,site_id,order_id,imei,user_sn,model,initial_price,status,category_id,check_template_id,final_price,sell_price,pay_status,pay_amount,pay_time,pay_uid,pay_no,confirm_status,confirm_time,confirm_member_id,confirm_remark,dispose_type,dispose_status,settlement_mode,consignment_order_id,return_order_id')
                        ->with(['consignmentOrder' => function($q) {
                            $q->field('id,consignment_no,source_device_id,status,listing_price,sold_price,settlement_amount,pay_status');
                        }])
                        ->append(['status_name', 'category_name', 'pay_status_name', 'confirm_status_name', 'dispose_type_name', 'dispose_status_name']);
                    if ($filterService && $viewMode === 'device_expand') {
                        $filterService->applyDeviceFilter($query, $filterKey, $where);
                    }
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

        $filterMeta = [];
        if ($filterService) {
            $search_model = $filterService->applyOrderFilter($search_model, $filterKey, $where);
            $filterMeta = $filterService->getFilterMeta($filterKey);
        }

        // 获取分页数据
        $result = $this->pageQuery($search_model);

        // 计算各个状态的数量（基于当前搜索条件，但不包含状态筛选）
        $statusCounts = $this->getStatusCounts($where);

        // 将状态统计添加到返回结果中
        $result['status_counts'] = $statusCounts;
        $result['filter_meta'] = $filterMeta;
        $result['view_mode'] = $viewMode;
        $result['flow_mode'] = $this->flowModeService->getDefaultFlowMode();
        $result['payment_mode'] = $result['flow_mode'];
        if (isset($result['data']) && is_array($result['data'])) {
            $result['data'] = array_map(fn($item) => $this->flowModeService->decorateOrder($item), $result['data']);
        }
        if (isset($result['list']) && is_array($result['list'])) {
            $result['list'] = array_map(fn($item) => $this->flowModeService->decorateOrder($item), $result['list']);
        }

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
                    $query->field('id,site_id,order_id,imei,user_sn,model,initial_price, category_id , check_template_id, status,check_result,final_price,sell_price,pay_status,pay_amount,pay_time,pay_uid,pay_no,confirm_status,confirm_time,confirm_member_id,confirm_remark,dispose_type,dispose_status,settlement_mode,consignment_order_id,return_order_id')
                        ->with(['consignmentOrder' => function($q) {
                            $q->field('id,consignment_no,source_device_id,status,listing_price,sold_price,settlement_amount,pay_status');
                        }])
                        ->append(['status_name','category_name', 'pay_status_name', 'confirm_status_name', 'dispose_type_name', 'dispose_status_name']);
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

        $info = $this->flowModeService->decorateOrder($info);
        $info['device_payment_summary'] = $this->devicePaymentService->getPaymentSummary($id);

        return $info;
    }

    public function confirmDevices(int $id, array $data): array
    {
        return $this->flowModeService->confirmDevices(
            $id,
            is_array($data['device_ids'] ?? null) ? $data['device_ids'] : [],
            trim((string)($data['remark'] ?? '')),
            (int)($data['confirm_status'] ?? RecycleOrderDict::CONFIRM_STATUS_CONFIRMED)
        );
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

            // 已完成的订单不允许删除
            if ($order['status'] == RecycleOrderDict::ORDER_STATUS_COMPLETED) {
                throw new CommonException('已完成的订单不允许删除');
            }

            // 只有已关闭或已取消的订单才能删除
            if (!in_array($order['status'], [
                RecycleOrderDict::ORDER_STATUS_CLOSED,
                RecycleOrderDict::ORDER_STATUS_CANCELLED
            ])) {
                throw new CommonException('当前订单状态不允许删除');
            }

            // 软删除：更新状态和删除时间
            $this->model->where([['id', '=', $id]])->update([
                'status' => RecycleOrderDict::ORDER_STATUS_DELETE,
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
                    if (empty($data['reason']) && !empty($data['cancel_reason'])) {
                        $data['reason'] = trim((string)$data['cancel_reason']);
                    }
                    return $this->cancel($id, $data);
                
                // 签收
                case 'order_sign':
                    return $this->sign($id, $data);
               
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

            // 验证订单是否有用户
            if (empty($order['member_id'])) {
                throw new CommonException('订单没有关联用户，无法推送通知');
            }

            $devices = $order['devices'] ?? [];
            $pendingDeviceIds = [];
            foreach ($devices as $device) {
                $status = (int)($device['status'] ?? 0);
                $confirmStatus = (int)($device['confirm_status'] ?? RecycleOrderDict::CONFIRM_STATUS_PENDING);
                if ($status === RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM && $confirmStatus !== RecycleOrderDict::CONFIRM_STATUS_CONFIRMED) {
                    $pendingDeviceIds[] = (int)$device['id'];
                }
            }

            if (empty($pendingDeviceIds)) {
                throw new CommonException('当前订单没有待客户确认的设备，暂不需要推送');
            }

            $notifyResult = (new CoreRecycleOrderNotifyService())->orderAgreeNotify([
                'order_id' => $id,
                'site_id' => $this->site_id,
                'scene' => 'manual_order_confirm',
                'device_ids' => $pendingDeviceIds,
            ]);

            if (empty($notifyResult['success'])) {
                throw new CommonException($notifyResult['message'] ?? '通知发送失败');
            }

            return [
                'success' => true,
                'message' => !empty($notifyResult['skipped']) ? '短时间内已推送过，本次已跳过重复发送' : '推送通知已发送',
                'skipped' => !empty($notifyResult['skipped']),
                'order_no' => $order['order_no'],
                'push_time' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            Log::record('推送订单通知失败：' . $e->getMessage(), 'error');
            throw new CommonException('推送通知失败：' . $e->getMessage());
        }
    }

    public function getNoticeLogs(int $id): array
    {
        $order = RecycleOrder::where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['delete_at', '=', 0],
        ])->findOrEmpty();

        if ($order->isEmpty()) {
            throw new CommonException('订单不存在');
        }

        return (new RecycleNoticeLogService())->getOrderLogs($id);
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

        // 使用 Model 的参数映射方法（与 getPage 保持一致）
        $searchParams = RecycleOrder::mapSearchParams($countWhere);

        $filterKey = trim((string)($where['filter_key'] ?? ''));
        $buildCountQuery = function (array $extraWhere = []) use ($searchParams, $filterKey, $where) {
            $query = (new RecycleOrder())
                ->withSearch(RecycleOrder::getSearchFields(), $searchParams)
                ->where([['site_id', '=', $this->site_id], ['delete_at', '=', 0]]);

            foreach ($extraWhere as $condition) {
                $query->where($condition[0], $condition[1], $condition[2]);
            }

            if ($filterKey !== '') {
                $query = (new RecycleDashboardFilterService())->applyOrderFilter($query, $filterKey, $where);
            }

            return $query;
        };

        // 获取所有状态的定义
        $allStatuses = RecycleOrderDict::getOrderStatus();

        // 初始化状态计数
        $statusCounts = [
            'all' => 0  // 总数
        ];

        // 计算总数
        $statusCounts['all'] = $buildCountQuery()->count();

        // 计算各个状态的数量
        foreach ($allStatuses as $statusKey => $statusInfo) {
            $statusCounts[$statusKey] = $buildCountQuery([
                ['status', '=', $statusKey]
            ])->count();
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
       $payment_service = new \addon\hsx_recycle\app\service\admin\address\PaymentService();
       return $payment_service->getList($id);
    }
}
