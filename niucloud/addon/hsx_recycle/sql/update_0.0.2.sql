-- hsx_recycle 0.0.2
-- 可控经营看板：异常阈值配置、打印变量配置预留

CREATE TABLE IF NOT EXISTS `{{prefix}}sys_adminapp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL DEFAULT '0' COMMENT '用户id',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点id',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '设置项类型  app  应用  stat统计  todo 待办事项',
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '配置数据',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '添加时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `create_time` (`create_time`) USING BTREE,
  KEY `site_id` (`site_id`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `type` (`type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='用户手机管理端偏好设置表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_alert_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `rule_key` varchar(100) NOT NULL DEFAULT '' COMMENT '规则标识',
  `rule_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则名称',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '规则说明',
  `condition_type` varchar(50) NOT NULL DEFAULT '' COMMENT '条件类型 timeout/rate/amount/count',
  `threshold_value` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '阈值',
  `threshold_unit` varchar(30) NOT NULL DEFAULT '' COMMENT '阈值单位 hour/percent/yuan/count',
  `level` varchar(30) NOT NULL DEFAULT 'warning' COMMENT '级别 info/warning/danger',
  `message_template` varchar(500) NOT NULL DEFAULT '' COMMENT '提醒文案模板',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用 1启用',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_rule` (`site_id`,`rule_key`),
  KEY `idx_site_status` (`site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收看板异常规则配置表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_variable` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `variable_key` varchar(100) NOT NULL DEFAULT '' COMMENT '变量标识',
  `variable_name` varchar(100) NOT NULL DEFAULT '' COMMENT '变量名称',
  `category` varchar(50) NOT NULL DEFAULT '' COMMENT '变量分类 order/device/payment/custom',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '变量说明',
  `sample_value` varchar(255) NOT NULL DEFAULT '' COMMENT '示例值',
  `source_type` varchar(30) NOT NULL DEFAULT 'field' COMMENT '来源类型 field/json/custom',
  `source_path` varchar(255) NOT NULL DEFAULT '' COMMENT '来源路径',
  `alias_keys` text COMMENT '兼容别名 JSON数组',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用 1启用',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_variable` (`site_id`,`variable_key`),
  KEY `idx_site_category` (`site_id`,`category`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收打印变量配置表';

ALTER TABLE `{{prefix}}recycle_order`
  ADD COLUMN `flow_mode` varchar(20) NOT NULL DEFAULT 'order' COMMENT '订单流转模式：order-整单流转，device-按设备流转' AFTER `site_id`;

ALTER TABLE `{{prefix}}recycle_device`
  ADD COLUMN `check_template_id` int NOT NULL DEFAULT 0 COMMENT '质检模板ID' AFTER `category_id`,
  ADD COLUMN `confirm_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '报价确认状态：0-待确认，1-已确认，2-已拒绝' AFTER `final_status`,
  ADD COLUMN `confirm_time` int NOT NULL DEFAULT 0 COMMENT '报价确认时间' AFTER `confirm_status`,
  ADD COLUMN `confirm_member_id` int NOT NULL DEFAULT 0 COMMENT '报价确认会员ID' AFTER `confirm_time`,
  ADD COLUMN `confirm_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '报价确认备注' AFTER `confirm_member_id`,
  ADD COLUMN `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '设备打款状态：0-未打款，1-已打款' AFTER `confirm_remark`,
  ADD COLUMN `pay_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '设备实付金额' AFTER `pay_status`,
  ADD COLUMN `pay_time` int NOT NULL DEFAULT 0 COMMENT '设备打款时间' AFTER `pay_amount`,
  ADD COLUMN `pay_uid` int NOT NULL DEFAULT 0 COMMENT '设备打款操作人ID' AFTER `pay_time`,
  ADD COLUMN `pay_no` varchar(64) NOT NULL DEFAULT '' COMMENT '最近一次设备打款批次号' AFTER `pay_uid`,
  ADD COLUMN `settlement_mode` varchar(20) NOT NULL DEFAULT 'recycle' COMMENT '结算模式：recycle-普通回收，consign-代卖' AFTER `pay_no`,
  ADD COLUMN `dispose_type` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '处置类型：pending-未处置，recycle-普通回收，return-退回，consign-代卖' AFTER `settlement_mode`,
  ADD COLUMN `dispose_status` tinyint NOT NULL DEFAULT 0 COMMENT '处置状态：0-未处置，1-已回收，2-已退回，3-已转代卖' AFTER `dispose_type`,
  ADD COLUMN `consignment_order_id` int NOT NULL DEFAULT 0 COMMENT '关联代卖订单ID' AFTER `dispose_status`,
  ADD COLUMN `return_order_id` int NOT NULL DEFAULT 0 COMMENT '关联退回订单ID' AFTER `consignment_order_id`;

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_consignment_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '代卖订单ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `consignment_no` varchar(64) NOT NULL DEFAULT '' COMMENT '代卖单号',
  `source_order_id` int NOT NULL DEFAULT 0 COMMENT '来源回收订单ID',
  `source_order_no` varchar(64) NOT NULL DEFAULT '' COMMENT '来源回收订单号',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '来源设备ID',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `customer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '客户姓名',
  `customer_phone` varchar(30) NOT NULL DEFAULT '' COMMENT '客户手机号',
  `device_imei` varchar(50) NOT NULL DEFAULT '' COMMENT '设备IMEI',
  `device_model` varchar(100) NOT NULL DEFAULT '' COMMENT '设备型号',
  `quote_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '原回收报价',
  `expected_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '客户期望价',
  `min_settlement_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '客户最低结算价',
  `listing_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '挂牌价',
  `sold_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '实际成交价',
  `settlement_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '客户结算金额',
  `service_fee` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '服务收益',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '代卖状态：0待上架 1代卖中 2已售出 3待结算 4已结算 5已取消 6已退回',
  `pay_status` tinyint NOT NULL DEFAULT 0 COMMENT '结算状态：0未结算 1已结算',
  `listed_time` int NOT NULL DEFAULT 0 COMMENT '上架时间',
  `sold_time` int NOT NULL DEFAULT 0 COMMENT '售出时间',
  `settle_time` int NOT NULL DEFAULT 0 COMMENT '结算时间',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '打款时间',
  `pay_uid` int NOT NULL DEFAULT 0 COMMENT '打款操作人',
  `cancel_time` int NOT NULL DEFAULT 0 COMMENT '取消时间',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '最近操作人',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_no` (`site_id`,`consignment_no`),
  KEY `idx_site_order` (`site_id`,`source_order_id`),
  KEY `idx_site_device` (`site_id`,`source_device_id`),
  KEY `idx_site_status` (`site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收代卖订单表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_consignment_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `consignment_id` int NOT NULL DEFAULT 0 COMMENT '代卖订单ID',
  `source_order_id` int NOT NULL DEFAULT 0 COMMENT '来源订单ID',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '来源设备ID',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(100) NOT NULL DEFAULT '' COMMENT '操作人名称',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作动作',
  `old_status` tinyint NOT NULL DEFAULT 0 COMMENT '原状态',
  `new_status` tinyint NOT NULL DEFAULT 0 COMMENT '新状态',
  `before_data` json COMMENT '变更前数据',
  `after_data` json COMMENT '变更后数据',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_consignment` (`site_id`,`consignment_id`),
  KEY `idx_site_device` (`site_id`,`source_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收代卖日志表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_device_payment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `pay_no` varchar(64) NOT NULL DEFAULT '' COMMENT '打款批次号',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `device_id` int NOT NULL DEFAULT 0 COMMENT '设备ID',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单编号',
  `device_imei` varchar(50) NOT NULL DEFAULT '' COMMENT '设备IMEI',
  `device_model` varchar(100) NOT NULL DEFAULT '' COMMENT '设备型号',
  `amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '打款金额',
  `pay_type` varchar(50) NOT NULL DEFAULT '' COMMENT '打款方式',
  `pay_account` varchar(255) NOT NULL DEFAULT '' COMMENT '收款账号',
  `pay_name` varchar(50) NOT NULL DEFAULT '' COMMENT '收款人',
  `pay_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '打款备注',
  `payment_images` text COMMENT '打款凭证图片',
  `pay_uid` int NOT NULL DEFAULT 0 COMMENT '打款操作人ID',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '打款时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_order` (`site_id`,`order_id`),
  KEY `idx_site_device` (`site_id`,`device_id`),
  KEY `idx_pay_no` (`pay_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收设备打款记录表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_notice_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单编号',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `notice_key` varchar(100) NOT NULL DEFAULT '' COMMENT '通知标识',
  `scene` varchar(100) NOT NULL DEFAULT '' COMMENT '业务场景',
  `receiver_type` varchar(30) NOT NULL DEFAULT 'member' COMMENT '接收人类型',
  `receiver_id` int NOT NULL DEFAULT 0 COMMENT '接收人ID',
  `device_ids` text COMMENT '设备ID JSON数组',
  `device_count` int NOT NULL DEFAULT 0 COMMENT '涉及设备数',
  `target_page` varchar(500) NOT NULL DEFAULT '' COMMENT '跳转页面',
  `request_data` text COMMENT '发送参数JSON',
  `response_data` text COMMENT '响应JSON',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待发送，1成功，2失败，3重复跳过',
  `fail_reason` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
  `send_time` int NOT NULL DEFAULT 0 COMMENT '发送时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_order` (`site_id`,`order_id`),
  KEY `idx_site_member` (`site_id`,`member_id`),
  KEY `idx_notice_status` (`notice_key`,`status`),
  KEY `idx_create_at` (`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收通知发送日志表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_scene` (
    `scene_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '场景ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `scene_key` varchar(50) NOT NULL DEFAULT '' COMMENT '场景标识',
    `trigger_key` varchar(100) NOT NULL DEFAULT '' COMMENT '触发事件标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型：device-设备，order-订单，return-退货，consignment-代卖',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收打印场景表';

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收打印任务表';

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收打印日志表';

-- 分类报价单历史快照表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_category_quote_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `category_id` int NOT NULL DEFAULT '0' COMMENT '分类ID',
  `images` text NOT NULL COMMENT '报价单图片快照',
  `operator_id` int NOT NULL DEFAULT '0' COMMENT '操作人ID',
  `operator_name` varchar(100) NOT NULL DEFAULT '' COMMENT '操作人名称',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '生成时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_cat_time` (`site_id`,`category_id`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收分类报价单历史快照表';

-- 老数据回填：将现有非空 images 按分类回填一条历史
INSERT INTO `{{prefix}}recycle_category_quote_history`
  (`site_id`, `category_id`, `images`, `operator_id`, `operator_name`, `remark`, `create_time`)
SELECT
  `site_id`,
  `category_id`,
  `images`,
  0,
  '',
  '初始化历史记录',
  IF(`update_time` > 0, `update_time`, IF(`create_time` > 0, `create_time`, UNIX_TIMESTAMP()))
FROM `{{prefix}}recycle_category`
WHERE `images` IS NOT NULL AND `images` <> '';

-- 历史表新增浏览量字段
ALTER TABLE `{{prefix}}recycle_category_quote_history` ADD COLUMN `view_count` int NOT NULL DEFAULT 0 COMMENT '该报价单浏览次数' AFTER `create_time`;
