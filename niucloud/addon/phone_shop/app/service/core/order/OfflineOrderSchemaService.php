<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;

class OfflineOrderSchemaService
{
    private static bool $checked = false;

    public static function ensure(): void
    {
        if (self::$checked) return;
        // 直接采用 ORM 当前连接解析出的完整表名，避免多数据库/动态前缀部署中
        // 静态配置前缀与模型实际使用前缀不一致。
        $table = (new OrderOfflineRecord())->getTable();
        if (!(new SchemaSyncService())->ensureTable($table)) {
            throw new \RuntimeException('线下订单责任记录表初始化失败：' . $table
                . '，请检查 phone_shop/sql/install.sql');
        }
        self::$checked = true;
    }
}
