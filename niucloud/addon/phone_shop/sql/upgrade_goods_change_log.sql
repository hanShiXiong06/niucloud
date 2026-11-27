-- phone_shop v1.6.6 手工覆盖升级：先备份，选中目标数据库，核对下面的表前缀。
-- 新增 1 个字段 price_changed_at、1 张修改日志表及查询索引。
-- 最新上架使用原 create_time；不增加 listed_at，不回填历史调价/操作记录。
-- 先执行 SQL，再覆盖插件后端；后台和销售端均需重新打包。
-- 可重复执行，不删除商品/订单/财务数据。
SET @phone_shop_prefix = 'saas_';
SET @phone_shop_table = CONCAT(@phone_shop_prefix, 'phone_shop_goods');
SET @phone_shop_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @phone_shop_table AND COLUMN_NAME = 'price_changed_at'),
    'SELECT ''price_changed_at already exists'' AS result',
    CONCAT('ALTER TABLE `', REPLACE(@phone_shop_table, '`', '``'), '` ADD COLUMN `price_changed_at` INT NOT NULL DEFAULT 0 COMMENT ''最近销售价格实际变更时间，0表示无记录''')
);
PREPARE phone_shop_stmt FROM @phone_shop_ddl;
EXECUTE phone_shop_stmt;
DEALLOCATE PREPARE phone_shop_stmt;
SET @phone_shop_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @phone_shop_table AND INDEX_NAME = 'idx_goods_site_created'),
    'SELECT ''idx_goods_site_created already exists'' AS result',
    CONCAT('ALTER TABLE `', REPLACE(@phone_shop_table, '`', '``'), '` ADD INDEX `idx_goods_site_created` (`site_id`, `status`, `create_time`)')
);
PREPARE phone_shop_stmt FROM @phone_shop_ddl;
EXECUTE phone_shop_stmt;
DEALLOCATE PREPARE phone_shop_stmt;
SET @phone_shop_ddl = CONCAT('CREATE TABLE IF NOT EXISTS `', REPLACE(@phone_shop_prefix, '`', '``'), 'phone_shop_goods_change_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点'',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT ''商品'',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT ''操作人，0为系统任务'',
  `operator_name` varchar(100) NOT NULL DEFAULT '''' COMMENT ''操作人名称快照'',
  `source` varchar(32) NOT NULL DEFAULT '''' COMMENT ''修改来源'',
  `changes` longtext NOT NULL COMMENT ''实际修改前后值 JSON'',
  `create_time` int NOT NULL DEFAULT 0 COMMENT ''操作时间'',
  PRIMARY KEY (`id`),
  KEY `idx_site_goods_log` (`site_id`, `goods_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT=''二手机商城商品修改记录'';');
PREPARE phone_shop_stmt FROM @phone_shop_ddl;
EXECUTE phone_shop_stmt;
DEALLOCATE PREPARE phone_shop_stmt;
