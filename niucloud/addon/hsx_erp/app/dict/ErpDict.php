<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

class ErpDict
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOID = 'void';

    public const ASSET_IN_STOCK = 'in_stock';
    public const ASSET_SOLD = 'sold';
    public const ASSET_RETURNED = 'returned';

    public const TARGET_PAYABLE = 'payable';
    public const TARGET_RECEIVABLE = 'receivable';

    public const SETTLEMENT_PAYMENT = 'payment';
    public const SETTLEMENT_RECEIPT = 'receipt';
    public const SETTLEMENT_OFFSET = 'offset';

    public static function financeStatus(float $amount, float $settled): string
    {
        $amount = round($amount, 2);
        $settled = round($settled, 2);
        if ($settled <= 0) {
            return self::STATUS_PENDING;
        }
        if ($settled + 0.0001 >= $amount) {
            return self::STATUS_SETTLED;
        }
        return self::STATUS_PARTIAL;
    }
}
