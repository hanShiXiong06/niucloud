<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\support;

/**
 * 报价源的数字 ID 可能随每日版本变化，业务身份必须由稳定字段生成。
 */
final class QuoteSyncIdentity
{
    public static function itemKey(array $data): string
    {
        $sourceUrlId = trim((string)($data['url'] ?? $data['source_url_id'] ?? ''));
        if ($sourceUrlId === '') {
            return mb_substr(trim((string)($data['id'] ?? $data['source_item_id'] ?? '')), 0, 64);
        }
        return 'quote_' . md5($sourceUrlId . '|' . self::itemSignature($data));
    }

    public static function itemSignature(array $data): string
    {
        return implode('|', [
            self::normalize($data['brand'] ?? ''),
            self::normalize($data['mobile_name'] ?? $data['name'] ?? ''),
            self::normalize($data['parent_name'] ?? ''),
            self::normalize($data['type'] ?? $data['quote_type'] ?? ''),
        ]);
    }

    public static function rowKey(array $row, int $index = 0): string
    {
        $signature = self::rowSignature($row);
        if ($signature !== '|') {
            return 'model_' . md5($signature);
        }

        $sourceRowId = trim((string)($row['id'] ?? $row['source_row_id'] ?? ''));
        if ($sourceRowId !== '') {
            return mb_substr($sourceRowId . '#' . md5((string)$index), 0, 64);
        }
        return 'row_' . md5(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '|' . $index);
    }

    public static function rowSignature(array $row): string
    {
        return self::normalize($row['tab'] ?? '') . '|' . self::normalize($row['mobile_name'] ?? $row['model_name'] ?? $row['name'] ?? '');
    }

    private static function normalize($value): string
    {
        $text = preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', trim((string)$value))) ?: '';
        return mb_strtolower($text, 'UTF-8');
    }
}
