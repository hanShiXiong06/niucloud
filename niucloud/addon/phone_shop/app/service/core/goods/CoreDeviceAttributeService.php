<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use core\exception\CommonException;

/**
 * 二手机筛选事实字段的统一清洗器。
 *
 * 只保存不会随时间变化的原始事实：颜色、电池健康度、保修到期日。
 * “剩余 30 天”“已过保”等展示区间由查询时动态计算，避免保存后逐日失真。
 */
class CoreDeviceAttributeService
{
    public const UNKNOWN_BATTERY = -1;

    public function normalizeColor($value, bool $strict = true): string
    {
        $color = trim((string)$value);
        if (mb_strlen($color) <= 50) return $color;
        if ($strict) throw new CommonException('【颜色】最多填写 50 个字符');
        return mb_substr($color, 0, 50);
    }

    public function normalizeBattery($value, bool $strict = true): int
    {
        if ($value === null || trim((string)$value) === '' || (string)$value === (string)self::UNKNOWN_BATTERY) return self::UNKNOWN_BATTERY;
        $normalized = trim(str_replace(['％', '%'], '', (string)$value));
        if (!preg_match('/^\d{1,3}$/', $normalized)) {
            if ($strict) throw new CommonException('【电池健康度】请填写 0-100 的整数，可带 %');
            return self::UNKNOWN_BATTERY;
        }
        $battery = (int)$normalized;
        if ($battery < 0 || $battery > 100) {
            if ($strict) throw new CommonException('【电池健康度】必须在 0-100 之间');
            return self::UNKNOWN_BATTERY;
        }
        return $battery;
    }

    /**
     * 保修期统一落成“到期当天 23:59:59”的秒级时间戳。
     * 接受 Y-m-d/Y/m/d/中文日期、秒或毫秒时间戳，不接受“剩余30天”这类相对值。
     */
    public function normalizeWarrantyExpire($value, bool $strict = true): int
    {
        if ($value === null || trim((string)$value) === '' || (string)$value === '0') return 0;

        if ($value instanceof \DateTimeInterface) {
            $date = $value->format('Y-m-d');
        } elseif (is_numeric($value) && (float)$value >= 1000000000) {
            $timestamp = (int)$value;
            if ($timestamp > 9999999999) $timestamp = (int)floor($timestamp / 1000);
            $date = date('Y-m-d', $timestamp);
        } else {
            $raw = trim((string)$value);
            $raw = str_replace(['年', '月', '日', '.', '/'], ['-', '-', '', '-', '-'], $raw);
            $raw = preg_replace('/\s+/', '', $raw) ?: $raw;
            $dateObject = \DateTimeImmutable::createFromFormat('!Y-n-j', $raw);
            $errors = \DateTimeImmutable::getLastErrors();
            $invalid = $dateObject === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0));
            if ($invalid) {
                if ($strict) throw new CommonException('【保修到期日】请填写绝对日期，例如 2027-06-01，不能填写“剩余30天”');
                return 0;
            }
            $date = $dateObject->format('Y-m-d');
        }

        $timestamp = strtotime($date . ' 23:59:59');
        if ($timestamp === false) {
            if ($strict) throw new CommonException('【保修到期日】日期无法识别');
            return 0;
        }
        return $timestamp;
    }

    public function formatWarrantyDate($value): string
    {
        $timestamp = (int)$value;
        return $timestamp > 0 ? date('Y-m-d', $timestamp) : '';
    }

    public function batteryRanges(): array
    {
        return [
            ['value' => '100', 'label' => '100%', 'min' => 100, 'max' => 100],
            ['value' => '95_99', 'label' => '95%-99%', 'min' => 95, 'max' => 99],
            ['value' => '90_94', 'label' => '90%-94%', 'min' => 90, 'max' => 94],
            ['value' => '85_89', 'label' => '85%-89%', 'min' => 85, 'max' => 89],
            ['value' => '80_84', 'label' => '80%-84%', 'min' => 80, 'max' => 84],
            ['value' => 'under_80', 'label' => '80%以下', 'min' => 0, 'max' => 79],
        ];
    }

    /** 保修区间的边界在每次请求时计算，保持“剩余天数”实时准确。 */
    public function warrantyRanges(?int $now = null): array
    {
        $now = $now ?: time();
        $today = strtotime(date('Y-m-d', $now) . ' 00:00:00');
        $day30 = strtotime('+30 days', $today);
        $day60 = strtotime('+60 days', $today);
        $day180 = strtotime('+180 days', $today);
        $day300 = strtotime('+300 days', $today);
        return [
            ['value' => 'expired', 'label' => '已过保', 'min' => 1, 'max' => $today - 1],
            ['value' => 'under_30', 'label' => '30天内', 'min' => $today, 'max' => $day30 - 1],
            ['value' => '31_60', 'label' => '31-60天', 'min' => $day30, 'max' => $day60 - 1],
            ['value' => '61_180', 'label' => '61-180天', 'min' => $day60, 'max' => $day180 - 1],
            ['value' => '181_300', 'label' => '181-300天', 'min' => $day180, 'max' => $day300 - 1],
            ['value' => 'over_300', 'label' => '300天以上', 'min' => $day300, 'max' => null],
        ];
    }
}
