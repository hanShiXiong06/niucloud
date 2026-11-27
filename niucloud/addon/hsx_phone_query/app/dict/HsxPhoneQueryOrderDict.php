<?php

namespace addon\hsx_phone_query\app\dict;

/**
 * 手机查询订单字典
 */
class HsxPhoneQueryOrderDict
{
    public const TRADE_TYPE = 'hsx_phone_query';

    public const PAY_TYPE_MONEY = 'money';
    public const PAY_TYPE_POINT = 'point';

    public const WAIT_PAY = 0;
    public const PAID = 1;
    public const QUERYING = 2;
    public const SUCCESS = 3;
    public const FAIL = -1;

    public static function getStatus(): array
    {
        return [
            self::WAIT_PAY => '待支付',
            self::PAID => '已支付',
            self::QUERYING => '查询中',
            self::SUCCESS => '查询成功',
            self::FAIL => '查询失败',
        ];
    }
}
