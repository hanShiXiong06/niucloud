<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentLog;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleDevicePayment;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderEventService;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use addon\hsx_recycle\app\support\RecyclePurchaseSettlementPolicy;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 设备级打款服务
 */
class RecycleDevicePaymentService extends BaseAdminService
{
    public function getPaymentMode(): string
    {
        return (new RecycleOrderFlowModeService())->getDefaultFlowMode();
    }

    public function assertOrderPaymentAllowed(?int $orderId = null): void
    {
        // 这里只做控制器的模式预检；会锁定/记录归属的最终闸门必须由付款事务调用。
        $mode = $this->getPaymentMode();
        if ($orderId) {
            $order = RecycleOrder::where([
                ['id', '=', $orderId],
                ['site_id', '=', $this->site_id],
                ['delete_at', '=', 0],
            ])->findOrEmpty();
            if (!$order->isEmpty()) {
                $mode = (new RecycleOrderFlowModeService())->getOrderFlowMode($order->toArray());
            }
        }
        if ($mode === RecycleOrderDict::FLOW_MODE_DEVICE) {
            throw new CommonException('当前为按设备打款模式，请在打款弹窗中选择需要打款的设备');
        }
    }

    public function payDevices(int $orderId, array $data): array
    {
        if ($orderId <= 0) throw new CommonException('订单标识无效');
        $deviceIds = self::normalizePaymentDeviceIds($data['device_ids'] ?? []);
        if (empty($deviceIds)) {
            throw new CommonException('请选择需要打款的设备');
        }

        Db::startTrans();
        try {
            $flowModeService = new RecycleOrderFlowModeService();
            $order = RecycleOrder::where([
                ['id', '=', $orderId],
                ['site_id', '=', $this->site_id],
                ['delete_at', '=', 0],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) {
                throw new CommonException('订单不存在');
            }
            $this->assertDevicePaymentOrderState($order->toArray());
            if ($flowModeService->getOrderFlowMode($order->toArray()) !== RecycleOrderDict::FLOW_MODE_DEVICE) {
                throw new CommonException('当前订单为整单流转，请使用订单确认打款');
            }
            $wasCompleted = (int)$order->status === RecycleOrderDict::ORDER_STATUS_COMPLETED;

            $devices = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $orderId],
            ])->whereIn('id', $deviceIds)->order('id asc')->lock(true)->select();
            if ($devices->count() !== count($deviceIds)) {
                throw new CommonException('选择的设备不属于当前订单');
            }

            $paymentInfo = is_array($data['payment_info'] ?? null) ? $data['payment_info'] : [];
            $payType = trim((string)($paymentInfo['pay_type'] ?? $data['pay_type'] ?? ''));
            $payAccount = trim((string)($paymentInfo['account'] ?? $data['pay_account'] ?? $data['account'] ?? ''));
            $payName = trim((string)($data['pay_name'] ?? $order->customer_name ?? ''));
            $paymentImages = $paymentInfo['payment_images'] ?? $data['payment_images'] ?? '';
            $payRemark = trim((string)($paymentInfo['remark'] ?? $data['pay_remark'] ?? $data['remark'] ?? ''));
            if ($payType === '') {
                throw new CommonException('请选择或填写打款方式');
            }

            $payTime = time();
            $payNo = $this->buildPayNo($orderId);
            $paidAmount = 0.0;
            $paidCount = 0;
            $payments = [];
            foreach ($devices as $device) {
                $payState = $flowModeService->getDevicePayState($device->toArray(), RecycleOrderDict::FLOW_MODE_DEVICE);
                if (!$payState['allowed']) {
                    throw new CommonException(($device->imei ?: $device->model ?: ('设备#' . $device->id)) . '：' . $payState['reason']);
                }
                if ((int)($device->pay_status ?? 0) !== RecycleOrderDict::PAY_STATUS_UNPAID
                    || (float)($device->pay_amount ?? 0) > 0 || (int)($device->pay_time ?? 0) > 0) {
                    throw new CommonException('设备已打款，请勿重复操作：' . ($device->imei ?: $device->model ?: ('设备#' . $device->id)));
                }

                $amount = round((float)($device->final_price ?: $device->initial_price ?: 0), 2);
                if (!is_finite($amount) || $amount <= 0) {
                    throw new CommonException('设备金额为0，无法打款：' . ($device->imei ?: $device->model ?: ('设备#' . $device->id)));
                }
                $payments[] = ['device' => $device, 'amount' => $amount];
            }
            // 全批校验完成后，在持有订单/设备锁的事务内确认真实付款归属，再写任何付款事实。
            $this->assertLocalPaymentAllowed($orderId, $deviceIds);
            foreach ($payments as $payment) {
                $device = $payment['device'];
                $amount = $payment['amount'];
                $device->save([
                    'pay_status' => RecycleOrderDict::PAY_STATUS_PAID,
                    'pay_amount' => $amount,
                    'pay_time' => $payTime,
                    'pay_uid' => $this->uid,
                    'pay_no' => $payNo,
                ]);

                RecycleDevicePayment::create([
                    'site_id' => $this->site_id,
                    'pay_no' => $payNo,
                    'order_id' => $orderId,
                    'device_id' => (int)$device->id,
                    'member_id' => (int)$order->member_id,
                    'order_no' => (string)$order->order_no,
                    'device_imei' => (string)$device->imei,
                    'device_model' => (string)$device->model,
                    'amount' => $amount,
                    'pay_type' => $payType,
                    'pay_account' => $payAccount,
                    'pay_name' => $payName,
                    'pay_remark' => $payRemark,
                    'payment_images' => is_array($paymentImages) ? implode(',', $paymentImages) : (string)$paymentImages,
                    'pay_uid' => $this->uid,
                    'pay_time' => $payTime,
                    'create_at' => $payTime,
                ]);

                RecycleDeviceLog::create([
                    'site_id' => $this->site_id,
                    'device_id' => (int)$device->id,
                    'order_id' => $orderId,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username,
                    'operation_type' => 'device_payment',
                    'action' => 'device_payment',
                    'old_status' => (int)$device->status,
                    'new_status' => RecycleOrderDict::DEVICE_OP_TYPE_PAYMENT,
                    'remark' => sprintf('设备打款 | 金额: %.2f | 批次: %s | 备注: %s', $amount, $payNo, $payRemark),
                    'create_at' => $payTime,
                ]);

                $paidAmount += $amount;
                $paidCount++;

                // 埋点：打款 = 离开"待打款"、入库 ERP 离场（回收系统终点），累计当日打款台数+金额（旁路，失败不阻断）
                try {
                    (new \addon\hsx_recycle\app\service\core\stat\CoreRecycleStatService())
                        ->recordStageChange($this->site_id, 'pay', '', (int)$this->uid, $amount);
                } catch (\Throwable $e) {
                }
            }

            $summary = $this->syncOrderPayStatus($orderId, [
                'pay_type' => $payType,
                'pay_account' => $payAccount,
                'pay_name' => $payName,
                'pay_remark' => $payRemark,
                'payment_images' => is_array($paymentImages) ? implode(',', $paymentImages) : (string)$paymentImages,
                'pay_time' => $payTime,
            ]);

            Db::commit();

            if (!$wasCompleted && !empty($summary['all_paid'])) {
                CoreRecycleOrderEventService::orderPaymentAfter([
                    'order_id' => $orderId,
                    'site_id' => $this->site_id,
                    'operator_id' => $this->uid,
                    'action' => 'device_payment_completed',
                ]);
            }

            return array_merge($summary, [
                'pay_no' => $payNo,
                'paid_count' => $paidCount,
                'paid_amount' => round($paidAmount, 2),
            ]);
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function assertLocalPaymentAllowed(int $orderId = 0, array $deviceIds = []): void
    {
        (new RecycleErpCapabilityService())->assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds);
    }

    protected function assertDevicePaymentOrderState(array $order): void
    {
        $payStatus = (int)($order['pay_status'] ?? 0);
        if (!in_array($payStatus, [RecycleOrderDict::PAY_STATUS_UNPAID, RecycleOrderDict::PAY_STATUS_PARTIAL], true)
            || ($payStatus === RecycleOrderDict::PAY_STATUS_UNPAID && (int)($order['pay_time'] ?? 0) > 0)
            || in_array((int)($order['status'] ?? 0), [RecycleOrderDict::ORDER_STATUS_COMPLETED, RecycleOrderDict::ORDER_STATUS_CLOSED, RecycleOrderDict::ORDER_STATUS_CANCELLED], true)) {
            throw new CommonException('订单已付款、付款记录待核对或已结束，请勿重复打款');
        }
    }

    /** 付款批次任何一个ID无效都拒绝，不能截断或删掉后继续付款。 */
    public static function normalizePaymentDeviceIds($values): array
    {
        if (!is_array($values)) throw new CommonException('付款设备列表格式不正确');
        $ids = [];
        foreach ($values as $value) {
            $text = is_int($value) ? (string)$value : (is_string($value) ? $value : '');
            if (!preg_match('/^[0-9]+$/', $text)) throw new CommonException('付款设备ID无效，已拒绝整批付款');
            $text = ltrim($text, '0');
            $max = (string)PHP_INT_MAX;
            if ($text === '' || strlen($text) > strlen($max)
                || (strlen($text) === strlen($max) && strcmp($text, $max) > 0)) {
                throw new CommonException('付款设备ID无效，已拒绝整批付款');
            }
            $ids[(int)$text] = (int)$text;
        }
        $ids = array_values($ids);
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    public function getPaymentLogs(int $orderId): array
    {
        return RecycleDevicePayment::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])
            ->with(['operator'])
            ->order('id desc')
            ->select()
            ->toArray();
    }

    public function getPaymentSummary(int $orderId, int $siteId = 0): array
    {
        $siteId = $siteId > 0 ? $siteId : (int)$this->site_id;
        $flowModeService = new RecycleOrderFlowModeService();
        $order = RecycleOrder::where([
            ['site_id', '=', $siteId],
            ['id', '=', $orderId],
            ['delete_at', '=', 0],
        ])->findOrEmpty();
        $mode = $order->isEmpty() ? $this->getPaymentMode() : $flowModeService->getOrderFlowMode($order->toArray());
        $devices = RecycleDevice::where([
            ['site_id', '=', $siteId],
            ['order_id', '=', $orderId],
        ])->select()->toArray();

        $totalCount = count($devices);
        $paidCount = 0;
        $totalAmount = 0.0;
        $paidAmount = 0.0;
        foreach ($devices as $device) {
            $payState = $flowModeService->getDevicePayState($device, $mode);
            $amount = round((float)($device['final_price'] ?: $device['initial_price'] ?: 0), 2);
            if ($payState['allowed'] || in_array((int)($device['pay_status'] ?? 0), [RecycleOrderDict::PAY_STATUS_PAID, RecycleOrderDict::PAY_STATUS_PARTIAL], true)) {
                $totalAmount += $amount;
            }
            $recordedPaid = max(0, round((float)($device['pay_amount'] ?? 0), 2));
            if ((int)($device['pay_status'] ?? 0) === RecycleOrderDict::PAY_STATUS_PAID) {
                $paidCount++;
                $paidAmount += $recordedPaid > 0 ? $recordedPaid : $amount;
            } else {
                $paidAmount += $recordedPaid;
            }
        }

        $flowSummary = $flowModeService->buildSummary(
            $flowModeService->decorateDevices($devices, $mode)
        );

        return [
            'payment_mode' => $mode,
            'payable_count' => (int)$flowSummary['payable_count'],
            'paid_count' => $paidCount,
            'unpaid_count' => max(0, (int)$flowSummary['payable_count'] - $paidCount),
            'total_amount' => round($totalAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'unpaid_amount' => round(max(0, $totalAmount - $paidAmount), 2),
            'all_paid' => !empty($flowSummary['all_closed']),
        ];
    }

    /** 只刷新付款汇总，不重走回收完成流程，不改历史付款时间或业务完成时间。 */
    public function refreshErpPaymentSummary(int $orderId, int $siteId): void
    {
        $summary = $this->getPaymentSummary($orderId, $siteId);
        RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', $orderId]])->update([
            'total_amount' => $summary['total_amount'],
            'pay_status' => $summary['unpaid_amount'] <= 0 && $summary['all_paid'] ? RecycleOrderDict::PAY_STATUS_PAID
                : ($summary['paid_amount'] > 0 ? RecycleOrderDict::PAY_STATUS_PARTIAL : RecycleOrderDict::PAY_STATUS_UNPAID),
            'update_at' => time(),
        ]);
    }

    /** 精确设备采购结算；每张结算单只记本次金额，累计状态使用单调快照。 */
    public function applyErpPurchaseSettlement(int $siteId, int $deviceId, string $settlementNo, array $snapshot, array $info): int
    {
        if ($siteId <= 0 || $deviceId <= 0 || $settlementNo === '') throw new CommonException('ERP 采购结算缺少来源标识');
        $completedOrderId = 0;
        $result = Db::transaction(function () use ($siteId, $deviceId, $settlementNo, $snapshot, $info, &$completedOrderId): int {
            $preview = RecycleDevice::where([['site_id', '=', $siteId], ['id', '=', $deviceId]])->findOrEmpty();
            if ($preview->isEmpty()) throw new CommonException('ERP 结算来源设备不存在');
            $order = RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', (int)$preview->order_id]])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) throw new CommonException('ERP 结算来源订单不存在');
            $device = RecycleDevice::where([['site_id', '=', $siteId], ['id', '=', $deviceId]])->lock(true)->findOrEmpty();
            $existing = RecycleDevicePayment::where([['site_id', '=', $siteId], ['device_id', '=', $deviceId], ['pay_no', '=', $settlementNo]])->findOrEmpty();
            if (!$existing->isEmpty()) return 0;
            $state = RecyclePurchaseSettlementPolicy::settlement($device->toArray(), $snapshot);
            $occurredAt = (int)($info['confirmed_at'] ?? 0) ?: time();
            $payType = ($info['method'] ?? '') === 'offset' ? 'ERP折账' : 'ERP实际付款';
            $remark = sprintf('%s本次 %.2f，累计已结算 %.2f，剩余待付 %.2f；单号 %s', $payType,
                $state['payment_amount'], $state['pay_amount'], max(0, (float)$device->final_price - $state['pay_amount']), $settlementNo);
            $update = ['pay_amount' => $state['pay_amount'], 'pay_status' => $state['pay_status'], 'update_at' => time()];
            if ($occurredAt >= (int)$device->pay_time) {
                $update += ['pay_time' => $occurredAt, 'pay_no' => $settlementNo];
            }
            $device->save($update);
            RecycleDevicePayment::create([
                'site_id' => $siteId, 'device_id' => $deviceId, 'order_id' => (int)$device->order_id,
                'member_id' => (int)$order->member_id, 'order_no' => (string)$order->order_no,
                'device_imei' => (string)$device->imei, 'device_model' => (string)$device->model,
                'pay_no' => $settlementNo, 'amount' => $state['payment_amount'], 'pay_type' => $payType,
                'pay_account' => (string)($info['account'] ?? ''), 'pay_name' => (string)($info['operator'] ?? 'ERP财务'),
                'pay_uid' => 0, 'pay_time' => $occurredAt, 'create_at' => time(), 'pay_remark' => $remark,
            ]);
            RecycleDeviceLog::create([
                'site_id' => $siteId, 'device_id' => $deviceId, 'order_id' => (int)$device->order_id,
                'operator_id' => 0, 'operator_name' => (string)($info['operator'] ?? 'ERP财务'),
                'operation_type' => 'device_payment', 'action' => 'device_offset_settle',
                'old_status' => (int)$device->status, 'new_status' => (int)$device->status,
                'remark' => $remark, 'create_at' => time(),
            ]);
            $this->refreshErpPaymentSummary((int)$device->order_id, $siteId);
            if ($state['pay_status'] === RecycleOrderDict::PAY_STATUS_PAID) {
                // 首次结清保持原回收完结链路；补付不重写原业务完成时间。
                if ((int)$order->status !== RecycleOrderDict::ORDER_STATUS_COMPLETED) {
                    $summary = $this->syncOrderPayStatus((int)$device->order_id,
                        ['pay_type' => $payType, 'pay_remark' => $remark, 'pay_time' => $occurredAt], $siteId);
                    if ($summary['all_paid']) $completedOrderId = (int)$device->order_id;
                }
            }
            return 1;
        });
        if ($completedOrderId > 0) {
            CoreRecycleOrderEventService::marketingDeliveryFactAfter([
                'order_id' => $completedOrderId, 'site_id' => $siteId, 'action' => 'erp_settlement_completed',
                'source_plugin' => 'hsx_erp', 'settlement_no' => $settlementNo,
            ]);
        }
        return $result;
    }

    /**
     * 折账结清: 财务中心把回收应付折账冲抵后, 回调本方把对应设备标记为"已折账结清"(等同已打款),
     * 备注写折账结算单号, 便于查账。已打款的设备跳过(幂等)。
     * @param array $deviceIds 回收设备ID(= ERP应付的 source_device_id)
     * @param string $settlementNo 折账结算单号(JS...)
     * @param array $info [offset=>本次折账额, cash=>本次现金额, operator=>操作人]
     * @return int 实际标记的设备数
     */
    public function settleByOffset(array $deviceIds, string $settlementNo, array $info = []): int
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            return 0;
        }
        $siteId = (int)($info['site_id'] ?? $this->site_id);
        if ($siteId <= 0) {
            throw new CommonException('ERP结算回写缺少站点标识');
        }
        $now = time();
        // 结清方式: 现金(有户头支出)/折账/折账+现金, 由结算事件带来的 method 决定, 不再写死"折账"
        $method = (string)($info['method'] ?? 'offset');
        $payTypeText = $method === 'payment'
            ? 'ERP实际付款'
            : ($method === 'cash' ? '现金' : ($method === 'mixed' ? '折账+现金' : '折账'));
        $payRemark = $payTypeText . '结清 单号:' . $settlementNo;
        $marked = 0;
        Db::startTrans();
        try {
            $devices = RecycleDevice::where([['site_id', '=', $siteId]])->whereIn('id', $deviceIds)->select();
            // 设备表无 member_id/order_no, 从订单补
            $orderIdsAll = array_values(array_unique(array_filter(array_map(static fn($d) => (int)$d['order_id'], $devices->toArray()))));
            $orderMap = [];
            if (!empty($orderIdsAll)) {
                foreach (RecycleOrder::where([['site_id', '=', $siteId]])->whereIn('id', $orderIdsAll)->field('id,order_no,member_id')->select()->toArray() as $o) {
                    $orderMap[(int)$o['id']] = $o;
                }
            }
            $orderIds = [];
            foreach ($devices as $device) {
                $ord = $orderMap[(int)$device->order_id] ?? [];
                $amount = round((float)($device->final_price ?: $device->initial_price ?: 0), 2);
                $consignment = null;
                if ((int)($device->consignment_order_id ?? 0) > 0) {
                    $candidate = RecycleConsignmentOrder::where([
                        ['site_id', '=', $siteId],
                        ['id', '=', (int)$device->consignment_order_id],
                        ['source_device_id', '=', (int)$device->id],
                    ])->lock(true)->findOrEmpty();
                    if (!$candidate->isEmpty()) {
                        $consignment = $candidate;
                        $consignmentAmount = round((float)$candidate->settlement_amount, 2);
                        if ($consignmentAmount > 0) $amount = $consignmentAmount;
                    }
                }
                $deviceNeedsSettlement = (int)($device->pay_status ?? 0) !== RecycleOrderDict::PAY_STATUS_PAID;
                $consignmentNeedsSettlement = $consignment instanceof RecycleConsignmentOrder
                    && (int)$consignment->pay_status !== RecycleConsignmentDict::PAY_STATUS_PAID;
                if (!$deviceNeedsSettlement && !$consignmentNeedsSettlement) {
                    continue;
                }

                if ($deviceNeedsSettlement) {
                    $device->save([
                        'pay_status' => RecycleOrderDict::PAY_STATUS_PAID,
                        'pay_amount' => $amount,
                        'pay_time'   => $now,
                        'pay_uid'    => (int)$this->uid,
                        'pay_no'     => $settlementNo,
                        'pay_type'   => $payTypeText,
                        'pay_remark' => $payRemark,
                        'update_at'  => $now,
                    ]);
                    RecycleDevicePayment::create([
                        'site_id'      => $siteId,
                        'pay_no'       => $settlementNo,
                        'order_id'     => (int)$device->order_id,
                        'device_id'    => (int)$device->id,
                        'member_id'    => (int)($ord['member_id'] ?? 0),
                        'order_no'     => (string)($ord['order_no'] ?? ''),
                        'device_imei'  => (string)$device->imei,
                        'device_model' => (string)$device->model,
                        'amount'       => $amount,
                        'pay_type'     => $payTypeText,
                        'pay_account'  => (string)($info['account'] ?? ''),
                        'pay_name'     => (string)($info['operator'] ?? ''),
                        'pay_remark'   => $payRemark,
                        'pay_uid'      => (int)$this->uid,
                        'pay_time'     => $now,
                        'create_at'    => $now,
                    ]);
                    RecycleDeviceLog::create([
                        'site_id'        => $siteId,
                        'device_id'      => (int)$device->id,
                        'order_id'       => (int)$device->order_id,
                        'operator_id'    => (int)$this->uid,
                        'operator_name'  => (string)($info['operator'] ?? $this->username),
                        'operation_type' => 'device_payment',
                        'action'         => 'device_offset_settle',
                        'old_status'     => (int)$device->status,
                        'new_status'     => RecycleOrderDict::DEVICE_OP_TYPE_PAYMENT,
                        'remark'         => sprintf('%s结清 | 金额: %.2f | 结算单: %s', $payTypeText, $amount, $settlementNo),
                        'create_at'      => $now,
                    ]);
                }
                if ($consignmentNeedsSettlement) {
                    $before = $consignment->toArray();
                    $consignment->save([
                        'settlement_amount' => $amount,
                        'service_fee' => max(0, round((float)$consignment->sold_price - $amount, 2)),
                        'status' => RecycleConsignmentDict::STATUS_SETTLED,
                        'pay_status' => RecycleConsignmentDict::PAY_STATUS_PAID,
                        'pay_time' => $now,
                        'pay_uid' => (int)$this->uid,
                        'settle_time' => $now,
                        'operator_id' => (int)$this->uid,
                        'update_time' => $now,
                    ]);
                    RecycleConsignmentLog::create([
                        'site_id' => $siteId,
                        'consignment_id' => (int)$consignment->id,
                        'source_order_id' => (int)$consignment->source_order_id,
                        'source_device_id' => (int)$device->id,
                        'operator_id' => (int)$this->uid,
                        'operator_name' => (string)($info['operator'] ?? 'ERP财务'),
                        'action' => 'settle',
                        'old_status' => (int)($before['status'] ?? 0),
                        'new_status' => RecycleConsignmentDict::STATUS_SETTLED,
                        'before_data' => $before,
                        'after_data' => $consignment->toArray(),
                        'remark' => sprintf('ERP财务结清代卖货款 | 金额: %.2f | 结算单: %s', $amount, $settlementNo),
                        'create_time' => $now,
                    ]);
                }
                if ($deviceNeedsSettlement) $orderIds[(int)$device->order_id] = true;
                $marked++;
            }
            $completedOrderIds = [];
            foreach (array_keys($orderIds) as $oid) {
                $summary = $this->syncOrderPayStatus($oid, ['pay_type' => $payTypeText, 'pay_remark' => $payRemark, 'pay_time' => $now], $siteId);
                if (!empty($summary['all_paid'])) {
                    $completedOrderIds[] = (int)$oid;
                }
            }
            Db::commit();

            // ERP 结算是跨插件回写路径，过去只更新了付款状态，没有发布营销事实。
            // 必须在事务提交后发布；稳定 event_id 会拦截 ERP 重试造成的重复累计。
            foreach ($completedOrderIds as $completedOrderId) {
                CoreRecycleOrderEventService::marketingDeliveryFactAfter([
                    'order_id' => $completedOrderId,
                    'site_id' => $siteId,
                    'action' => 'erp_settlement_completed',
                    'source_plugin' => 'hsx_erp',
                    'settlement_no' => $settlementNo,
                ]);
            }
            return $marked;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    private function syncOrderPayStatus(int $orderId, array $paymentData, int $siteId = 0): array
    {
        $siteId = $siteId > 0 ? $siteId : (int)$this->site_id;
        $summary = $this->getPaymentSummary($orderId, $siteId);
        $payStatus = RecycleOrderDict::PAY_STATUS_UNPAID;
        if ($summary['all_paid']) {
            $payStatus = RecycleOrderDict::PAY_STATUS_PAID;
        } elseif ($summary['paid_count'] > 0) {
            $payStatus = RecycleOrderDict::PAY_STATUS_PARTIAL;
        }

        $update = [
            'pay_status' => $payStatus,
            'total_amount' => $summary['total_amount'],
            'pay_type' => $paymentData['pay_type'] ?? '',
            'pay_account' => $paymentData['pay_account'] ?? '',
            'pay_name' => $paymentData['pay_name'] ?? '',
            'pay_remark' => $paymentData['pay_remark'] ?? '',
            'payment_images' => $paymentData['payment_images'] ?? '',
            'update_at' => time(),
        ];

        if ($summary['all_paid']) {
            $update['pay_time'] = $paymentData['pay_time'] ?? time();
            $update['pay_uid'] = $this->uid;
            $update['status'] = RecycleOrderDict::ORDER_STATUS_COMPLETED;
            $update['complete_at'] = time();
        }

        RecycleOrder::where([
            ['id', '=', $orderId],
            ['site_id', '=', $siteId],
        ])->update($update);

        return $summary;
    }

    private function buildPayNo(int $orderId): string
    {
        return 'DP' . date('YmdHis') . $orderId . random_int(1000, 9999);
    }
}
