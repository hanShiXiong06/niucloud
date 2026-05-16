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

-- 为回收订单表添加提交设备数量字段
ALTER TABLE `{{prefix}}recycle_order` ADD COLUMN `count` int NOT NULL DEFAULT '1' COMMENT '提交设备数量' AFTER `device_count`;

-- 用已有设备数量回填提交设备数量
UPDATE `{{prefix}}recycle_order` SET `count` = IF(`device_count` > 0, `device_count`, 1) WHERE `count` <= 0;

-- 为回收订单表补充后台订单流程字段
ALTER TABLE `{{prefix}}recycle_order`
ADD COLUMN `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '打款状态：0-未打款，1-已打款' AFTER `pay_account`,
ADD COLUMN `pay_name` varchar(50) NOT NULL DEFAULT '' COMMENT '收款人姓名' AFTER `pay_status`,
ADD COLUMN `pay_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '打款备注' AFTER `pay_name`,
ADD COLUMN `pay_url` varchar(500) NOT NULL DEFAULT '' COMMENT '打款凭证' AFTER `pay_remark`,
ADD COLUMN `payment_images` text COMMENT '打款凭证图片' AFTER `pay_url`,
ADD COLUMN `delivery_platform` varchar(50) NOT NULL DEFAULT '' COMMENT '快递平台' AFTER `express_no`,
ADD COLUMN `delivery_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '快递状态：0-未下单，1-已下单，2-运输中，3-已签收，4-已取消' AFTER `delivery_platform`,
ADD COLUMN `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '快递费用' AFTER `delivery_status`,
ADD COLUMN `delivery_order_id` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方快递订单号' AFTER `delivery_fee`,
ADD COLUMN `pickup_time` varchar(50) NOT NULL DEFAULT '' COMMENT '预约揽收时间' AFTER `delivery_order_id`,
ADD COLUMN `delivery_data` text COMMENT '快递扩展数据' AFTER `pickup_time`,
ADD COLUMN `confirm_time` int NOT NULL DEFAULT 0 COMMENT '确认价格时间' AFTER `complete_at`,
ADD COLUMN `receipt_confirm_time` int NOT NULL DEFAULT 0 COMMENT '用户确认收货时间' AFTER `confirm_time`,
ADD COLUMN `cancel_time` int NOT NULL DEFAULT 0 COMMENT '取消时间' AFTER `receipt_confirm_time`,
ADD COLUMN `cancel_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '取消原因' AFTER `cancel_time`,
ADD COLUMN `close_time` int NOT NULL DEFAULT 0 COMMENT '关闭时间' AFTER `cancel_reason`,
ADD COLUMN `close_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '关闭原因' AFTER `close_time`,
ADD COLUMN `negotiate_time` int NOT NULL DEFAULT 0 COMMENT '议价时间' AFTER `close_reason`,
ADD COLUMN `expected_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '用户期望价格' AFTER `negotiate_time`,
ADD COLUMN `negotiate_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '议价原因' AFTER `expected_price`,
ADD COLUMN `is_negotiating` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否议价中' AFTER `negotiate_reason`,
ADD COLUMN `is_force_confirm` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否强制确认' AFTER `is_negotiating`;

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
    `trigger_key` varchar(100) NOT NULL DEFAULT '' COMMENT '触发事件标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型：device-设备，order-订单，return-退货',
    `template_type` varchar(50) NOT NULL DEFAULT '' COMMENT '模板类型',
    `auto_print` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动打印：0-否，1-是',
    `idempotency_scope` varchar(50) NOT NULL DEFAULT 'site_scene_biz' COMMENT '幂等范围：none/site_scene_biz/site_scene_device/site_scene_order',
    `retry_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否失败重试：0-否，1-是',
    `max_attempts` int(11) NOT NULL DEFAULT 3 COMMENT '最大尝试次数',
    `condition_config` text NOT NULL COMMENT '触发条件配置',
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

-- 回收打印任务表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_task` (
    `task_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '任务ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `scene_key` varchar(50) NOT NULL DEFAULT '' COMMENT '场景标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `trigger_key` varchar(100) NOT NULL DEFAULT '' COMMENT '触发事件标识',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型',
    `biz_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务ID',
    `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单ID',
    `device_id` int(11) NOT NULL DEFAULT 0 COMMENT '设备ID',
    `template_id` int(11) NOT NULL DEFAULT 0 COMMENT '模板ID',
    `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
    `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '打印机ID',
    `printer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '打印机名称',
    `copies` int(11) NOT NULL DEFAULT 1 COMMENT '打印份数',
    `priority` int(11) NOT NULL DEFAULT 100 COMMENT '优先级，越小越优先',
    `mode` varchar(20) NOT NULL DEFAULT 'auto' COMMENT '打印模式：auto/manual/reprint',
    `unique_key` varchar(191) NOT NULL DEFAULT '' COMMENT '幂等键',
    `payload` text NOT NULL COMMENT '业务入参快照',
    `variables_snapshot` text NOT NULL COMMENT '变量快照',
    `instruction_snapshot` mediumtext COMMENT '打印指令快照',
    `response_snapshot` text NOT NULL COMMENT '打印响应快照',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待执行 1执行中 2成功 3失败 4跳过 5取消',
    `fail_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '失败原因',
    `attempts` int(11) NOT NULL DEFAULT 0 COMMENT '已尝试次数',
    `max_attempts` int(11) NOT NULL DEFAULT 3 COMMENT '最大尝试次数',
    `next_retry_at` int(11) NOT NULL DEFAULT 0 COMMENT '下次重试时间',
    `operator_uid` int(11) NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    `finish_time` int(11) NOT NULL DEFAULT 0 COMMENT '完成时间',
    PRIMARY KEY (`task_id`),
    UNIQUE KEY `uk_site_unique` (`site_id`, `unique_key`),
    KEY `idx_status_retry` (`status`, `next_retry_at`),
    KEY `idx_site_scene` (`site_id`, `scene_key`),
    KEY `idx_site_biz` (`site_id`, `biz_type`, `biz_id`),
    KEY `idx_site_device` (`site_id`, `device_id`),
    KEY `idx_site_order` (`site_id`, `order_id`)
) COMMENT='回收打印任务表';

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
  `third_order_no` varchar(100) NOT NULL DEFAULT '' COMMENT '商户订单号',
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
  KEY `idx_third_order_no` (`site_id`,`third_order_no`),
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

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_dashboard_widget` (
  `widget_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '组件ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `widget_key` varchar(100) NOT NULL DEFAULT '' COMMENT '组件标识',
  `widget_name` varchar(100) NOT NULL DEFAULT '' COMMENT '组件名称',
  `widget_type` varchar(30) NOT NULL DEFAULT 'stat' COMMENT '组件类型 stat/chart/table/action/section',
  `data_key` varchar(100) NOT NULL DEFAULT '' COMMENT '指标标识',
  `data_scope` varchar(30) NOT NULL DEFAULT 'own' COMMENT '数据范围 own/site/assigned/none',
  `role_ids` text COMMENT '可见角色ID JSON数组，空表示不限制',
  `uids` text COMMENT '可见用户ID JSON数组，空表示不限制',
  `config` text COMMENT '组件扩展配置',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用 1启用',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`widget_id`),
  UNIQUE KEY `uk_site_widget` (`site_id`,`widget_key`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_site_type` (`site_id`,`widget_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收首页组件配置表';

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
