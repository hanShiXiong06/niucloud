<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\support;

final class PartyMatch
{
    public static function decide(string $phone, array $candidates, string $mappedNumber = ''): array
    {
        if ($mappedNumber !== '') return ['decision' => 'mapped', 'number' => $mappedNumber];
        $phone = self::normalize($phone);
        if ($phone === '') return ['decision' => 'create', 'reason' => '无有效手机号，创建独立业务往来单位，不合并会员'];
        $matches = [];
        foreach ($candidates as $candidate) {
            $number = trim((string)($candidate['number'] ?? ''));
            if ($number !== '' && self::normalize((string)($candidate['phone'] ?? '')) === $phone) $matches[$number] = true;
        }
        if (count($matches) > 1) return ['decision' => 'manual', 'reason' => '同一手机号匹配多个往来单位，请人工确认'];
        if (count($matches) === 1) return ['decision' => 'match', 'number' => (string)array_key_first($matches)];
        return ['decision' => 'create', 'reason' => '未匹配到往来单位'];
    }

    private static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s()-]+/', '', trim($phone));
        if (str_starts_with($phone, '+86')) $phone = substr($phone, 3);
        // Do not turn masked or alphabetic strings into an apparently valid phone number.
        return preg_match('/^1[3-9][0-9]{9}$/D', $phone) ? $phone : '';
    }
}
