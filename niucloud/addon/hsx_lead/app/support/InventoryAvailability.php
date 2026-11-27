<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

use core\exception\CommonException;

/** One stock eligibility contract for lists, totals, candidates and merchant reads. */
final class InventoryAvailability
{
    public static function scope(array $params, bool $manager): string
    {
        $value = $params['inventory_scope'] ?? 'sellable';
        if (!is_string($value) || !in_array($value, ['sellable', 'all', 'off_shelf', 'sold_out', 'unverified'], true)) {
            throw new CommonException('请选择有效的库存范围');
        }
        // A merchant cannot expand its public stock reference into diagnostics.
        return $manager ? $value : 'sellable';
    }

    /** All identifiers and JSON paths are internal constants, never request fragments. */
    public static function apply($query, string $scope = 'sellable', bool $fresh = true)
    {
        $listing = self::jsonState('listing_status');
        $stock = self::jsonState('stock_status');
        $sold = "(".self::jsonState('sale_state')." = 'sold')";
        $off = "($listing = 'unlisted')";
        $empty = "($sold OR $stock = 'out_of_stock' OR i.saleable_quantity = 0 OR i.quantity = 0)";
        $positive = "(COALESCE(i.saleable_quantity,i.quantity) > 0 AND (i.quantity IS NULL OR i.quantity > 0))";
        $known = "(i.availability = 'available' AND $positive AND $listing = 'listed' AND $stock = 'in_stock' AND NOT $sold)";
        $current = self::freshSql();
        if ($scope === 'sellable') {
            $query->whereRaw($known)->where('m.status', 1);
            if ($fresh) $query->whereRaw($current, [time()]);
        } elseif ($scope === 'off_shelf') {
            $query->whereRaw("($off AND NOT $sold)");
        } elseif ($scope === 'sold_out') {
            $query->whereRaw("($sold OR (NOT $off AND $empty))");
        } elseif ($scope === 'unverified') {
            // SQL COALESCE avoids NULL quantities dropping unknown records from diagnostics.
            $query->whereRaw("((NOT $off AND NOT COALESCE($empty,0) AND NOT COALESCE($known,0)) OR NOT $current OR m.status <> 1)", [time()]);
        }
        return $query;
    }

    public static function present(array $row): array
    {
        $source = Data::decode($row['source_status_json'] ?? null);
        $state = is_array($source['_inventory'] ?? null) ? $source['_inventory'] : [];
        $listing = in_array($state['listing_status'] ?? '', ['listed', 'unlisted', 'unknown'], true) ? $state['listing_status'] : 'unknown';
        $stock = in_array($state['stock_status'] ?? '', ['in_stock', 'out_of_stock', 'unknown'], true) ? $state['stock_status'] : 'unknown';
        $saleState = in_array($state['sale_state'] ?? '', ['unsold', 'sold', 'unknown'], true) ? $state['sale_state'] : 'unknown';
        $sold = $saleState === 'sold';
        $quantity = self::decimal($row['quantity'] ?? null);
        $saleable = self::decimal($row['saleable_quantity'] ?? null);
        $amount = $saleable ?? $quantity;
        $zero = ($quantity !== null && bccomp($quantity, '0', 3) === 0) || ($saleable !== null && bccomp($saleable, '0', 3) === 0);
        $positive = $amount !== null && bccomp($amount, '0', 3) > 0 && !$zero;
        $availability = in_array($row['availability'] ?? '', ['available', 'unavailable', 'unknown'], true) ? $row['availability'] : 'unknown';
        $off = $listing === 'unlisted';
        $empty = $sold || $stock === 'out_of_stock' || $zero;
        $known = $availability === 'available' && $positive && $listing === 'listed' && $stock === 'in_stock' && !$empty;
        $sale = $sold ? 'sold_out' : ($off ? 'off_shelf' : ($empty ? 'sold_out' : ($listing === 'listed' || $known ? 'on_sale' : 'unknown')));
        $lastSync = (int) ($row['last_sync_at'] ?? 0);
        $fetched = (int) ($row['fetched_at'] ?? 0);
        $threshold = max(2 * max(1, (int) ($row['sync_interval_minutes'] ?? 60)), 120) * 60;
        $timestamp = max($lastSync, $fetched);
        $failed = in_array($row['last_sync_status'] ?? '', ['failed', 'cancelled'], true);
        $stale = $failed || $timestamp <= 0 || $timestamp + $threshold < time();
        $merchantActive = (int) ($row['merchant_status'] ?? 1) === 1;
        $stockLabel = $empty ? '无可售库存' : ($positive ? '有货 · '.rtrim(rtrim($amount, '0'), '.').' 台' : ($stock === 'in_stock' ? '在库，接单前请核对' : '库存待核实'));
        $basis = in_array($state['quantity_basis'] ?? '', ['source_quantity', 'listed_stock_device'], true) ? $state['quantity_basis'] : 'unknown';
        return [
            'availability' => $availability, 'quantity' => $quantity, 'saleable_quantity' => $saleable,
            'listing_status' => $listing, 'stock_status' => $stock, 'sale_state' => $saleState, 'sale_status' => $sale,
            'sale_status_label' => $sold ? '已售出' : ($sale === 'unknown' && $stock === 'in_stock' ? '在库 · 未标记上架' : [
                    'on_sale' => '在售',
                    'off_shelf' => '已下架',
                    'sold_out' => '无可售库存',
                    'unknown' => '状态待核实'
                ][$sale]),
            'stock_status_label' => $stockLabel, 'quantity_basis' => $basis,
            'is_sellable' => $known && !$stale && $merchantActive, 'is_stale' => $stale,
            'sync_failed' => $failed, 'stale_after_minutes' => intdiv($threshold, 60),
        ];
    }

    private static function decimal($value): ?string
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]{1,11}(?:\.[0-9]{1,3})?$/D', (string) $value)) return null;
        return bcadd((string) $value, '0', 3);
    }

    private static function jsonState(string $key): string
    {
        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(i.source_status_json),i.source_status_json,'{}'),'$._inventory.$key')),'unknown')";
    }

    private static function freshSql(): string
    {
        return "(c.last_sync_status NOT IN ('failed','cancelled') AND GREATEST(c.last_sync_at,i.fetched_at) > 0 AND GREATEST(c.last_sync_at,i.fetched_at) + GREATEST(2 * GREATEST(c.sync_interval_minutes,1),120) * 60 >= ?)";
    }
}
