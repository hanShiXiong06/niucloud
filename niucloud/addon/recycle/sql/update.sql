-- 为设备表添加分类字段
ALTER TABLE `{{prefix}}recycle_device` ADD COLUMN `category_id` int NOT NULL DEFAULT '1' COMMENT '设备分类ID' AFTER `model`;

-- 添加分类字段索引
ALTER TABLE `{{prefix}}recycle_device` ADD KEY `idx_category_id` (`category_id`);

-- 创建统计表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stats_daily` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_type` varchar(20) NOT NULL DEFAULT '' COMMENT '用户类型：checker(质检员),pricer(估价员),admin(管理员)',
  `stat_date` date NOT NULL COMMENT '统计日期',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '设备分类ID，0表示全部分类',
  `check_count` int NOT NULL DEFAULT '0' COMMENT '质检数量',
  `price_count` int NOT NULL DEFAULT '0' COMMENT '定价数量',
  `recycle_count` int NOT NULL DEFAULT '0' COMMENT '回收数量',
  `return_count` int NOT NULL DEFAULT '0' COMMENT '退回数量',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date_category` (`site_id`,`user_id`,`stat_date`,`category_id`),
  KEY `idx_site_user_type` (`site_id`,`user_type`),
  KEY `idx_stat_date` (`stat_date`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='回收业务日统计表';

-- 创建月统计表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stats_monthly` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_type` varchar(20) NOT NULL DEFAULT '' COMMENT '用户类型：checker(质检员),pricer(估价员),admin(管理员)',
  `stat_month` varchar(7) NOT NULL COMMENT '统计月份 YYYY-MM',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '设备分类ID，0表示全部分类',
  `check_count` int NOT NULL DEFAULT '0' COMMENT '质检数量',
  `price_count` int NOT NULL DEFAULT '0' COMMENT '定价数量',
  `recycle_count` int NOT NULL DEFAULT '0' COMMENT '回收数量',
  `return_count` int NOT NULL DEFAULT '0' COMMENT '退回数量',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_month_category` (`site_id`,`user_id`,`stat_month`,`category_id`),
  KEY `idx_site_user_type` (`site_id`,`user_type`),
  KEY `idx_stat_month` (`stat_month`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='回收业务月统计表';

-- 创建年统计表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stats_yearly` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_type` varchar(20) NOT NULL DEFAULT '' COMMENT '用户类型：checker(质检员),pricer(估价员),admin(管理员)',
  `stat_year` int NOT NULL COMMENT '统计年份',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '设备分类ID，0表示全部分类',
  `check_count` int NOT NULL DEFAULT '0' COMMENT '质检数量',
  `price_count` int NOT NULL DEFAULT '0' COMMENT '定价数量',
  `recycle_count` int NOT NULL DEFAULT '0' COMMENT '回收数量',
  `return_count` int NOT NULL DEFAULT '0' COMMENT '退回数量',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_year_category` (`site_id`,`user_id`,`stat_year`,`category_id`),
  KEY `idx_site_user_type` (`site_id`,`user_type`),
  KEY `idx_stat_year` (`stat_year`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='回收业务年统计表';

-- 为回收订单表添加签收时间字段
ALTER TABLE `{{prefix}}recycle_order` ADD COLUMN `sign_at` int NOT NULL DEFAULT '0' COMMENT '签收时间' AFTER `update_at`;

-- 为设备表添加分类ID字段
ALTER TABLE `{{prefix}}recycle_device` ADD COLUMN `category_id` int NOT NULL DEFAULT '1' COMMENT '设备分类ID' AFTER `order_id`;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_category_id` (`category_id`);

-- 将已签收状态的订单设置签收时间为更新时间（临时数据修复）
UPDATE `{{prefix}}recycle_order` SET `sign_at` = `update_at` WHERE `status` >= 2 AND `sign_at` = 0; 