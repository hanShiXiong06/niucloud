<?php
declare(strict_types=1);

namespace addon\recycle\app\dict\quotation;

/**
 * 报价 2.0 字典
 */
class QuotationV2Dict
{
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    const FOLLOW_CUSTOM = 0;
    const FOLLOW_CRAWLER = 1;

    const FIELD_TYPE_PRICE = 'price';
    const FIELD_TYPE_ADJUSTMENT = 'adjustment';
    const FIELD_TYPE_NOTE = 'note';
    const FIELD_TYPE_REMARK = 'remark';

    const SYNC_STATUS_PENDING = 0;
    const SYNC_STATUS_SUCCESS = 1;
    const SYNC_STATUS_FAILED = 2;

    public static function getFieldTypeName(string $type): string
    {
        $map = [
            self::FIELD_TYPE_PRICE => '价格项',
            self::FIELD_TYPE_ADJUSTMENT => '价格调整项',
            self::FIELD_TYPE_NOTE => '附加说明项',
            self::FIELD_TYPE_REMARK => '报价说明',
        ];

        return $map[$type] ?? '未知字段';
    }
}
