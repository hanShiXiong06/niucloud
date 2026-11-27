<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/** Read model only. The list, its SQL count/filter, and the purchase-detail rows share this projection. */
final class ErpDevicePayableDisplay
{
    /** The model supplies physical table names; aliases are fixed internally, never request parameters. */
    public static function originalJoin(string $table, string $asset = 'a', string $payable = 'dp'): string
    {
        // Preserve exactly one row per asset, including when all originals are void or duplicates exist.
        return "$payable.id = (SELECT COALESCE(MIN(CASE WHEN x.status = 'void' THEN NULL ELSE x.id END), MIN(x.id))
            FROM $table x WHERE x.site_id = $asset.site_id AND x.source_type = 'purchase_asset' AND x.source_id = $asset.id)";
    }

    public static function stateSql(string $table, string $returns, string $a = 'a', string $p = 'dp'): string
    {
        $inactive = "($a.status IN ('returned','void') OR o.status = 'void' OR EXISTS
            (SELECT 1 FROM $returns ri WHERE ri.site_id = $a.site_id AND ri.asset_id = $a.id))";
        return "CASE
            WHEN o.id IS NULL OR $a.purchase_order_id <> o.id OR $p.id IS NULL THEN 'review'
            WHEN $p.status = 'void' THEN CASE WHEN $inactive THEN 'void' ELSE 'review' END
            WHEN $a.party_id IS NULL OR $p.party_id IS NULL OR $a.party_id <= 0 OR $p.party_id <> $a.party_id
                OR ($p.asset_id <> 0 AND $p.asset_id <> $a.id)
                OR $p.amount IS NULL OR $p.settled_amount IS NULL OR $p.amount < 0 OR $p.settled_amount < 0
                OR $p.settled_amount > $p.amount OR EXISTS (SELECT 1 FROM $table other_pay
                    WHERE other_pay.site_id = $a.site_id AND other_pay.source_type = 'purchase_asset'
                    AND other_pay.source_id = $a.id AND other_pay.status <> 'void' AND other_pay.id <> $p.id) THEN 'review'
            WHEN $p.status = 'settled' AND $p.settled_amount = $p.amount THEN 'settled'
            WHEN $inactive THEN 'review'
            WHEN $p.status = 'pending' AND $p.amount > 0 AND $p.settled_amount = 0 THEN 'pending'
            WHEN $p.status = 'partial' AND $p.settled_amount > 0 AND $p.settled_amount < $p.amount THEN 'partial'
            ELSE 'review' END";
    }

    public static function filterSql(string $state, string $tab): string
    {
        switch ($tab) {
            case 'outstanding': return "($state) IN ('pending','partial')";
            case 'settled': return "($state) = 'settled'";
            case 'review': return "($state) = 'review'";
            case '': return '';
            default: return '1 = 0';
        }
    }

    /** Both queries already join the current purchase as o and the asset as a. */
    public static function attach($query, string $table, string $returns, string $tab = ''): void
    {
        $state = self::stateSql($table, $returns);
        $query->leftJoin("$table dp", self::originalJoin($table))
            ->fieldRaw("$state AS dp_state, dp.id AS dp_id, dp.amount AS dp_amount, dp.settled_amount AS dp_paid");
        if ($tab !== '') $query->whereRaw(self::filterSql($state, $tab));
    }

    /** Unknown or void facts never expose an amount, a finance link, or another asset's money. */
    public static function take(array &$row): array
    {
        $state = (string)($row['dp_state'] ?? 'review');
        $hint = ['state' => $state === 'void' ? 'void' : 'review'];
        if (in_array($state, ['pending', 'partial', 'settled'], true) && (int)($row['dp_id'] ?? 0) > 0) {
            $amount = round((float)$row['dp_amount'], 2);
            $paid = round((float)$row['dp_paid'], 2);
            $hint = ['state' => $state, 'remaining' => round($amount - $paid, 2),
                'amount' => $amount, 'paid' => $paid, 'payable_id' => (int)$row['dp_id']];
        }
        unset($row['dp_state'], $row['dp_id'], $row['dp_amount'], $row['dp_paid']);
        return $hint;
    }
}
