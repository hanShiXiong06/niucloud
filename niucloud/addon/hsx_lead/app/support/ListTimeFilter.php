<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\support;

use core\exception\CommonException;

/** Shared list-only date contract. Callers own the allowlist and SQL aliases. */
final class ListTimeFilter
{
    /** Unix seconds, inclusive bounds; zero/empty means no bound, never SQL text. */
    public static function apply($query, array $params, array $fields, string $default): void
    {
        $field = Data::text($params, 'time_field', 32) ?: $default;
        if (!array_key_exists($field, $fields)) throw new CommonException('请选择当前列表支持的时间维度');
        $start = self::timestamp($params['start_time'] ?? null);
        $end = self::timestamp($params['end_time'] ?? null);
        if ($start !== null && $end !== null && $start > $end) throw new CommonException('开始时间不能晚于结束时间，请重新选择日期范围');
        if ($start === null && $end === null) return;

        // Unrecorded events use 0. A date search must not treat them as 1970 events,
        // especially when only an end date is supplied (e.g. unreceived commissions).
        $query->where($fields[$field], '>', 0);
        if ($start !== null) $query->where($fields[$field], '>=', $start);
        if ($end !== null) $query->where($fields[$field], '<=', $end);
    }

    private static function timestamp($value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') return null;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]{1,10}$/D', (string) $value)
            || (int) $value <= 0 || (int) $value > 4294967295) {
            throw new CommonException('时间范围格式不正确，请重新选择日期；接口仅接受秒级时间戳');
        }
        return (int) $value;
    }
}
