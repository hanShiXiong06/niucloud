CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_boyuan_task` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int unsigned NOT NULL,
 `connection_key` varchar(32) NOT NULL, `mode` varchar(16) NOT NULL,
 `fact_key` varchar(160) NOT NULL, `event_id` varchar(128) NOT NULL,
 `operation_id` varchar(128) NOT NULL, `kind` varchar(40) NOT NULL,
 `business_no` varchar(128) NOT NULL DEFAULT '', `status` varchar(24) NOT NULL DEFAULT 'pending',
 `reason` varchar(1000) NOT NULL DEFAULT '', `side_effect` varchar(16) NOT NULL DEFAULT 'none',
 `snapshot_json` longtext NOT NULL, `request_json` longtext NOT NULL, `result_json` longtext NOT NULL,
 `attempts` int unsigned NOT NULL DEFAULT 0, `revision` int unsigned NOT NULL DEFAULT 1,
 `next_at` int unsigned NOT NULL DEFAULT 0, `lease_token` varchar(32) NOT NULL DEFAULT '', `lease_until` int unsigned NOT NULL DEFAULT 0,
 `external_no` varchar(255) NOT NULL DEFAULT '', `resolution` varchar(1000) NOT NULL DEFAULT '',
 `query_only` tinyint unsigned NOT NULL DEFAULT 0,
 `notice_status` varchar(24) NOT NULL DEFAULT 'pending', `notice_at` int unsigned NOT NULL DEFAULT 0,
 `create_at` int unsigned NOT NULL, `update_at` int unsigned NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `fact` (`site_id`,`connection_key`,`fact_key`),
 UNIQUE KEY `operation` (`site_id`,`connection_key`,`operation_id`), KEY `queue` (`site_id`,`status`,`next_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_boyuan_mapping` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int unsigned NOT NULL, `connection_key` varchar(32) NOT NULL,
 `kind` varchar(32) NOT NULL, `local_ref` varchar(128) NOT NULL, `local_name` varchar(255) NOT NULL DEFAULT '',
 `external_id` varchar(128) NOT NULL, `external_code` varchar(128) NOT NULL DEFAULT '', `external_name` varchar(255) NOT NULL DEFAULT '',
 `extra_json` text NOT NULL, `revision` int unsigned NOT NULL DEFAULT 1, `operator_uid` int unsigned NOT NULL DEFAULT 0,
 `update_at` int unsigned NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `identity` (`site_id`,`connection_key`,`kind`,`local_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_boyuan_binding` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int unsigned NOT NULL, `connection_key` varchar(32) NOT NULL,
 `kind` varchar(32) NOT NULL, `local_ref` varchar(128) NOT NULL, `task_id` bigint unsigned NOT NULL,
 `external_id` varchar(128) NOT NULL, `external_no` varchar(128) NOT NULL DEFAULT '', `detail_json` longtext NOT NULL,
 `update_at` int unsigned NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `identity` (`site_id`,`connection_key`,`kind`,`local_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_boyuan_log` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int unsigned NOT NULL, `connection_key` varchar(32) NOT NULL DEFAULT '',
 `task_id` bigint unsigned NOT NULL DEFAULT 0, `operation_id` varchar(128) NOT NULL DEFAULT '',
 `action` varchar(40) NOT NULL, `outcome` varchar(32) NOT NULL, `message` varchar(1000) NOT NULL,
 `detail_json` longtext NOT NULL, `operator_uid` int unsigned NOT NULL DEFAULT 0, `create_at` int unsigned NOT NULL,
 PRIMARY KEY (`id`), KEY `task` (`site_id`,`task_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_boyuan_mock_record` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int unsigned NOT NULL, `connection_key` varchar(32) NOT NULL,
 `record_key` varchar(160) NOT NULL, `value_json` longtext NOT NULL, `update_at` int unsigned NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `identity` (`site_id`,`connection_key`,`record_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
