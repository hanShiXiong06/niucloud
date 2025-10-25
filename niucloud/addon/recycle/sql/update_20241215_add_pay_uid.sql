-- 为回收订单表添加打款操作人员字段
-- 更新时间：2024-12-15
-- 功能：记录实际执行打款操作的人员ID

-- 检查字段是否存在，如果不存在则添加
SET @col_exists = 0;
SELECT COUNT(*)
INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'saas_recycle_order'
  AND COLUMN_NAME = 'pay_uid';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `saas_recycle_order` ADD COLUMN `pay_uid` int(11) NOT NULL DEFAULT 0 COMMENT ''打款操作人员ID'' AFTER `pay_time`',
    'SELECT ''Column pay_uid already exists'' as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加索引以提高查询性能
SET @index_exists = 0;
SELECT COUNT(*)
INTO @index_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'saas_recycle_order'
  AND INDEX_NAME = 'idx_pay_uid';

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `saas_recycle_order` ADD INDEX `idx_pay_uid` (`pay_uid`)',
    'SELECT ''Index idx_pay_uid already exists'' as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 