
-- ALTER TABLE `saas_phone_shop_goods_sku`
-- ADD COLUMN `locked_stock` int(11) NOT NULL DEFAULT 0 COMMENT '已锁定库存(未付款/挂单订单)' AFTER `stock`;

-- -- 2. 扩展订单表 - 添加支付方式字段
-- ALTER TABLE `saas_phone_shop_order`
-- ADD COLUMN `pay_type` varchar(50) DEFAULT NULL COMMENT '支付方式(wechatpay/alipay/hsx_offlinepay等)' AFTER `pay_time`;

-- -- 3. 扩展订单表 - 添加线下收款账户字段
-- ALTER TABLE `saas_phone_shop_order`
-- ADD COLUMN `offline_pay_account` varchar(100) DEFAULT NULL COMMENT '线下收款账户名称(如:微信1、支付宝1)' AFTER `pay_type`;
-- -- 添加 is_deleted 字段到订单商品表
-- -- 用于标识在挂单确认收款时，客户返还的商品
-- ALTER TABLE `saas_phone_shop_order_goods`
-- ADD COLUMN `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已删除/返还：0-正常，1-已删除' AFTER `is_enable_refund`;

-- -- 为 is_deleted 字段添加索引以提高查询性能
-- ALTER TABLE `saas_phone_shop_order_goods`
-- ADD INDEX `idx_is_deleted` (`is_deleted`);
 // mysql 清空数据表
 	-- truncate table saas_phone_shop_goods;
	-- truncate table saas_phone_shop_goods_sku;
	-- truncate table saas_phone_shop_order_log;

	-- truncate table saas_phone_shop_order_log; 
	-- truncate table saas_phone_shop_order;   
	-- truncate table saas_phone_shop_order_goods; 


-- 第三方服务配置表
CREATE TABLE IF NOT EXISTS `saas_third_party_service` (
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
CREATE TABLE IF NOT EXISTS `saas_third_party_api_log` (
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
CREATE TABLE IF NOT EXISTS `saas_third_party_cost_stats` (
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


-- 第三方服务配置 SQL
-- 站点ID: 100000

-- 1. 阿里云快递查询服务
INSERT INTO `saas_third_party_service` (
    `site_id`,
    `service_type`,
    `provider_name`,
    `priority`,
    `config`,
    `status`,
    `balance`,
    `min_balance_alert`,
    `create_at`,
    `update_at`
) VALUES (
    100000,
    'device_query',
    'alicloudapi',
    2,
    '{
        "base_url": "https://kzexpress.market.alicloudapi.com",
        "api_key": "f61c5bd1d2cc42c4b64012d38c5565bf",
        "enabled_apis": "/api-mall/api/express/query",
        "timeout": 30,
        "max_retry": 3,
        "cache_time": 3600,
        "daily_limit": 1000
    }',
    1,
    0.00,
    100.00,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);

-- 2. 3023设备查询服务（主服务商 - 优先级1）
INSERT INTO `saas_third_party_service` (
    `site_id`,
    `service_type`,
    `provider_name`,
    `priority`,
    `config`,
    `status`,
    `balance`,
    `min_balance_alert`,
    `create_at`,
    `update_at`
) VALUES (
    100000,
    'device_query',
    '3023',
    1,
    '{
        "base_url": "http://api.3023data.com",
        "api_key": "x7U77AYc9TEI9KWzh1vGLi6T14BmwPEh",
        "enabled_apis": "/apple/coverage-capacity",
        "timeout": 30,
        "max_retry": 3,
        "cache_time": 3600,
        "daily_limit": 1000
    }',
    1,
    0.00,
    100.00,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);

-- 3. 安果ERP快递服务
INSERT INTO `saas_third_party_service` (
    `site_id`,
    `service_type`,
    `provider_name`,
    `priority`,
    `config`,
    `status`,
    `balance`,
    `min_balance_alert`,
    `create_at`,
    `update_at`
) VALUES (
    100000,
    'delivery',
    'anguo',
    1,
    '{
        "base_url": "http://115.190.35.168:3000",
        "api_key": "afdd0b4ad2ec172c586e2150770fbf9e",
        "timeout": 30
    }',
    1,
    0.00,
    100.00,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);



CREATE TABLE IF NOT EXISTS `saas_third_party_api_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `service_type` varchar(50) NOT NULL DEFAULT '' COMMENT '服务类型',
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称',
    `method` varchar(100) NOT NULL DEFAULT '' COMMENT '调用方法',
    `request_params` json COMMENT '请求参数',
    `response_data` json COMMENT '响应数据',
    `cost` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '费用',
    `duration` int(11) NOT NULL DEFAULT 0 COMMENT '耗时（毫秒）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1成功 0失败',
    `error_msg` varchar(500) DEFAULT NULL COMMENT '错误信息',
    `create_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_site_service` (`site_id`, `service_type`, `create_at`),
    KEY `idx_create_at` (`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='第三方API调用日志表';
CREATE TABLE IF NOT EXISTS `saas_third_party_cost_stats` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='第三方服务费用统计表';


-- 易速快递产品配置表
CREATE TABLE IF NOT EXISTS `saas_yisu_product_config` (
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

-- 快递订单记录表
CREATE TABLE IF NOT EXISTS `saas_express_order_record` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `order_no` varchar(100) NOT NULL DEFAULT '' COMMENT '快递订单号（易速返回）',
    `recycle_order_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联回收订单ID',
    `recycle_device_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联设备ID',

    -- 快递服务商信息
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称：yisu, anguo等',
    `product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '产品代码',
    `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '产品名称',
    `delivery_id` varchar(100) NOT NULL DEFAULT '' COMMENT '运单号',

    -- 发件人信息
    `sender_name` varchar(100) NOT NULL DEFAULT '' COMMENT '发件人姓名',
    `sender_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '发件人手机',
    `sender_province` varchar(50) NOT NULL DEFAULT '' COMMENT '发件省份',
    `sender_city` varchar(50) NOT NULL DEFAULT '' COMMENT '发件城市',
    `sender_district` varchar(50) NOT NULL DEFAULT '' COMMENT '发件区县',
    `sender_address` varchar(255) NOT NULL DEFAULT '' COMMENT '发件详细地址',

    -- 收件人信息
    `receiver_name` varchar(100) NOT NULL DEFAULT '' COMMENT '收件人姓名',
    `receiver_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '收件人手机',
    `receiver_province` varchar(50) NOT NULL DEFAULT '' COMMENT '收件省份',
    `receiver_city` varchar(50) NOT NULL DEFAULT '' COMMENT '收件城市',
    `receiver_district` varchar(50) NOT NULL DEFAULT '' COMMENT '收件区县',
    `receiver_address` varchar(255) NOT NULL DEFAULT '' COMMENT '收件详细地址',

    -- 物品信息
    `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '物品名称',
    `goods_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '物品价值（保价金额）',
    `package_count` int(11) NOT NULL DEFAULT 1 COMMENT '包裹数量',

    -- 重量和体积信息
    `estimated_weight` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '预估重量（kg）',
    `actual_weight` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际重量（kg）',
    `weight_diff` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '重量差异（实际-预估）',
    `volume` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '体积（立方米）',
    `volume_long` int(11) NOT NULL DEFAULT 0 COMMENT '长度（cm）',
    `volume_width` int(11) NOT NULL DEFAULT 0 COMMENT '宽度（cm）',
    `volume_height` int(11) NOT NULL DEFAULT 0 COMMENT '高度（cm）',

    -- 费用信息
    `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '预估费用（元）',
    `actual_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际费用（元）',
    `cost_diff` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '费用差异（实际-预估）',
    `payment_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态：0-未支付 1-已支付 2-部分支付',
    `user_paid` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '用户已支付金额',
    `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',

    -- 订单状态
    `order_status` varchar(50) NOT NULL DEFAULT 'pending' COMMENT '订单状态：pending-待揽收, picked-已揽收, in_transit-运输中, delivered-已签收, cancelled-已取消',
    `cancel_reason` varchar(255) DEFAULT '' COMMENT '取消原因',
    `status_history` json COMMENT '状态变更历史（JSON格式）',

    -- 时间信息
    `pickup_time` int(11) DEFAULT NULL COMMENT '揽收时间',
    `delivery_time` int(11) DEFAULT NULL COMMENT '签收时间',
    `cancel_time` int(11) DEFAULT NULL COMMENT '取消时间',
    `create_at` int(11) DEFAULT NULL COMMENT '创建时间',
    `update_at` int(11) DEFAULT NULL COMMENT '更新时间',

    -- 其他信息
    `remark` varchar(500) DEFAULT '' COMMENT '备注',
    `api_response` json COMMENT 'API响应数据（JSON格式）',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_site_id` (`site_id`),
    KEY `idx_recycle_order` (`recycle_order_id`),
    KEY `idx_recycle_device` (`recycle_device_id`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_order_status` (`order_status`),
    KEY `idx_create_at` (`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快递订单记录表';




-- ====================================
-- 回收订单对接安果ERP快递 - 数据库升级SQL
-- 执行时间：2026-01-21
-- 说明：手动执行此SQL以添加快递相关字段
-- ====================================

-- 1. 为 recycle_order 表添加快递相关字段
ALTER TABLE `phone_recycle_order`
ADD COLUMN `delivery_platform` varchar(50) NOT NULL DEFAULT '' COMMENT '快递平台：anguo-安果ERP，manual-手动录入' AFTER `express_no`,
ADD COLUMN `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '快递费用' AFTER `delivery_platform`,
ADD COLUMN `delivery_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '快递状态：0-未下单，1-已下单，2-运输中，3-已签收，4-已取消' AFTER `delivery_fee`,
ADD COLUMN `delivery_order_id` varchar(100) NOT NULL DEFAULT '' COMMENT '快递平台订单ID（用于拦截等操作）' AFTER `delivery_status`,
ADD COLUMN `delivery_data` json COMMENT '快递下单原始数据（发件人、收件人、预约时间等）' AFTER `delivery_order_id`,
ADD COLUMN `pickup_time` varchar(50) NOT NULL DEFAULT '' COMMENT '预约取件时间' AFTER `delivery_data`;

-- 2. 创建快递费用配置表（可选，用于后期费用控制）
CREATE TABLE IF NOT EXISTS `phone_recycle_delivery_fee_config` (
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
ALTER TABLE `phone_recycle_order`
ADD INDEX `idx_delivery_platform` (`delivery_platform`),
ADD INDEX `idx_delivery_status` (`delivery_status`),
ADD INDEX `idx_express_no` (`express_no`);

-- ====================================
-- 执行说明：
-- 1. 将 {{prefix}} 替换为你的实际表前缀（如 niucloud_ ）
-- 2. 手动在数据库中执行此SQL
-- 3. 执行完成后，检查字段是否添加成功
-- ====================================


