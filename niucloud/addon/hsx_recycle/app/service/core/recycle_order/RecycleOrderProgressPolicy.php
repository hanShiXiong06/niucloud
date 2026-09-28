<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;

/**
 * 设备汇总唯一口径。只判断回收业务进度，不把“拒绝出售”当作实物退货已签收。
 */
class RecycleOrderProgressPolicy
{
    public static function summarize(array $devices): array
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
            $confirmStatus = self::resolveConfirmStatus($device);
            $payStatus = (int)($device['pay_status'] ?? RecycleOrderDict::PAY_STATUS_UNPAID);
            $amount = round((float)(($device['final_price'] ?? 0) ?: ($device['initial_price'] ?? 0)), 2);

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
                $summary['paid_amount'] += (float)(($device['pay_amount'] ?? 0) ?: $amount);
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
        // 待处理 = 真正未进入"待确认/待打款/已打款/异常"任一桶的设备;
        // 减去 pending_confirm,避免"待确认"的设备又被算进"待处理"(一台机器只落一个标签)。
        $pending = $summary['pending_check'] + $summary['checking']
            + ($summary['checked'] - $summary['confirmed'] - $summary['returned'] - ($summary['consigned'] ?? 0) - $summary['pending_confirm']);
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

    public static function resolveConfirmStatus(array $device): int
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

    public static function nextStatus(array $order, array $summary): int
    {
        $current = (int)($order['status'] ?? 0);
        // 不用刷新重新打开已结束/已取消订单，也不越过签收。
        if (in_array($current, [
            RecycleOrderDict::ORDER_STATUS_PENDING_SIGN,
            RecycleOrderDict::ORDER_STATUS_COMPLETED,
            RecycleOrderDict::ORDER_STATUS_CLOSED,
            RecycleOrderDict::ORDER_STATUS_CANCELLED,
            RecycleOrderDict::ORDER_STATUS_DELETE,
        ], true) || empty($summary['total'])) {
            return $current;
        }
        if (!empty($summary['all_closed'])) return RecycleOrderDict::ORDER_STATUS_COMPLETED;
        if ($summary['pending_pay'] > 0) return RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
        if ($summary['pending_confirm'] > 0) return RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
        if ($summary['checking'] > 0 || $summary['pending_check'] > 0) return RecycleOrderDict::ORDER_STATUS_CHECKING;
        if ($summary['checked'] > $summary['closed']) return RecycleOrderDict::ORDER_STATUS_CHECKED;
        return $current;
    }
}

