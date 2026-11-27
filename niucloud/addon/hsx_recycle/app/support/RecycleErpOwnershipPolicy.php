<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\support;

/** 纯责任判定：安装/开关变化不得把已有 ERP 账款重新变成本地付款责任。 */
final class RecycleErpOwnershipPolicy
{
    public static function resolve(array $config, int $createdAt, string $recordedOwner, array $erpEvidence, bool $lookupAvailable): string
    {
        if (!$lookupAvailable || !empty($erpEvidence['ambiguous']) || !empty($erpEvidence['error'])
            || !empty($erpEvidence['lookup_failed']) || in_array((string)($erpEvidence['status'] ?? ''), ['failed', 'unknown'], true)) {
            return 'unknown';
        }
        $recordedOwner = trim($recordedOwner);
        if ($recordedOwner !== '' && !self::isMode($recordedOwner)) return 'unknown';

        $hasErpRecord = false;
        foreach (['has_asset', 'has_payable', 'has_settlement', 'has_erp_record'] as $field) {
            if (in_array($erpEvidence[$field] ?? false, [true, 1, '1'], true)) $hasErpRecord = true;
        }
        foreach (['asset_id', 'payable_id', 'settlement_id'] as $field) {
            if ((int)($erpEvidence[$field] ?? 0) > 0) $hasErpRecord = true;
        }
        if ($hasErpRecord) return $recordedOwner === 'local' ? 'unknown' : 'self_erp';
        if ($recordedOwner !== '') return $recordedOwner;

        $mode = (string)($config['mode'] ?? '');
        if (!self::isMode($mode)) return 'unknown';
        $configured = array_key_exists('configured', $config)
            ? (bool)$config['configured']
            : (array_key_exists('initial_mode', $config) || array_key_exists('history', $config));
        if (!$configured) return $mode;

        $initialMode = (string)($config['initial_mode'] ?? '');
        $history = $config['history'] ?? null;
        if (!self::isMode($initialMode) || !is_array($history)) return 'unknown';
        if ($history === []) return $initialMode === $mode ? $initialMode : 'unknown';
        if ($createdAt <= 0) return 'unknown';

        $owner = $initialMode;
        $lastAt = 0;
        $lastMode = $initialMode;
        foreach ($history as $entry) {
            if (!is_array($entry)) return 'unknown';
            $at = $entry['at'] ?? null;
            $nextMode = (string)($entry['mode'] ?? '');
            if (!is_int($at) || $at <= $lastAt || !self::isMode($nextMode)) return 'unknown';
            if ($createdAt >= $at) $owner = $nextMode;
            $lastAt = $at;
            $lastMode = $nextMode;
        }
        return $lastMode === $mode ? $owner : 'unknown';
    }

    private static function isMode(string $mode): bool
    {
        return in_array($mode, ['local', 'self_erp'], true);
    }
}
