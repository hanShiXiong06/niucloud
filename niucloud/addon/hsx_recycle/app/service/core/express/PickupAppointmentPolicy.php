<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use core\exception\CommonException;

/** 门店营业窗口内的预约选项；只计算时间，不请求快递、不创建订单。 */
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
        if (self::minutes($result['end']) - self::minutes($result['start']) < 30 || self::minutes($result['end']) - self::minutes($result['cutoff']) < 30) {
            if ($strict) throw new CommonException('取件时间段须至少30分钟；当天预约截止时间须至少早于结束时间30分钟');
            return self::DEFAULTS;
        }
        return $result;
    }

    public static function resolve(array $schedule, ?\DateTimeImmutable $now = null, string $selected = '', bool $allowImmediate = false): array
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
        $immediate = $allowImmediate ? self::immediateWindow($schedule, $now) : '';
        $tomorrow = $immediate === '' && ($now >= $cutoff || $start >= $end);
        if ($tomorrow) {
            $date = $now->modify('+1 day')->format('Y-m-d');
            $start = new \DateTimeImmutable($date . ' ' . $schedule['start'], $zone);
            $end = new \DateTimeImmutable($date . ' ' . $schedule['end'], $zone);
        }
        $range = $start->format('H:i') . '-' . $end->format('H:i');
        $value = $immediate !== '' ? 'immediate' : $date . ' ' . $range;
        $changed = false;
        if (trim($selected) !== '') {
            try {
                self::validate($selected, $schedule, $now, $allowImmediate);
                $value = trim($selected);
            }
            catch (CommonException $e) { $changed = true; }
        }
        $options = [];
        foreach (['今天', '明天', '后天', '大后天'] as $offset => $label) {
            $day = $now->modify('+' . $offset . ' day')->format('Y-m-d');
            if ($offset === 0 && $tomorrow && substr($value, 0, 10) !== $day) continue;
            $dayStart = new \DateTimeImmutable($day . ' ' . $schedule['start'], $zone);
            $dayEnd = new \DateTimeImmutable($day . ' ' . $schedule['end'], $zone);
            $availableStart = $offset === 0 && $dayStart < $earliest ? $earliest : $dayStart;
            $slots = [];
            if ($offset === 0 && $immediate !== '') {
                $slots['immediate'] = ['value' => 'immediate', 'label' => '立即取件',
                    'text' => '立即取件 · 今天 ' . substr($immediate, 11),
                    'hint' => substr($immediate, 11) . '，提交后立即发起预约'];
            }
            $append = static function (string $slot, string $hint = '') use (&$slots, $day, $label): void {
                $slots[$slot] = ['value' => $slot, 'label' => substr($slot, 11), 'text' => $label . '（'
                    . substr($day, 5, 2) . '月' . substr($day, 8, 2) . '日）' . substr($slot, 11), 'hint' => $hint];
            };
            if ($dayEnd->getTimestamp() - $availableStart->getTimestamp() >= 1800) {
                $append($day . ' ' . $availableStart->format('H:i') . '-' . $dayEnd->format('H:i'), '此时段均可');
            }
            // 沿用门店配置的起止时间，按两小时拆分；末段不足两小时保留实际结束时间。
            for ($cursor = $dayStart; $cursor < $dayEnd; $cursor = $cursor->modify('+2 hours')) {
                $slotEnd = min($cursor->modify('+2 hours'), $dayEnd);
                if ($cursor < $availableStart || $slotEnd->getTimestamp() - $cursor->getTimestamp() < 1800) continue;
                $slot = $day . ' ' . $cursor->format('H:i') . '-' . $slotEnd->format('H:i');
                if (!isset($slots[$slot])) $append($slot);
            }
            // 客户之前选定且仍然有效的时间不因默认时段刷新而被悄悄替换。
            if (substr($value, 0, 10) === $day && !isset($slots[$value])) $append($value, '已选时段');
            $options[] = ['date' => $day, 'label' => $label, 'date_text' => substr($day, 5, 2) . '月' . substr($day, 8, 2) . '日', 'slots' => array_values($slots)];
        }
        $selectedText = '';
        foreach ($options as $option) foreach ($option['slots'] as $slot) if ($slot['value'] === $value) $selectedText = $slot['text'];
        return ['pickup_time' => $value, 'pickup_time_text' => $selectedText,
            'pickup_time_range' => $value === 'immediate' ? $immediate : $value,
            'pickup_time_changed' => $changed, 'pickup_time_options' => $options, 'pickup_server_time' => $now->getTimestamp()];
    }

    /** 未传时由后台安排；已传但过期/配置已改变的时段应刷新后确认，不擅自改约。 */
    public static function validate(string $value, array $schedule, ?\DateTimeImmutable $now = null, bool $allowImmediate = false): string
    {
        $zone = new \DateTimeZone('Asia/Shanghai');
        $now = ($now ?? new \DateTimeImmutable('now', $zone))->setTimezone($zone);
        $schedule = self::normalize($schedule, true);
        $value = trim($value);
        // 客户端没有提供时段（含空字符串）时，使用北京时间计算出的本站预约窗口。
        // 只兜底缺省值；明确传来的错误/过期时段仍需确认，避免静默改成另一天。
        if ($value === '') return self::resolve($schedule, $now, '', $allowImmediate)['pickup_time_range'];
        if ($value === 'immediate') {
            $range = $allowImmediate ? self::immediateWindow($schedule, $now) : '';
            if ($range === '') throw new CommonException('当前已不能立即取件，请刷新后选择可预约时段；尚未提交订单');
            return $range;
        }
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) ((?:[01]\d|2[0-3]):[0-5]\d)-((?:[01]\d|2[0-3]):[0-5]\d)$/D', $value, $m)
            || $m[1] < $now->format('Y-m-d') || $m[1] > $now->modify('+3 days')->format('Y-m-d')
            || !checkdate((int)substr($m[1], 5, 2), (int)substr($m[1], 8, 2), (int)substr($m[1], 0, 4))
            || $m[2] < $schedule['start'] || $m[3] > $schedule['end']
            || self::minutes($m[3]) - self::minutes($m[2]) < 30
            || ($m[1] === $now->format('Y-m-d') && $now->format('H:i') >= $schedule['cutoff'])
            || (new \DateTimeImmutable($m[1] . ' ' . $m[2], $zone))->format('Y-m-d H:i') !== $m[1] . ' ' . $m[2]
            || (new \DateTimeImmutable($m[1] . ' ' . $m[2], $zone)) <= $now) {
            throw new CommonException('取件时段已更新，请刷新下单页后确认新的时间；尚未提交订单');
        }
        return $value;
    }

    private static function minutes(string $time): int
    {
        return (int)substr($time, 0, 2) * 60 + (int)substr($time, 3, 2);
    }

    /** 动态意图只在本站营业窗口内有效；不把客户端预览时间当成固定预约。 */
    private static function immediateWindow(array $schedule, \DateTimeImmutable $now): string
    {
        $day = $now->format('Y-m-d');
        $zone = $now->getTimezone();
        $opening = new \DateTimeImmutable($day . ' ' . $schedule['start'], $zone);
        $closing = new \DateTimeImmutable($day . ' ' . $schedule['end'], $zone);
        $cutoff = new \DateTimeImmutable($day . ' ' . $schedule['cutoff'], $zone);
        if ($now < $opening || $now >= $cutoff) return '';
        $nextHour = $now->setTime((int)$now->format('H'), 0)->modify('+1 hour');
        $start = $now->setTime((int)$now->format('H'), (int)$now->format('i'));
        $end = $nextHour;
        if ($nextHour->getTimestamp() - $now->getTimestamp() <= 900) {
            $start = $nextHour;
            $end = $nextHour->modify('+1 hour');
        }
        $end = min($end, $closing);
        if ($end->getTimestamp() - max($start->getTimestamp(), $now->getTimestamp()) <= 900) return '';
        return $day . ' ' . $start->format('H:i') . '-' . $end->format('H:i');
    }
}
