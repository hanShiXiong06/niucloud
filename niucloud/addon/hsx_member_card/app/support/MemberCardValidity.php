<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

use core\exception\CommonException;

final class MemberCardValidity
{
    /** @return array{valid_start_at:int,valid_end_at:int,activated_at:int} */
    public static function calculate(array $rule, int $issuedAt, ?int $firstUsedAt = null): array
    {
        $effectiveMode = (string)($rule['effective_mode'] ?? 'immediate');
        $validityMode = (string)($rule['validity_mode'] ?? 'permanent');
        if (!in_array($effectiveMode, ['immediate', 'first_use', 'fixed'], true)) {
            throw new CommonException('卡种生效方式不正确');
        }
        if (!in_array($validityMode, ['permanent', 'duration', 'fixed'], true)) {
            throw new CommonException('卡种有效期方式不正确');
        }

        $start = match ($effectiveMode) {
            'first_use' => max(0, (int)($firstUsedAt ?? 0)),
            'fixed' => max(0, (int)($rule['fixed_start_at'] ?? 0)),
            default => $issuedAt,
        };
        if ($effectiveMode === 'first_use' && $start <= 0) {
            return ['valid_start_at' => 0, 'valid_end_at' => 0, 'activated_at' => 0];
        }
        if ($start <= 0) throw new CommonException('卡种生效时间不正确');

        if ($validityMode === 'permanent') $end = 0;
        elseif ($validityMode === 'fixed') $end = max(0, (int)($rule['fixed_end_at'] ?? 0));
        else {
            $value = (int)($rule['duration_value'] ?? 0);
            $unit = (string)($rule['duration_unit'] ?? 'day');
            if ($value <= 0 || !in_array($unit, ['day', 'month'], true)) throw new CommonException('固定时长配置不正确');
            $end = $unit === 'month' ? strtotime('+' . $value . ' month', $start) : $start + $value * 86400;
        }
        if ($end > 0 && $end <= $start) throw new CommonException('失效时间必须晚于生效时间');
        return ['valid_start_at' => $start, 'valid_end_at' => $end, 'activated_at' => $start];
    }

    public static function isExpired(int $endAt, ?int $now = null): bool
    {
        return $endAt > 0 && $endAt < ($now ?? time());
    }
}
