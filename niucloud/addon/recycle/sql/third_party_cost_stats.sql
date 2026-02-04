-- 第三方服务费用统计表
CREATE TABLE IF NOT EXISTS `{{prefix}}third_party_cost_stats` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `service_type` varchar(50) NOT NULL DEFAULT '' COMMENT '服务类型',
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称',
    `date` date NOT NULL COMMENT '统计日期',
    `total_calls` int(11) NOT NULL DEFAULT 0 COMMENT '总调用次数',
    `success_calls` int(11) NOT NULL DEFAULT 0 COMMENT '成功次数',
    `failed_calls` int(11) NOT NULL DEFAULT 0 COMMENT '失败次数',
    `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '总费用',
    `avg_duration` int(11) NOT NULL DEFAULT 0 COMMENT '平均耗时（毫秒）',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_site_service_date` (`site_id`, `service_type`, `provider_name`, `date`),
    KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方服务费用统计表';
