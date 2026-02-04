-- 第三方服务配置表
CREATE TABLE IF NOT EXISTS `{{prefix}}third_party_service` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `service_type` varchar(50) NOT NULL DEFAULT '' COMMENT '服务类型：device_query-设备查询, delivery-快递服务, sms-短信服务, payment-支付网关',
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称：3023, anguo, ali_express等',
    `priority` int(11) NOT NULL DEFAULT 1 COMMENT '优先级（越小越优先，用于主备切换）',
    `config` text COMMENT '配置信息（JSON格式）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '余额',
    `min_balance_alert` decimal(10,2) NOT NULL DEFAULT 100.00 COMMENT '最低余额告警阈值',
    `create_at` int(11) DEFAULT NULL COMMENT '创建时间',
    `update_at` int(11) DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_site_type` (`site_id`, `service_type`),
    KEY `idx_status` (`status`),
    KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方服务配置表';

-- 第三方API调用日志表
CREATE TABLE IF NOT EXISTS `{{prefix}}third_party_api_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `service_type` varchar(50) NOT NULL DEFAULT '' COMMENT '服务类型',
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称',
    `method` varchar(100) NOT NULL DEFAULT '' COMMENT '调用方法',
    `request_params` json COMMENT '请求参数（JSON格式）',
    `response_data` json COMMENT '响应数据（JSON格式）',
    `cost` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '费用',
    `duration` int(11) NOT NULL DEFAULT 0 COMMENT '耗时（毫秒）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1-成功 0-失败',
    `error_msg` varchar(500) DEFAULT '' COMMENT '错误信息',
    `create_at` int(11) DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_site_service` (`site_id`, `service_type`, `create_at`),
    KEY `idx_create_at` (`create_at`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方API调用日志表';

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
