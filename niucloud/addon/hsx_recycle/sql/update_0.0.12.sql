-- 回收任务从“自行认领”升级为“可指定、可转交、可追溯”。
ALTER TABLE `{{prefix}}recycle_task_claim`
  ADD COLUMN `assigner_uid` int NOT NULL DEFAULT 0 COMMENT '分配人UID' AFTER `assignee_name`,
  ADD COLUMN `assigner_name` varchar(50) NOT NULL DEFAULT '' COMMENT '分配人名称快照' AFTER `assigner_uid`,
  ADD COLUMN `assignment_mode` varchar(20) NOT NULL DEFAULT 'claim' COMMENT 'claim认领/assign指定/transfer转交' AFTER `assigner_name`,
  ADD COLUMN `assigned_at` int NOT NULL DEFAULT 0 COMMENT '最近分配时间' AFTER `claimed_at`;

UPDATE `{{prefix}}recycle_task_claim`
SET `assigner_uid` = `assignee_uid`,
    `assigner_name` = `assignee_name`,
    `assignment_mode` = 'claim',
    `assigned_at` = `claimed_at`
WHERE `assigned_at` = 0;

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
