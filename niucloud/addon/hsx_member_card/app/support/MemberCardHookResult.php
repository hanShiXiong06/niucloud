<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

use core\exception\CommonException;

final class MemberCardHookResult
{
    public static function first(mixed $results, string $capability): array
    {
        if (is_array($results) && isset($results['consumer'])) return $results;
        foreach ((array)$results as $result) {
            if (is_array($result) && ($result['consumer'] ?? '') === 'hsx_erp') return $result;
        }
        throw new CommonException('ERP未提供' . $capability . '能力，请确认ERP插件已安装并启用');
    }
}
