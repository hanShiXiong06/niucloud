<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

final class MemberCardNumber
{
    public static function make(string $prefix): string
    {
        [$micro] = explode(' ', microtime());
        $tail = str_pad((string)(int)round((float)$micro * 1000000), 6, '0', STR_PAD_LEFT);
        return strtoupper($prefix) . date('YmdHis') . $tail . random_int(100, 999);
    }
}
