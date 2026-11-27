<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use core\exception\CommonException;

/** Stable business identities, independent of price, provider, name or display rank. */
final class MembershipProductIdentity
{
    public static function forCycle(int $site, int $level, string $cycle): string
    {
        if ($site <= 0 || $level <= 0 || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,23}$/D', $cycle))throw new CommonException('会员套餐标识信息无效');
        $readable = 's'.base_convert((string) $site, 10, 36).'_l'.base_convert((string) $level, 10, 36).'_'.$cycle;
        if (strlen($readable) <= 20 && preg_match('/^[A-Za-z0-9_]+$/D', $readable))return $readable;
        return 'p'.substr(hash('sha256', json_encode(['v' => 1, 'site' => $site, 'level' => $level, 'cycle' => $cycle], JSON_THROW_ON_ERROR)), 0, 19);
    }

    /** A real frozen quotation is an independent business identity, not another price editor. */
    public static function forOrder(int $site, int $order): string
    {
        if ($site <= 0 || $order <= 0)throw new CommonException('会员订单标识无效');
        return 'q'.substr(hash('sha256', json_encode(['v' => 1, 'site' => $site, 'order' => $order], JSON_THROW_ON_ERROR)), 0, 19);
    }
}
