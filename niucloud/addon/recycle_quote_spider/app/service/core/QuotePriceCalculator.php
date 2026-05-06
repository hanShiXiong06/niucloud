<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\core;

class QuotePriceCalculator
{
    public const ADJUST_NONE = 0;
    public const ADJUST_FIXED = 1;
    public const ADJUST_RATIO = 2;
    public const ADJUST_OVERRIDE = 3;

    public function calculateList(array $prices, array $rule): array
    {
        $result = [];
        foreach ($prices as $key => $value) {
            $result[$key] = $this->calculateValue($value, $rule);
        }
        return $result;
    }

    public function calculateValue($value, array $rule)
    {
        if (!$this->isNumericPrice($value)) {
            return $value;
        }

        $price = (float)$value;
        $type = (int)($rule['adjust_type'] ?? self::ADJUST_NONE);
        $adjustValue = (float)($rule['adjust_value'] ?? 0);
        $ratio = (float)($rule['adjust_ratio'] ?? 1);

        if ($type === self::ADJUST_FIXED) {
            $price += $adjustValue;
        } elseif ($type === self::ADJUST_RATIO) {
            $price *= $ratio > 0 ? $ratio : 1;
        } elseif ($type === self::ADJUST_OVERRIDE) {
            $price = $adjustValue;
        }

        $price = $this->roundValue($price, (string)($rule['round_mode'] ?? 'round'));
        return is_int($value) ? (int)$price : $price;
    }

    public function isNumericPrice($value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        return (bool)preg_match('/^-?\d+(\.\d+)?$/', $value);
    }

    private function roundValue(float $value, string $mode): int
    {
        if ($mode === 'floor') {
            return (int)floor($value);
        }
        if ($mode === 'ceil') {
            return (int)ceil($value);
        }
        return (int)round($value);
    }
}
