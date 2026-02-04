-- 易速快递产品配置表
CREATE TABLE IF NOT EXISTS `{{prefix}}yisu_product_config` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '产品代码',
    `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '产品名称',
    `logo` varchar(255) DEFAULT '' COMMENT '产品logo',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1-启用 0-禁用',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `create_at` int(11) DEFAULT NULL COMMENT '创建时间',
    `update_at` int(11) DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_site_product` (`site_id`, `product_code`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='易速快递产品配置表';
