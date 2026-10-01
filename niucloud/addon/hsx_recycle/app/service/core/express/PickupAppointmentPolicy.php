<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use core\exception\CommonException;

/** 门店统一安排的每日预约窗口；只计算时间，不请求快递、不创建订单。 */
class PickupAppointmentPolicy
{
    public const DEFAULTS = ['start' => '09:00', 'end' => '18:00', 'cutoff' => '16:00'];

    public static function normalize(array $value, bool $strict = false): array
    {
        $result = [];
        foreach (self::DEFAULTS as $key => $default) {
            $time = $value[$key] ?? $default;
            if (!is_string($time) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time)) {
                if ($strict) throw new CommonException('取件时段请填写有效时间，例如 09:00');
                return self::DEFAULTS;
            }
            $result[$key] = $time;
        }
        if ($result['start'] >= $result['end'] || self::minutes($result['end']) - self::minutes($result['cutoff']) < 30) {
            if ($strict) throw new CommonException('取件结束时间须晚于开始时间；当天预约截止时间须至少早于结束时间30分钟');
            return self::DEFAULTS;
        }
        return $result;
    }

    public static function resolve(array $schedule, ?\DateTimeImmutable $now = null): array
    {
        $schedule = self::normalize($schedule, true);
        $zone = new \DateTimeZone('Asia/Shanghai');
        $now = ($now ?? new \DateTimeImmutable('now', $zone))->setTimezone($zone);
        $date = $now->format('Y-m-d');
        $start = new \DateTimeImmutable($date . ' ' . $schedule['start'], $zone);
        $end = new \DateTimeImmutable($date . ' ' . $schedule['end'], $zone);
        $cutoff = new \DateTimeImmutable($date . ' ' . $schedule['cutoff'], $zone);
        // 至少预留30分钟，向上对齐半小时，避免把已开始的时段提交给顺丰。
        $earliest = $now->setTimestamp((int)(ceil(($now->getTimestamp() + 1800) / 1800) * 1800));
        $start = $start > $earliest ? $start : $earliest;
        $tomorrow = $now >= $cutoff || $start >= $end;
        if ($tomorrow) {
            $date = $now->modify('+1 day')->format('Y-m-d');
            $start = new \DateTimeImmutable($date . ' ' . $schedule['start'], $zone);
            $end = new \DateTimeImmutable($date . ' ' . $schedule['end'], $zone);
        }
        $range = $start->format('H:i') . '-' . $end->format('H:i');
        return [
            'pickup_time' => $date . ' ' . $range,
            'pickup_time_text' => ($tomorrow ? '明天' : '今天') . '（' . $start->format('m月d日') . '）' . $range,
        ];
    }

    /** 未传时由后台安排；已传但过期/配置已改变的时段应刷新后确认，不擅自改约。 */
    public static function validate(string $value, array $schedule, ?\DateTimeImmutable $now = null): string
    {
        $zone = new \DateTimeZone('Asia/Shanghai');
        $now = ($now ?? new \DateTimeImmutable('now', $zone))->setTimezone($zone);
        $schedule = self::normalize($schedule, true);
        $expected = self::resolve($schedule, $now)['pickup_time'];
        $value = trim($value);
        // 客户端没有提供时段（含空字符串）时，使用北京时间计算出的本站预约窗口。
        // 只兜底缺省值；明确传来的错误/过期时段仍需确认，避免静默改成另一天。
        if ($value === '') return $expected;
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) ((?:[01]\d|2[0-3]):[0-5]\d)-((?:[01]\d|2[0-3]):[0-5]\d)$/D', $value, $m)
            || $m[1] !== substr($expected, 0, 10) || $m[2] < $schedule['start'] || $m[3] !== $schedule['end']
            || $m[2] > substr($expected, 11, 5) || $m[2] >= $m[3]
            || (new \DateTimeImmutable($m[1] . ' ' . $m[2], $zone)) <= $now) {
            throw new CommonException('取件时段已更新，请刷新下单页后确认新的时间；尚未提交订单');
        }
        return $value;
    }

    private static function minutes(string $time): int
    {
        return (int)substr($time, 0, 2) * 60 + (int)substr($time, 3, 2);
    }
}
