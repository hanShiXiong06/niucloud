SET time_zone = '+08:00';

ALTER TABLE `{{prefix}}performance_fact`
  ADD COLUMN `event_name` varchar(80) NOT NULL DEFAULT 'performance.fact.recorded.v1' COMMENT '契约事件名称' AFTER `event_id`,
  ADD COLUMN `event_version` smallint unsigned NOT NULL DEFAULT 1 COMMENT '契约版本' AFTER `event_name`,
  ADD COLUMN `payload_hash` char(64) NOT NULL DEFAULT '' COMMENT '规范化载荷SHA256' AFTER `event_version`,
  ADD COLUMN `metric_name` varchar(80) NOT NULL DEFAULT '' COMMENT '指标名称快照' AFTER `action_key`,
  ADD COLUMN `fact_scope` varchar(20) NOT NULL DEFAULT 'action' COMMENT 'action/outcome/quality' AFTER `metric_name`,
  ADD COLUMN `fact_type` varchar(20) NOT NULL DEFAULT 'original' COMMENT 'original/reversal' AFTER `fact_scope`,
  ADD COLUMN `direction` tinyint NOT NULL DEFAULT 1 COMMENT '1正向/-1冲红' AFTER `fact_type`,
  ADD COLUMN `reversal_of_event_id` varchar(100) NOT NULL DEFAULT '' COMMENT '被冲红事件ID' AFTER `direction`,
  ADD COLUMN `duration_seconds` int NOT NULL DEFAULT 0 COMMENT '处理时长，冲红为负数' AFTER `profit`,
  ADD COLUMN `quality_score` decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '质量分值' AFTER `duration_seconds`,
  ADD COLUMN `unit` varchar(20) NOT NULL DEFAULT 'item' COMMENT '计量单位' AFTER `quality_score`,
  ADD COLUMN `dimensions_json` longtext NULL COMMENT '筛选维度快照' AFTER `unit`,
  ADD COLUMN `source_route_json` longtext NULL COMMENT '业务追溯入口' AFTER `dimensions_json`,
  ADD COLUMN `business_date` varchar(10) NOT NULL DEFAULT '' COMMENT '业务日期YYYY-MM-DD' AFTER `payload_json`,
  ADD COLUMN `received_at` int NOT NULL DEFAULT 0 AFTER `occurred_at`,
  ADD COLUMN `update_at` int NOT NULL DEFAULT 0 AFTER `create_at`,
  ADD KEY `idx_site_date_metric` (`site_id`,`business_date`,`action_key`,`fact_scope`),
  ADD KEY `idx_site_reversal` (`site_id`,`reversal_of_event_id`);

UPDATE `{{prefix}}performance_fact`
SET `event_name` = 'performance.fact.recorded.v1',
    `event_version` = 1,
    `metric_name` = `action_key`,
    `fact_scope` = 'action',
    `fact_type` = 'original',
    `direction` = IF(`quantity` < 0 OR `amount` < 0 OR `profit` < 0, -1, 1),
    `unit` = 'item',
    `business_date` = DATE_FORMAT(FROM_UNIXTIME(`occurred_at`), '%Y-%m-%d'),
    `received_at` = `create_at`,
    `update_at` = `create_at`
WHERE `business_date` = '';

UPDATE `{{prefix}}performance_fact`
SET
  `metric_name` = CASE `action_key`
    WHEN 'member_card_issued' THEN '会员卡开卡'
    WHEN 'member_card_redeemed' THEN '会员卡核销服务'
    WHEN 'member_card_received' THEN '会员卡确认收款'
    WHEN 'member_card_refund_applied' THEN '会员卡退款申请'
    WHEN 'member_card_refunded' THEN '会员卡退款完成'
    WHEN 'member_card_refund_paid' THEN '会员卡退款付款'
    WHEN 'recycle.device.signed' THEN '设备签收'
    WHEN 'recycle.check.completed' THEN '完成质检'
    WHEN 'recycle.price.completed' THEN '完成定价'
    WHEN 'recycle.inbound.completed' THEN '回收入库成功'
    WHEN 'recycle.device.returned' THEN '回收设备退回'
    WHEN 'erp.purchase.inbound' THEN '采购入库'
    WHEN 'erp.asset.photo.completed' THEN '设备拍照完成'
    WHEN 'erp.asset.price.completed' THEN '商城销售定价'
    WHEN 'erp.asset.material.completed' THEN '商城资料完善'
    WHEN 'erp.asset.listed' THEN '设备成功上架'
    WHEN 'erp.asset.transferred' THEN '库存调拨'
    WHEN 'erp.sale.created' THEN '销售开单'
    WHEN 'erp.sale.outbound' THEN '销售出库'
    WHEN 'erp.finance.receipt.confirmed' THEN '确认收款'
    WHEN 'erp.finance.payment.confirmed' THEN '确认付款'
    WHEN 'erp.finance.offset.confirmed' THEN '确认折账'
    ELSE `metric_name`
  END,
  `fact_scope` = CASE
    WHEN `action_key` IN (
      'member_card_issued','member_card_refunded','recycle.inbound.completed',
      'erp.purchase.inbound','erp.asset.listed','erp.sale.created','erp.sale.outbound'
    ) THEN 'outcome'
    WHEN `action_key` IN ('recycle.device.returned') THEN 'quality'
    ELSE 'action'
  END,
  `unit` = CASE
    WHEN `action_key` = 'member_card_issued' THEN 'card'
    WHEN `action_key` = 'member_card_redeemed' THEN 'service'
    WHEN `action_key` IN ('member_card_refund_applied','member_card_refunded') THEN 'refund'
    WHEN `action_key` IN ('member_card_received','member_card_refund_paid','erp.finance.receipt.confirmed','erp.finance.payment.confirmed','erp.finance.offset.confirmed') THEN 'settlement'
    WHEN `action_key` = 'erp.sale.created' THEN 'order'
    ELSE 'device'
  END
WHERE `action_key` IN (
  'member_card_issued','member_card_redeemed','member_card_received','member_card_refund_applied','member_card_refunded','member_card_refund_paid',
  'recycle.device.signed','recycle.check.completed','recycle.price.completed','recycle.inbound.completed','recycle.device.returned',
  'erp.purchase.inbound','erp.asset.photo.completed','erp.asset.price.completed','erp.asset.material.completed','erp.asset.listed','erp.asset.transferred',
  'erp.sale.created','erp.sale.outbound','erp.finance.receipt.confirmed','erp.finance.payment.confirmed','erp.finance.offset.confirmed'
);

CREATE TABLE IF NOT EXISTS `{{prefix}}performance_metric` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `source_plugin` varchar(50) NOT NULL DEFAULT '',
  `business_chain` varchar(40) NOT NULL DEFAULT '',
  `metric_key` varchar(80) NOT NULL DEFAULT '',
  `metric_name` varchar(80) NOT NULL DEFAULT '',
  `fact_scope` varchar(20) NOT NULL DEFAULT 'action',
  `unit` varchar(20) NOT NULL DEFAULT 'item',
  `description` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `sort` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_metric` (`site_id`,`source_plugin`,`metric_key`),
  KEY `idx_site_chain` (`site_id`,`business_chain`,`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='员工产出指标目录';

CREATE TABLE IF NOT EXISTS `{{prefix}}performance_employee_daily` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `business_date` varchar(10) NOT NULL DEFAULT '',
  `employee_uid` int NOT NULL DEFAULT 0,
  `employee_name` varchar(60) NOT NULL DEFAULT '',
  `source_plugin` varchar(50) NOT NULL DEFAULT '',
  `business_chain` varchar(40) NOT NULL DEFAULT '',
  `metric_key` varchar(80) NOT NULL DEFAULT '',
  `metric_name` varchar(80) NOT NULL DEFAULT '',
  `fact_scope` varchar(20) NOT NULL DEFAULT 'action',
  `role_key` varchar(50) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'item',
  `quantity` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `profit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `duration_seconds` bigint NOT NULL DEFAULT 0,
  `quality_score` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `fact_count` int NOT NULL DEFAULT 0,
  `effective_fact_count` int NOT NULL DEFAULT 0,
  `original_count` int NOT NULL DEFAULT 0,
  `reversal_count` int NOT NULL DEFAULT 0,
  `last_occurred_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_daily_metric` (`site_id`,`business_date`,`employee_uid`,`source_plugin`,`metric_key`,`fact_scope`,`role_key`),
  KEY `idx_site_date` (`site_id`,`business_date`,`employee_uid`),
  KEY `idx_site_metric` (`site_id`,`metric_key`,`business_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='员工产出日汇总投影';

CREATE TABLE IF NOT EXISTS `{{prefix}}performance_anomaly` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `event_id` varchar(100) NOT NULL DEFAULT '',
  `anomaly_type` varchar(50) NOT NULL DEFAULT '',
  `severity` varchar(20) NOT NULL DEFAULT 'error',
  `message` varchar(500) NOT NULL DEFAULT '',
  `payload_json` longtext NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `occurrence_count` int NOT NULL DEFAULT 1,
  `resolved_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site_status` (`site_id`,`status`,`update_at`),
  KEY `idx_site_event` (`site_id`,`event_id`,`anomaly_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='员工产出数据异常';

INSERT INTO `{{prefix}}performance_metric`
  (`site_id`,`source_plugin`,`business_chain`,`metric_key`,`metric_name`,`fact_scope`,`unit`,`description`,`status`,`sort`,`create_at`,`update_at`)
SELECT
  `site_id`,
  `source_plugin`,
  MAX(`business_chain`),
  `action_key`,
  MAX(IF(`metric_name` = '', `action_key`, `metric_name`)),
  MAX(`fact_scope`),
  MAX(`unit`),
  '',
  1,
  0,
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
FROM `{{prefix}}performance_fact`
GROUP BY `site_id`,`source_plugin`,`action_key`
ON DUPLICATE KEY UPDATE
  `business_chain` = VALUES(`business_chain`),
  `metric_name` = VALUES(`metric_name`),
  `fact_scope` = VALUES(`fact_scope`),
  `unit` = VALUES(`unit`),
  `update_at` = VALUES(`update_at`);

INSERT INTO `{{prefix}}performance_employee_daily`
  (`site_id`,`business_date`,`employee_uid`,`employee_name`,`source_plugin`,`business_chain`,`metric_key`,`metric_name`,`fact_scope`,`role_key`,`unit`,
   `quantity`,`amount`,`profit`,`duration_seconds`,`quality_score`,`fact_count`,`effective_fact_count`,`original_count`,`reversal_count`,
   `last_occurred_at`,`create_at`,`update_at`)
SELECT
  `site_id`,
  `business_date`,
  `employee_uid`,
  MAX(`employee_name`),
  `source_plugin`,
  MAX(`business_chain`),
  `action_key`,
  MAX(IF(`metric_name` = '', `action_key`, `metric_name`)),
  `fact_scope`,
  `role_key`,
  MAX(`unit`),
  COALESCE(SUM(`quantity`), 0),
  COALESCE(SUM(`amount`), 0),
  COALESCE(SUM(`profit`), 0),
  COALESCE(SUM(`duration_seconds`), 0),
  COALESCE(SUM(`quality_score`), 0),
  COUNT(`id`),
  COALESCE(SUM(`direction`), 0),
  SUM(CASE WHEN `fact_type` = 'original' THEN 1 ELSE 0 END),
  SUM(CASE WHEN `fact_type` = 'reversal' THEN 1 ELSE 0 END),
  MAX(`occurred_at`),
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
FROM `{{prefix}}performance_fact`
GROUP BY `site_id`,`business_date`,`employee_uid`,`source_plugin`,`action_key`,`fact_scope`,`role_key`
ON DUPLICATE KEY UPDATE
  `employee_name` = VALUES(`employee_name`),
  `business_chain` = VALUES(`business_chain`),
  `metric_name` = VALUES(`metric_name`),
  `unit` = VALUES(`unit`),
  `quantity` = VALUES(`quantity`),
  `amount` = VALUES(`amount`),
  `profit` = VALUES(`profit`),
  `duration_seconds` = VALUES(`duration_seconds`),
  `quality_score` = VALUES(`quality_score`),
  `fact_count` = VALUES(`fact_count`),
  `effective_fact_count` = VALUES(`effective_fact_count`),
  `original_count` = VALUES(`original_count`),
  `reversal_count` = VALUES(`reversal_count`),
  `last_occurred_at` = VALUES(`last_occurred_at`),
  `update_at` = VALUES(`update_at`);
