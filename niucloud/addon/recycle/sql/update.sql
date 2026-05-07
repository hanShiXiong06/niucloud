-- 为设备表添加分类字段
ALTER TABLE `{{prefix}}recycle_device` ADD COLUMN `category_id` int NOT NULL DEFAULT '1' COMMENT '设备分类ID' AFTER `model`;

-- 添加分类字段索引
ALTER TABLE `{{prefix}}recycle_device` ADD KEY `idx_category_id` (`category_id`);

-- 创建统计表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stats_daily` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_type` varchar(20) NOT NULL DEFAULT '' COMMENT '用户类型：checker(质检员),pricer(估价员),admin(管理员)',
  `stat_date` date NOT NULL COMMENT '统计日期',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '设备分类ID，0表示全部分类',
  `check_count` int NOT NULL DEFAULT '0' COMMENT '质检数量',
  `price_count` int NOT NULL DEFAULT '0' COMMENT '定价数量',
  `recycle_count` int NOT NULL DEFAULT '0' COMMENT '回收数量',
  `return_count` int NOT NULL DEFAULT '0' COMMENT '退回数量',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date_category` (`site_id`,`user_id`,`stat_date`,`category_id`),
  KEY `idx_site_user_type` (`site_id`,`user_type`),
  KEY `idx_stat_date` (`stat_date`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='回收业务日统计表';

-- 创建月统计表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stats_monthly` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_type` varchar(20) NOT NULL DEFAULT '' COMMENT '用户类型：checker(质检员),pricer(估价员),admin(管理员)',
  `stat_month` varchar(7) NOT NULL COMMENT '统计月份 YYYY-MM',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '设备分类ID，0表示全部分类',
  `check_count` int NOT NULL DEFAULT '0' COMMENT '质检数量',
  `price_count` int NOT NULL DEFAULT '0' COMMENT '定价数量',
  `recycle_count` int NOT NULL DEFAULT '0' COMMENT '回收数量',
  `return_count` int NOT NULL DEFAULT '0' COMMENT '退回数量',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_month_category` (`site_id`,`user_id`,`stat_month`,`category_id`),
  KEY `idx_site_user_type` (`site_id`,`user_type`),
  KEY `idx_stat_month` (`stat_month`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='回收业务月统计表';

-- 创建年统计表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stats_yearly` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_type` varchar(20) NOT NULL DEFAULT '' COMMENT '用户类型：checker(质检员),pricer(估价员),admin(管理员)',
  `stat_year` int NOT NULL COMMENT '统计年份',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '设备分类ID，0表示全部分类',
  `check_count` int NOT NULL DEFAULT '0' COMMENT '质检数量',
  `price_count` int NOT NULL DEFAULT '0' COMMENT '定价数量',
  `recycle_count` int NOT NULL DEFAULT '0' COMMENT '回收数量',
  `return_count` int NOT NULL DEFAULT '0' COMMENT '退回数量',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_year_category` (`site_id`,`user_id`,`stat_year`,`category_id`),
  KEY `idx_site_user_type` (`site_id`,`user_type`),
  KEY `idx_stat_year` (`stat_year`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='回收业务年统计表';

-- 为回收订单表添加签收时间字段
ALTER TABLE `{{prefix}}recycle_order` ADD COLUMN `sign_at` int NOT NULL DEFAULT '0' COMMENT '签收时间' AFTER `update_at`;

-- 为设备表添加分类ID字段
ALTER TABLE `{{prefix}}recycle_device` ADD COLUMN `category_id` int NOT NULL DEFAULT '1' COMMENT '设备分类ID' AFTER `order_id`;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_category_id` (`category_id`);

-- 为设备表增加买家/卖家可见的质检结果与质检图片字段
ALTER TABLE `{{prefix}}recycle_device`
ADD COLUMN `check_result_seller` text COMMENT '卖家可见质检结果' AFTER `check_result`,
ADD COLUMN `check_result_buyer` text COMMENT '买家可见质检结果' AFTER `check_result_seller`,
ADD COLUMN `check_images_seller` text COMMENT '卖家可见质检图片 逗号 , 隔开' AFTER `check_images`,
ADD COLUMN `check_images_buyer` text COMMENT '买家可见质检图片 逗号 , 隔开' AFTER `check_images_seller`;

-- 为设备表增加卖货价格字段
ALTER TABLE `{{prefix}}recycle_device`
ADD COLUMN `sell_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '卖货价格' AFTER `final_price`;

-- 将已签收状态的订单设置签收时间为更新时间（临时数据修复）
UPDATE `{{prefix}}recycle_order` SET `sign_at` = `update_at` WHERE `status` >= 2 AND `sign_at` = 0; 

-- 打印模板兼容字段
ALTER TABLE `{{prefix}}recycle_printer_template`
ADD COLUMN `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '绑定打印机ID，0表示不指定' AFTER `is_default`,
ADD COLUMN `trigger_event` varchar(50) NOT NULL DEFAULT '' COMMENT '旧版触发时机，兼容字段' AFTER `printer_id`;

-- 回收打印场景表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_scene` (
    `scene_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '场景ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `scene_key` varchar(50) NOT NULL DEFAULT '' COMMENT '场景标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型：device-设备，order-订单，return-退货',
    `template_type` varchar(50) NOT NULL DEFAULT '' COMMENT '模板类型',
    `auto_print` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动打印：0-否，1-是',
    `template_id` int(11) NOT NULL DEFAULT 0 COMMENT '指定模板ID，0使用默认模板',
    `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '指定打印机ID，0使用模板绑定或账号默认打印机',
    `copies` int(11) NOT NULL DEFAULT 1 COMMENT '打印份数',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0-停用，1-启用',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`scene_id`),
    UNIQUE KEY `uk_site_scene` (`site_id`, `scene_key`)
) COMMENT='回收打印场景表';

-- 回收打印日志表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_log` (
    `log_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `scene_key` varchar(50) NOT NULL DEFAULT '' COMMENT '场景标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型',
    `biz_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务ID',
    `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单ID',
    `device_id` int(11) NOT NULL DEFAULT 0 COMMENT '设备ID',
    `template_id` int(11) NOT NULL DEFAULT 0 COMMENT '模板ID',
    `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
    `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '打印机ID',
    `printer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '打印机名称',
    `copies` int(11) NOT NULL DEFAULT 1 COMMENT '打印份数',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0-失败/跳过，1-成功',
    `message` varchar(500) NOT NULL DEFAULT '' COMMENT '结果消息',
    `operator_uid` int(11) NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `plan_snapshot` text NOT NULL COMMENT '打印计划快照',
    `request_snapshot` text NOT NULL COMMENT '请求快照',
    `response_snapshot` text NOT NULL COMMENT '响应快照',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`log_id`),
    KEY `idx_site_scene` (`site_id`, `scene_key`),
    KEY `idx_site_device` (`site_id`, `device_id`),
    KEY `idx_site_order` (`site_id`, `order_id`)
) COMMENT='回收打印日志表';

CREATE TABLE IF NOT EXISTS `{{prefix}}express_address_book` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `address_type` varchar(20) NOT NULL DEFAULT 'sender' COMMENT '地址类型 sender寄件人 receiver收件人',
  `name` varchar(80) NOT NULL DEFAULT '' COMMENT '联系人',
  `mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '手机号',
  `province` varchar(80) NOT NULL DEFAULT '' COMMENT '省',
  `city` varchar(80) NOT NULL DEFAULT '' COMMENT '市',
  `district` varchar(80) NOT NULL DEFAULT '' COMMENT '区县',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '详细地址',
  `tag` varchar(50) NOT NULL DEFAULT '' COMMENT '标签',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_site_type` (`site_id`,`address_type`,`status`,`is_top`,`is_default`),
  KEY `idx_mobile` (`site_id`,`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快递常用寄收件地址';

CREATE TABLE IF NOT EXISTS `{{prefix}}express_order_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `order_no` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方订单号',
  `recycle_order_id` int NOT NULL DEFAULT '0' COMMENT '回收订单ID',
  `recycle_device_id` int NOT NULL DEFAULT '0' COMMENT '回收设备ID',
  `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商',
  `product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '快递产品编码',
  `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '快递产品名称',
  `delivery_id` varchar(100) NOT NULL DEFAULT '' COMMENT '运单号',
  `sender_name` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件人',
  `sender_mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '寄件手机号',
  `sender_province` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件省',
  `sender_city` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件市',
  `sender_district` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件区县',
  `sender_address` varchar(255) NOT NULL DEFAULT '' COMMENT '寄件详细地址',
  `receiver_name` varchar(80) NOT NULL DEFAULT '' COMMENT '收件人',
  `receiver_mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '收件手机号',
  `receiver_province` varchar(80) NOT NULL DEFAULT '' COMMENT '收件省',
  `receiver_city` varchar(80) NOT NULL DEFAULT '' COMMENT '收件市',
  `receiver_district` varchar(80) NOT NULL DEFAULT '' COMMENT '收件区县',
  `receiver_address` varchar(255) NOT NULL DEFAULT '' COMMENT '收件详细地址',
  `goods_name` varchar(100) NOT NULL DEFAULT '' COMMENT '物品名称',
  `goods_value` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '保价金额',
  `package_count` int NOT NULL DEFAULT '1' COMMENT '包裹数',
  `estimated_weight` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '预估重量',
  `actual_weight` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实际重量',
  `weight_diff` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '重量差异',
  `volume` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '体积',
  `volume_long` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '长cm',
  `volume_width` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '宽cm',
  `volume_height` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '高cm',
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '预估费用',
  `actual_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实际费用',
  `cost_diff` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '费用差异',
  `payment_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付状态',
  `user_paid` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '用户支付',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `order_status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT '运单状态',
  `status_history` json DEFAULT NULL COMMENT '状态历史',
  `api_response` json DEFAULT NULL COMMENT 'API响应',
  `pickup_time` int NOT NULL DEFAULT '0' COMMENT '揽收时间',
  `delivery_time` int NOT NULL DEFAULT '0' COMMENT '签收时间',
  `cancel_time` int NOT NULL DEFAULT '0' COMMENT '取消时间',
  `cancel_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '取消原因',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_site_create` (`site_id`,`create_at`),
  KEY `idx_order_no` (`site_id`,`order_no`),
  KEY `idx_delivery_id` (`site_id`,`delivery_id`),
  KEY `idx_recycle_order` (`site_id`,`recycle_order_id`),
  KEY `idx_status` (`site_id`,`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快递运单记录';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_express_provider_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `provider` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商标识',
  `provider_name` varchar(100) NOT NULL DEFAULT '' COMMENT '服务商名称',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认',
  `config` json DEFAULT NULL COMMENT '服务商配置',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_provider` (`site_id`,`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收快递服务商配置';

CREATE TABLE IF NOT EXISTS `{{prefix}}yisu_product_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '产品编码',
  `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '产品名称',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '产品图标',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_product` (`site_id`,`product_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='易速快递产品配置';
