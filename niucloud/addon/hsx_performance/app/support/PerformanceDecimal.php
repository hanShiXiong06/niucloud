<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\support;

use InvalidArgumentException;

final class PerformanceDecimal
{
    public static function normalize($value, int $scale, bool $allowNegative = true): string
    {
        $raw = trim((string)$value);
        if ($raw === '') $raw = '0';
        if (!preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('数值格式无效');
        }

        $negative = $raw[0] === '-';
        if (($raw[0] ?? '') === '-' || ($raw[0] ?? '') === '+') $raw = substr($raw, 1);
        [$integer, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $integer = ltrim($integer, '0');
        if ($integer === '') $integer = '0';
        if (strlen($fraction) > $scale && trim(substr($fraction, $scale), '0') !== '') {
            throw new InvalidArgumentException('数值小数位超出允许精度');
        }
        $fraction = $scale > 0 ? str_pad(substr($fraction, 0, $scale), $scale, '0') : '';
        $zero = $integer === '0' && ($fraction === '' || trim($fraction, '0') === '');
        if ($negative && !$allowNegative && !$zero) {
            throw new InvalidArgumentException('数值不能为负数');
        }
        return ($negative && !$zero ? '-' : '') . $integer . ($scale > 0 ? '.' . $fraction : '');
    }

    public static function signed($value, int $scale, int $direction): string
    {
        $normalized = self::normalize($value, $scale);
        $absolute = ltrim($normalized, '+-');
        $isZero = self::isZero($absolute);
        return $direction < 0 && !$isZero ? '-' . $absolute : $absolute;
    }

    public static function absolute($value, int $scale): string
    {
        return ltrim(self::normalize($value, $scale), '+-');
    }

    public static function isZero(string $value): bool
    {
        return trim(str_replace('.', '', ltrim($value, '+-')), '0') === '';
    }

    public static function subtract($left, $right, int $scale): string
    {
        [$leftNegative, $leftDigits] = self::scaledDigits(self::normalize($left, $scale), $scale);
        [$rightNegative, $rightDigits] = self::scaledDigits(self::normalize($right, $scale), $scale);
        $rightNegative = !$rightNegative;
        if ($leftNegative === $rightNegative) {
            return self::formatDigits(self::addDigits($leftDigits, $rightDigits), $scale, $leftNegative);
        }
        $compare = self::compareDigits($leftDigits, $rightDigits);
        if ($compare === 0) return self::formatDigits('0', $scale, false);
        if ($compare > 0) {
            return self::formatDigits(self::subtractDigits($leftDigits, $rightDigits), $scale, $leftNegative);
        }
        return self::formatDigits(self::subtractDigits($rightDigits, $leftDigits), $scale, $rightNegative);
    }

    private static function scaledDigits(string $value, int $scale): array
    {
        $negative = ($value[0] ?? '') === '-';
        $digits = str_replace('.', '', ltrim($value, '+-'));
        $digits = ltrim($digits, '0');
        return [$negative, $digits === '' ? '0' : $digits];
    }

    private static function addDigits(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';
        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = ($leftIndex >= 0 ? (int)$left[$leftIndex--] : 0)
                + ($rightIndex >= 0 ? (int)$right[$rightIndex--] : 0)
                + $carry;
            $result = (string)($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }
        return ltrim($result, '0') ?: '0';
    }

    private static function subtractDigits(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $borrow = 0;
        $result = '';
        while ($leftIndex >= 0) {
            $digit = (int)$left[$leftIndex--] - $borrow - ($rightIndex >= 0 ? (int)$right[$rightIndex--] : 0);
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = (string)$digit . $result;
        }
        return ltrim($result, '0') ?: '0';
    }

    private static function compareDigits(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';
        if (strlen($left) !== strlen($right)) return strlen($left) <=> strlen($right);
        return strcmp($left, $right);
    }

    private static function formatDigits(string $digits, int $scale, bool $negative): string
    {
        $digits = ltrim($digits, '0') ?: '0';
        if ($scale > 0) {
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            $value = substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
        } else {
            $value = $digits;
        }
        return $negative && !self::isZero($value) ? '-' . $value : $value;
    }
}
