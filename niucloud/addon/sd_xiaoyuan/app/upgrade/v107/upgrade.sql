SET FOREIGN_KEY_CHECKS = 0;

-- 添加表白墙类型字段（如果不存在）
SET @dbname = DATABASE();
SET @tablename = 'xiaoyuan_confession';
SET @columnname = 'type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE 
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `type` VARCHAR(20) NOT NULL DEFAULT ''ALL'' COMMENT ''类型:ALL-全部,CRUSH-暗恋,REAL-实名,FIND-寻人,WISH-祝福'' AFTER `status`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 更新已存在的空值或NULL值为默认值
UPDATE `xiaoyuan_confession` SET `type` = 'ALL' WHERE `type` = '' OR `type` IS NULL;

-- 创建包裹价格表（如果不存在）
CREATE TABLE IF NOT EXISTS `xiaoyuan_package_price` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `size` varchar(20) NOT NULL COMMENT '尺寸标识:small-小件,medium-中件,large-中大件,xlarge-大件',
  `name` varchar(50) NOT NULL COMMENT '尺寸名称',
  `description` varchar(255) DEFAULT NULL COMMENT '尺寸描述',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '价格',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0-禁用,1-启用',
  `create_time` int(11) DEFAULT NULL COMMENT '创建时间',
  `update_time` int(11) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='包裹价格表';


SET FOREIGN_KEY_CHECKS = 1;
