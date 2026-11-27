CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_kingdee_jdy_task` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int unsigned NOT NULL,
  `connection_key` varchar(32) NOT NULL,
  `fact_key` varchar(80) NOT NULL,
  `event_id` varchar(80) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `business_no` varchar(100) NOT NULL DEFAULT '',
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `snapshot_json` longtext NOT NULL,
  `plan_json` longtext NOT NULL,
  `reason` varchar(1000) NOT NULL DEFAULT '',
  `attempts` int unsigned NOT NULL DEFAULT 0,
  `next_retry_at` int unsigned NOT NULL DEFAULT 0,
  `lease_token` varchar(32) NOT NULL DEFAULT '',
  `lease_until` int unsigned NOT NULL DEFAULT 0,
  `assignee_uid` int unsigned NOT NULL DEFAULT 0,
  `notice_status` varchar(24) NOT NULL DEFAULT 'pending',
  `notice_attempts` int unsigned NOT NULL DEFAULT 0,
  `notice_round` int unsigned NOT NULL DEFAULT 1,
  `notice_next_at` int unsigned NOT NULL DEFAULT 0,
  `resolution` varchar(1000) NOT NULL DEFAULT '',
  `external_no` varchar(100) NOT NULL DEFAULT '',
  `resolved_by` int unsigned NOT NULL DEFAULT 0,
  `create_at` int unsigned NOT NULL,
  `update_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_connection_fact` (`site_id`,`connection_key`,`fact_key`),
  KEY `task_queue` (`site_id`,`status`,`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_kingdee_jdy_mapping` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int unsigned NOT NULL,
  `connection_key` varchar(32) NOT NULL,
  `kind` varchar(24) NOT NULL,
  `local_id` bigint unsigned NOT NULL,
  `external_number` varchar(100) NOT NULL,
  `unit_id` varchar(64) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `operator_uid` int unsigned NOT NULL,
  `create_at` int unsigned NOT NULL,
  `update_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_connection_identity` (`site_id`,`connection_key`,`kind`,`local_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_kingdee_jdy_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int unsigned NOT NULL,
  `task_id` bigint unsigned NOT NULL DEFAULT 0,
  `action` varchar(40) NOT NULL,
  `outcome` varchar(40) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `operator_uid` int unsigned NOT NULL DEFAULT 0,
  `create_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `site_task` (`site_id`,`task_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
