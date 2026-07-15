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

    /**
     * 数据库幂等键使用可空唯一索引：没有请求号时必须写 NULL，不能写空字符串。
     * MySQL 允许唯一索引中存在多个 NULL，但同一站点只能存在一个空字符串。
     */
    public static function nullable($value): ?string
    {
        $requestId = self::normalize($value);
        return $requestId === '' ? null : $requestId;
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
