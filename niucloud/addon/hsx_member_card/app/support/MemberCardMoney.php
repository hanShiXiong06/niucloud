<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

use core\exception\CommonException;

final class MemberCardMoney
{
    public static function normalize(mixed $amount): string
    {
        $value = trim((string)$amount);
        if ($value === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new CommonException('金额格式不正确');
        }
        return bcadd($value, '0', 2);
    }

    public static function add(mixed $left, mixed $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), 2);
    }

    public static function subtract(mixed $left, mixed $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), 2);
    }

    public static function multiply(mixed $left, mixed $right): string
    {
        return bcmul(self::normalize($left), self::normalize($right), 2);
    }

    public static function divide(mixed $left, int $right): string
    {
        if ($right <= 0) throw new CommonException('金额分摊除数必须大于0');
        return bcdiv(self::normalize($left), (string)$right, 2);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), 2);
    }
}
