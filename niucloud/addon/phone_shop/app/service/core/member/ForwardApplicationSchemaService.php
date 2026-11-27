<?php

namespace addon\phone_shop\app\service\core\member;

use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;
use think\facade\Db;

/** 老站点首次进入同行申请业务时，幂等补齐申请表。 */
class ForwardApplicationSchemaService
{
    private static bool $checked = false;

    public static function ensure(): void
    {
        if (self::$checked) return;
        // 交给 ORM 拼接实际前缀，避免 phone_ 等前缀与业务表名开头重合时被误判。
        $table = Db::name('phone_shop_forward_application')->getTable();
        if (!(new SchemaSyncService())->ensureTable($table)) {
            throw new \RuntimeException('同行申请表初始化失败，请核对插件 sql/install.sql，并在项目根目录执行 php addon/phone_shop/scripts/upgrade_forward_application.php --check');
        }
        self::$checked = true;
    }
}
