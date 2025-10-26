-- ============================================
-- 校园跑腿业务 - 数据库扩展方案
-- ============================================

-- 1. 扩展分类表：添加业务类型字段
ALTER TABLE `{{prefix}}home_service_goods_category` 
ADD COLUMN `business_type` VARCHAR(50) NOT NULL DEFAULT 'service' COMMENT '业务类型：service-上门服务, errand-跑腿' AFTER `category_name`;

-- 2. 扩展商品表：添加业务类型字段
ALTER TABLE `{{prefix}}home_service_goods` 
ADD COLUMN `business_type` VARCHAR(50) NOT NULL DEFAULT 'service' COMMENT '业务类型：service-上门服务, errand-跑腿' AFTER `goods_name`;


-- ============================================
-- 跑腿核心表 1：配送点管理表
-- ============================================
DROP TABLE IF EXISTS `{{prefix}}home_service_delivery_point`;
CREATE TABLE `{{prefix}}home_service_delivery_point`
(
    `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配送点ID',
    `site_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `point_name`  VARCHAR(100) NOT NULL DEFAULT '' COMMENT '配送点名称（如：东门快递站、宿舍楼1栋、图书馆、食堂）',
    `point_code`  VARCHAR(50) NOT NULL DEFAULT '' COMMENT '配送点编码',
    `category`    VARCHAR(50) NOT NULL DEFAULT '' COMMENT '类型：express_station-快递站, dormitory-宿舍, canteen-食堂, teaching-教学楼, other-其他',
    `address`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '详细地址',
    `longitude`   VARCHAR(50) NOT NULL DEFAULT '' COMMENT '经度',
    `latitude`    VARCHAR(50) NOT NULL DEFAULT '' COMMENT '纬度',
    `contact`     VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系人',
    `mobile`      VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系电话',
    `image`       VARCHAR(500) NOT NULL DEFAULT '' COMMENT '配送点图片',
    `remark`      VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注说明',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用：0-禁用，1-启用',
    `sort`        INT(11) NOT NULL DEFAULT 0 COMMENT '排序（数字越小越靠前）',
    `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_site_id` (`site_id`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(跑腿)配送点管理表';


-- ============================================
-- 跑腿核心表 2：配送路线表
-- ============================================
DROP TABLE IF EXISTS `{{prefix}}home_service_delivery_route`;
CREATE TABLE `{{prefix}}home_service_delivery_route`
(
    `id`                INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '路线ID',
    `site_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `route_name`        VARCHAR(100) NOT NULL DEFAULT '' COMMENT '路线名称（如：东门→1号楼）',
    `start_point_id`    INT(11) NOT NULL DEFAULT 0 COMMENT '起点ID（关联配送点表）',
    `end_point_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '终点ID（关联配送点表）',
    `start_point_name`  VARCHAR(100) NOT NULL DEFAULT '' COMMENT '起点名称（冗余字段，便于查询）',
    `end_point_name`    VARCHAR(100) NOT NULL DEFAULT '' COMMENT '终点名称（冗余字段，便于查询）',
    `distance`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '路线距离(km)（可选字段）',
    `estimated_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '预计送达时间(分钟)',
    `is_active`         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用：0-禁用，1-启用',
    `sort`              INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `remark`            VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `create_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_site_id` (`site_id`),
    KEY `idx_start_point` (`start_point_id`),
    KEY `idx_end_point` (`end_point_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(跑腿)配送路线表';


-- ============================================
-- 跑腿核心表 3：包裹大小价格配置表
-- ============================================
DROP TABLE IF EXISTS `{{prefix}}home_service_package_size`;
CREATE TABLE `{{prefix}}home_service_package_size`
(
    `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `site_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `size_name`   VARCHAR(50) NOT NULL DEFAULT '' COMMENT '大小名称（小件、中件、大件）',
    `size_code`   VARCHAR(50) NOT NULL DEFAULT '' COMMENT '大小编码（small、medium、large）',
    `description` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '描述（如：长宽高30cm以内，重量5kg以内）',
    `max_weight`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '最大重量(kg)',
    `max_volume`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '最大体积(立方厘米)',
    `image`       VARCHAR(500) NOT NULL DEFAULT '' COMMENT '示例图片',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用：0-禁用，1-启用',
    `sort`        INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_site_id` (`site_id`),
    KEY `idx_size_code` (`size_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(跑腿)包裹大小配置表';


-- ============================================
-- 跑腿核心表 4：路线价格配置表
-- ============================================
DROP TABLE IF EXISTS `{{prefix}}home_service_route_price`;
CREATE TABLE `{{prefix}}home_service_route_price`
(
    `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `route_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '路线ID（关联delivery_route表）',
    `package_size_id` INT(11) NOT NULL DEFAULT 0 COMMENT '包裹大小ID（关联package_size表）',
    `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '价格',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_route_size` (`route_id`, `package_size_id`),
    KEY `idx_site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(跑腿)路线价格配置表';


-- ============================================
-- 扩展订单表：添加跑腿专属字段
-- ============================================
ALTER TABLE `{{prefix}}home_service_order`
-- 跑腿：取件信息
ADD COLUMN `pickup_point_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '取件点ID（关联配送点表）' AFTER `taker_full_address`,
ADD COLUMN `pickup_point_name`    VARCHAR(100) NOT NULL DEFAULT '' COMMENT '取件点名称' AFTER `pickup_point_id`,
ADD COLUMN `pickup_code`          VARCHAR(100) NOT NULL DEFAULT '' COMMENT '取件码（快递取件码或备注信息）' AFTER `pickup_point_name`,
ADD COLUMN `pickup_contact`       VARCHAR(50) NOT NULL DEFAULT '' COMMENT '取件联系人（可选）' AFTER `pickup_code`,
ADD COLUMN `pickup_mobile`        VARCHAR(50) NOT NULL DEFAULT '' COMMENT '取件联系电话（可选）' AFTER `pickup_contact`,
ADD COLUMN `pickup_time`          INT(11) NOT NULL DEFAULT 0 COMMENT '实际取件时间' AFTER `pickup_mobile`,
ADD COLUMN `pickup_photos`        TEXT DEFAULT NULL COMMENT '取件照片（JSON格式）' AFTER `pickup_time`,

-- 跑腿：送达信息（复用 taker_* 字段作为送达信息）
-- taker_name -> 收件人姓名
-- taker_mobile -> 收件人电话
-- taker_address -> 送达地址描述

ADD COLUMN `delivery_point_id`    INT(11) NOT NULL DEFAULT 0 COMMENT '送达点ID（关联配送点表）' AFTER `pickup_photos`,
ADD COLUMN `delivery_point_name`  VARCHAR(100) NOT NULL DEFAULT '' COMMENT '送达点名称' AFTER `delivery_point_id`,
ADD COLUMN `delivery_photos`      TEXT DEFAULT NULL COMMENT '送达照片（JSON格式）' AFTER `delivery_point_name`,

-- 跑腿：包裹信息
ADD COLUMN `package_size_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '包裹大小ID（关联package_size表）' AFTER `delivery_photos`,
ADD COLUMN `package_size_name`    VARCHAR(50) NOT NULL DEFAULT '' COMMENT '包裹大小名称（冗余）' AFTER `package_size_id`,
ADD COLUMN `package_remark`       VARCHAR(500) NOT NULL DEFAULT '' COMMENT '包裹备注（物品描述、注意事项等）' AFTER `package_size_name`,

-- 跑腿：路线信息
ADD COLUMN `route_id`             INT(11) NOT NULL DEFAULT 0 COMMENT '配送路线ID（关联delivery_route表）' AFTER `package_remark`,
ADD COLUMN `route_name`           VARCHAR(100) NOT NULL DEFAULT '' COMMENT '路线名称（冗余）' AFTER `route_id`;


-- ============================================
-- 初始化数据：配送点示例（可选）
-- ============================================
-- INSERT INTO `{{prefix}}home_service_delivery_point` 
-- (`site_id`, `point_name`, `point_code`, `category`, `address`, `is_active`, `sort`, `create_time`, `update_time`) 
-- VALUES
-- (100000, '东门快递站', 'EAST_EXPRESS', 'express_station', '东门快递代收点', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- (100000, '西门快递站', 'WEST_EXPRESS', 'express_station', '西门快递代收点', 1, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- (100000, '1号宿舍楼', 'DORM_1', 'dormitory', '1号学生宿舍楼', 1, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- (100000, '2号宿舍楼', 'DORM_2', 'dormitory', '2号学生宿舍楼', 1, 4, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- (100000, '图书馆', 'LIBRARY', 'teaching', '校图书馆一楼', 1, 5, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());


-- ============================================
-- 初始化数据：包裹大小示例（可选）
-- ============================================
-- INSERT INTO `{{prefix}}home_service_package_size` 
-- (`site_id`, `size_name`, `size_code`, `description`, `max_weight`, `max_volume`, `is_active`, `sort`, `create_time`, `update_time`) 
-- VALUES
-- (100000, '小件', 'small', '长宽高30cm以内，重量5kg以内', 5.00, 27000.00, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- (100000, '中件', 'medium', '长宽高50cm以内，重量10kg以内', 10.00, 125000.00, 1, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- (100000, '大件', 'large', '长宽高80cm以内，重量20kg以内', 20.00, 512000.00, 1, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

