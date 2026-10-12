<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\support;

final class PartyMatch
{
    public static function phone(string $phone): string
    {
        $phone = preg_replace('/[\s()-]/u', '', trim($phone));
        if (str_starts_with($phone, '+86')) $phone = substr($phone, 3);
        if (str_starts_with($phone, '0086')) $phone = substr($phone, 4);
        return preg_match('/^1[3-9]\d{9}$/D', $phone) ? $phone : '';
    }

    /** Input candidates are normalized, authorized, fully paginated data, never fuzzy search hits. */
    public static function decide(string $phone, array $candidates, bool $complete, ?string $mapped = null): array
    {
        if ($mapped !== null && $mapped !== '') return ['action' => 'mapped', 'number' => $mapped];
        if (trim($phone) === '') return ['action' => 'create', 'reason' => '没有手机号，使用本地客户ID生成稳定编码，不能按姓名合并'];
        $normalized = self::phone($phone);
        if ($normalized === '') return ['action' => 'manual', 'reason' => '手机号格式不完整或已脱敏，不能自动关联'];
        if (!$complete) return ['action' => 'manual', 'reason' => '客户查询或隐私字段解密不完整，不能判定不存在'];
        $matches = [];
        foreach ($candidates as $candidate) {
            if (empty($candidate['number']) || !empty($candidate['disabled'])) continue;
            foreach ($candidate['phones'] ?? [] as $value) {
                if (self::phone((string)$value) === $normalized) $matches[(string)$candidate['number']] = true;
            }
        }
        if (count($matches) > 1) return ['action' => 'manual', 'reason' => '同手机号对应多个客户，请人工确认'];
        if (count($matches) === 1) return ['action' => 'match', 'number' => (string)array_key_first($matches)];
        return ['action' => 'create', 'reason' => '完整查询未找到同手机号客户，按稳定编码创建'];
    }

    public static function decryptPhone(string $cipher, string $clientSecret): string
    {
        if (strlen($clientSecret) !== 32) throw new Problem('PRIVACY_KEY', '手机号解密密钥格式不符，请核对应用密钥');
        $raw = base64_decode($cipher, true);
        $value = $raw === false ? false : openssl_decrypt($raw, 'aes-256-cbc', $clientSecret, OPENSSL_RAW_DATA, '5e8y6w45ju8w9jq8');
        if ($value === false || self::phone($value) === '') throw new Problem('PRIVACY_DECRYPT', '无法核验完整手机号，请人工关联客户');
        return self::phone($value);
    }
}
