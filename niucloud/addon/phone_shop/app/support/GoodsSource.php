<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

/** 商品来源以 source 为准，is_proxy 为废弃标记，不能用于判断 ERP 归属。 */
final class GoodsSource
{
    public static function isLocal(int $siteId, array $goods): bool
    {
        // 空值、0、1 是商城已有的本站来源口径，与 ERP 库存对账保持一致。
        return $siteId > 0 && (int)($goods['site_id'] ?? 0) === $siteId
            && in_array(trim((string)($goods['source'] ?? '')), ['', '0', '1', (string)$siteId], true);
    }
}
