-- 二手机财务插件建表
-- 约定 金额单位元 decimal(12,2) 往来单位为锚 软状态用 varchar 便于扩展
-- 应付(我欠往来单位) 来源=回收确认/定价 等业务事实
CREATE TABLE IF NOT EXISTS `{{prefix}}finance_payable` (
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
CREATE TABLE IF NOT EXISTS `{{prefix}}finance_receivable` (
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
CREATE TABLE IF NOT EXISTS `{{prefix}}finance_settlement` (
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
CREATE TABLE IF NOT EXISTS `{{prefix}}finance_settlement_link` (
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
