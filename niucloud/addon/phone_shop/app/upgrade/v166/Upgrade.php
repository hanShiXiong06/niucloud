<?php
namespace addon\phone_shop\app\upgrade\v166;

use think\facade\Db;

/** 可与手工 SQL 升级重复执行；不回填/伪造历史调价时间。 */
final class Upgrade
{
    public function handle(): void
    {
        $table = str_replace('`', '``', Db::name('phone_shop_goods')->getTable());
        $columns = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'price_changed_at'");
        if (!$columns) Db::execute("ALTER TABLE `{$table}` ADD COLUMN `price_changed_at` INT NOT NULL DEFAULT 0 COMMENT '最近销售价格实际变更时间，0表示无记录'");
        $indexes = Db::query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_goods_site_created'");
        if (!$indexes) Db::execute("ALTER TABLE `{$table}` ADD INDEX `idx_goods_site_created` (`site_id`, `status`, `create_time`)");
    }
}
