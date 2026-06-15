-- 结算单记录"所用资金户头"，用于结算记录直接显示户头、对账可追溯。
-- 已安装站点执行此增量脚本（仅新增两列，幂等：列已存在请忽略报错）。
-- 现金已由外部流程(如回收打款 recordCapitalOutflow)扣账时，结算只存户头不重复扣账。
ALTER TABLE `{{prefix}}erp_finance_settlement`
  ADD COLUMN `capital_account_id` int NOT NULL DEFAULT 0 COMMENT '现金所用资金账户ID(0=无/未记)' AFTER `cash_direction`,
  ADD COLUMN `account_name` varchar(60) NOT NULL DEFAULT '' COMMENT '资金账户名(快照,用于结算记录展示)' AFTER `capital_account_id`;
