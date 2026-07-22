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
  ADD COLUMN `export_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '导出状态：0-未导出，1-已导出' AFTER `site_id`,
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
  ADD COLUMN `cost_adjust_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '累计成本调整金额，负数为成本减少' AFTER `pay_no`,
  ADD COLUMN `cost_adjust_count` int NOT NULL DEFAULT 0 COMMENT '成本调整次数' AFTER `cost_adjust_amount`,
  ADD COLUMN `last_cost_adjust_time` int NOT NULL DEFAULT 0 COMMENT '最后成本调整时间' AFTER `cost_adjust_count`,
  ADD COLUMN `last_cost_adjust_no` varchar(64) NOT NULL DEFAULT '' COMMENT '最后成本调整单号' AFTER `last_cost_adjust_time`,
  ADD COLUMN `settlement_mode` varchar(20) NOT NULL DEFAULT 'recycle' COMMENT '结算模式：recycle-普通回收，consign-代卖' AFTER `last_cost_adjust_no`,
  ADD COLUMN `dispose_type` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '处置类型：pending-未处置，recycle-普通回收，return-退回，consign-代卖' AFTER `settlement_mode`,
  ADD COLUMN `dispose_status` tinyint NOT NULL DEFAULT 0 COMMENT '处置状态：0-未处置，1-已回收，2-已退回，3-已转代卖' AFTER `dispose_type`,
  ADD COLUMN `sale_destination` varchar(20) NOT NULL DEFAULT 'mall' COMMENT '销售去向：mall-商城销售，peer-同行出货，hold-暂存' AFTER `dispose_status`,
  ADD COLUMN `consignment_order_id` int NOT NULL DEFAULT 0 COMMENT '关联代卖订单ID' AFTER `sale_destination`,
  ADD COLUMN `return_order_id` int NOT NULL DEFAULT 0 COMMENT '关联退回订单ID' AFTER `consignment_order_id`,
  ADD COLUMN `return_time` int NOT NULL DEFAULT 0 COMMENT '退回处理时间' AFTER `return_order_id`,
  ADD COLUMN `return_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '退回备注' AFTER `return_time`;

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

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_device_cost_adjustment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `adjust_no` varchar(64) NOT NULL DEFAULT '' COMMENT '成本调整单号',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单编号',
  `device_id` int NOT NULL DEFAULT 0 COMMENT '设备ID',
  `device_imei` varchar(50) NOT NULL DEFAULT '' COMMENT '设备IMEI',
  `device_model` varchar(100) NOT NULL DEFAULT '' COMMENT '设备型号',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `adjust_type` varchar(50) NOT NULL DEFAULT '' COMMENT '调整类型',
  `adjust_type_name` varchar(50) NOT NULL DEFAULT '' COMMENT '调整类型名称',
  `before_cost` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '调整前成本',
  `adjust_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '调整金额绝对值',
  `adjust_delta` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '成本变动值',
  `after_cost` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '调整后成本',
  `customer_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '客户应退/应补金额',
  `customer_direction` varchar(30) NOT NULL DEFAULT 'none' COMMENT '客户资金方向：customer_refund/merchant_pay/none',
  `customer_handled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '客户差额是否已处理',
  `reason` varchar(1000) NOT NULL DEFAULT '' COMMENT '调整原因',
  `images` text COMMENT '凭证图片',
  `inventory_sync_tip` varchar(500) NOT NULL DEFAULT '' COMMENT '进销存同步提醒',
  `inventory_tip_confirmed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已确认同步提醒',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(100) NOT NULL DEFAULT '' COMMENT '操作人名称',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_adjust_no` (`site_id`,`adjust_no`),
  KEY `idx_site_device` (`site_id`,`device_id`),
  KEY `idx_site_order` (`site_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收设备成本调整记录表';

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



-- 历史表新增浏览量字段
ALTER TABLE `{{prefix}}recycle_category_quote_history` ADD COLUMN `view_count` int NOT NULL DEFAULT 0 COMMENT '该报价单浏览次数' AFTER `create_time`;

-- 用户退货地址地区反显字段
ALTER TABLE `{{prefix}}recycle_user_address` ADD COLUMN `province_id` int NOT NULL DEFAULT 0 COMMENT '省份ID' AFTER `name`;
ALTER TABLE `{{prefix}}recycle_user_address` ADD COLUMN `city_id` int NOT NULL DEFAULT 0 COMMENT '城市ID' AFTER `province_id`;
ALTER TABLE `{{prefix}}recycle_user_address` ADD COLUMN `district_id` int NOT NULL DEFAULT 0 COMMENT '区县ID' AFTER `city_id`;
ALTER TABLE `{{prefix}}recycle_user_address` ADD COLUMN `province_name` varchar(50) NOT NULL DEFAULT '' COMMENT '省份名称' AFTER `district_id`;
ALTER TABLE `{{prefix}}recycle_user_address` ADD COLUMN `city_name` varchar(50) NOT NULL DEFAULT '' COMMENT '城市名称' AFTER `province_name`;
ALTER TABLE `{{prefix}}recycle_user_address` ADD COLUMN `district_name` varchar(50) NOT NULL DEFAULT '' COMMENT '区县名称' AFTER `city_name`;
ALTER TABLE `{{prefix}}recycle_user_address` ADD COLUMN `detail_address` varchar(255) NOT NULL DEFAULT '' COMMENT '详细地址' AFTER `district_name`;

ALTER TABLE `{{prefix}}recycle_order`
  ADD COLUMN `order_source` varchar(20) NOT NULL DEFAULT 'customer' COMMENT '下单来源：customer-客户下单，agent-代下单' AFTER `flow_mode`,
  ADD COLUMN `agent_uid` int NOT NULL DEFAULT 0 COMMENT '代下单操作人ID' AFTER `order_source`,
  ADD COLUMN `agent_name` varchar(50) NOT NULL DEFAULT '' COMMENT '代下单操作人' AFTER `agent_uid`,
  ADD COLUMN `agent_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '代下单人联系方式' AFTER `agent_name`;

DROP TABLE IF EXISTS `{{prefix}}recycle_device_model_dict`;
CREATE TABLE `{{prefix}}recycle_device_model_dict` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `pid` int NOT NULL DEFAULT 0 COMMENT '上级ID',
  `level` tinyint NOT NULL DEFAULT 1 COMMENT '层级，从1开始',
  `node_type` varchar(30) NOT NULL DEFAULT 'unknown' COMMENT '节点类型：category/subcategory/brand/series/model/group/unknown',
  `node_name` varchar(100) NOT NULL DEFAULT '' COMMENT '节点名称',
  `source` varchar(50) NOT NULL DEFAULT 'manual' COMMENT '数据来源：manual或外部来源标识',
  `source_node_id` varchar(100) NOT NULL DEFAULT '' COMMENT '外部节点ID或手动节点编号',
  `source_parent_id` varchar(100) NOT NULL DEFAULT '' COMMENT '外部父级ID',
  `category_source_id` varchar(100) NOT NULL DEFAULT '' COMMENT '外部品类ID',
  `brand_source_id` varchar(100) NOT NULL DEFAULT '' COMMENT '外部品牌ID',
  `series_source_id` varchar(100) NOT NULL DEFAULT '' COMMENT '外部系列ID或内部系列编号',
  `product_source_id` varchar(100) NOT NULL DEFAULT '' COMMENT '外部产品ID',
  `model_full_name` varchar(255) NOT NULL DEFAULT '' COMMENT '完整路径',
  `extra_json` text NULL COMMENT '外部原始数据或扩展信息',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用，0停用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `is_hot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否热门',
  `select_count` int NOT NULL DEFAULT 0 COMMENT '被选择次数',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_parent_name` (`site_id`,`pid`,`node_name`),
  KEY `idx_site_source_node` (`site_id`,`source`,`source_node_id`),
  KEY `idx_site_category_source` (`site_id`,`source`,`category_source_id`),
  KEY `idx_site_brand_source` (`site_id`,`source`,`brand_source_id`),
  KEY `idx_site_series_source` (`site_id`,`source`,`series_source_id`),
  KEY `idx_site_product_source` (`site_id`,`source`,`product_source_id`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_site_pid` (`site_id`,`pid`),
  KEY `idx_site_level` (`site_id`,`level`),
  KEY `idx_site_select` (`site_id`,`is_hot`,`select_count`,`sort`)
) COMMENT='回收设备分类表';


CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_template_binding` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `target_type` varchar(30) NOT NULL DEFAULT 'model_dict' COMMENT '绑定对象：model_dict型号节点，global通用兜底',
  `target_id` int NOT NULL DEFAULT 0 COMMENT '绑定对象ID，通用兜底为0',
  `scene_key` varchar(80) NOT NULL DEFAULT 'manual_device_label' COMMENT '打印场景标识',
  `check_template_id` int NOT NULL DEFAULT 0 COMMENT '质检模板ID',
  `print_template_id` int NOT NULL DEFAULT 0 COMMENT '打印模板ID',
  `inherit_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许子级继承',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用，0停用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_target_scene` (`site_id`,`target_type`,`target_id`,`scene_key`),
  KEY `idx_site_scene` (`site_id`,`scene_key`,`status`)
) COMMENT='回收型号模板绑定表';

ALTER TABLE `{{prefix}}recycle_device`
  ADD COLUMN `refurbishment_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要整备：0-否，1-是' AFTER `price_remark`,
  ADD COLUMN `refurbishment_assignee_uid` int NOT NULL DEFAULT 0 COMMENT '建议整备负责人ID' AFTER `refurbishment_required`,
  ADD COLUMN `refurbishment_assignee_name` varchar(100) NOT NULL DEFAULT '' COMMENT '建议整备负责人姓名' AFTER `refurbishment_assignee_uid`,
  ADD COLUMN `refurbishment_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '整备原因' AFTER `refurbishment_assignee_name`,
  ADD COLUMN `refurbishment_items` text COMMENT '建议整备项目JSON' AFTER `refurbishment_reason`,
  ADD COLUMN `refurbishment_estimated_cost` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '预估整备成本' AFTER `refurbishment_items`,
  ADD COLUMN  `sale_destination` varchar(20) NOT NULL DEFAULT 'mall' COMMENT '销售去向：mall-商城销售，peer-同行出货，hold-暂存';


-- hsx_recycle 0.0.3
-- 下游流转回流：在回收设备上镜像 ERP/中台 的生命周期阶段，使回收侧能看全程（已入库/转中台/已定价/已售）。
-- 纯加法列，不改动现有状态机；由回收侧监听器幂等写入，缺失插件时列保持默认值，无副作用。

ALTER TABLE `{{prefix}}recycle_device`
  ADD COLUMN `downstream_stage` tinyint NOT NULL DEFAULT 0 COMMENT '下游流转阶段镜像：0-未流转,10-已入库,20-转中台待拍照,30-已定价可售,40-已售下架' AFTER `last_cost_adjust_time`,
  ADD COLUMN `downstream_stage_at` int NOT NULL DEFAULT 0 COMMENT '下游流转阶段更新时间' AFTER `downstream_stage`,
  ADD COLUMN `downstream_erp_asset_id` int NOT NULL DEFAULT 0 COMMENT '关联ERP资产ID(下游回流)' AFTER `downstream_stage_at`,
  ADD COLUMN `downstream_sale_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '中台销售定价(下游回流)' AFTER `downstream_erp_asset_id`,
  ADD COLUMN `downstream_event_id` varchar(64) NOT NULL DEFAULT '' COMMENT '最近一次应用的下游事件ID(幂等追溯)' AFTER `downstream_sale_price`,
  ADD COLUMN `target_warehouse_id` int NOT NULL DEFAULT 0 COMMENT '目标仓库ID(ERP安装时定价选择,0为未指定)' AFTER `downstream_event_id`,
  ADD COLUMN `target_warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '目标仓库名称快照' AFTER `target_warehouse_id`,
  ADD COLUMN `target_location_id` int NOT NULL DEFAULT 0 COMMENT '目标库位ID(定价手动选择,0为未指定)' AFTER `target_warehouse_name`,
  ADD COLUMN `target_location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '目标库位名称快照' AFTER `target_location_id`;

-- 修复历史安装中"报价单每日快照"任务的非法 cron：
-- 旧 time JSON 误用 minute 且缺 day，type=day 拼出 `0 * 23 */* * *`，被 workerman/crontab 判为非法字符串导致调度进程崩溃。
-- 修正为合法的"每天 23:00 执行"（day=1 即每天）。
-- UPDATE `{{prefix}}sys_schedule`
--   SET `time` = '{"type":"day","day":1,"hour":23,"min":0}'
--   WHERE `addon` = 'hsx_recycle' AND `key` = 'quote_daily_snapshot';

-- 选择频次统计表（通用：按场景记录某用户被选中的次数，用于"常用优先"排序，如整备负责人）
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_user_pick_stat` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `scene` varchar(50) NOT NULL DEFAULT '' COMMENT '选择场景，如 refurbishment_assignee',
  `user_id` int NOT NULL DEFAULT 0 COMMENT '被选用户ID',
  `pick_count` int NOT NULL DEFAULT 0 COMMENT '被选次数',
  `last_pick_at` int NOT NULL DEFAULT 0 COMMENT '最近被选时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_scene_user` (`site_id`,`scene`,`user_id`),
  KEY `idx_site_scene_count` (`site_id`,`scene`,`pick_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收选择频次统计表';

-- ============ 质检：参考表 + 数据表(全ID映射) ============
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_dict` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `dict_type` varchar(10) NOT NULL DEFAULT '' COMMENT 'group分类/field检测项/option选项',
  `text` varchar(255) NOT NULL DEFAULT '' COMMENT '中文文本',
  `severity` varchar(16) NOT NULL DEFAULT 'normal' COMMENT '仅option用: normal/general/abnormal',
  `is_user_modified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户改过=1，重导不覆盖',
  `sort` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_type_text` (`site_id`,`dict_type`,`text`(180)),
  KEY `idx_site_type_sev` (`site_id`,`dict_type`,`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检参考表(字典:分类/检测项/选项)';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_import_batch` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `source` varchar(40) NOT NULL DEFAULT 'paijitang',
  `file_name` varchar(255) NOT NULL DEFAULT '',
  `total_rows` int NOT NULL DEFAULT 0,
  `inserted` int NOT NULL DEFAULT 0,
  `updated` int NOT NULL DEFAULT 0,
  `skipped_same` int NOT NULL DEFAULT 0,
  `skipped_user` int NOT NULL DEFAULT 0,
  `new_dict` int NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'processing',
  `error_message` varchar(1000) NOT NULL DEFAULT '',
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site` (`site_id`,`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检导入批次';

-- recycle_check_option 加 severity(级别)列（仅 0.0.1/0.0.2 升级时执行；全新安装已在 install.sql 含此列）
ALTER TABLE `{{prefix}}recycle_check_option`
  ADD COLUMN `severity` varchar(16) NOT NULL DEFAULT 'normal' COMMENT '级别 normal/general/abnormal' AFTER `is_default`;

-- 设备表加 电池效率/单机全套 列(供质检勾选回写 + 打印单独显示；全新安装已在 install.sql 含)
ALTER TABLE `{{prefix}}recycle_device`
  ADD COLUMN `battery` varchar(50) NOT NULL DEFAULT '' COMMENT '电池效率/健康（如85%）' AFTER `color`,
  ADD COLUMN `package_type` varchar(50) NOT NULL DEFAULT '' COMMENT '单机/全套等套装情况' AFTER `battery`;

-- 设备加 成色等级 列(质检「成色等级」单选回写 + 打印 {condition_grade}；全新安装已在 install.sql 含)
ALTER TABLE `{{prefix}}recycle_device`
  ADD COLUMN `condition_grade` varchar(20) NOT NULL DEFAULT '' COMMENT '成色等级（10新/99新…）' AFTER `package_type`;


-- hsx_recycle 0.0.4（线上 0.0.3 → 0.0.4 一次性升级）
-- 任务驱动工单系统数据地基 + 性能优化，全部合并在此一个升级文件：
--   1) 实时在途计数 recycle_stat_current
--   2) 按日流水汇总 recycle_stat_daily
--   3) 任务认领 recycle_task_claim
--   4) recycle_device / recycle_order 补高频索引（在线 DDL）
--   5) 每日维度汇总 recycle_stat_daily_dim（型号/分类/成色/来源，抗千万级）
-- 表均 CREATE TABLE IF NOT EXISTS，已手动建过的会自动跳过。


-- 2. 实时态计数（看板/待办读它，免聚合）
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stat_current` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `metric_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '指标键:stage_check/stage_price/stage_confirm/stage_pay 等,当前在该环节台数',
  `uid` int NOT NULL DEFAULT 0 COMMENT '维度:0=全站汇总,>0=经手人',
  `value` int NOT NULL DEFAULT 0 COMMENT '当前数量',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_site_metric_uid` (`site_id`,`metric_key`,`uid`) USING BTREE,
  KEY `site_id` (`site_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收实时态计数(看板/待办读它)';

-- 3. 按日流水汇总（趋势/绩效读它）
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stat_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `stat_date` int NOT NULL DEFAULT 0 COMMENT '统计日 YYYYMMDD',
  `metric_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '指标键:enter_check/done_check/recycled/paid 等,当日发生量',
  `uid` int NOT NULL DEFAULT 0 COMMENT '维度:0=全站,>0=经手人',
  `value` int NOT NULL DEFAULT 0 COMMENT '台数',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_site_date_metric_uid` (`site_id`,`stat_date`,`metric_key`,`uid`) USING BTREE,
  KEY `site_date` (`site_id`,`stat_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收按日流水汇总(趋势/绩效读它)';

-- 任务驱动工单 · 认领表：店员将某环节的某台设备认领到人（责任到人）。
-- 纯新增表，不改动设备主表/状态机。一台设备在一个环节最多一条认领记录；进入下一环节即另起。

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_task_claim` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `device_id` int NOT NULL DEFAULT 0 COMMENT '设备ID',
  `stage_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '环节标识',
  `assignee_uid` int NOT NULL DEFAULT 0 COMMENT '认领人UID',
  `assignee_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '认领人名称快照',
  `claimed_at` int NOT NULL DEFAULT 0 COMMENT '认领时间',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_site_device_stage` (`site_id`,`device_id`,`stage_key`) USING BTREE,
  KEY `assignee` (`site_id`,`assignee_uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收任务认领(责任到人)';

ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_status_pay` (`site_id`,`status`,`pay_status`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_order` (`site_id`,`order_id`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_create` (`site_id`,`create_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_category` (`site_id`,`category_id`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_check_uid` (`site_id`,`check_uid`,`check_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_price_uid` (`site_id`,`price_uid`,`final_price_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_status` (`site_id`,`status`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_sign_at` (`site_id`,`sign_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_pay_time` (`site_id`,`pay_time`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_create` (`site_id`,`create_at`), ALGORITHM=INPLACE, LOCK=NONE;

-- 每日维度汇总表：把"型号分布/分类排行/成色/来源"这类维度统计预聚合到按日小表，
-- 分析页读这张小表(大小=天数×维度数,不随设备总量增长)，天然抗千万级；大表只留给明细钻取。
-- 历史天由"懒回填"算一次即固定，当天实时算。

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stat_daily_dim` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `stat_date` int NOT NULL DEFAULT 0 COMMENT '统计日 YYYYMMDD',
  `dim_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '维度类型:category/model/grade/source',
  `dim_value` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '维度值:分类ID/型号名/成色/来源',
  `cnt` int NOT NULL DEFAULT 0 COMMENT '当日该维度设备数(按 create_at 归日)',
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '当日该维度金额(final_price 合计)',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_dim` (`site_id`,`stat_date`,`dim_type`,`dim_value`) USING BTREE,
  KEY `idx_site_type_date` (`site_id`,`dim_type`,`stat_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收每日维度汇总(型号/分类/成色/来源)';

-- ===== 快递公司 + 电子面单模板（原 0.0.8，合并至此）=====

-- 快递公司：统一字典，供电子面单模板/发件下拉选择；存各服务商的编码映射与可选业务类型/打印样式
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_delivery_company` (
  `company_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `company_name` varchar(60) NOT NULL DEFAULT '' COMMENT '快递公司名称',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT 'LOGO',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '官网链接',
  `express_code` varchar(50) NOT NULL DEFAULT '' COMMENT '通用/物流跟踪编码',
  `kuaidi100_com` varchar(50) NOT NULL DEFAULT '' COMMENT '快递100编码(kuaidicom)',
  `yisu_product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '易速产品编码(productCode)',
  `electronic_sheet_switch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否支持电子面单 0否1是',
  `exp_type` text COMMENT '业务类型列表JSON [{text,value}]',
  `print_style` text COMMENT '打印样式列表JSON [{template_name,template_size}]',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用1启用',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`company_id`),
  KEY `idx_site_status` (`site_id`,`status`,`electronic_sheet_switch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收-快递公司字典';

-- 电子面单模板：可管理对象（列表+新建向导+设默认）。provider 指向执行引擎(yisu/kuaidi100)；print_channel 决定打印方式(可切换)
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_express_sheet` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `template_name` varchar(60) NOT NULL DEFAULT '' COMMENT '模板名称',
  `provider` varchar(30) NOT NULL DEFAULT '' COMMENT '执行服务商 yisu/kuaidi100',
  `express_company_id` int NOT NULL DEFAULT '0' COMMENT '快递公司ID',
  `exp_type` varchar(50) NOT NULL DEFAULT '' COMMENT '业务类型值(来自公司exp_type)',
  `exp_type_name` varchar(60) NOT NULL DEFAULT '' COMMENT '业务类型名称',
  `print_style` varchar(60) NOT NULL DEFAULT '' COMMENT '打印样式标识(来自公司print_style)',
  `customer_name` varchar(120) NOT NULL DEFAULT '' COMMENT '电子面单客户账号',
  `customer_pwd` varchar(255) NOT NULL DEFAULT '' COMMENT '电子面单密码',
  `send_site` varchar(60) NOT NULL DEFAULT '' COMMENT '发件网点',
  `send_staff` varchar(60) NOT NULL DEFAULT '' COMMENT '发件员',
  `month_code` varchar(60) NOT NULL DEFAULT '' COMMENT '月结编码',
  `pay_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '邮费支付方式 1现付2到付3月结',
  `output_type` varchar(10) NOT NULL DEFAULT 'IMAGE' COMMENT '面单形式 IMAGE/HTML/CLOUD',
  `print_channel` varchar(10) NOT NULL DEFAULT 'browser' COMMENT '打印方式 browser网页/cloud云打印/lodop本地',
  `temp_id` varchar(120) NOT NULL DEFAULT '' COMMENT '面单模板ID(快递100)',
  `child_temp_id` varchar(120) NOT NULL DEFAULT '' COMMENT '子模板ID',
  `back_temp_id` varchar(120) NOT NULL DEFAULT '' COMMENT '回单模板ID',
  `siid` varchar(120) NOT NULL DEFAULT '' COMMENT '云打印机设备码(CLOUD)',
  `is_notice` tinyint(1) NOT NULL DEFAULT '0' COMMENT '上门揽件 0否1是',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用1启用',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认 0否1是',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_site_company` (`site_id`,`express_company_id`,`status`),
  KEY `idx_site_default` (`site_id`,`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收-电子面单模板';

-- 0.0.9 方案A：公司与服务商绑定拆分
-- 1) 新增「公司-服务商绑定」表：一家公司可对接多个服务商，各自编码/面单能力独立
-- 2) 从快递公司表移除"塞在一起"的服务商编码与面单字段（迁移到绑定表）

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_delivery_company_provider` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `company_id` int NOT NULL DEFAULT '0' COMMENT '快递公司ID',
  `provider` varchar(30) NOT NULL DEFAULT '' COMMENT '服务商 yisu/kuaidi100',
  `provider_code` varchar(60) NOT NULL DEFAULT '' COMMENT '该服务商下的公司编码(kuaidicom / 易速productCode)',
  `electronic_sheet_switch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '该服务商是否出面单 0否1是',
  `exp_type` text COMMENT '业务类型列表JSON [{text,value}]',
  `print_style` text COMMENT '打印样式列表JSON [{template_name,template_size}]',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用1启用',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_company_provider` (`site_id`,`company_id`,`provider`),
  KEY `idx_site_provider` (`site_id`,`provider`,`electronic_sheet_switch`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收-快递公司服务商绑定';

-- 快递公司表精简：移除按服务商的编码与面单字段（已迁到绑定表）
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `kuaidi100_com`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `yisu_product_code`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `electronic_sheet_switch`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `exp_type`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `print_style`;


-- hsx_recycle 0.0.10
-- 拍机堂质检模板导入改为紧凑结构存储：导入模板只写模板主表 schema_json，
-- 验机 schema 接口按需展开，避免批量写入大量一次性 group/field/option 明细数据。

ALTER TABLE `{{prefix}}recycle_check_template`
  ADD COLUMN `schema_hash` varchar(32) NOT NULL DEFAULT '' COMMENT '紧凑模板结构hash' AFTER `version`,
  ADD COLUMN `schema_json` longtext NULL COMMENT '导入模板紧凑结构JSON' AFTER `schema_hash`;


-- hsx_recycle 0.0.11
-- 设备分类 Excel 导入改为后台任务，支持进度、结果、失败重试和历史记录。

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_device_model_import_task` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT '操作人UID',
  `operator_name` varchar(60) NOT NULL DEFAULT '' COMMENT '操作人名称',
  `source` varchar(50) NOT NULL DEFAULT 'recycle_spider' COMMENT '数据来源',
  `file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL DEFAULT '' COMMENT '服务端文件路径',
  `sheet_name` varchar(120) NOT NULL DEFAULT '' COMMENT '工作表名称',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/queued/processing/completed/partial/failed',
  `queue_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '创建时是否启用队列',
  `total_rows` int NOT NULL DEFAULT 0 COMMENT '数据总行数',
  `processed_rows` int NOT NULL DEFAULT 0 COMMENT '已处理行数',
  `created_count` int NOT NULL DEFAULT 0 COMMENT '新增数量',
  `updated_count` int NOT NULL DEFAULT 0 COMMENT '更新数量',
  `skipped_count` int NOT NULL DEFAULT 0 COMMENT '跳过数量',
  `error_count` int NOT NULL DEFAULT 0 COMMENT '错误数量',
  `result_json` longtext NULL COMMENT '结果与错误样例JSON',
  `message` varchar(500) NOT NULL DEFAULT '' COMMENT '任务提示',
  `error_message` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
  `start_at` int NOT NULL DEFAULT 0 COMMENT '开始时间',
  `finish_at` int NOT NULL DEFAULT 0 COMMENT '完成时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_site_create` (`site_id`,`create_at`)
) COMMENT='回收设备分类异步导入任务';


-- 回收任务从“自行认领”升级为“可指定、可转交、可追溯”。
ALTER TABLE `{{prefix}}recycle_task_claim`
  ADD COLUMN `assigner_uid` int NOT NULL DEFAULT 0 COMMENT '分配人UID' AFTER `assignee_name`,
  ADD COLUMN `assigner_name` varchar(50) NOT NULL DEFAULT '' COMMENT '分配人名称快照' AFTER `assigner_uid`,
  ADD COLUMN `assignment_mode` varchar(20) NOT NULL DEFAULT 'claim' COMMENT 'claim认领/assign指定/transfer转交' AFTER `assigner_name`,
  ADD COLUMN `assigned_at` int NOT NULL DEFAULT 0 COMMENT '最近分配时间' AFTER `claimed_at`;

-- UPDATE `{{prefix}}recycle_task_claim`
-- SET `assigner_uid` = `assignee_uid`,
--     `assigner_name` = `assignee_name`,
--     `assignment_mode` = 'claim',
--     `assigned_at` = `claimed_at`
-- WHERE `assigned_at` = 0;

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_task_assignment_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `device_id` int NOT NULL DEFAULT 0 COMMENT '设备ID，签收环节为订单ID',
  `stage_key` varchar(50) NOT NULL DEFAULT '' COMMENT '环节标识',
  `from_uid` int NOT NULL DEFAULT 0 COMMENT '原责任人UID',
  `from_name` varchar(50) NOT NULL DEFAULT '' COMMENT '原责任人名称',
  `to_uid` int NOT NULL DEFAULT 0 COMMENT '新责任人UID',
  `to_name` varchar(50) NOT NULL DEFAULT '' COMMENT '新责任人名称',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT '操作人UID',
  `operator_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人名称',
  `assignment_mode` varchar(20) NOT NULL DEFAULT 'assign' COMMENT 'claim/assign/transfer',
  `event_id` varchar(100) NOT NULL DEFAULT '' COMMENT '分配事件唯一标识',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_event` (`site_id`,`event_id`),
  KEY `idx_task` (`site_id`,`device_id`,`stage_key`,`create_at`),
  KEY `idx_assignee` (`site_id`,`to_uid`,`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收任务分配与转交日志';
