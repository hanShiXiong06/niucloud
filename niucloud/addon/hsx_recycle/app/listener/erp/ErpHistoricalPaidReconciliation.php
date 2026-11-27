<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\erp;

use addon\hsx_recycle\app\service\core\recycle_order\RecyclePaymentOwnershipService;
use core\exception\CommonException;

/** 历史付款补账的纯校验：只接受逐台来源事实与完整 ERP 证据，不产生任何付款或归属写入。 */
final class ErpHistoricalPaidReconciliation
{
    /** @return array<int, int> 每台本次核销金额，单位分。 */
    public static function requestedAmounts(array $ids, mixed $rows): array
    {
        return self::deviceAmounts($ids, $rows, false);
    }

    /** 当前 ERP 事务已锁定应付的已核销快照，允许零，但不能缺少任何设备。 */
    public static function priorPaidAmounts(array $ids, mixed $rows): array
    {
        return self::deviceAmounts($ids, $rows, true);
    }

    private static function deviceAmounts(array $ids, mixed $rows, bool $allowZero): array
    {
        if (!is_array($rows) || $rows === [] || count($rows) !== count($ids)) {
            throw new CommonException('历史付款核销金额必须完整对应所选设备');
        }
        $amounts = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('device_id', $row)) {
                throw new CommonException('历史付款核销设备金额格式不正确');
            }
            $id = RecyclePaymentOwnershipService::deviceIds([$row['device_id']])[0];
            if (isset($amounts[$id])) {
                throw new CommonException('历史付款核销设备不能重复');
            }
            $amount = self::cents($row['amount'] ?? null);
            if (!$allowZero && $amount <= 0) throw new CommonException('历史付款核销金额必须大于零');
            $amounts[$id] = $amount;
        }
        ksort($amounts, SORT_NUMERIC);
        if (array_keys($amounts) !== $ids) {
            throw new CommonException('历史付款核销金额与所选设备不一致');
        }
        return $amounts;
    }

    /** 来源设备必须来自调用方事务内的站点锁定查询；ERP 回执来自既有只读归属事件。 */
    public static function assertAllowed(int $siteId, array $amounts, array $devices, array $responses, array $priorPaid): void
    {
        $ids = array_keys($amounts);
        if ($ids === [] || array_keys($priorPaid) !== $ids) {
            throw new CommonException('ERP 当前已核销快照缺少完整设备金额');
        }
        $source = [];
        foreach ($devices as $device) {
            if (!is_array($device) || !array_key_exists('id', $device)) {
                throw new CommonException('历史付款来源设备资料不完整');
            }
            $id = RecyclePaymentOwnershipService::deviceIds([$device['id']])[0];
            if (isset($source[$id]) || !isset($amounts[$id]) || (int)($device['site_id'] ?? 0) !== $siteId) {
                throw new CommonException('历史付款来源设备不属于当前站点或关联不唯一');
            }
            $source[$id] = self::sourcePaidCents($device);
        }
        ksort($source, SORT_NUMERIC);
        if (array_keys($source) !== $ids) {
            throw new CommonException('历史付款来源设备缺失，未执行结算');
        }

        $evidence = null;
        foreach ($responses as $response) {
            if (!is_array($response) || ($response['consumer'] ?? '') !== 'hsx_erp') continue;
            if ($evidence !== null || ($response['status'] ?? '') !== 'processed' || !empty($response['error'])
                || !is_array($response['devices'] ?? null)) {
                throw new CommonException('ERP 历史付款核销查询未完整确认');
            }
            $evidence = $response['devices'];
        }
        if ($evidence === null || RecyclePaymentOwnershipService::deviceIds(array_keys($evidence)) !== $ids) {
            throw new CommonException('ERP 历史付款核销查询缺少完整设备回执');
        }

        $seenAssets = [];
        foreach ($ids as $id) {
            $entry = $evidence[$id] ?? null;
            if (!is_array($entry) || ($entry['has_asset'] ?? null) !== true || ($entry['has_payable'] ?? null) !== true
                || ($entry['ambiguous'] ?? null) !== false || !is_array($entry['asset_ids'] ?? null)
                || count($entry['asset_ids']) !== 1) {
                throw new CommonException('ERP 历史付款应付关联不唯一或不完整，未执行结算');
            }
            $assetId = RecyclePaymentOwnershipService::deviceIds($entry['asset_ids'])[0];
            if (isset($seenAssets[$assetId])) {
                throw new CommonException('ERP 历史付款设备不能共用同一资产应付');
            }
            $seenAssets[$assetId] = true;
            // 普通只读查询可能保留 RR 旧快照；当前财务事务锁定读的金额只可提高已核销扣减。
            $erpPaid = max(self::cents($entry['paid_amount'] ?? null), $priorPaid[$id]);
            $remaining = self::cents($entry['remaining_amount'] ?? null);
            if ($erpPaid > $source[$id] || $amounts[$id] > $source[$id] - $erpPaid || $amounts[$id] > $remaining) {
                throw new CommonException('历史付款核销超过该设备真实已付款的未核销余额');
            }
        }
    }

    private static function sourcePaidCents(array $device): int
    {
        $status = $device['pay_status'] ?? null;
        if (!in_array($status, [0, 1, 2, '0', '1', '2'], true)
            || !array_key_exists('pay_time', $device) || !self::isNonNegativeInteger($device['pay_time'])) {
            throw new CommonException('历史付款来源状态不明确，未执行结算');
        }
        $paid = self::cents($device['pay_amount'] ?? null);
        // 真实部分付款金额可作为事实，兼容旧记录没有付款时间；仅有时间不能猜测金额。
        if ($paid > 0) return $paid;
        if ((int)$status === 1) {
            $paid = self::cents($device['final_price'] ?? null);
            if ($paid > 0) return $paid;
        }
        throw new CommonException('来源设备没有可确认的历史已付款金额，未执行结算');
    }

    private static function isNonNegativeInteger(mixed $value): bool
    {
        return (is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value)
                && (string)(int)$value === $value);
    }

    private static function cents(mixed $value): int
    {
        if (is_float($value)) {
            if (!is_finite($value) || $value < 0 || abs($value * 100 - round($value * 100)) > 0.000001) {
                throw new CommonException('历史付款核销金额格式不正确');
            }
            $value = sprintf('%.2F', $value);
        }
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', (string)$value)) {
            throw new CommonException('历史付款核销金额格式不正确');
        }
        $parts = explode('.', (string)$value, 2);
        $digits = ltrim($parts[0] . str_pad($parts[1] ?? '', 2, '0'), '0');
        $digits = $digits === '' ? '0' : $digits;
        $maximum = (string)PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new CommonException('历史付款核销金额超出范围');
        }
        return (int)$digits;
    }
}
