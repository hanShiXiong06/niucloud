<?php

namespace addon\phone_shop\app\service\core\member;

use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;

/** 老站点首次进入同行申请业务时，幂等补齐申请表。 */
class ForwardApplicationSchemaService
{
    private static bool $checked = false;

    public static function ensure(): void
    {
        if (self::$checked) return;
        if (!(new SchemaSyncService())->ensureTable('phone_shop_forward_application')) {
            throw new \RuntimeException('同行转发申请表自动初始化失败，请检查插件 install.sql');
        }
        self::$checked = true;
    }
}
