-- ==================== 手机报价接口功能相关表 ====================

-- 1. 报价请求记录表
DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_request`;
CREATE TABLE `{{prefix}}recycle_quotation_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `quotation_id` int NOT NULL DEFAULT 0 COMMENT '报价单ID',
  `price_name` varchar(100) NOT NULL DEFAULT '' COMMENT '价格名称',
  `default_price_value` int NOT NULL DEFAULT 0 COMMENT '默认价格值',
  `default_percentage_value` int NOT NULL DEFAULT 0 COMMENT '默认百分比值',
  `price_adjustment_type` tinyint NOT NULL DEFAULT 0 COMMENT '价格调整类型',
  `price_adjustment_value` int NOT NULL DEFAULT 0 COMMENT '价格调整值',
  `quotation_background_color` varchar(255) NOT NULL DEFAULT '' COMMENT '报价单背景色',
  `quotation_text_color` varchar(50) NOT NULL DEFAULT '' COMMENT '报价单文字颜色',
  `request_url` text COMMENT '完整请求URL',
  `request_headers` json COMMENT '请求头信息',
  `response_data` json COMMENT '接口返回的原始数据',
  `request_status` tinyint NOT NULL DEFAULT 0 COMMENT '请求状态：0-待请求，1-请求成功，2-请求失败',
  `error_message` text COMMENT '错误信息',
  `request_time` int NOT NULL DEFAULT 0 COMMENT '请求时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_quotation_id` (`quotation_id`),
  KEY `idx_request_time` (`request_time`),
  KEY `idx_request_status` (`request_status`)
) COMMENT='报价请求记录表';

-- 2. 报价数据表
DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_data`;
CREATE TABLE `{{prefix}}recycle_quotation_data` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `request_id` int NOT NULL DEFAULT 0 COMMENT '请求记录ID',
  `quotation_id` int NOT NULL DEFAULT 0 COMMENT '报价单ID',
  `price_name` varchar(100) NOT NULL DEFAULT '' COMMENT '价格名称',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID',
  `goods_name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品名称（型号）',
  `group_key` int NOT NULL DEFAULT 0 COMMENT '分组key',
  `capacity` varchar(50) NOT NULL DEFAULT '' COMMENT '容量（从general_attr解析）',
  `capacity_answer_id` int NOT NULL DEFAULT 0 COMMENT '容量答案ID',
  `config_items` json COMMENT '配置项信息（config_attr数组）',
  `config_selected` json COMMENT '配置项名称数组',
  `prices` json COMMENT '价格数据：{"花机":5250,"内爆可测":3700}',
  `add_value_info` text COMMENT '加/扣钱项说明',
  `price_date` date NOT NULL COMMENT '价格日期',
  `is_current` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否当前价格：1-是，0-否',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_quotation_id` (`quotation_id`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_price_date` (`price_date`),
  KEY `idx_is_current` (`is_current`),
  KEY `idx_group_key` (`group_key`),
  KEY `idx_goods_capacity` (`goods_id`, `capacity`)
) COMMENT='报价数据表';

-- 3. 价格配置表
DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_price_config`;
CREATE TABLE `{{prefix}}recycle_quotation_price_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `config_type` tinyint NOT NULL DEFAULT 1 COMMENT '配置类型：1-SKU级别(型号+内存+配置项)，2-批量管理(型号+内存)，3-按型号，4-按分组',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID（兼容旧数据，SKU级别配置时为空）',
  `capacity` varchar(50) NOT NULL DEFAULT '' COMMENT '内存容量（兼容旧数据，SKU级别配置时为空）',
  `capacity_answer_id` int NOT NULL DEFAULT 0 COMMENT '容量答案ID（从接口数据中获取）',
  `config_item_name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置项名称（兼容旧数据，SKU级别配置时为空）',
  `group_key` int NOT NULL DEFAULT 0 COMMENT '分组key（config_type=4时使用）',
  `sku_list` json COMMENT 'SKU列表（JSON数组，存储多个SKU信息，格式：[{"goods_id":1,"goods_name":"xxx","capacity":"256G","capacity_answer_id":1,"config_item_name":"花机"},...]，仅config_type=1时使用）',
  `adjustment_type` tinyint NOT NULL DEFAULT 1 COMMENT '调整方式：1-固定金额，2-百分比，3-固定价格覆盖',
  `adjustment_value` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '调整值（正数为加，负数为减）',
  `is_enable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用：1-启用，0-禁用',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_config_type` (`config_type`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_capacity` (`capacity`),
  KEY `idx_group_key` (`group_key`),
  KEY `idx_is_enable` (`is_enable`),
  KEY `idx_goods_capacity` (`goods_id`, `capacity`),
  KEY `idx_goods_capacity_config` (`goods_id`, `capacity`, `config_item_name`)
) COMMENT='价格配置表';

-- 4. 报价型号表
DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_model`;
CREATE TABLE `{{prefix}}recycle_quotation_model` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID（外部接口）',
  `goods_name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品名称（型号名称）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_goods` (`site_id`, `goods_id`),
  KEY `idx_goods_name` (`goods_name`),
  KEY `idx_status` (`status`)
) COMMENT='报价型号表';

-- 5. 报价单配置表
DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_config`;
CREATE TABLE `{{prefix}}recycle_quotation_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `quotation_id` int NOT NULL DEFAULT 0 COMMENT '报价单ID',
  `price_name` varchar(100) NOT NULL DEFAULT '' COMMENT '价格名称',
  `config_name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置名称（用于管理）',
  `default_price_value` int NOT NULL DEFAULT 0 COMMENT '默认价格值',
  `default_percentage_value` int NOT NULL DEFAULT 0 COMMENT '默认百分比值',
  `price_adjustment_type` tinyint NOT NULL DEFAULT 0 COMMENT '价格调整类型',
  `price_adjustment_value` int NOT NULL DEFAULT 0 COMMENT '价格调整值',
  `quotation_background_color` varchar(255) NOT NULL DEFAULT '' COMMENT '报价单背景色',
  `quotation_text_color` varchar(50) NOT NULL DEFAULT '' COMMENT '报价单文字颜色',
  `authorization_token` varchar(500) NOT NULL DEFAULT '' COMMENT 'Authorization Token（加密存储）',
  `open_id` varchar(100) NOT NULL DEFAULT '' COMMENT 'OpenId（加密存储）',
  `is_enable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用：1-启用，0-禁用',
  `auto_request` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否自动请求：1-是，0-否',
  `request_time` varchar(10) NOT NULL DEFAULT '00:00' COMMENT '自动请求时间（格式：HH:mm）',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_quotation_price` (`site_id`, `quotation_id`, `price_name`),
  KEY `idx_is_enable` (`is_enable`),
  KEY `idx_auto_request` (`auto_request`)
) COMMENT='报价单配置表';

