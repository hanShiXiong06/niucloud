-- 在报价单配置表中添加"是否使用爬虫备注"字段
ALTER TABLE `saas_recycle_quotation_config`
ADD COLUMN `use_crawler_remark` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否使用爬虫备注：0=否，1=是' AFTER `is_enable`;

-- 添加索引（可选，提升查询性能）
ALTER TABLE `saas_recycle_quotation_config`
ADD INDEX `idx_use_crawler_remark` (`use_crawler_remark`);
