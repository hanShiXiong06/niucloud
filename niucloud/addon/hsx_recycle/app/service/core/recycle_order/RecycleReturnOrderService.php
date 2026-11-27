<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\order\RecycleReturnOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleReturnDevice;
use addon\hsx_recycle\app\model\order\RecycleReturnOrder;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\admin\printer\RecyclePrintTriggerService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 退回订单核心服务
 * Class RecycleReturnOrderService
 * @package addon\hsx_recycle\app\service\core
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
            ['site_id', '=', $siteId],
            ['delete_at', '=', 0]
        ])
        ->with([
            'returnDevices.device',
            'memberAddress' => function ($query) use ($siteId) {
                $query->where('site_id', '=', $siteId)
                    ->field('id,member_id,name,mobile,address,create_time,update_time')
                    ->order('update_time desc,create_time desc,id desc');
            }
        ])
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
        $device_ids = array_values(array_unique(array_filter(array_map('intval', (array)($data['device_ids'] ?? [])), static fn($id) => $id > 0)));
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

        // 开始事务
        Db::startTrans();
        try {
            // 设备归属锁内再核对，避免重复点击生成多个退回单、旧单覆盖新单。
            $devices = (new RecycleDevice())->where('site_id', $site_id)->where('order_id', $order_id)
                ->whereIn('id', $device_ids)->lock(true)->select();
            if (count($devices) !== count($device_ids)) throw new CommonException('部分设备不存在或不属于当前站点订单，本次未创建');
            foreach ($devices as $device) {
                if ((int)$device['return_order_id'] > 0) throw new CommonException('设备已关联退回单，请在原退回单继续处理，勿重复创建');
            }
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
                'create_at' => time(),
                'update_at' => time(),
            ];
            
            $return_order_id = $this->model->insertGetId($return_order);
            
            // 创建退回设备关联
            $return_devices = [];
            foreach ($devices as $device) {
                $return_devices[] = [
                    'return_order_id' => $return_order_id,
                    'device_id' => $device['id'],
                    'status' => $this->getReturnDeviceStatusByOrderStatus(RecycleReturnOrderDict::ORDER_STATUS_PENDING),
                    'remark' => $data['remark'] ?? ($data['comment'] ?? ''),
                    'create_at' => time(),
                    'update_at' => time()
                ];
            }
            
            (new RecycleReturnDevice())->insertAll($return_devices);
            $this->syncLinkedDevicesForReturnOrder($order_id, $return_order_id, RecycleReturnOrderDict::ORDER_STATUS_PENDING, $data);
            $this->syncParentOrderStatus($order_id);
            
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
        } catch (\Throwable $e) {
            Db::rollback();
            return ['code' => -1, 'msg' => '创建退货订单失败：' . $e->getMessage(), 'data' => []];
        }
    }

    private function triggerReturnPrint(int $status, int $returnOrderId): void
    {
        $triggerKeys = [];
        if ($status === RecycleReturnOrderDict::ORDER_STATUS_RETURNING) {
            $triggerKeys[] = 'return.confirmed';
            $triggerKeys[] = 'return.express.saved';
        }
        if ($status === RecycleReturnOrderDict::ORDER_STATUS_COMPLETED) {
            $triggerKeys[] = 'return.completed';
        }

        if (empty($triggerKeys)) {
            return;
        }

        foreach ($triggerKeys as $triggerKey) {
            try {
                $result = (new RecyclePrintTriggerService())->auto($triggerKey, [
                    'return_order_id' => $returnOrderId,
                ]);
                Log::info('【退货打印】自动触发结果', [
                    'trigger_key' => $triggerKey,
                    'return_order_id' => $returnOrderId,
                    'success' => $result['success'] ?? false,
                ]);
            } catch (\Throwable $e) {
                Log::error('【退货打印】自动触发失败：' . $e->getMessage(), [
                    'trigger_key' => $triggerKey,
                    'return_order_id' => $returnOrderId,
                ]);
            }
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
        $site_id = (int)($data['site_id'] ?? 0);
        if ($id <= 0 || $site_id <= 0) throw new CommonException('退回单或站点信息不完整');
        Db::startTrans();
        try {
            // 单笔、批量、定时任务共用同一把行锁，不重复完成、不重复打印。
            $order = $this->model->where('id', $id)->where('site_id', $site_id)->where('delete_at', 0)->lock(true)->find();
            if (empty($order)) throw new CommonException('退回订单不存在或已删除');
            if ((int)$order['status'] === $status && in_array($status, [1, 2, 3], true)) {
                Db::commit();
                return ['code' => 0, 'msg' => '该退回单已处理，未重复执行', 'data' => ['id' => $id, 'status' => $status, 'duplicate' => true]];
            }
            $transitions = RecycleReturnOrderDict::getValidStatusTransitions();
            if (!in_array($status, $transitions[(int)$order['status']] ?? [], true)) {
                throw new CommonException('当前为“' . (RecycleReturnOrderDict::getOrderStatus((int)$order['status'])['name'] ?? '未知状态') . '”，不能执行此操作，请刷新后核对');
            }
            $shippedAt = (int)$order->getData('update_at');
            if (!empty($data['auto_complete']) && ($shippedAt <= 0 || $shippedAt > time() - 72 * 60 * 60)) {
                throw new CommonException('尚未达到发货后72小时，未自动完成');
            }
            $deviceIds = array_values(array_unique(array_map('intval', (new RecycleReturnDevice())->where('return_order_id', $id)->column('device_id'))));
            $devices = (new RecycleDevice())->where('site_id', $site_id)->where('order_id', (int)$order['order_id'])
                ->where('return_order_id', $id)->whereIn('id', $deviceIds ?: [0])->lock(true)->select()->toArray();
            if ($deviceIds === [] || count($devices) !== count($deviceIds)) {
                throw new CommonException('退回设备缺失或已关联其他退回单，请核对设备明细；本单未修改');
            }
            $company = trim((string)($data['express_company'] ?? $order['express_company']));
            $expressNo = trim((string)($data['express_no'] ?? $order['express_no']));
            if ($status === RecycleReturnOrderDict::ORDER_STATUS_RETURNING) {
                $offline = in_array($company, ['自取', '物流车', '物流车/自取', 'express_car', 'self_pickup', 'logistics_car'], true);
                if ($company === '' || (!$offline && $expressNo === '')) throw new CommonException('请填写快递公司和单号，或明确选择物流车/客户自取');
                if (mb_strlen($company) > 100 || mb_strlen($expressNo) > 100) throw new CommonException('快递公司或单号不能超过100个字符');
            }
            $comment = trim((string)($data['comment'] ?? ''));
            if (mb_strlen($comment) > 1000) throw new CommonException('处理备注不能超过1000个字符');
            if ($status === RecycleReturnOrderDict::ORDER_STATUS_CANCELLED && $comment === '') throw new CommonException('请填写取消原因，并确认设备仍在商家手中');
            $action = [1 => '确认退回发货', 2 => !empty($data['auto_complete']) ? '发出满72小时自动完成（非物流签收确认）' : '确认客户已签收', 3 => '取消退回，设备恢复回收处理'][$status];
            $operatorName = trim((string)($data['operator_name'] ?? '')) ?: '系统';
            $record = date('Y-m-d H:i:s') . ' ' . $operatorName . '：' . $action . ($comment !== '' ? '；' . $comment : '');
            $update_data = [
                'status' => $status,
                'comment' => trim((string)$order['comment']) . (empty($order['comment']) ? '' : "\n") . $record,
                'operator_uid' => $data['operator_id'] ?? ($data['operator_uid'] ?? 0),
                'operator_name' => $operatorName,
                'update_at' => time(),
                'express_no' => $expressNo,
                'express_company' => $company,
                'member_mobile' => array_key_exists('member_mobile', $data) ? ($data['member_mobile'] ?? '') : $order['member_mobile'],
                'member_name' => array_key_exists('member_name', $data) ? ($data['member_name'] ?? '') : $order['member_name'],
                'return_address' => array_key_exists('return_address', $data) ? ($data['return_address'] ?? '') : $order['return_address'],
                'remark' => array_key_exists('remark', $data) ? ($data['remark'] ?? '') : $order['remark'],
            ];

            if ($status == RecycleReturnOrderDict::ORDER_STATUS_COMPLETED) {
                $update_data['over_at'] = date('Y-m-d H:i:s');
            }

            $this->model->where('site_id', $site_id)->where('id', $id)->update($update_data);
            $relationUpdate = [
                'status' => $this->getReturnDeviceStatusByOrderStatus($status),
                'update_at' => time()
            ];
            if (array_key_exists('remark', $data)) $relationUpdate['remark'] = (string)$data['remark'];
            (new RecycleReturnDevice())->where('return_order_id', $id)->update($relationUpdate);
            $this->syncLinkedDevicesForReturnOrder((int)$order['order_id'], $id, $status, $data);
            $this->syncParentOrderStatus((int)$order['order_id']);
            Db::commit();

        } catch (\Throwable $e) {
            Db::rollback();
            // 不返回“外层成功、内层失败”的响应，避免界面误报已经完成。
            throw new CommonException($e->getMessage());
        }
        $this->triggerReturnPrint($status, $id);
        return ['code' => 0, 'msg' => '更新成功', 'data' => [
            'id' => $id, 'status' => $status, 'status_name' => RecycleReturnOrderDict::getOrderStatus($status)['name'] ?? '',
        ]];
    }

    /**
     * 确认退回订单
     * @param int $id
     * @param int $siteId
     * @return array
     */
    public function confirm(int $id, int $siteId , array $data = []): array
    {
        return $this->updateStatus($id, RecycleReturnOrderDict::ORDER_STATUS_RETURNING, array_merge(
            array_intersect_key($data, array_flip(['express_no', 'express_company', 'remark', 'comment', 'member_mobile', 'member_name', 'return_address'])), [
            'site_id' => $siteId,
            'operator_uid' => request()->uid(),
            'operator_name' => request()->username(),
        ]));
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
                'count' => $count,
                // 台数复用看板口径；单数仍是退回单数，两者不混用。
                'device_count' => in_array($status, [0, 1], true)
                    ? (int)(new \addon\hsx_recycle\app\service\core\stat\CoreRecycleWorkloadService())
                        ->deviceQuery($siteId, ['abnormal'])->whereIn('return_order_id', function ($query) use ($siteId, $status) {
                            $query->name('recycle_return_order')->where('site_id', $siteId)->where('status', $status)->where('delete_at', 0)->field('id');
                        })->count()
                    : 0,
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

    private function getReturnDeviceStatusByOrderStatus(int $orderStatus): int
    {
        $map = [
            RecycleReturnOrderDict::ORDER_STATUS_PENDING => 0,
            RecycleReturnOrderDict::ORDER_STATUS_RETURNING => 1,
            RecycleReturnOrderDict::ORDER_STATUS_COMPLETED => 2,
            RecycleReturnOrderDict::ORDER_STATUS_CANCELLED => 0,
        ];

        return $map[$orderStatus] ?? 0;
    }

    private function syncLinkedDevicesForReturnOrder(int $orderId, int $returnOrderId, int $returnOrderStatus, array $data = []): void
    {
        $remark = trim((string)($data['remark'] ?? ($data['comment'] ?? '')));
        $deviceIds = (new RecycleReturnDevice())->where('return_order_id', $returnOrderId)->column('device_id');
        if (empty($deviceIds)) {
            return;
        }

        $devices = (new RecycleDevice())->where([
            ['order_id', '=', $orderId],
        ])->whereIn('id', $deviceIds)
            ->select()
            ->toArray();

        foreach ($devices as $device) {
            $updateData = $returnOrderStatus === RecycleReturnOrderDict::ORDER_STATUS_CANCELLED
                ? $this->buildCancelledReturnDeviceData($device)
                : $this->buildReturnDeviceData($returnOrderId, $remark, $returnOrderStatus, $device);

            (new RecycleDevice())->where('id', (int)$device['id'])->update($updateData);
        }
    }

    private function buildReturnDeviceData(int $returnOrderId, string $remark, int $returnOrderStatus, array $device): array
    {
        $updateData = [
            'status' => RecycleOrderDict::DEVICE_STATUS_RETURNED,
            'settlement_mode' => RecycleOrderDict::DISPOSE_TYPE_RETURN,
            'dispose_type' => RecycleOrderDict::DISPOSE_TYPE_RETURN,
            'dispose_status' => RecycleOrderDict::DISPOSE_STATUS_RETURNED,
            'return_order_id' => $returnOrderId,
            'return_remark' => $remark !== '' ? $remark : (string)($device['return_remark'] ?? ''),
        ];

        // 申请退回/发货不等于已退到客户手中，完成时才记录完成时间。
        $updateData['return_time'] = $returnOrderStatus === RecycleReturnOrderDict::ORDER_STATUS_COMPLETED ? time() : 0;

        return $updateData;
    }

    private function buildCancelledReturnDeviceData(array $device): array
    {
        $status = RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
        $disposeType = RecycleOrderDict::DISPOSE_TYPE_PENDING;
        $disposeStatus = RecycleOrderDict::DISPOSE_STATUS_PENDING;
        $settlementMode = RecycleOrderDict::DISPOSE_TYPE_PENDING;
        $confirmStatus = RecycleOrderDict::CONFIRM_STATUS_PENDING;

        if (!empty($device['consignment_order_id']) || ($device['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN) {
            $status = RecycleOrderDict::DEVICE_STATUS_CONSIGNED;
            $disposeType = RecycleOrderDict::DISPOSE_TYPE_CONSIGN;
            $disposeStatus = RecycleOrderDict::DISPOSE_STATUS_CONSIGNED;
            $settlementMode = RecycleOrderDict::DISPOSE_TYPE_CONSIGN;
            $confirmStatus = RecycleOrderDict::CONFIRM_STATUS_CONFIRMED;
        } elseif ((int)($device['pay_status'] ?? 0) === RecycleOrderDict::PAY_STATUS_PAID
            || (float)($device['pay_amount'] ?? 0) > 0
            || (int)($device['confirm_status'] ?? 0) === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED) {
            $status = RecycleOrderDict::DEVICE_STATUS_RECYCLED;
            $disposeType = RecycleOrderDict::DISPOSE_TYPE_RECYCLE;
            $disposeStatus = RecycleOrderDict::DISPOSE_STATUS_RECYCLED;
            $settlementMode = RecycleOrderDict::DISPOSE_TYPE_RECYCLE;
            $confirmStatus = RecycleOrderDict::CONFIRM_STATUS_CONFIRMED;
        } elseif ((float)($device['final_price'] ?? 0) > 0 || in_array((int)($device['status'] ?? 0), [
            RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            RecycleOrderDict::DEVICE_STATUS_PRICED,
            RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE,
        ], true)) {
            $status = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
        } elseif (in_array((int)($device['status'] ?? 0), [RecycleOrderDict::DEVICE_STATUS_CHECKING], true)) {
            $status = RecycleOrderDict::DEVICE_STATUS_CHECKING;
        } elseif (!empty($device['check_result']) || !empty($device['check_result_seller']) || !empty($device['check_at'])) {
            $status = RecycleOrderDict::DEVICE_STATUS_CHECKED;
        }

        return [
            'status' => $status,
            'settlement_mode' => $settlementMode,
            'dispose_type' => $disposeType,
            'dispose_status' => $disposeStatus,
            'return_order_id' => 0,
            'return_time' => 0,
            'return_remark' => '',
            'confirm_status' => $confirmStatus,
        ];
    }

    private function syncParentOrderStatus(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $order = (new RecycleOrder())->where('id', $orderId)->find();
        if (empty($order) || in_array((int)$order['status'], [RecycleOrderDict::ORDER_STATUS_CANCELLED, RecycleOrderDict::ORDER_STATUS_DELETE], true)) {
            return;
        }

        $devices = (new RecycleDevice())->where('order_id', $orderId)->select()->toArray();
        if (empty($devices)) {
            return;
        }

        $counts = [
            RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK => 0,
            RecycleOrderDict::DEVICE_STATUS_CHECKING => 0,
            RecycleOrderDict::DEVICE_STATUS_CHECKED => 0,
            RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM => 0,
            RecycleOrderDict::DEVICE_STATUS_RECYCLED => 0,
            RecycleOrderDict::DEVICE_STATUS_RETURNED => 0,
            RecycleOrderDict::DEVICE_STATUS_PRICED => 0,
            RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE => 0,
            RecycleOrderDict::DEVICE_STATUS_CONSIGNED => 0,
        ];

        $allDevicesChecked = true;
        $allDevicesPriced = true;
        $allDevicesInFinalState = true;
        $recycledPaidCount = 0; // 已回收且已打款的设备数(打款只改 pay_status, status 仍为 RECYCLED)

        foreach ($devices as $device) {
            $deviceStatus = (int)($device['status'] ?? 0);
            if (isset($counts[$deviceStatus])) {
                $counts[$deviceStatus]++;
            }
            if ($deviceStatus === RecycleOrderDict::DEVICE_STATUS_RECYCLED
                && (int)($device['pay_status'] ?? 0) === RecycleOrderDict::PAY_STATUS_PAID) {
                $recycledPaidCount++;
            }

            if (in_array($deviceStatus, [
                RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                RecycleOrderDict::DEVICE_STATUS_CHECKING,
            ], true)) {
                $allDevicesChecked = false;
            }

            if (!in_array($deviceStatus, [
                RecycleOrderDict::DEVICE_STATUS_RETURNED,
                RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
            ], true) && (float)($device['final_price'] ?? 0) <= 0 && $deviceStatus !== RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
                $allDevicesPriced = false;
            }

            if (!in_array($deviceStatus, [
                RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                RecycleOrderDict::DEVICE_STATUS_RETURNED,
                RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
            ], true)) {
                $allDevicesInFinalState = false;
            }
        }

        $total = count($devices);
        $newStatus = (int)$order['status'];

        if ($allDevicesInFinalState) {
            if ($counts[RecycleOrderDict::DEVICE_STATUS_RETURNED] === $total) {
                $newStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
            } elseif ($counts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] > 0) {
                // 已回收设备:全部打过款 → 订单已完成;还有未打款的 → 维持待打款。
                // (打款只改 pay_status, 设备 status 仍是 RECYCLED, 故须看 pay_status 而非仅 status)
                $newStatus = ($recycledPaidCount >= $counts[RecycleOrderDict::DEVICE_STATUS_RECYCLED])
                    ? RecycleOrderDict::ORDER_STATUS_COMPLETED
                    : RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
            } elseif ($counts[RecycleOrderDict::DEVICE_STATUS_CONSIGNED] > 0) {
                $newStatus = RecycleOrderDict::ORDER_STATUS_COMPLETED;
            }
        } elseif ($counts[RecycleOrderDict::DEVICE_STATUS_CHECKING] > 0) {
            $newStatus = RecycleOrderDict::ORDER_STATUS_CHECKING;
        } elseif ($counts[RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK] > 0) {
            $newStatus = RecycleOrderDict::ORDER_STATUS_SIGNED;
        } else {
            $confirmedAndRecycled = $counts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] + $counts[RecycleOrderDict::DEVICE_STATUS_RECYCLED];
            $nonClosedDevices = $total - $counts[RecycleOrderDict::DEVICE_STATUS_RETURNED] - $counts[RecycleOrderDict::DEVICE_STATUS_CONSIGNED];

            if ($confirmedAndRecycled === $total && $counts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] > 0) {
                $newStatus = RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
            } elseif ($allDevicesChecked) {
                if ($allDevicesPriced && $nonClosedDevices > 0 && $counts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] === $nonClosedDevices) {
                    $newStatus = RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
                } elseif (
                    $counts[RecycleOrderDict::DEVICE_STATUS_CHECKED] > 0
                    || $counts[RecycleOrderDict::DEVICE_STATUS_PRICED] > 0
                    || $counts[RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE] > 0
                ) {
                    $newStatus = RecycleOrderDict::ORDER_STATUS_CHECKED;
                }
            }
        }

        if ($newStatus !== (int)$order['status']) {
            (new RecycleOrder())->where('id', $orderId)->update(['status' => $newStatus]);
        }
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
