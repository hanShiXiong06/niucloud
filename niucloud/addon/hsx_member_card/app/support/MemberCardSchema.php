<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

use think\facade\Db;

/**
 * 会员卡插件增量结构迁移。
 *
 * 只允许由插件 install/upgrade 生命周期调用，业务请求不得执行 DDL。
 */
final class MemberCardSchema
{
    public static function migrate(): void
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $columns = [
            $prefix . 'member_card_product_item' => [
                'binding_mode' => "`binding_mode` varchar(20) NOT NULL DEFAULT 'member' COMMENT 'member/imei/model' AFTER `item_name`",
                'consumable_code' => "`consumable_code` varchar(60) NOT NULL DEFAULT '' COMMENT '耗材稳定编码' AFTER `recognition_amount`",
                'consumable_name' => "`consumable_name` varchar(100) NOT NULL DEFAULT '' COMMENT '默认耗材名称' AFTER `consumable_code`",
                'consumable_unit' => "`consumable_unit` varchar(20) NOT NULL DEFAULT '张' COMMENT '耗材单位' AFTER `consumable_name`",
                'standard_consumable_qty' => "`standard_consumable_qty` decimal(12,3) NOT NULL DEFAULT 1.000 COMMENT '单次核销标准耗材数量' AFTER `consumable_unit`",
            ],
            $prefix . 'member_card_card_item' => [
                'binding_mode' => "`binding_mode` varchar(20) NOT NULL DEFAULT 'member' COMMENT 'member/imei/model' AFTER `item_name`",
                'bound_imei' => "`bound_imei` varchar(40) NOT NULL DEFAULT '' AFTER `binding_mode`",
                'bound_model' => "`bound_model` varchar(100) NOT NULL DEFAULT '' AFTER `bound_imei`",
                'consumable_code' => "`consumable_code` varchar(60) NOT NULL DEFAULT '' AFTER `recognized_amount`",
                'consumable_name' => "`consumable_name` varchar(100) NOT NULL DEFAULT '' AFTER `consumable_code`",
                'consumable_unit' => "`consumable_unit` varchar(20) NOT NULL DEFAULT '张' AFTER `consumable_name`",
                'standard_consumable_qty' => "`standard_consumable_qty` decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `consumable_unit`",
            ],
            $prefix . 'member_card_redemption' => [
                'binding_mode' => "`binding_mode` varchar(20) NOT NULL DEFAULT 'member' AFTER `item_name`",
                'service_imei' => "`service_imei` varchar(40) NOT NULL DEFAULT '' AFTER `binding_mode`",
                'service_model' => "`service_model` varchar(100) NOT NULL DEFAULT '' AFTER `service_imei`",
                'inventory_mode' => "`inventory_mode` varchar(20) NOT NULL DEFAULT 'none' COMMENT 'none/auto/strict' AFTER `recognized_amount`",
                'consumable_code' => "`consumable_code` varchar(60) NOT NULL DEFAULT '' AFTER `inventory_mode`",
                'consumable_name' => "`consumable_name` varchar(100) NOT NULL DEFAULT '' AFTER `consumable_code`",
                'consumable_unit' => "`consumable_unit` varchar(20) NOT NULL DEFAULT '张' AFTER `consumable_name`",
                'standard_consumable_qty' => "`standard_consumable_qty` decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `consumable_unit`",
                'actual_consumable_qty' => "`actual_consumable_qty` decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `standard_consumable_qty`",
                'loss_consumable_qty' => "`loss_consumable_qty` decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `actual_consumable_qty`",
                'inventory_status' => "`inventory_status` varchar(20) NOT NULL DEFAULT 'not_managed' AFTER `loss_consumable_qty`",
                'inventory_product_id' => "`inventory_product_id` int NOT NULL DEFAULT 0 AFTER `inventory_status`",
                'inventory_warehouse_id' => "`inventory_warehouse_id` int NOT NULL DEFAULT 0 AFTER `inventory_product_id`",
                'inventory_warehouse_name' => "`inventory_warehouse_name` varchar(100) NOT NULL DEFAULT '' AFTER `inventory_warehouse_id`",
                'inventory_location_id' => "`inventory_location_id` int NOT NULL DEFAULT 0 AFTER `inventory_warehouse_name`",
                'inventory_location_name' => "`inventory_location_name` varchar(100) NOT NULL DEFAULT '' AFTER `inventory_location_id`",
                'inventory_stock_before' => "`inventory_stock_before` decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `inventory_location_name`",
                'inventory_stock_after' => "`inventory_stock_after` decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `inventory_stock_before`",
                'inventory_message' => "`inventory_message` varchar(255) NOT NULL DEFAULT '' AFTER `inventory_stock_after`",
            ],
        ];

        foreach ($columns as $table => $definitions) {
            if (!self::hasTable($table)) continue;
            foreach ($definitions as $column => $definition) {
                if (!self::hasColumn($table, $column)) {
                    Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
                }
            }
        }

        $redemptionTable = $prefix . 'member_card_redemption';
        if (self::hasTable($redemptionTable) && !self::hasIndex($redemptionTable, 'idx_site_service_imei')) {
            Db::execute("ALTER TABLE `{$redemptionTable}` ADD KEY `idx_site_service_imei` (`site_id`,`service_imei`)");
        }
    }

    private static function hasTable(string $table): bool
    {
        return Db::query("SHOW TABLES LIKE '" . addslashes($table) . "'") !== [];
    }

    private static function hasColumn(string $table, string $column): bool
    {
        return Db::query("SHOW COLUMNS FROM `{$table}` LIKE '" . addslashes($column) . "'") !== [];
    }

    private static function hasIndex(string $table, string $index): bool
    {
        return Db::query("SHOW INDEX FROM `{$table}` WHERE `Key_name` = '" . addslashes($index) . "'") !== [];
    }
}
