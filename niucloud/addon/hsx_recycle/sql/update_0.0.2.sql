-- hsx_recycle 0.0.2
-- 可控经营看板：异常阈值配置、打印变量配置预留

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
  ADD COLUMN `pay_no` varchar(64) NOT NULL DEFAULT '' COMMENT '最近一次设备打款批次号' AFTER `pay_uid`;

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
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待发送，1成功，2失败',
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
