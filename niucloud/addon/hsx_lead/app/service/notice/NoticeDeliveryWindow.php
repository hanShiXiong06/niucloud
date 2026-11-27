<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

use core\exception\CommonException;

/** Site rule only. Persisted notification tasks retain their own due/expiry facts. */
final class NoticeDeliveryWindow
{
    public static function defaults(): array
    {
        return ['enabled' => false, 'start' => '22:00', 'end' => '08:00', 'timezone' => 'Asia/Shanghai'];
    }
    public static function normalize($input): array
    {
        if (!is_array($input) || array_diff(array_keys($input), array_keys(self::defaults())))throw new CommonException('通知时段配置格式不正确');
        $out = array_replace(self::defaults(), $input);
        if (!is_bool($out['enabled']) || $out['timezone'] !== 'Asia/Shanghai')throw new CommonException('通知时段使用北京时间，请核对开关与时区');
        foreach (['start', 'end'] as $key)if (!is_string($out[$key])
            || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $out[$key]))throw new CommonException('通知时段请使用有效的小时与分钟');
        if ($out['start'] === $out['end'])throw new CommonException('免打扰开始与结束时间不能相同');
        return $out;
    }
    /** Returns now when open, otherwise the next open instant; independent of the PHP host timezone. */
    public static function nextAllowed(array $policy, ?int $now = null): int
    {
        $now = $now ?? time();
        $rule = self::normalize($policy['quiet_hours'] ?? self::defaults());
        if (!$rule['enabled'])return $now;
        $local = (new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone('Asia/Shanghai'));
        $minute = (int) $local->format('H') * 60 + (int) $local->format('i');
        $minutes = static fn (string $value): int => (int) substr($value, 0, 2) * 60 + (int) substr($value, 3, 2);
        $start = $minutes($rule['start']);
        $end = $minutes($rule['end']);
        $blocked = $start > $end ? ($minute >= $start || $minute < $end) : ($minute >= $start && $minute < $end);
        if (!$blocked)return $now;
        $date = $start > $end && $minute >= $start ? $local->modify('+1 day') : $local;
        return $date->setTime(intdiv($end, 60), $end % 60, 0)->getTimestamp();
    }
    public static function assertOpen(int $site, ?int $now = null): void
    {
        $now = $now ?? time();
        $next = self::nextAllowed(AccountNoticePolicyService::raw($site), $now);
        if ($next > $now)throw new NoticeDeliveryDeferredException($next);
    }
    public static function message(int $until): string
    {
        return '免打扰时段，等待北京时间'.(new \DateTimeImmutable('@'.$until))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('m-d H:i').'后重新核对发送';
    }
}
