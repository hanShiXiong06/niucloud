-- 资金账户 / 账目往来。已安装站点执行此增量脚本。
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

-- 账目往来流水：账户的每一笔收/付，记录余额变化、对手方、来源单据
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
