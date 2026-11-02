-- hsx_lead 1.1.0 -> 1.1.1: one owner field and one index, no business backfill.
-- Select the intended database in phpMyAdmin first. Back up the plugin tables.
-- Detect the actual prefix only when exactly one matching plugin table exists.
-- An absent/ambiguous table stops changes and reports the problem; no guessed prefix.
SET @lead_table = (
    SELECT IF(COUNT(*) = 1, MAX(TABLE_NAME), NULL)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND RIGHT(TABLE_NAME, CHAR_LENGTH('hsx_lead_lead')) = 'hsx_lead_lead'
);
SET @owner_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @lead_table AND COLUMN_NAME = 'owner_uid'
);
SET @ddl = IF(@lead_table IS NULL,
    'SELECT ''未执行：当前数据库没有唯一的hsx_lead_lead表，请核对数据库与表前缀'' AS message',
    IF(@owner_exists > 0, 'SELECT ''owner_uid已存在，保留原数据'' AS message',
        CONCAT('ALTER TABLE `', REPLACE(@lead_table, '`', '``'), '` ADD COLUMN `owner_uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Current responsible operator; 0 is unassigned, never public''')));
PREPARE lead_upgrade FROM @ddl;
EXECUTE lead_upgrade;
DEALLOCATE PREPARE lead_upgrade;

SET @owner_valid = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @lead_table AND COLUMN_NAME = 'owner_uid'
      AND COLUMN_TYPE REGEXP '^int(\\([0-9]+\\))? unsigned$'
      AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT = '0'
);
SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @lead_table AND INDEX_NAME = 'idx_site_owner_status'
);
SET @ddl = IF(@lead_table IS NULL OR @owner_valid <> 1,
    'SELECT ''索引未执行：目标表或owner_uid字段定义异常，请核对'' AS message',
    IF(@index_exists > 0, 'SELECT ''归属索引已存在，请核对下方列顺序；不覆盖自定义结构'' AS message',
        CONCAT('ALTER TABLE `', REPLACE(@lead_table, '`', '``'), '` ADD KEY `idx_site_owner_status` (`site_id`,`owner_uid`,`status`,`id`)')));
PREPARE lead_upgrade FROM @ddl;
EXECUTE lead_upgrade;
DEALLOCATE PREPARE lead_upgrade;

SELECT @lead_table AS target_table, @owner_valid AS owner_field_valid;
SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS index_columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @lead_table AND INDEX_NAME = 'idx_site_owner_status'
GROUP BY INDEX_NAME, NON_UNIQUE;
-- Expected: owner_field_valid=1, NON_UNIQUE=1,
-- index_columns=site_id,owner_uid,status,id. Existing records remain owner_uid=0.
-- Refresh hsx_lead menus/role permissions through NiuCloud after deploying code.
