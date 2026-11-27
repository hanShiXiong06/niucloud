CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_boyuan_reference_sync` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int unsigned NOT NULL,
 `connection_key` varchar(32) NOT NULL, `mode` varchar(16) NOT NULL, `resource` varchar(32) NOT NULL,
 `run_key` varchar(32) NOT NULL, `status` varchar(16) NOT NULL DEFAULT 'queued', `cursor` varchar(16) NOT NULL DEFAULT '1',
 `processed` int unsigned NOT NULL DEFAULT 0, `pages` int unsigned NOT NULL DEFAULT 0, `reported_total` int NOT NULL DEFAULT -1,
 `scope_json` text NOT NULL, `warning` varchar(1000) NOT NULL DEFAULT '',
 `attempts` int unsigned NOT NULL DEFAULT 0, `next_at` int unsigned NOT NULL DEFAULT 0,
 `lease_token` varchar(32) NOT NULL DEFAULT '', `lease_until` int unsigned NOT NULL DEFAULT 0,
 `reason` varchar(1000) NOT NULL DEFAULT '', `operator_uid` int unsigned NOT NULL, `create_at` int unsigned NOT NULL, `update_at` int unsigned NOT NULL,
 PRIMARY KEY (`id`), KEY `queue` (`site_id`,`connection_key`,`status`,`next_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
