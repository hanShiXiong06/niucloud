<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

use core\exception\CommonException;

final class MemberCardIdempotency
{
    public static function normalize(mixed $value): string
    {
        $id = trim((string)$value);
        if ($id === '' || strlen($id) > 80 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $id)) {
            throw new CommonException('request_id缺失或格式不正确');
        }
        return $id;
    }

    public static function child(string $requestId, string $suffix): string
    {
        $tail = ':' . trim($suffix, ':');
        return substr(self::normalize($requestId), 0, 80 - strlen($tail)) . $tail;
    }

    public static function hash(array $payload): string
    {
        ksort($payload);
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }
}
