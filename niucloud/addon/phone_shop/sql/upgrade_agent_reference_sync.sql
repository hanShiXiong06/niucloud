-- Apply to the selected database. Replace {{prefix}} with its actual table prefix.
-- Idempotent: adds only missing columns/index; no business rows are changed.
SET @ref_table = '{{prefix}}phone_shop_agent';
SET SESSION group_concat_max_len = 8192;
SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', defs.col, '` ', defs.definition) ORDER BY defs.seq SEPARATOR ', ')
INTO @ref_columns
FROM (
    SELECT 1 AS seq, 'ref_sync_mode' AS col, 'varchar(16) NOT NULL DEFAULT ''realtime''' AS definition
    UNION ALL SELECT 2, 'ref_sync_interval', 'int NOT NULL DEFAULT 60'
    UNION ALL SELECT 3, 'ref_auto_create_category', 'tinyint NOT NULL DEFAULT 0'
    UNION ALL SELECT 4, 'ref_sync_next_time', 'int NOT NULL DEFAULT 0'
    UNION ALL SELECT 5, 'ref_sync_last_time', 'int NOT NULL DEFAULT 0'
    UNION ALL SELECT 6, 'ref_sync_status', 'varchar(20) NOT NULL DEFAULT ''idle'''
    UNION ALL SELECT 7, 'ref_sync_token', 'varchar(32) NOT NULL DEFAULT '''''
    UNION ALL SELECT 8, 'ref_sync_started_at', 'int NOT NULL DEFAULT 0'
    UNION ALL SELECT 9, 'ref_sync_result', 'text NULL'
) defs
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS c
    WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = @ref_table AND c.COLUMN_NAME = defs.col
);
SET @ref_ddl = IF(@ref_columns IS NULL, 'SELECT 1', CONCAT('ALTER TABLE `', @ref_table, '` ', @ref_columns));
PREPARE ref_stmt FROM @ref_ddl;
EXECUTE ref_stmt;
DEALLOCATE PREPARE ref_stmt;
SET @ref_ddl = IF(EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ref_table AND INDEX_NAME = 'idx_ref_sync_due'
), 'SELECT 1', CONCAT('ALTER TABLE `', @ref_table, '` ADD INDEX `idx_ref_sync_due` (`master_site_id`,`status`,`ref_sync_mode`,`ref_sync_next_time`)'));
PREPARE ref_stmt FROM @ref_ddl;
EXECUTE ref_stmt;
DEALLOCATE PREPARE ref_stmt;
