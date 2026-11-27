<?php

namespace addon\phone_shop\app\service\admin\cashier;

/** SQL sort keys for the cashier; never interpolate client-provided column names. */
class CashierGoodsOrder
{
    public static function memoryGb(string $value): ?float
    {
        $parts = explode('+', str_replace(' ', '', strtoupper(trim($value))));
        $value = end($parts);
        if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)(TB|T|GB|G|MB|M)?$/', $value, $match)) return null;
        $amount = (float) $match[1];
        $unit = $match[2] ?? '';
        if ($amount <= 0 || ($unit === '' && !in_array($amount, [16, 32, 64, 128, 256, 512, 1024, 2048, 4096, 8192]))) return null;
        return $amount * (in_array($unit, ['T', 'TB']) ? 1024 : (in_array($unit, ['M', 'MB']) ? 1 / 1024 : 1));
    }

    public static function priceExpression(array $memberInfo, int $levelKey): string
    {
        $price = 'ROUND(sku.price, 2)';
        if (empty($memberInfo['member_level'])) {
            return $price;
        }

        $benefit = $memberInfo['memberLevelData']['level_benefits']['discount'] ?? [];
        $discountPrice = $price;
        if (!empty($benefit['is_use'])) {
            $discount = sprintf('%.10F', max(0, min(10, (float) ($benefit['discount'] ?? 10))));
            $cap = sprintf('%.2F', max(0, round((float) ($benefit['max_discount_money'] ?? 0), 2)));
            $discountPrice = "ROUND(GREATEST(0, {$price}) * {$discount} / 10, 2)";
            if ((float) $cap > 0) {
                $discountPrice = "GREATEST({$discountPrice}, GREATEST(0, {$price} - {$cap}))";
            }
        }

        $key = max(0, $levelKey);
        $json = "IF(JSON_VALID(sku.member_price), sku.member_price, '{}')";
        $fixed = "ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT({$json}, '$.level_{$key}')) AS DECIMAL(20, 6)), 2)";
        $candidate = "CASE goods.member_discount WHEN 'discount' THEN {$discountPrice} WHEN 'fixed_price' THEN {$fixed} ELSE {$price} END";
        // Match the displayed cashier price: only positive prices below the original are member prices.
        return "CASE WHEN ({$candidate}) > 0 AND ({$candidate}) < {$price} THEN ({$candidate}) ELSE {$price} END";
    }

    public static function memoryFilter(array $values): array
    {
        $bind = [];
        $rawKeys = [];
        $capacityKeys = [];
        foreach (array_values($values) as $index => $value) {
            $key = 'cashier_memory_raw_' . $index;
            $rawKeys[] = ':' . $key;
            $bind[$key] = (string) $value;
            $capacity = self::memoryGb((string) $value);
            if ($capacity !== null) {
                $key = 'cashier_memory_gb_' . $index;
                $capacityKeys[] = ':' . $key;
                $bind[$key] = $capacity;
            }
        }
        if (!$rawKeys) return ['1=1', []];
        $where = 'goods.memory_group IN (' . implode(',', $rawKeys) . ')';
        if ($capacityKeys) $where .= ' OR (' . self::memoryExpression() . ') IN (' . implode(',', $capacityKeys) . ')';
        return ['(' . $where . ')', $bind];
    }

    public static function memoryExpression(): string
    {
        $value = "SUBSTRING_INDEX(REPLACE(UPPER(TRIM(goods.memory_group)), ' ', ''), '+', -1)";
        $number = "CAST({$value} AS DECIMAL(12, 3))";
        $capacity = "CASE WHEN {$value} REGEXP '^[0-9]+([.][0-9]+)?(TB|T|GB|G|MB|M)$' AND {$number} > 0 THEN {$number} * CASE WHEN {$value} REGEXP 'T(B)?$' THEN 1024 WHEN {$value} REGEXP 'M(B)?$' THEN 0.0009765625 ELSE 1 END WHEN {$value} IN ('16','32','64','128','256','512','1024','2048','4096','8192') THEN {$number} ELSE NULL END";
        // Some imported memory_group values are indexes, not capacities. Only explicit units in the title may fill the gap.
        $title = 'CASE';
        foreach ([8 => 8192, 4 => 4096, 2 => 2048, 1 => 1024] as $tb => $gb) {
            $title .= " WHEN UPPER(goods.goods_name) REGEXP '(^|[^0-9.]){$tb}TB?([^A-Z0-9]|$)' THEN {$gb}";
        }
        foreach ([8192, 4096, 2048, 1024, 512, 256, 128, 64, 32, 16] as $gb) {
            $title .= " WHEN UPPER(goods.goods_name) REGEXP '(^|[^0-9.]){$gb}GB?([^A-Z0-9]|$)' THEN {$gb}";
        }
        $title .= ' ELSE NULL END';
        return "COALESCE({$capacity}, {$title})";
    }

    public static function order(string $sortBy, string $direction): string
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'price') {
            return "cashier_price {$direction}, sku.sku_id desc";
        }
        if ($sortBy === 'memory') {
            return "cashier_memory IS NULL asc, cashier_memory {$direction}, sku.sku_id desc";
        }
        return 'sku.sku_id desc';
    }
}
