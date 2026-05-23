SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for {{prefix}}hsx_phone_query_category
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}hsx_phone_query_category`;
CREATE TABLE `{{prefix}}hsx_phone_query_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '查询项目ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `channel_key` varchar(60) NOT NULL DEFAULT '' COMMENT '渠道标识，如 gkdt_main、3023_main',
  `channel_name` varchar(100) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `type_id` int NOT NULL DEFAULT 0 COMMENT '分类：1苹果 2安卓 9其他',
  `service_code` varchar(100) NOT NULL DEFAULT '' COMMENT '统一查询服务编码，如 apple_coverage',
  `query_param` varchar(50) NOT NULL DEFAULT 'sn' COMMENT '默认查询参数名，如 sn、imei、code、phone',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '查询项目名称',
  `price` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '销售价格',
  `cost_price` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '第三方成本价',
  `is_show` tinyint NOT NULL DEFAULT 1 COMMENT '是否显示：0隐藏 1显示',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序，越大越靠前',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_channel_service` (`site_id`, `channel_key`, `service_code`),
  KEY `idx_site_channel` (`site_id`, `channel_key`),
  KEY `idx_site_channel_service` (`site_id`, `channel_key`, `service_code`),
  KEY `idx_site_type` (`site_id`, `type_id`),
  KEY `idx_service_code` (`site_id`, `service_code`),
  KEY `idx_site_show_sort` (`site_id`, `is_show`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='手机查询项目表';

-- ----------------------------
-- Table structure for {{prefix}}hsx_phone_query_info
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}hsx_phone_query_info`;
CREATE TABLE `{{prefix}}hsx_phone_query_info` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '查询记录ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int unsigned NOT NULL DEFAULT 0 COMMENT '会员ID',
  `order_id` int unsigned NOT NULL DEFAULT 0 COMMENT '关联订单ID',
  `sn` varchar(80) NOT NULL DEFAULT '' COMMENT 'IMEI/SN/查询串号',
  `type_id` int unsigned NOT NULL DEFAULT 0 COMMENT '查询项目ID',
  `service_code` varchar(100) NOT NULL DEFAULT '' COMMENT '统一查询服务编码',
  `channel_key` varchar(60) NOT NULL DEFAULT '' COMMENT '渠道标识',
  `query_param` varchar(50) NOT NULL DEFAULT '' COMMENT '查询参数名',
  `pid` int NOT NULL DEFAULT 0 COMMENT '前端分类索引',
  `info` longtext NULL COMMENT '查询结果JSON',
  `is_look` tinyint NOT NULL DEFAULT 0 COMMENT '是否已读：0未读 1已读',
  `pay_type` varchar(32) NOT NULL DEFAULT '' COMMENT '支付类型：money现金 point积分 balance旧余额',
  `money` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '本次查询金额',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_order` (`site_id`, `order_id`),
  KEY `idx_site_channel_time` (`site_id`, `channel_key`, `create_time`),
  KEY `idx_site_service_time` (`site_id`, `service_code`, `create_time`),
  KEY `idx_site_member_time` (`site_id`, `member_id`, `create_time`),
  KEY `idx_sn_type_time` (`sn`, `type_id`, `create_time`),
  KEY `idx_member_look_time` (`member_id`, `is_look`, `create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='手机查询结果记录表';

-- ----------------------------
-- Table structure for {{prefix}}hsx_phone_query_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}hsx_phone_query_order`;
CREATE TABLE `{{prefix}}hsx_phone_query_order` (
  `order_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单编号',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int unsigned NOT NULL DEFAULT 0 COMMENT '会员ID',
  `imeis` text NOT NULL COMMENT '查询串号，多个英文逗号分隔',
  `type_id` int unsigned NOT NULL DEFAULT 0 COMMENT '查询项目ID',
  `pid` int NOT NULL DEFAULT 0 COMMENT '前端分类索引',
  `service_code` varchar(100) NOT NULL DEFAULT '' COMMENT '统一查询服务编码',
  `service_name` varchar(255) NOT NULL DEFAULT '' COMMENT '查询项目名称',
  `channel_key` varchar(60) NOT NULL DEFAULT '' COMMENT '渠道标识',
  `query_param` varchar(50) NOT NULL DEFAULT '' COMMENT '查询参数名',
  `endpoint_type` varchar(40) NOT NULL DEFAULT '' COMMENT '接口类型：path/service_id',
  `endpoint_value` varchar(255) NOT NULL DEFAULT '' COMMENT '接口路径或服务编码',
  `pay_type` varchar(32) NOT NULL DEFAULT 'money' COMMENT '支付类型：money现金 point积分',
  `unit_price` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '下单时单价',
  `unit_cost` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '下单时单次成本',
  `pay_money` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '实收/折算金额',
  `pay_point` int NOT NULL DEFAULT 0 COMMENT '积分支付数量',
  `query_count` int unsigned NOT NULL DEFAULT 1 COMMENT '查询数量',
  `success_count` int unsigned NOT NULL DEFAULT 0 COMMENT '成功查询数量',
  `fail_count` int unsigned NOT NULL DEFAULT 0 COMMENT '失败查询数量',
  `cost_money` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '第三方成本',
  `profit_money` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '利润',
  `provider_name` varchar(100) NOT NULL DEFAULT '' COMMENT '服务商标识',
  `channel_name` varchar(100) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `from_cache_count` int unsigned NOT NULL DEFAULT 0 COMMENT '24小时缓存命中数量',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '状态：0待支付 1已支付 2查询中 3成功 -1失败',
  `refund_status` tinyint NOT NULL DEFAULT 0 COMMENT '退款状态：0未退款 1已退款',
  `notice_status` tinyint NOT NULL DEFAULT 0 COMMENT '通知状态：0未发送 1已发送 2失败',
  `notice_time` int NOT NULL DEFAULT 0 COMMENT '通知时间',
  `notice_error` varchar(500) NOT NULL DEFAULT '' COMMENT '通知失败原因',
  `result_ids` varchar(500) NOT NULL DEFAULT '' COMMENT '关联查询记录ID，多个英文逗号分隔',
  `fail_reason` text NULL COMMENT '失败原因',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '支付时间',
  `finish_time` int NOT NULL DEFAULT 0 COMMENT '完成时间',
  `refund_time` int NOT NULL DEFAULT 0 COMMENT '退款时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_site_member` (`site_id`, `member_id`),
  KEY `idx_site_channel` (`site_id`, `channel_key`),
  KEY `idx_site_service` (`site_id`, `service_code`),
  KEY `idx_site_status` (`site_id`, `status`),
  KEY `idx_site_create_time` (`site_id`, `create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='手机查询订单表';

-- ----------------------------
-- Table structure for {{prefix}}hsx_phone_query_api_log
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}hsx_phone_query_api_log`;
CREATE TABLE `{{prefix}}hsx_phone_query_api_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '调用日志ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int unsigned NOT NULL DEFAULT 0 COMMENT '关联订单ID',
  `result_id` int unsigned NOT NULL DEFAULT 0 COMMENT '关联查询结果ID',
  `member_id` int unsigned NOT NULL DEFAULT 0 COMMENT '会员ID',
  `type_id` int unsigned NOT NULL DEFAULT 0 COMMENT '查询项目ID',
  `service_code` varchar(100) NOT NULL DEFAULT '' COMMENT '统一查询服务编码',
  `service_name` varchar(255) NOT NULL DEFAULT '' COMMENT '查询项目名称',
  `provider_key` varchar(60) NOT NULL DEFAULT '' COMMENT '服务商类型：path_query/service_id_query',
  `provider_name` varchar(100) NOT NULL DEFAULT '' COMMENT '服务商名称',
  `channel_key` varchar(60) NOT NULL DEFAULT '' COMMENT '渠道标识',
  `channel_name` varchar(100) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `endpoint_type` varchar(40) NOT NULL DEFAULT '' COMMENT '接口类型：path/service_id',
  `endpoint_value` varchar(255) NOT NULL DEFAULT '' COMMENT '接口路径或服务编码',
  `query_param` varchar(50) NOT NULL DEFAULT '' COMMENT '查询参数名',
  `query_code` varchar(120) NOT NULL DEFAULT '' COMMENT '查询串号',
  `request_method` varchar(10) NOT NULL DEFAULT 'GET' COMMENT '请求方式',
  `request_url` varchar(500) NOT NULL DEFAULT '' COMMENT '请求地址',
  `request_params` text NULL COMMENT '脱敏后的请求参数JSON',
  `response_code` varchar(50) NOT NULL DEFAULT '' COMMENT '第三方返回状态码',
  `response_message` varchar(500) NOT NULL DEFAULT '' COMMENT '第三方返回消息',
  `response_data` longtext NULL COMMENT '第三方返回JSON',
  `cost_price` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '本次第三方成本',
  `duration_ms` int unsigned NOT NULL DEFAULT 0 COMMENT '接口耗时毫秒',
  `status` varchar(20) NOT NULL DEFAULT 'success' COMMENT '调用状态：success/fail',
  `error_message` varchar(1000) NOT NULL DEFAULT '' COMMENT '异常信息',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_time` (`site_id`, `create_time`),
  KEY `idx_order_result` (`order_id`, `result_id`),
  KEY `idx_result` (`result_id`),
  KEY `idx_site_channel_time` (`site_id`, `channel_key`, `create_time`),
  KEY `idx_site_service_time` (`site_id`, `service_code`, `create_time`),
  KEY `idx_site_status_time` (`site_id`, `status`, `create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='手机查询第三方接口调用日志表';

SET FOREIGN_KEY_CHECKS = 1;
