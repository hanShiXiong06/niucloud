-- ====================================
-- 回收订单对接安果ERP快递 - 数据库升级SQL
-- 执行时间：2026-01-21
-- 说明：手动执行此SQL以添加快递相关字段
-- ====================================

-- 1. 为 recycle_order 表添加快递相关字段
ALTER TABLE `{{prefix}}recycle_order`
ADD COLUMN `delivery_platform` varchar(50) NOT NULL DEFAULT '' COMMENT '快递平台：anguo-安果ERP，manual-手动录入' AFTER `express_no`,
ADD COLUMN `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '快递费用' AFTER `delivery_platform`,
ADD COLUMN `delivery_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '快递状态：0-未下单，1-已下单，2-运输中，3-已签收，4-已取消' AFTER `delivery_fee`,
ADD COLUMN `delivery_order_id` varchar(100) NOT NULL DEFAULT '' COMMENT '快递平台订单ID（用于拦截等操作）' AFTER `delivery_status`,
ADD COLUMN `delivery_data` json COMMENT '快递下单原始数据（发件人、收件人、预约时间等）' AFTER `delivery_order_id`,
ADD COLUMN `pickup_time` varchar(50) NOT NULL DEFAULT '' COMMENT '预约取件时间' AFTER `delivery_data`;

-- 2. 创建快递费用配置表（可选，用于后期费用控制）
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_delivery_fee_config` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `fee_type` tinyint NOT NULL DEFAULT 1 COMMENT '收费类型：1-免费，2-固定金额，3-按重量',
  `base_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '基础费用',
  `weight_unit_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '每公斤费用（fee_type=3时使用）',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收快递费用配置表';

-- 3. 为 recycle_order 表添加索引，提升查询效率
ALTER TABLE `{{prefix}}recycle_order`
ADD INDEX `idx_delivery_platform` (`delivery_platform`),
ADD INDEX `idx_delivery_status` (`delivery_status`),
ADD INDEX `idx_express_no` (`express_no`);

-- ====================================
-- 执行说明：
-- 1. 将 {{prefix}} 替换为你的实际表前缀（如 niucloud_ ）
-- 2. 手动在数据库中执行此SQL
-- 3. 执行完成后，检查字段是否添加成功
-- ====================================
