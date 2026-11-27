-- ERP 自动任务责任链：应收/应付，以及待拍照 -> 待商城定价 -> 待完善资料并上架。
ALTER TABLE `{{prefix}}erp_asset`
  ADD COLUMN `task_stage_key` varchar(40) NOT NULL DEFAULT '' COMMENT '当前ERP待办环节' AFTER `listing_status`,
  ADD COLUMN `task_assignee_uid` int NOT NULL DEFAULT 0 COMMENT '当前待办责任人UID' AFTER `task_stage_key`,
  ADD COLUMN `task_assignee_name` varchar(60) NOT NULL DEFAULT '' COMMENT '当前待办责任人名称快照' AFTER `task_assignee_uid`,
  ADD COLUMN `task_assigner_uid` int NOT NULL DEFAULT 0 COMMENT '最近分配人UID' AFTER `task_assignee_name`,
  ADD COLUMN `task_assigner_name` varchar(60) NOT NULL DEFAULT '' COMMENT '最近分配人名称快照' AFTER `task_assigner_uid`,
  ADD COLUMN `task_assigned_at` int NOT NULL DEFAULT 0 COMMENT '最近分配时间' AFTER `task_assigner_name`,
  ADD KEY `idx_site_task_assignee` (`site_id`,`task_stage_key`,`task_assignee_uid`,`task_assigned_at`);

ALTER TABLE `{{prefix}}erp_payable`
  ADD COLUMN `task_stage_key` varchar(40) NOT NULL DEFAULT '' COMMENT '当前财务待办环节' AFTER `asset_id`,
  ADD COLUMN `task_assignee_uid` int NOT NULL DEFAULT 0 COMMENT '当前待办责任人UID' AFTER `task_stage_key`,
  ADD COLUMN `task_assignee_name` varchar(60) NOT NULL DEFAULT '' COMMENT '当前待办责任人' AFTER `task_assignee_uid`,
  ADD COLUMN `task_assigner_uid` int NOT NULL DEFAULT 0 COMMENT '分配人UID' AFTER `task_assignee_name`,
  ADD COLUMN `task_assigner_name` varchar(60) NOT NULL DEFAULT '' COMMENT '分配人' AFTER `task_assigner_uid`,
  ADD COLUMN `task_assigned_at` int NOT NULL DEFAULT 0 COMMENT '最近分配时间' AFTER `task_assigner_name`,
  ADD KEY `idx_task_assignee` (`site_id`,`task_stage_key`,`task_assignee_uid`,`status`);

ALTER TABLE `{{prefix}}erp_receivable`
  ADD COLUMN `task_stage_key` varchar(40) NOT NULL DEFAULT '' COMMENT '当前财务待办环节' AFTER `asset_id`,
  ADD COLUMN `task_assignee_uid` int NOT NULL DEFAULT 0 COMMENT '当前待办责任人UID' AFTER `task_stage_key`,
  ADD COLUMN `task_assignee_name` varchar(60) NOT NULL DEFAULT '' COMMENT '当前待办责任人' AFTER `task_assignee_uid`,
  ADD COLUMN `task_assigner_uid` int NOT NULL DEFAULT 0 COMMENT '分配人UID' AFTER `task_assignee_name`,
  ADD COLUMN `task_assigner_name` varchar(60) NOT NULL DEFAULT '' COMMENT '分配人' AFTER `task_assigner_uid`,
  ADD COLUMN `task_assigned_at` int NOT NULL DEFAULT 0 COMMENT '最近分配时间' AFTER `task_assigner_name`,
  ADD KEY `idx_task_assignee` (`site_id`,`task_stage_key`,`task_assignee_uid`,`status`);

-- 历史的 need_price 中可能同时包含“缺售价”和“缺分类/规格”，部署后由业务服务按资料重新判定。
