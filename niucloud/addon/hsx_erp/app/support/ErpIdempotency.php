<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use core\exception\CommonException;

final class ErpIdempotency
{
    public const MAX_REQUEST_ID_LENGTH = 80;

    public static function normalize($value): string
    {
        $requestId = trim((string)$value);
        if ($requestId === '') {
            return '';
        }
        if (strlen($requestId) > self::MAX_REQUEST_ID_LENGTH) {
            throw new CommonException('request_id长度不能超过80个字符');
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $requestId)) {
            throw new CommonException('request_id格式不正确');
        }
        return $requestId;
    }

    public static function child(string $requestId, string $suffix): string
    {
        if ($requestId === '') {
            return '';
        }
        $tail = ':' . trim($suffix, ':');
        return substr($requestId, 0, self::MAX_REQUEST_ID_LENGTH - strlen($tail)) . $tail;
    }
}
