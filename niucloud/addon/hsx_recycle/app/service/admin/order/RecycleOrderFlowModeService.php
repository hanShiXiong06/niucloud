<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleOrderProgressPolicy;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleOrderProgressService;
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
        return RecycleOrderProgressPolicy::summarize($devices);
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
        return RecycleOrderProgressPolicy::resolveConfirmStatus($device);
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
        $result = (new RecycleOrderProgressService())->sync((int)$this->site_id, $orderId, (int)$this->uid);
        return $result['flow_summary'];
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
