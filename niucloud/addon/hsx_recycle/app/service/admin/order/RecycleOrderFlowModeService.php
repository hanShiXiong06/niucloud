<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 回收订单流转模式服务
 * 负责把订单级状态、设备级状态、客户确认状态和打款状态汇总成前端可理解的数据。
 */
class RecycleOrderFlowModeService extends BaseAdminService
{
    public function getDefaultFlowMode(): string
    {
        $config = (new OrderSubmitConfigService())->getConfig($this->site_id);
        return $this->normalizeFlowMode((string)($config['flow']['mode'] ?? $config['payment']['mode'] ?? RecycleOrderDict::FLOW_MODE_ORDER));
    }

    public function normalizeFlowMode(string $mode): string
    {
        return $mode === RecycleOrderDict::FLOW_MODE_DEVICE
            ? RecycleOrderDict::FLOW_MODE_DEVICE
            : RecycleOrderDict::FLOW_MODE_ORDER;
    }

    public function getOrderFlowMode(array $order): string
    {
        return $this->normalizeFlowMode((string)($order['flow_mode'] ?? RecycleOrderDict::FLOW_MODE_ORDER));
    }

    public function decorateOrder(array $order): array
    {
        $mode = $this->getOrderFlowMode($order);
        $devices = array_values($order['devices'] ?? []);
        $decoratedDevices = $this->decorateDevices($devices, $mode);
        $summary = $this->buildSummary($decoratedDevices);

        $order['flow_mode'] = $mode;
        $order['flow_mode_name'] = RecycleOrderDict::getFlowMode($mode);
        $order['payment_mode'] = $mode;
        $order['flow_summary'] = $summary;
        $order['available_actions'] = $this->buildAvailableActions($order, $summary, $mode);
        $order['devices'] = $decoratedDevices;

        return $order;
    }

    public function decorateDevices(array $devices, string $mode): array
    {
        return array_map(function (array $device) use ($mode) {
            $device['confirm_status'] = $this->resolveConfirmStatus($device);
            $device['confirm_status_name'] = RecycleOrderDict::getConfirmStatus($device['confirm_status']);
            $device['pay_status'] = (int)($device['pay_status'] ?? RecycleOrderDict::PAY_STATUS_UNPAID);
            $device['pay_status_name'] = RecycleOrderDict::getPayStatus($device['pay_status']);

            $confirmState = $this->getDeviceConfirmState($device, $mode);
            $payState = $this->getDevicePayState($device, $mode);

            $device['can_confirm'] = $confirmState['allowed'];
            $device['confirm_disabled_reason'] = $confirmState['reason'];
            $device['can_pay'] = $payState['allowed'];
            $device['pay_disabled_reason'] = $payState['reason'];
            $device['disabled_reason'] = $payState['reason'];

            return $device;
        }, $devices);
    }

    public function buildSummary(array $devices): array
    {
        $summary = [
            'total' => count($devices),
            'pending_check' => 0,
            'checking' => 0,
            'checked' => 0,
            'pending_confirm' => 0,
            'confirmed' => 0,
            'pending_pay' => 0,
            'paid' => 0,
            'returned' => 0,
            'consigned' => 0,
            'closed' => 0,
            'payable_count' => 0,
            'payable_amount' => 0.0,
            'paid_amount' => 0.0,
        ];

        foreach ($devices as $device) {
            $status = (int)($device['status'] ?? 0);
            $confirmStatus = (int)($device['confirm_status'] ?? RecycleOrderDict::CONFIRM_STATUS_PENDING);
            $payStatus = (int)($device['pay_status'] ?? RecycleOrderDict::PAY_STATUS_UNPAID);
            $amount = round((float)($device['final_price'] ?: $device['initial_price'] ?: 0), 2);

            if ($status === RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK) {
                $summary['pending_check']++;
            }
            if ($status === RecycleOrderDict::DEVICE_STATUS_CHECKING) {
                $summary['checking']++;
            }
            if (in_array($status, [
                RecycleOrderDict::DEVICE_STATUS_CHECKED,
                RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
                RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                RecycleOrderDict::DEVICE_STATUS_RETURNED,
                RecycleOrderDict::DEVICE_STATUS_PRICED,
                RecycleOrderDict::DEVICE_STATUS_PRICED_REPRICE,
                RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
            ], true)) {
                $summary['checked']++;
            }
            if ($status === RecycleOrderDict::DEVICE_STATUS_CONSIGNED || ($device['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN) {
                $summary['consigned'] = (int)($summary['consigned'] ?? 0) + 1;
                $summary['closed']++;
                continue;
            }
            if ($status === RecycleOrderDict::DEVICE_STATUS_RETURNED || $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_REJECTED) {
                $summary['returned']++;
                $summary['closed']++;
                continue;
            }
            if ($status === RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM && $confirmStatus !== RecycleOrderDict::CONFIRM_STATUS_CONFIRMED) {
                $summary['pending_confirm']++;
            }
            if ($confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED || $status === RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
                $summary['confirmed']++;
            }
            if ($payStatus === RecycleOrderDict::PAY_STATUS_PAID) {
                $summary['paid']++;
                $summary['closed']++;
                $summary['paid_amount'] += (float)($device['pay_amount'] ?: $amount);
            } elseif (($confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED || $status === RecycleOrderDict::DEVICE_STATUS_RECYCLED) && $amount > 0) {
                $summary['pending_pay']++;
                $summary['payable_count']++;
                $summary['payable_amount'] += $amount;
            }
        }

        $summary['payable_amount'] = round($summary['payable_amount'], 2);
        $summary['paid_amount'] = round($summary['paid_amount'], 2);
        $summary['all_closed'] = $summary['total'] > 0 && $summary['closed'] >= $summary['total'];

        // 按 DeviceProgressDict 分组输出（供前端直接渲染）
        $pending = $summary['pending_check'] + $summary['checking']
            + ($summary['checked'] - $summary['confirmed'] - $summary['returned'] - ($summary['consigned'] ?? 0));
        if ($pending < 0) $pending = 0;
        $summary['progress'] = [
            ['key' => 'total', 'label' => '共', 'value' => $summary['total'], 'color' => 'info'],
            ['key' => 'pending', 'label' => '待处理', 'value' => $pending, 'color' => 'warning'],
            ['key' => 'pending_confirm', 'label' => '待确认', 'value' => $summary['pending_confirm'], 'color' => 'primary'],
            ['key' => 'pending_pay', 'label' => '待打款', 'value' => $summary['pending_pay'], 'color' => 'success'],
            ['key' => 'paid', 'label' => '已打款', 'value' => $summary['paid'], 'color' => 'success'],
            ['key' => 'abnormal', 'label' => '异常', 'value' => $summary['returned'] + ($summary['consigned'] ?? 0), 'color' => 'danger'],
        ];

        return $summary;
    }

    public function getDevicePayState(array $device, string $mode): array
    {
        $status = (int)($device['status'] ?? 0);
        $confirmStatus = $this->resolveConfirmStatus($device);
        $payStatus = (int)($device['pay_status'] ?? RecycleOrderDict::PAY_STATUS_UNPAID);
        $amount = round((float)($device['final_price'] ?: $device['initial_price'] ?: 0), 2);

        if ($payStatus === RecycleOrderDict::PAY_STATUS_PAID) {
            return ['allowed' => false, 'reason' => '已打款'];
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_RETURNED || $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_REJECTED) {
            return ['allowed' => false, 'reason' => '设备已退回'];
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_CONSIGNED || ($device['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN) {
            return ['allowed' => false, 'reason' => '设备已转代卖，请在代卖订单中结算'];
        }
        if ($amount <= 0) {
            return ['allowed' => false, 'reason' => '金额为0，不可打款'];
        }
        if ($mode === RecycleOrderDict::FLOW_MODE_DEVICE && $confirmStatus !== RecycleOrderDict::CONFIRM_STATUS_CONFIRMED && $status !== RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
            return ['allowed' => false, 'reason' => '待客户确认'];
        }
        if (!in_array($status, [RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM, RecycleOrderDict::DEVICE_STATUS_RECYCLED], true)) {
            return ['allowed' => false, 'reason' => '待质检或待定价'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    public function getDeviceConfirmState(array $device, string $mode): array
    {
        $status = (int)($device['status'] ?? 0);
        $confirmStatus = $this->resolveConfirmStatus($device);

        if ($confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED || $status === RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
            return ['allowed' => false, 'reason' => '已确认'];
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_RETURNED || $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_REJECTED) {
            return ['allowed' => false, 'reason' => '设备已退回'];
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_CONSIGNED || ($device['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN) {
            return ['allowed' => false, 'reason' => '设备已转代卖'];
        }
        if ($status !== RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
            return ['allowed' => false, 'reason' => '待质检或待定价'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    public function resolveConfirmStatus(array $device): int
    {
        $status = (int)($device['status'] ?? 0);
        if ($status === RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
            return RecycleOrderDict::CONFIRM_STATUS_CONFIRMED;
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_RETURNED) {
            return RecycleOrderDict::CONFIRM_STATUS_REJECTED;
        }
        if ($status === RecycleOrderDict::DEVICE_STATUS_CONSIGNED) {
            return RecycleOrderDict::CONFIRM_STATUS_CONFIRMED;
        }
        if (array_key_exists('confirm_status', $device) && $device['confirm_status'] !== null) {
            return (int)$device['confirm_status'];
        }
        return RecycleOrderDict::CONFIRM_STATUS_PENDING;
    }

    public function confirmDevices(int $orderId, array $deviceIds, string $remark = '', int $confirmStatus = RecycleOrderDict::CONFIRM_STATUS_CONFIRMED): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            throw new CommonException('请选择需要确认的设备');
        }

        Db::startTrans();
        try {
            $order = RecycleOrder::where([
                ['id', '=', $orderId],
                ['site_id', '=', $this->site_id],
                ['delete_at', '=', 0],
            ])->findOrEmpty();
            if ($order->isEmpty()) {
                throw new CommonException('订单不存在');
            }

            $mode = $this->getOrderFlowMode($order->toArray());
            if ($mode !== RecycleOrderDict::FLOW_MODE_DEVICE) {
                throw new CommonException('当前为整单流转，请使用整单确认流程');
            }

            $devices = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $orderId],
            ])->whereIn('id', $deviceIds)->select();
            if ($devices->count() !== count($deviceIds)) {
                throw new CommonException('选择的设备不属于当前订单');
            }

            $now = time();
            foreach ($devices as $device) {
                $state = $this->getDeviceConfirmState($device->toArray(), $mode);
                if (!$state['allowed']) {
                    throw new CommonException(($device->imei ?: $device->model ?: ('设备#' . $device->id)) . '：' . $state['reason']);
                }

                $oldStatus = (int)$device->status;
                $device->save([
                    'confirm_status' => $confirmStatus,
                    'confirm_time' => $now,
                    'confirm_member_id' => (int)$order->member_id,
                    'confirm_remark' => $remark,
                    'status' => $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED
                        ? RecycleOrderDict::DEVICE_STATUS_RECYCLED
                        : RecycleOrderDict::DEVICE_STATUS_RETURNED,
                    'settlement_mode' => $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED
                        ? RecycleOrderDict::DISPOSE_TYPE_RECYCLE
                        : RecycleOrderDict::DISPOSE_TYPE_RETURN,
                    'dispose_type' => $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED
                        ? RecycleOrderDict::DISPOSE_TYPE_RECYCLE
                        : RecycleOrderDict::DISPOSE_TYPE_RETURN,
                    'dispose_status' => $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED
                        ? RecycleOrderDict::DISPOSE_STATUS_RECYCLED
                        : RecycleOrderDict::DISPOSE_STATUS_RETURNED,
                    'update_at' => $now,
                ]);

                RecycleDeviceLog::create([
                    'site_id' => $this->site_id,
                    'device_id' => (int)$device->id,
                    'order_id' => $orderId,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username,
                    'operation_type' => 'device_confirm',
                    'action' => 'device_confirm',
                    'old_status' => $oldStatus,
                    'new_status' => $confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED
                        ? RecycleOrderDict::DEVICE_STATUS_RECYCLED
                        : RecycleOrderDict::DEVICE_STATUS_RETURNED,
                    'remark' => '设备报价确认 | ' . ($confirmStatus === RecycleOrderDict::CONFIRM_STATUS_CONFIRMED ? '已确认' : '已拒绝') . ($remark !== '' ? ' | 备注: ' . $remark : ''),
                    'create_at' => $now,
                ]);
            }

            $this->syncOrderProgress($orderId);
            $summary = $this->getOrderSummary($orderId);
            Db::commit();

            return $summary;
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function getOrderSummary(int $orderId): array
    {
        $order = RecycleOrder::where([
            ['id', '=', $orderId],
            ['site_id', '=', $this->site_id],
            ['delete_at', '=', 0],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('订单不存在');
        }
        $devices = RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])->select()->toArray();

        return $this->buildSummary($this->decorateDevices($devices, $this->getOrderFlowMode($order->toArray())));
    }

    public function syncOrderProgress(int $orderId): array
    {
        $order = RecycleOrder::where([
            ['id', '=', $orderId],
            ['site_id', '=', $this->site_id],
            ['delete_at', '=', 0],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('订单不存在');
        }

        $summary = $this->getOrderSummary($orderId);
        $update = [
            'update_at' => time(),
        ];
        if (!empty($summary['all_closed'])) {
            $update['status'] = RecycleOrderDict::ORDER_STATUS_COMPLETED;
            $update['complete_at'] = time();
        } elseif ($summary['pending_pay'] > 0) {
            $update['status'] = RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
        } elseif ($summary['pending_confirm'] > 0) {
            $update['status'] = RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
        } elseif ($summary['checking'] > 0 || $summary['pending_check'] > 0) {
            $update['status'] = RecycleOrderDict::ORDER_STATUS_CHECKING;
        }

        RecycleOrder::where([
            ['id', '=', $orderId],
            ['site_id', '=', $this->site_id],
        ])->update($update);

        return $summary;
    }

    private function buildAvailableActions(array $order, array $summary, string $mode): array
    {
        return [
            'can_confirm_devices' => $mode === RecycleOrderDict::FLOW_MODE_DEVICE && $summary['pending_confirm'] > 0,
            'can_push_confirm_notice' => $summary['pending_confirm'] > 0,
            'can_pay_devices' => $mode === RecycleOrderDict::FLOW_MODE_DEVICE && $summary['payable_count'] > 0,
            'can_order_payment' => $mode === RecycleOrderDict::FLOW_MODE_ORDER && (int)($order['status'] ?? 0) === RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
            'can_complete_order' => !empty($summary['all_closed']),
        ];
    }
}
