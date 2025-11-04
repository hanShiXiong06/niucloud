-- 为价格配置表添加 sku_list 字段，用于存储多个SKU的数组
ALTER TABLE `{{prefix}}recycle_quotation_price_config` 
ADD COLUMN `sku_list` json COMMENT 'SKU列表（JSON数组，存储多个SKU信息，格式：[{"goods_id":1,"goods_name":"xxx","capacity":"256G","capacity_answer_id":1,"config_item_name":"花机"},...]）' AFTER `group_key`;

-- 添加索引以支持JSON查询（MySQL 5.7+）
-- ALTER TABLE `{{prefix}}recycle_quotation_price_config` ADD INDEX `idx_sku_list` ((CAST(`sku_list` AS CHAR(255) ARRAY)));
