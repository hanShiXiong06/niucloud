-- 回收设备表结构更新脚本
-- 添加价格重定义相关字段

-- 检查before_price字段是否存在，不存在则添加
ALTER TABLE `{{prefix}}recycle_device` 
ADD COLUMN IF NOT EXISTS `before_price` decimal(10,2) NULL DEFAULT 0.00 
COMMENT '重新定价前的价格' AFTER `final_price`;

-- 检查reprice_time字段是否存在，不存在则添加
ALTER TABLE `{{prefix}}recycle_device` 
ADD COLUMN IF NOT EXISTS `reprice_time` int(11) NULL DEFAULT NULL 
COMMENT '重新定价时间' AFTER `check_at`;

-- 创建索引
ALTER TABLE `{{prefix}}recycle_device` 
ADD INDEX IF NOT EXISTS `idx_before_price` (`before_price`);

ALTER TABLE `{{prefix}}recycle_device` 
ADD INDEX IF NOT EXISTS `idx_reprice_time` (`reprice_time`); 