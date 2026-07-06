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
    public const ASSET_VOID = 'void';

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

    public static function lists(): array
    {
        return [
            'asset_status' => [
                ['value' => self::ASSET_IN_STOCK, 'label' => '在库', 'type' => 'success', 'filterable' => true],
                ['value' => self::ASSET_SOLD, 'label' => '已售', 'type' => 'primary', 'filterable' => true],
                ['value' => self::ASSET_RETURNED, 'label' => '已退', 'type' => 'warning', 'filterable' => true],
                ['value' => self::ASSET_VOID, 'label' => '已作废', 'type' => 'info', 'filterable' => true],
            ],
            'finance_status' => [
                ['value' => self::STATUS_PENDING, 'label' => '待结算', 'type' => 'warning', 'filterable' => true],
                ['value' => self::STATUS_PARTIAL, 'label' => '部分结算', 'type' => 'primary', 'filterable' => true],
                ['value' => self::STATUS_SETTLED, 'label' => '已结清', 'type' => 'success', 'filterable' => true],
                ['value' => self::STATUS_VOID, 'label' => '已作废', 'type' => 'info', 'filterable' => true],
            ],
            'return_status' => [
                ['value' => self::STATUS_PENDING, 'label' => '待财务确认', 'type' => 'warning', 'filterable' => true],
                ['value' => 'confirmed', 'label' => '已确认', 'type' => 'success', 'filterable' => true],
                ['value' => 'cancelled', 'label' => '已撤销', 'type' => 'info', 'filterable' => true],
            ],
            'refurbish_status' => [
                ['value' => 'none', 'label' => '无需整备', 'type' => 'info', 'filterable' => true],
                ['value' => 'pending', 'label' => '待整备', 'type' => 'warning', 'filterable' => true],
                ['value' => 'processing', 'label' => '整备中', 'type' => 'primary', 'filterable' => true],
                ['value' => 'done', 'label' => '整备完成', 'type' => 'success', 'filterable' => true],
            ],
            'sale_target' => [
                ['value' => 'unset', 'label' => '去向未定', 'type' => 'info', 'filterable' => true],
                ['value' => 'peer', 'label' => '卖同行', 'type' => 'primary', 'filterable' => true],
                ['value' => 'mall', 'label' => '上商城', 'type' => 'primary', 'filterable' => true],
            ],
            'listing_status' => [
                ['value' => 'none', 'label' => '无需上架', 'type' => 'info', 'filterable' => true],
                ['value' => 'need_photo', 'label' => '待拍照', 'type' => 'warning', 'filterable' => true],
                ['value' => 'need_price', 'label' => '待定价', 'type' => 'warning', 'filterable' => true],
                ['value' => 'ready', 'label' => '可上架', 'type' => 'primary', 'filterable' => true],
                ['value' => 'listed', 'label' => '已上架', 'type' => 'success', 'filterable' => true],
            ],
        ];
    }
}
