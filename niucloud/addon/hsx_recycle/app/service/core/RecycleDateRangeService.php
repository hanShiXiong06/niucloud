<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core;

/**
 * 回收业务日期范围统一处理。
 *
 * 前端只需要传日期，后端统一扩展到自然日完整边界，避免同一天只查到 00:00:00。
 */
class RecycleDateRangeService
{
    public static function normalizeRange($start = null, $end = null): array
    {
        $today = date('Y-m-d');
        $startDate = self::normalizeDate($start, $today);
        $endDate = self::normalizeDate($end, $startDate);

        $startAt = strtotime($startDate . ' 00:00:00') ?: strtotime($today . ' 00:00:00');
        $endAt = strtotime($endDate . ' 23:59:59') ?: strtotime($startDate . ' 23:59:59');

        if ($endAt < $startAt) {
            [$startAt, $endAt] = [$endAt, $startAt];
            [$startDate, $endDate] = [date('Y-m-d', $startAt), date('Y-m-d', $endAt)];
        }

        return [
            'start_time' => $startDate,
            'end_time' => $endDate,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    public static function normalizeRangeFromArray(array $range = [], ?string $fallbackStart = null, ?string $fallbackEnd = null): array
    {
        return self::normalizeRange($range[0] ?? $fallbackStart, $range[1] ?? $fallbackEnd);
    }

    private static function normalizeDate($value, string $fallback): string
    {
        if (is_numeric($value)) {
            $time = (int)$value;
            return $time > 0 ? date('Y-m-d', $time) : $fallback;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $matches)) {
            return $matches[0];
        }

        $time = strtotime($value);
        return $time ? date('Y-m-d', $time) : $fallback;
    }
}
