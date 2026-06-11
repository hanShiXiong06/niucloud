<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use core\exception\CommonException;

final class ErpMoney
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

    public static function compare(mixed $left, mixed $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), 2);
    }
}
