-- 外部 ERP 集成框架数据表

-- 1. 外部 ERP 连接配置
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_integration_connection` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `adapter_code` varchar(50) NOT NULL DEFAULT '' COMMENT '适配器标识：boyuan/kingdee/yongyou',
  `adapter_name` varchar(100) NOT NULL DEFAULT '' COMMENT '连接显示名称',
  `status` varchar(20) NOT NULL DEFAULT 'disabled' COMMENT '状态：enabled启用/disabled禁用/testing测试中',
  `config` text COMMENT '连接配置JSON（API地址、密钥等，加密存储）',
  `business_types` varchar(500) DEFAULT NULL COMMENT '启用的业务类型JSON：["purchase","sale","payment","receipt"]',
  `sync_mode` varchar(20) DEFAULT 'async' COMMENT '模式：async异步/sync同步/manual人工原单',
  `bridge_started_at` int(11) NOT NULL DEFAULT 0 COMMENT '人工原单入口启用时间，0不接管',
  `retry_times` int(11) DEFAULT 3 COMMENT '失败重试次数',
  `last_test_at` int(11) DEFAULT NULL COMMENT '最后测试时间',
  `last_test_result` varchar(20) DEFAULT NULL COMMENT '最后测试结果：success/failed',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_adapter` (`site_id`, `adapter_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='外部ERP连接配置';

-- 2. 同步任务队列
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_integration_sync_task` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `connection_id` int(11) NOT NULL DEFAULT 0 COMMENT '外部ERP连接ID',
  `business_type` varchar(20) NOT NULL DEFAULT '' COMMENT '业务类型：purchase/sale/payment/receipt/return',
  `source_type` varchar(50) NOT NULL DEFAULT '' COMMENT '来源类型：erp_purchase/erp_sale/erp_finance_settlement',
  `source_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务单ID',
  `source_no` varchar(50) DEFAULT NULL COMMENT '业务单号',
  `status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT '任务实际状态，包括awaiting_confirmation人工待确认',
  `payload` mediumtext COMMENT '推送数据快照JSON，人工模式保留完整原始事件',
  `origin_event_id` varbinary(128) DEFAULT NULL COMMENT '人工原单的ERP原始事件ID',
  `fact_key` varbinary(160) DEFAULT NULL COMMENT '人工原单不可重复的业务事实或入库批次',
  `target_key` varbinary(64) DEFAULT NULL COMMENT '冻结的站点和目标账套身份摘要',
  `receipt_json` text COMMENT '厂商原单关联及核查回执，不包含连接凭据',
  `external_doc_no` varchar(100) DEFAULT NULL COMMENT '外部系统单号',
  `external_doc_id` varchar(100) DEFAULT NULL COMMENT '外部系统单据ID',
  `error_message` text COMMENT '错误信息',
  `error_code` varchar(50) DEFAULT NULL COMMENT '错误代码',
  `retry_count` int(11) DEFAULT 0 COMMENT '已重试次数',
  `last_retry_at` int(11) DEFAULT NULL COMMENT '最后重试时间',
  `succeeded_at` int(11) DEFAULT NULL COMMENT '成功时间',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_status` (`site_id`, `status`),
  KEY `idx_source` (`source_type`, `source_id`),
  KEY `idx_connection` (`connection_id`, `status`),
  KEY `idx_created` (`created_at`),
  UNIQUE KEY `uk_bridge_fact` (`site_id`, `target_key`, `fact_key`),
  UNIQUE KEY `uk_bridge_event` (`site_id`, `target_key`, `origin_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='同步任务队列';

-- 3. 失败记录（待人工处理）
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_integration_failure` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `task_id` int(11) NOT NULL DEFAULT 0 COMMENT '任务ID',
  `connection_id` int(11) NOT NULL DEFAULT 0 COMMENT '连接ID',
  `business_type` varchar(20) DEFAULT NULL COMMENT '业务类型',
  `source_type` varchar(50) DEFAULT NULL COMMENT '来源类型',
  `source_id` int(11) DEFAULT NULL COMMENT '业务单ID',
  `source_no` varchar(50) DEFAULT NULL COMMENT '业务单号',
  `failure_type` varchar(50) NOT NULL DEFAULT '' COMMENT '失败类型：mapping_missing/api_error/timeout/validation_error',
  `error_detail` text COMMENT '错误详情',
  `resolved` tinyint(4) DEFAULT 0 COMMENT '是否已解决：0未解决/1已解决',
  `resolved_at` int(11) DEFAULT NULL COMMENT '解决时间',
  `resolved_by` int(11) DEFAULT NULL COMMENT '解决人ID',
  `resolution_note` text COMMENT '解决说明',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_resolved` (`site_id`, `resolved`),
  KEY `idx_task` (`task_id`),
  KEY `idx_failure_type` (`failure_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='同步失败记录';

-- 4. 映射配置
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_integration_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `connection_id` int(11) NOT NULL DEFAULT 0 COMMENT '连接ID',
  `resource_type` varchar(50) NOT NULL DEFAULT '' COMMENT '资源类型：product/warehouse/party/staff/payment_method',
  `local_id` int(11) NOT NULL DEFAULT 0 COMMENT '本地资源ID',
  `local_code` varchar(100) DEFAULT NULL COMMENT '本地资源编码',
  `local_name` varchar(200) DEFAULT NULL COMMENT '本地资源名称',
  `external_code` varchar(100) DEFAULT NULL COMMENT '外部系统编码',
  `external_id` varchar(100) DEFAULT NULL COMMENT '外部系统ID',
  `external_name` varchar(200) DEFAULT NULL COMMENT '外部系统名称',
  `auto_matched` tinyint(4) DEFAULT 0 COMMENT '是否自动匹配：0手动/1自动',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updated_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mapping` (`site_id`, `connection_id`, `resource_type`, `local_id`),
  KEY `idx_external` (`connection_id`, `resource_type`, `external_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='资源映射配置';

-- 5. 同步日志汇总
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_integration_sync_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `connection_id` int(11) NOT NULL DEFAULT 0 COMMENT '连接ID',
  `sync_date` date NOT NULL COMMENT '同步的业务日期',
  `business_type` varchar(20) NOT NULL DEFAULT '' COMMENT '业务类型',
  `total_count` int(11) DEFAULT 0 COMMENT '总数',
  `success_count` int(11) DEFAULT 0 COMMENT '成功数',
  `failed_count` int(11) DEFAULT 0 COMMENT '失败数',
  `started_at` int(11) DEFAULT NULL COMMENT '开始时间',
  `finished_at` int(11) DEFAULT NULL COMMENT '完成时间',
  `created_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_date` (`site_id`, `sync_date`),
  KEY `idx_connection` (`connection_id`, `sync_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='同步日志汇总';
