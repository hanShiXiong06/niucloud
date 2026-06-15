CREATE TABLE IF NOT EXISTS `{{prefix}}erp_device_identity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `identity_no` varchar(64) NOT NULL DEFAULT '',
  `identity_key` varchar(255) NOT NULL DEFAULT '',
  `imei` varchar(64) NOT NULL DEFAULT '',
  `imei2` varchar(64) NOT NULL DEFAULT '',
  `sn` varchar(128) NOT NULL DEFAULT '',
  `model` varchar(255) NOT NULL DEFAULT '',
  `category_id` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_identity_no` (`site_id`,`identity_no`),
  UNIQUE KEY `uk_site_identity_key` (`site_id`,`identity_key`),
  KEY `idx_site_imei` (`site_id`,`imei`),
  KEY `idx_site_sn` (`site_id`,`sn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP物理设备身份';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_counterparty` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `counterparty_no` varchar(64) NOT NULL DEFAULT '',
  `counterparty_type` varchar(32) NOT NULL DEFAULT 'individual',
  `role_type` varchar(32) NOT NULL DEFAULT 'supplier',
  `name` varchar(150) NOT NULL DEFAULT '',
  `mobile` varchar(32) NOT NULL DEFAULT '',
  `contact_name` varchar(100) NOT NULL DEFAULT '',
  `tax_no` varchar(100) NOT NULL DEFAULT '',
  `bank_name` varchar(150) NOT NULL DEFAULT '',
  `bank_account` varchar(150) NOT NULL DEFAULT '',
  `source_plugin` varchar(64) NOT NULL DEFAULT '',
  `source_type` varchar(64) NOT NULL DEFAULT '',
  `source_id` int NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `remark` varchar(500) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_no` (`site_id`,`counterparty_no`),
  UNIQUE KEY `uk_site_source` (`site_id`,`source_plugin`,`source_type`,`source_id`),
  KEY `idx_site_name` (`site_id`,`name`),
  KEY `idx_site_mobile` (`site_id`,`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP往来单位';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_counterparty_member` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `counterparty_id` int NOT NULL DEFAULT 0,
  `member_id` int NOT NULL DEFAULT 0,
  `relation_role` varchar(32) NOT NULL DEFAULT 'business',
  `is_finance_contact` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `remark` varchar(500) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_member` (`site_id`,`member_id`),
  KEY `idx_counterparty` (`site_id`,`counterparty_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP往来主体会员归属';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_asset_cycle` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `cycle_no` varchar(64) NOT NULL DEFAULT '',
  `identity_id` int NOT NULL DEFAULT 0,
  `source_plugin` varchar(64) NOT NULL DEFAULT '',
  `source_type` varchar(64) NOT NULL DEFAULT '',
  `source_id` int NOT NULL DEFAULT 0,
  `source_device_id` int NOT NULL DEFAULT 0,
  `counterparty_id` int NOT NULL DEFAULT 0,
  `source_member_id` int NOT NULL DEFAULT 0,
  `ownership_type` varchar(32) NOT NULL DEFAULT 'owned',
  `status` varchar(32) NOT NULL DEFAULT 'pending_in',
  `acquired_at` int NOT NULL DEFAULT 0,
  `closed_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_cycle_no` (`site_id`,`cycle_no`),
  UNIQUE KEY `uk_site_source_device` (`site_id`,`source_plugin`,`source_type`,`source_device_id`),
  KEY `idx_identity` (`site_id`,`identity_id`),
  KEY `idx_source_member` (`site_id`,`source_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP设备经营周期';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_asset` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `asset_no` varchar(64) NOT NULL DEFAULT '',
  `identity_id` int NOT NULL DEFAULT 0,
  `cycle_id` int NOT NULL DEFAULT 0,
  `source_device_id` int NOT NULL DEFAULT 0,
  `counterparty_id` int NOT NULL DEFAULT 0,
  `source_member_id` int NOT NULL DEFAULT 0,
  `imei` varchar(64) NOT NULL DEFAULT '',
  `imei2` varchar(64) NOT NULL DEFAULT '',
  `sn` varchar(128) NOT NULL DEFAULT '',
  `model` varchar(255) NOT NULL DEFAULT '',
  `category_id` int NOT NULL DEFAULT 0,
  `capacity` varchar(64) NOT NULL DEFAULT '',
  `color` varchar(64) NOT NULL DEFAULT '',
  `ownership_type` varchar(32) NOT NULL DEFAULT 'owned',
  `inventory_status` varchar(32) NOT NULL DEFAULT 'pending_in',
  `warehouse_id` int NOT NULL DEFAULT 0,
  `location_id` int NOT NULL DEFAULT 0,
  `purchase_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_sale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `check_snapshot` text COMMENT '来源质检快照JSON',
  `source_snapshot` text COMMENT '来源设备快照JSON',
  `stock_in_at` int NOT NULL DEFAULT 0,
  `stock_out_at` int NOT NULL DEFAULT 0,
  `version` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_asset_no` (`site_id`,`asset_no`),
  UNIQUE KEY `uk_site_cycle` (`site_id`,`cycle_id`),
  KEY `idx_site_status` (`site_id`,`inventory_status`),
  KEY `idx_site_imei` (`site_id`,`imei`),
  KEY `idx_counterparty` (`site_id`,`counterparty_id`),
  KEY `idx_source_member` (`site_id`,`source_member_id`),
  KEY `idx_source_device` (`site_id`,`source_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP资产当前快照';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_warehouse` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `warehouse_name` varchar(100) NOT NULL DEFAULT '',
  `warehouse_code` varchar(64) NOT NULL DEFAULT '',
  `business_type` varchar(20) NOT NULL DEFAULT 'mall' COMMENT '业务类型(=销售流向)：mall商城/peer同行/consignment代卖/scrap报废/hold暂存',
  `allow_inbound` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许调拨/设库位调入本仓(0否1是)',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int NOT NULL DEFAULT 0,
  `remark` varchar(500) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_name` (`site_id`,`warehouse_name`),
  KEY `idx_site_status` (`site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP仓库';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_warehouse_location` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `warehouse_id` int NOT NULL DEFAULT 0,
  `location_name` varchar(100) NOT NULL DEFAULT '',
  `location_code` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort` int NOT NULL DEFAULT 0,
  `remark` varchar(500) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_warehouse_name` (`site_id`,`warehouse_id`,`location_name`),
  KEY `idx_site_warehouse` (`site_id`,`warehouse_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP仓库库位';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_stock_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `order_no` varchar(64) NOT NULL DEFAULT '',
  `order_type` varchar(32) NOT NULL DEFAULT 'purchase_in',
  `status` varchar(32) NOT NULL DEFAULT 'draft',
  `source_plugin` varchar(64) NOT NULL DEFAULT '',
  `source_type` varchar(64) NOT NULL DEFAULT '',
  `source_id` int NOT NULL DEFAULT 0,
  `counterparty_id` int NOT NULL DEFAULT 0,
  `source_member_id` int NOT NULL DEFAULT 0,
  `warehouse_id` int NOT NULL DEFAULT 0,
  `device_count` int NOT NULL DEFAULT 0,
  `total_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `operator_id` int NOT NULL DEFAULT 0,
  `operator_name` varchar(100) NOT NULL DEFAULT '',
  `confirmed_by` int NOT NULL DEFAULT 0,
  `confirmed_at` int NOT NULL DEFAULT 0,
  `remark` varchar(1000) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_order_no` (`site_id`,`order_no`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_source_member` (`site_id`,`source_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP库存单';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_stock_order_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `order_id` int NOT NULL DEFAULT 0,
  `asset_id` int NOT NULL DEFAULT 0,
  `cycle_id` int NOT NULL DEFAULT 0,
  `source_device_id` int NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `reject_reason` varchar(500) NOT NULL DEFAULT '',
  `rejected_by` int NOT NULL DEFAULT 0,
  `rejected_name` varchar(100) NOT NULL DEFAULT '',
  `rejected_at` int NOT NULL DEFAULT 0,
  `retry_count` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_order_asset` (`site_id`,`order_id`,`asset_id`),
  KEY `idx_asset` (`site_id`,`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP库存单设备明细';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_stock_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `ledger_no` varchar(64) NOT NULL DEFAULT '',
  `asset_id` int NOT NULL DEFAULT 0,
  `cycle_id` int NOT NULL DEFAULT 0,
  `stock_order_id` int NOT NULL DEFAULT 0,
  `action` varchar(32) NOT NULL DEFAULT '',
  `before_status` varchar(32) NOT NULL DEFAULT '',
  `after_status` varchar(32) NOT NULL DEFAULT '',
  `warehouse_id` int NOT NULL DEFAULT 0,
  `location_id` int NOT NULL DEFAULT 0,
  `operator_id` int NOT NULL DEFAULT 0,
  `operator_name` varchar(100) NOT NULL DEFAULT '',
  `occurred_at` int NOT NULL DEFAULT 0,
  `payload` text COMMENT '流水上下文JSON',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_ledger_no` (`site_id`,`ledger_no`),
  KEY `idx_asset` (`site_id`,`asset_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP不可变库存流水';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_cost_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `ledger_no` varchar(64) NOT NULL DEFAULT '',
  `asset_id` int NOT NULL DEFAULT 0,
  `cycle_id` int NOT NULL DEFAULT 0,
  `cost_type` varchar(32) NOT NULL DEFAULT '',
  `amount_delta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `before_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `after_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `source_plugin` varchar(64) NOT NULL DEFAULT '',
  `source_type` varchar(64) NOT NULL DEFAULT '',
  `source_id` int NOT NULL DEFAULT 0,
  `counterparty_id` int NOT NULL DEFAULT 0,
  `operator_id` int NOT NULL DEFAULT 0,
  `operator_name` varchar(100) NOT NULL DEFAULT '',
  `occurred_at` int NOT NULL DEFAULT 0,
  `remark` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_ledger_no` (`site_id`,`ledger_no`),
  KEY `idx_asset` (`site_id`,`asset_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP不可变成本流水';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_price_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `price_no` varchar(64) NOT NULL DEFAULT '',
  `asset_id` int NOT NULL DEFAULT 0,
  `cycle_id` int NOT NULL DEFAULT 0,
  `action` varchar(32) NOT NULL DEFAULT 'initial',
  `before_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `after_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_profit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_margin` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `min_profit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `operator_id` int NOT NULL DEFAULT 0,
  `operator_name` varchar(100) NOT NULL DEFAULT '',
  `remark` varchar(1000) NOT NULL DEFAULT '',
  `occurred_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_price_no` (`site_id`,`price_no`),
  KEY `idx_asset` (`site_id`,`asset_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP销售定价日志';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_refurbish_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `order_no` varchar(64) NOT NULL DEFAULT '',
  `asset_id` int NOT NULL DEFAULT 0,
  `cycle_id` int NOT NULL DEFAULT 0,
  `status` varchar(32) NOT NULL DEFAULT 'processing',
  `assigned_uid` int NOT NULL DEFAULT 0,
  `assigned_name` varchar(100) NOT NULL DEFAULT '',
  `planned_finish_at` int NOT NULL DEFAULT 0,
  `started_at` int NOT NULL DEFAULT 0,
  `completed_at` int NOT NULL DEFAULT 0,
  `accepted_by` int NOT NULL DEFAULT 0,
  `accepted_name` varchar(100) NOT NULL DEFAULT '',
  `total_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rework_count` int NOT NULL DEFAULT 0,
  `remark` varchar(1000) NOT NULL DEFAULT '',
  `completion_remark` varchar(1000) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_order_no` (`site_id`,`order_no`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_asset` (`site_id`,`asset_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP整备工单';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_refurbish_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `order_id` int NOT NULL DEFAULT 0,
  `item_type` varchar(32) NOT NULL DEFAULT 'other',
  `item_name` varchar(100) NOT NULL DEFAULT '',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remark` varchar(500) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`site_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP整备项目及费用';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_sync_batch` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `batch_no` varchar(64) NOT NULL DEFAULT '',
  `event_id` varchar(64) NOT NULL DEFAULT '',
  `source_plugin` varchar(64) NOT NULL DEFAULT '',
  `source_type` varchar(64) NOT NULL DEFAULT '',
  `device_count` int NOT NULL DEFAULT 0,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `operator_id` int NOT NULL DEFAULT 0,
  `operator_name` varchar(100) NOT NULL DEFAULT '',
  `payload` mediumtext COMMENT '标准入库事件JSON',
  `error_message` varchar(1000) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_batch_no` (`site_id`,`batch_no`),
  UNIQUE KEY `uk_site_event` (`site_id`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP设备同步批次';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_sync_target` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `batch_id` int NOT NULL DEFAULT 0,
  `target` varchar(64) NOT NULL DEFAULT '',
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `attempts` int NOT NULL DEFAULT 0,
  `external_no` varchar(128) NOT NULL DEFAULT '',
  `response_data` mediumtext COMMENT '目标响应JSON',
  `error_message` varchar(1000) NOT NULL DEFAULT '',
  `last_attempt_at` int NOT NULL DEFAULT 0,
  `completed_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_batch_target` (`site_id`,`batch_id`,`target`),
  KEY `idx_site_status` (`site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP同步目标';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_inbox_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `event_id` varchar(64) NOT NULL DEFAULT '',
  `event_name` varchar(100) NOT NULL DEFAULT '',
  `consumer` varchar(100) NOT NULL DEFAULT '',
  `status` varchar(32) NOT NULL DEFAULT 'processing',
  `payload` mediumtext COMMENT '事件JSON',
  `error_message` varchar(1000) NOT NULL DEFAULT '',
  `processed_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_event_consumer` (`site_id`,`event_id`,`consumer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP幂等消费事件';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_outbox_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `event_id` varchar(64) NOT NULL DEFAULT '',
  `event_name` varchar(100) NOT NULL DEFAULT '',
  `aggregate_type` varchar(64) NOT NULL DEFAULT '',
  `aggregate_id` int NOT NULL DEFAULT 0,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `payload` mediumtext COMMENT '事件JSON',
  `attempts` int NOT NULL DEFAULT 0,
  `error_message` varchar(1000) NOT NULL DEFAULT '',
  `published_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_event` (`site_id`,`event_id`),
  KEY `idx_site_status` (`site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP可靠发布事件';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_operation_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `event_id` varchar(64) NOT NULL DEFAULT '',
  `event_name` varchar(100) NOT NULL DEFAULT '',
  `asset_id` int NOT NULL DEFAULT 0,
  `cycle_id` int NOT NULL DEFAULT 0,
  `document_type` varchar(64) NOT NULL DEFAULT '',
  `document_id` int NOT NULL DEFAULT 0,
  `stage` varchar(64) NOT NULL DEFAULT '',
  `action` varchar(64) NOT NULL DEFAULT '',
  `operator_id` int NOT NULL DEFAULT 0,
  `operator_name` varchar(100) NOT NULL DEFAULT '',
  `payload` mediumtext COMMENT '操作上下文JSON',
  `occurred_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_event` (`site_id`,`event_id`),
  KEY `idx_asset` (`site_id`,`asset_id`,`occurred_at`),
  KEY `idx_operator` (`site_id`,`operator_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP业务操作事件';

-- ============ 财务中心(应付/应收/结算/折账) ============
-- 二手机财务插件建表
-- 约定 金额单位元 decimal(12,2) 往来单位为锚 软状态用 varchar 便于扩展
-- 应付(我欠往来单位) 来源=回收确认/定价 等业务事实
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_finance_payable` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `counterparty_id` int NOT NULL DEFAULT '0' COMMENT '往来单位ID(锚)',
  `counterparty_name` varchar(100) NOT NULL DEFAULT '' COMMENT '往来单位名称(快照)',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '应付金额',
  `settled_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '已结算金额(现金+折账累计)',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending待结/partial部分/settled已结/void作废',
  `source_type` varchar(40) NOT NULL DEFAULT '' COMMENT '来源类型 如 recycle_device',
  `source_no` varchar(64) NOT NULL DEFAULT '' COMMENT '来源单号(展示用)',
  `source_device_id` int NOT NULL DEFAULT '0' COMMENT '跨插件设备锚 source_device_id',
  `event_id` varchar(64) NOT NULL DEFAULT '' COMMENT '事件幂等键',
  `occurred_at` int NOT NULL DEFAULT '0' COMMENT '业务发生时间',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `ext_json` text COMMENT '扩展快照',
  `create_time` int NOT NULL DEFAULT '0',
  `update_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event` (`site_id`,`event_id`),
  KEY `idx_cp` (`site_id`,`counterparty_id`,`status`),
  KEY `idx_device` (`source_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='财务-应付';

-- 应收(往来单位欠我): 来源=销售成交
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_finance_receivable` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `counterparty_id` int NOT NULL DEFAULT '0' COMMENT '往来单位ID(锚)',
  `counterparty_name` varchar(100) NOT NULL DEFAULT '' COMMENT '往来单位名称(快照)',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '应收金额',
  `settled_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '已结算金额(现金+折账累计)',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending待结/partial部分/settled已结/void作废',
  `source_type` varchar(40) NOT NULL DEFAULT '' COMMENT '来源类型 如 sale_order',
  `source_no` varchar(64) NOT NULL DEFAULT '' COMMENT '来源单号(展示用)',
  `source_device_id` int NOT NULL DEFAULT '0' COMMENT '跨插件设备锚',
  `event_id` varchar(64) NOT NULL DEFAULT '' COMMENT '事件幂等键',
  `occurred_at` int NOT NULL DEFAULT '0' COMMENT '业务发生时间',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `ext_json` text COMMENT '扩展快照',
  `create_time` int NOT NULL DEFAULT '0',
  `update_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event` (`site_id`,`event_id`),
  KEY `idx_cp` (`site_id`,`counterparty_id`,`status`),
  KEY `idx_device` (`source_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='财务-应收';

-- 结算单: 一次结算可同时处理多条应付/应收, 方式=现金/折账/混合
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_finance_settlement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `settlement_no` varchar(40) NOT NULL DEFAULT '' COMMENT '结算单号',
  `counterparty_id` int NOT NULL DEFAULT '0' COMMENT '往来单位ID(锚)',
  `counterparty_name` varchar(100) NOT NULL DEFAULT '' COMMENT '往来单位名称(快照)',
  `method` varchar(20) NOT NULL DEFAULT 'cash' COMMENT 'cash现金/offset折账/mixed混合',
  `payable_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '本次结算的应付合计',
  `receivable_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '本次结算的应收合计',
  `offset_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '折账(净额冲抵)金额',
  `cash_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '现金净额(>0我付出, <0我收到)',
  `cash_direction` varchar(10) NOT NULL DEFAULT 'none' COMMENT 'pay我付/collect我收/none无现金',
  `capital_account_id` int NOT NULL DEFAULT '0' COMMENT '现金所用资金账户ID(0=无/未记)',
  `account_name` varchar(60) NOT NULL DEFAULT '' COMMENT '资金账户名(快照,用于结算记录展示)',
  `status` varchar(20) NOT NULL DEFAULT 'completed' COMMENT 'completed已完成/void已作废',
  `operator_uid` int NOT NULL DEFAULT '0' COMMENT '操作人',
  `operator_name` varchar(60) NOT NULL DEFAULT '' COMMENT '操作人名',
  `event_id` varchar(64) NOT NULL DEFAULT '' COMMENT '结算事件幂等键',
  `occurred_at` int NOT NULL DEFAULT '0' COMMENT '结算时间',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT '0',
  `update_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_cp` (`site_id`,`counterparty_id`),
  KEY `idx_no` (`settlement_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='财务-结算单';

-- 结算明细: 结算单对每条应付/应收实际核销了多少
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_finance_settlement_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `settlement_id` int NOT NULL DEFAULT '0' COMMENT '结算单ID',
  `target_type` varchar(20) NOT NULL DEFAULT '' COMMENT 'payable应付/receivable应收',
  `target_id` int NOT NULL DEFAULT '0' COMMENT '应付/应收记录ID',
  `applied_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '本次核销金额',
  `pay_part` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '其中现金部分',
  `offset_part` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '其中折账部分',
  `create_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_settlement` (`settlement_id`),
  KEY `idx_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='财务-结算核销明细';
-- ============ 出库 / 调拨(同行出货) ============
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_outbound_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `outbound_no` varchar(40) NOT NULL DEFAULT '' COMMENT '出库单号',
  `outbound_type` varchar(20) NOT NULL DEFAULT 'peer_sale' COMMENT 'peer_sale同行销售/scrap报废/other其他',
  `counterparty_id` int NOT NULL DEFAULT 0 COMMENT '往来单位锚(member_id, 同行也建member)',
  `counterparty_name` varchar(100) NOT NULL DEFAULT '' COMMENT '往来单位名(快照)',
  `counterparty_enterprise_id` int NOT NULL DEFAULT 0 COMMENT '关联企业主体ID(可选)',
  `settle_mode` varchar(20) NOT NULL DEFAULT 'now' COMMENT 'now现结/later回填/none无结算',
  `price_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending待回填/filled已定价',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '出货总额',
  `qty` int NOT NULL DEFAULT 0 COMMENT '台数',
  `status` varchar(20) NOT NULL DEFAULT 'completed' COMMENT 'completed已出库/void已作废',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT '操作人',
  `operator_name` varchar(60) NOT NULL DEFAULT '' COMMENT '操作人名',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `out_at` int NOT NULL DEFAULT 0 COMMENT '出库时间',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cp` (`site_id`,`counterparty_id`),
  KEY `idx_no` (`outbound_no`),
  KEY `idx_type` (`site_id`,`outbound_type`,`price_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-出库单';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_outbound_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `outbound_id` int NOT NULL DEFAULT 0 COMMENT '出库单ID',
  `asset_id` int NOT NULL DEFAULT 0 COMMENT '资产ID',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '跨插件设备锚',
  `imei` varchar(64) NOT NULL DEFAULT '' COMMENT 'IMEI快照',
  `model` varchar(255) NOT NULL DEFAULT '' COMMENT '型号快照',
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '出库时成本(快照)',
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '出货价(回填前为0)',
  `receivable_emitted` tinyint NOT NULL DEFAULT 0 COMMENT '是否已生成应收(幂等)',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_outbound` (`outbound_id`),
  KEY `idx_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-出库明细';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_asset_move_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `asset_id` int NOT NULL DEFAULT 0 COMMENT '资产ID',
  `from_warehouse_id` int NOT NULL DEFAULT 0,
  `from_location_id` int NOT NULL DEFAULT 0,
  `to_warehouse_id` int NOT NULL DEFAULT 0,
  `to_location_id` int NOT NULL DEFAULT 0,
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-调拨日志';

-- 库位责任分配（人↔库位 多对多）：出库/调拨/资产列表据此过滤；管理员看全部
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_location_assign` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `warehouse_id` int NOT NULL DEFAULT 0 COMMENT '所属仓库(冗余,便于按仓筛选)',
  `location_id` int NOT NULL DEFAULT 0 COMMENT '负责的库位/分类(责任原子单元)',
  `uid` int NOT NULL DEFAULT 0 COMMENT '责任人(sys_user.uid)',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_loc_uid` (`site_id`,`location_id`,`uid`),
  KEY `idx_site_uid` (`site_id`,`uid`),
  KEY `idx_site_wh` (`site_id`,`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP库位责任分配';

-- ============ 资金账户 / 账目往来 ============
-- 资金账户：现金/微信/支付宝/银行卡(不同银行各一条)，各自记余额
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_capital_account` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `account_name` varchar(100) NOT NULL DEFAULT '' COMMENT '账户名称(如:招商银行尾号1234)',
  `account_type` varchar(20) NOT NULL DEFAULT 'bank' COMMENT 'cash现金/wechat微信/alipay支付宝/bank银行卡/other其他',
  `bank_name` varchar(100) NOT NULL DEFAULT '' COMMENT '开户行(银行卡用)',
  `account_no` varchar(100) NOT NULL DEFAULT '' COMMENT '卡号/账号(可脱敏)',
  `holder` varchar(60) NOT NULL DEFAULT '' COMMENT '持卡人/户名',
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '当前余额',
  `currency` varchar(8) NOT NULL DEFAULT 'CNY' COMMENT '币种',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '默认账户',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用/0停用',
  `sort` int NOT NULL DEFAULT 0,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_site_type` (`site_id`,`account_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP资金账户';

-- 账目往来流水：账户的每一笔收/付，记录余额变化、对手方、来源单据(结算/回收/销售/手工/转账)
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_capital_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `ledger_no` varchar(40) NOT NULL DEFAULT '' COMMENT '流水号',
  `account_id` int NOT NULL DEFAULT 0 COMMENT '资金账户ID',
  `account_name` varchar(100) NOT NULL DEFAULT '' COMMENT '账户名(快照)',
  `direction` varchar(8) NOT NULL DEFAULT 'in' COMMENT 'in收入/out支出',
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '金额(正数)',
  `balance_after` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '记账后余额',
  `biz_type` varchar(30) NOT NULL DEFAULT 'manual' COMMENT 'settlement结算/recycle回收付款/sale销售收款/buyout买断/transfer转账/fee费用/manual手工',
  `counterparty_id` int NOT NULL DEFAULT 0 COMMENT '往来单位ID(可空)',
  `counterparty_name` varchar(100) NOT NULL DEFAULT '' COMMENT '往来单位名(快照)',
  `source_type` varchar(40) NOT NULL DEFAULT '' COMMENT '来源单据类型',
  `source_no` varchar(64) NOT NULL DEFAULT '' COMMENT '来源单号(展示)',
  `source_id` int NOT NULL DEFAULT 0 COMMENT '来源单据ID',
  `settlement_id` int NOT NULL DEFAULT 0 COMMENT '关联结算单ID(可空)',
  `event_id` varchar(64) NOT NULL DEFAULT '' COMMENT '事件幂等键(可空)',
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `occurred_at` int NOT NULL DEFAULT 0 COMMENT '发生时间',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_no` (`site_id`,`ledger_no`),
  KEY `idx_site_account` (`site_id`,`account_id`,`occurred_at`),
  KEY `idx_site_cp` (`site_id`,`counterparty_id`),
  KEY `idx_event` (`site_id`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP资金账目往来流水';
-- ============ 库存盘点 ============
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_stocktake_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `stocktake_no` varchar(40) NOT NULL DEFAULT '' COMMENT '盘点单号',
  `warehouse_id` int NOT NULL DEFAULT 0 COMMENT '盘点仓库',
  `warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '仓库名快照',
  `location_id` int NOT NULL DEFAULT 0 COMMENT '盘点库位(0=整仓)',
  `location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '库位名快照',
  `status` varchar(20) NOT NULL DEFAULT 'counting' COMMENT 'counting盘点中/finished已完成/void作废',
  `system_count` int NOT NULL DEFAULT 0 COMMENT '应在库台数(快照时)',
  `counted_count` int NOT NULL DEFAULT 0 COMMENT '实盘到台数',
  `match_count` int NOT NULL DEFAULT 0 COMMENT '账实相符',
  `loss_count` int NOT NULL DEFAULT 0 COMMENT '盘亏台数',
  `profit_count` int NOT NULL DEFAULT 0 COMMENT '盘盈台数',
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `started_at` int NOT NULL DEFAULT 0,
  `finished_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_wh` (`site_id`,`warehouse_id`,`status`),
  KEY `idx_no` (`stocktake_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-盘点单';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_stocktake_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `stocktake_id` int NOT NULL DEFAULT 0 COMMENT '盘点单ID',
  `asset_id` int NOT NULL DEFAULT 0 COMMENT '资产ID(盘盈未建档为0)',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '跨插件设备锚',
  `imei` varchar(64) NOT NULL DEFAULT '',
  `model` varchar(255) NOT NULL DEFAULT '',
  `system_status` varchar(32) NOT NULL DEFAULT '' COMMENT '快照时库存状态',
  `result` varchar(20) NOT NULL DEFAULT 'uncounted' COMMENT 'uncounted待盘/matched相符/loss盘亏/profit盘盈',
  `counted` tinyint NOT NULL DEFAULT 0 COMMENT '是否盘到',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_take` (`stocktake_id`,`result`),
  KEY `idx_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-盘点明细';
