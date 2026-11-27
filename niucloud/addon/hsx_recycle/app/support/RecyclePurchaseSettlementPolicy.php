<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\support;

use core\exception\CommonException;

/** 采购调价与结算快照：本次流水和累计已付必须分开。金额统一按分比较。 */
final class RecyclePurchaseSettlementPolicy
{
    public static function cents($value): int
    {
        if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > 999999999.99) {
            throw new CommonException('采购结算金额无效');
        }
        return (int)round((float)$value * 100);
    }

    public static function adjustment(array $device, array $event): array
    {
        $before = self::cents($event['before_amount'] ?? null);
        $after = self::cents($event['after_amount'] ?? null);
        $settled = self::cents($event['settled_amount'] ?? null);
        $delta = self::cents($event['delta'] ?? null);
        $current = self::cents($device['final_price'] ?? 0);
        $paid = self::cents($device['pay_amount'] ?? 0);
        if ($paid === 0 && (int)($device['pay_status'] ?? 0) === 1) $paid = $current;
        if ($current !== $before || $settled !== $paid) {
            throw new CommonException('回收价格或已付款与 ERP 不一致，请先等待结算回写并核对原账，本次未调价');
        }
        if ($before <= 0 || $after <= 0 || $after < $paid || $paid < 0 || $delta === 0 || $before + $delta !== $after) {
            throw new CommonException('采购调价金额不一致或涉及退差款，本次未调价');
        }
        return ['final_price' => $after / 100, 'pay_amount' => $paid / 100,
            'pay_status' => $after === $paid ? 1 : ($paid > 0 ? 2 : 0)];
    }

    public static function settlement(array $device, array $snapshot): array
    {
        $target = self::cents($snapshot['target_amount'] ?? null);
        $settled = self::cents($snapshot['settled_amount'] ?? null);
        $applied = self::cents($snapshot['applied_amount'] ?? null);
        $remaining = self::cents($snapshot['remaining_amount'] ?? null);
        $price = self::cents($device['final_price'] ?? 0);
        $paid = self::cents($device['pay_amount'] ?? 0);
        if ($paid === 0 && (int)($device['pay_status'] ?? 0) === 1) $paid = $price;
        if ($target <= 0 || $applied <= 0 || $settled < $applied || $settled > $target || $remaining !== $target - $settled
            || $price <= 0 || $paid < 0 || $settled > $price || $paid > $price) {
            throw new CommonException('ERP 采购结算快照与回收价格不一致，请核对后重试回写');
        }
        // 老事件晚到只补它自己的流水，不得倒退累计已付，也不能用老价格覆盖新价格。
        $cumulative = max($paid, $settled);
        return ['pay_amount' => $cumulative / 100, 'payment_amount' => $applied / 100,
            'pay_status' => $cumulative === $price ? 1 : 2];
    }
}
