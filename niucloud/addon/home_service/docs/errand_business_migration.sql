-- ============================================
-- 跑腿业务数据库迁移脚本
-- 创建时间: 2025-10-26
-- 说明: 为家政服务系统添加跑腿业务支持
-- ============================================

-- 1. 修改订单表，添加跑腿业务相关字段
ALTER TABLE `home_service_order` 
ADD COLUMN `is_errand` TINYINT(1) DEFAULT 0 COMMENT '是否为跑腿业务(0=否,1=是)' AFTER `is_auto_refund`,
ADD COLUMN `errand_items` TEXT NULL COMMENT '跑腿包裹信息JSON数据' AFTER `is_errand`;

-- 添加索引以提高查询效率
ALTER TABLE `home_service_order` 
ADD INDEX `idx_is_errand` (`is_errand`);

-- 2. 修改订单项表，添加取件码字段
ALTER TABLE `home_service_order_item` 
ADD COLUMN `pickup_code` VARCHAR(100) NULL COMMENT '取件码或快递单号' AFTER `sku_name`;

-- ============================================
-- 字段说明
-- ============================================
-- 
-- home_service_order 表:
-- - is_errand: 标识是否为跑腿订单，用于区分普通服务订单和跑腿订单
-- - errand_items: JSON格式保存所有包裹的完整信息
--   格式示例: [{"sku_id":1,"sku_name":"邮政｜小件","pickup_code":"111","price":"2.00"}]
-- 
-- home_service_order_item 表:
-- - pickup_code: 每个包裹的取件码或快递单号，方便师傅取件
-- 
-- ============================================
-- 数据验证查询
-- ============================================

-- 查看是否成功添加字段
SHOW FULL COLUMNS FROM `home_service_order` WHERE `Field` IN ('is_errand', 'errand_items');
SHOW FULL COLUMNS FROM `home_service_order_item` WHERE `Field` = 'pickup_code';

-- 查看索引是否创建成功
SHOW INDEX FROM `home_service_order` WHERE `Key_name` = 'idx_is_errand';

-- ============================================
-- 回滚脚本（如需回滚，请执行以下语句）
-- ============================================

-- ALTER TABLE `home_service_order` 
-- DROP COLUMN `is_errand`,
-- DROP COLUMN `errand_items`,
-- DROP INDEX `idx_is_errand`;

-- ALTER TABLE `home_service_order_item` 
-- DROP COLUMN `pickup_code`;






