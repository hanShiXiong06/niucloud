<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/** 回收设备关联的纯解析规则：订单号、普通 source_id 和 IMEI 均不是设备关联证据。 */
final class ErpRecycleDeviceIdentity
{
    public static function resolve(array $asset, array $purchaseItems = []): array
    {
        $empty = ['device_id' => 0, 'ambiguous' => false, 'candidate_device_ids' => []];
        if ((string)($asset['source_plugin'] ?? '') !== 'hsx_recycle') return $empty;

        $candidates = [];
        $conflict = false;
        $collect = static function ($value) use (&$candidates, &$conflict): void {
            $snapshot = is_array($value) ? $value : json_decode((string)$value, true);
            if (!is_array($snapshot)) return;
            $id = self::positiveId($snapshot['source_device_id'] ?? 0);
            if ($id <= 0) return;
            $candidates[$id] = $id;
            $plugin = trim((string)($snapshot['source_plugin'] ?? ''));
            if ($plugin !== '' && $plugin !== 'hsx_recycle') $conflict = true;
        };
        $collect($asset['spec_json'] ?? []);
        foreach ($purchaseItems as $item) {
            // 必须同时确证站点和资产外键；不能拿同一采购订单的其他设备快照补关联。
            if ((int)($item['site_id'] ?? 0) !== (int)($asset['site_id'] ?? 0)
                || (int)($asset['id'] ?? 0) <= 0
                || (int)($item['asset_id'] ?? 0) !== (int)$asset['id']) continue;
            if ((int)($asset['purchase_item_id'] ?? 0) > 0
                && (int)($item['id'] ?? 0) !== (int)$asset['purchase_item_id']) continue;
            if ((int)($asset['purchase_order_id'] ?? 0) > 0
                && (int)($item['purchase_order_id'] ?? 0) !== (int)$asset['purchase_order_id']) continue;
            $collect($item['spec_json'] ?? []);
        }
        if ($candidates === [] && in_array((string)($asset['source_type'] ?? ''), [
            'hsx_recycle.consignment', 'hsx_recycle_consignment',
        ], true)) {
            $id = self::positiveId($asset['source_id'] ?? 0);
            if ($id > 0) $candidates[$id] = $id;
        }
        $ids = array_values($candidates);
        sort($ids, SORT_NUMERIC);
        $ambiguous = $conflict || count($ids) > 1;
        return ['device_id' => !$ambiguous && count($ids) === 1 ? $ids[0] : 0,
            'ambiguous' => $ambiguous, 'candidate_device_ids' => $ids];
    }

    public static function positiveId($value): int
    {
        if (is_int($value)) return max(0, $value);
        if (!is_string($value) || !preg_match('/^[0-9]+$/', $value)) return 0;
        $value = ltrim($value, '0');
        $max = (string)PHP_INT_MAX;
        if ($value === '' || strlen($value) > strlen($max)
            || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) return 0;
        return (int)$value;
    }
}
