<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

final class TaskView
{
    public const STATUS_SQL = "CASE WHEN status='succeeded' AND COALESCE(external_doc_no,'')='' AND COALESCE(external_doc_id,'')='' THEN 'unverified' ELSE status END";

    public static function displayStatus(array $row): string
    {
        return ($row['status'] ?? '') === 'succeeded' && empty($row['external_doc_no']) && empty($row['external_doc_id'])
            ? 'unverified' : (string)($row['status'] ?? '');
    }

    /** 只有适配器明确表示没有发送的错误才能人工重试；超时、已有外部单号一律不能。 */
    public static function canRetry(array $row): bool
    {
        return empty($row['fact_key']) && ($row['status'] ?? '') === 'failed' && ($row['error_code'] ?? '') === 'MAPPING_MISSING'
            && empty($row['external_doc_no']) && empty($row['external_doc_id']);
    }

    public static function snapshot(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (preg_match('/secret|password|token|authorization|private.key|signature/i', (string)$key)) $value = '******';
            elseif (is_array($value)) $value = self::snapshot($value);
        }
        return $data;
    }
}
