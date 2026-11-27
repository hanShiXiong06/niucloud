-- 企业微信服务商手动增量升级（2026-09-29）
-- 自动生成来源：app/support/WecomSchema.php；请勿手改生成内容。
-- schema-sha256: 75d12f92c6ebcacc621710d169f543ee4ae285de0e688a3c2ed49838d8a08144
-- 先备份并在客户端选择目标数据库；仅修改下面一处前缀。
-- 不删除数据、不覆盖配置、不自动给角色授权；DDL 不支持事务整体回滚。
-- 任意语句报错立即停止，不使用 mysql --force。执行后再运行插件的只读校验。
SET @wecom_prefix = 'ns_';
SET @wecom_schema = DATABASE();
SET @wecom_ready = (@wecom_schema IS NOT NULL AND @wecom_prefix IS NOT NULL AND @wecom_prefix REGEXP '^[A-Za-z0-9_]*$' AND CHAR_LENGTH(@wecom_prefix) <= 38);
SELECT @wecom_schema AS target_database, @wecom_prefix AS table_prefix, IF(@wecom_ready, 'READY - confirm database and prefix', 'STOP - select database and correct prefix') AS preflight;

-- wecom_provider_suite
SET @wecom_sql = IF(@wecom_ready, CONCAT('CREATE TABLE IF NOT EXISTS `', @wecom_prefix, 'wecom_provider_suite` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `channel_code` varchar(60) NOT NULL DEFAULT '''' COMMENT ''SaaS部署渠道唯一编码'',
          `provider_corp_id` varchar(100) NOT NULL DEFAULT '''' COMMENT ''服务商企业CorpID'',
          `suite_id` varchar(100) NOT NULL DEFAULT '''' COMMENT ''第三方应用SuiteID'',
          `suite_secret_cipher` longtext NULL COMMENT ''加密保存的SuiteSecret'',
          `callback_token` varchar(255) NOT NULL DEFAULT '''' COMMENT ''回调Token'',
          `encoding_aes_key_cipher` longtext NULL COMMENT ''加密保存的EncodingAESKey'',
          `admin_miniapp_appid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''管理端小程序AppID'',
          `admin_miniapp_name` varchar(100) NOT NULL DEFAULT '''' COMMENT ''管理端小程序名称'',
          `web_base_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''当前SaaS网页根地址'',
          `event_callback_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''服务商事件回调地址'',
          `auth_callback_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''企业授权完成回调地址'',
          `suite_ticket` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''企业微信推送的SuiteTicket'',
          `suite_ticket_at` int NOT NULL DEFAULT 0 COMMENT ''SuiteTicket最近接收时间'',
          `status` varchar(20) NOT NULL DEFAULT ''disabled'' COMMENT ''enabled/disabled'',
          `last_error` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''最近错误'',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_channel_code` (`channel_code`),
          UNIQUE KEY `uk_suite_id` (`suite_id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=''企业微信服务商应用渠道'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.channel_code
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'channel_code'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `channel_code` varchar(60) NOT NULL DEFAULT '''' COMMENT ''SaaS部署渠道唯一编码'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.provider_corp_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'provider_corp_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `provider_corp_id` varchar(100) NOT NULL DEFAULT '''' COMMENT ''服务商企业CorpID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.suite_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'suite_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `suite_id` varchar(100) NOT NULL DEFAULT '''' COMMENT ''第三方应用SuiteID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.suite_secret_cipher
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'suite_secret_cipher'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `suite_secret_cipher` longtext NULL COMMENT ''加密保存的SuiteSecret'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.callback_token
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'callback_token'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `callback_token` varchar(255) NOT NULL DEFAULT '''' COMMENT ''回调Token'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.encoding_aes_key_cipher
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'encoding_aes_key_cipher'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `encoding_aes_key_cipher` longtext NULL COMMENT ''加密保存的EncodingAESKey'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.admin_miniapp_appid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'admin_miniapp_appid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `admin_miniapp_appid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''管理端小程序AppID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.admin_miniapp_name
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'admin_miniapp_name'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `admin_miniapp_name` varchar(100) NOT NULL DEFAULT '''' COMMENT ''管理端小程序名称'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.web_base_url
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'web_base_url'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `web_base_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''当前SaaS网页根地址'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.event_callback_url
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'event_callback_url'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `event_callback_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''服务商事件回调地址'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.auth_callback_url
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'auth_callback_url'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `auth_callback_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''企业授权完成回调地址'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.suite_ticket
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'suite_ticket'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `suite_ticket` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''企业微信推送的SuiteTicket'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.suite_ticket_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'suite_ticket_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `suite_ticket_at` int NOT NULL DEFAULT 0 COMMENT ''SuiteTicket最近接收时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT ''disabled'' COMMENT ''enabled/disabled'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.last_error
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'last_error'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `last_error` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''最近错误'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.create_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'create_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `create_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_provider_suite.update_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND COLUMN_NAME = 'update_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD COLUMN `update_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_provider_suite.PRIMARY
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND INDEX_NAME = 'PRIMARY'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD PRIMARY KEY (`id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_provider_suite.uk_channel_code
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND INDEX_NAME = 'uk_channel_code'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD UNIQUE KEY `uk_channel_code` (`channel_code`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_provider_suite.uk_suite_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND INDEX_NAME = 'uk_suite_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD UNIQUE KEY `uk_suite_id` (`suite_id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_provider_suite.idx_status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_provider_suite') AND INDEX_NAME = 'idx_status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_provider_suite` ADD KEY `idx_status` (`status`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- wecom_corp_authorization
SET @wecom_sql = IF(@wecom_ready, CONCAT('CREATE TABLE IF NOT EXISTS `', @wecom_prefix, 'wecom_corp_authorization` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'',
          `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商应用渠道ID'',
          `auth_corpid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''授权企业CorpID（服务商域）'',
          `permanent_code_cipher` longtext NULL COMMENT ''加密保存的永久授权码'',
          `agent_id` int NOT NULL DEFAULT 0 COMMENT ''授权企业应用AgentID'',
          `corp_name` varchar(200) NOT NULL DEFAULT '''' COMMENT ''授权企业名称快照'',
          `auth_info_json` longtext NULL COMMENT ''授权详情快照'',
          `status` varchar(20) NOT NULL DEFAULT ''pending'' COMMENT ''pending/authorized/changed/cancelled/error'',
          `last_error` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''最近错误'',
          `authorized_at` int NOT NULL DEFAULT 0 COMMENT ''首次授权时间'',
          `changed_at` int NOT NULL DEFAULT 0 COMMENT ''授权变更时间'',
          `cancelled_at` int NOT NULL DEFAULT 0 COMMENT ''取消授权时间'',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_site_suite` (`site_id`,`provider_suite_id`),
          UNIQUE KEY `uk_suite_corp` (`provider_suite_id`,`auth_corpid`),
          KEY `idx_site_status` (`site_id`,`status`),
          KEY `idx_auth_corpid` (`auth_corpid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=''企业微信客户企业授权'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.site_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'site_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.provider_suite_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'provider_suite_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商应用渠道ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.auth_corpid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'auth_corpid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `auth_corpid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''授权企业CorpID（服务商域）'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.permanent_code_cipher
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'permanent_code_cipher'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `permanent_code_cipher` longtext NULL COMMENT ''加密保存的永久授权码'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.agent_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'agent_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `agent_id` int NOT NULL DEFAULT 0 COMMENT ''授权企业应用AgentID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.corp_name
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'corp_name'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `corp_name` varchar(200) NOT NULL DEFAULT '''' COMMENT ''授权企业名称快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.auth_info_json
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'auth_info_json'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `auth_info_json` longtext NULL COMMENT ''授权详情快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT ''pending'' COMMENT ''pending/authorized/changed/cancelled/error'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.last_error
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'last_error'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `last_error` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''最近错误'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.authorized_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'authorized_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `authorized_at` int NOT NULL DEFAULT 0 COMMENT ''首次授权时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.changed_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'changed_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `changed_at` int NOT NULL DEFAULT 0 COMMENT ''授权变更时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.cancelled_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'cancelled_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `cancelled_at` int NOT NULL DEFAULT 0 COMMENT ''取消授权时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.create_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'create_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `create_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_corp_authorization.update_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND COLUMN_NAME = 'update_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD COLUMN `update_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_corp_authorization.PRIMARY
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND INDEX_NAME = 'PRIMARY'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD PRIMARY KEY (`id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_corp_authorization.uk_site_suite
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND INDEX_NAME = 'uk_site_suite'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD UNIQUE KEY `uk_site_suite` (`site_id`,`provider_suite_id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_corp_authorization.uk_suite_corp
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND INDEX_NAME = 'uk_suite_corp'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD UNIQUE KEY `uk_suite_corp` (`provider_suite_id`,`auth_corpid`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_corp_authorization.idx_site_status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND INDEX_NAME = 'idx_site_status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD KEY `idx_site_status` (`site_id`,`status`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_corp_authorization.idx_auth_corpid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_corp_authorization') AND INDEX_NAME = 'idx_auth_corpid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_corp_authorization` ADD KEY `idx_auth_corpid` (`auth_corpid`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- wecom_authorization_intent
SET @wecom_sql = IF(@wecom_ready, CONCAT('CREATE TABLE IF NOT EXISTS `', @wecom_prefix, 'wecom_authorization_intent` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `state` varchar(160) NOT NULL DEFAULT '''' COMMENT ''一次性授权状态码'',
          `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'',
          `uid` int NOT NULL DEFAULT 0 COMMENT ''发起员工UID'',
          `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商应用渠道ID'',
          `purpose` varchar(30) NOT NULL DEFAULT ''install'' COMMENT ''install/member_bind'',
          `pre_auth_code` varchar(255) NOT NULL DEFAULT '''' COMMENT ''预授权码'',
          `return_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''授权完成后的安全回跳地址'',
          `meta_json` longtext NULL COMMENT ''授权上下文'',
          `expires_at` int NOT NULL DEFAULT 0 COMMENT ''状态码过期时间'',
          `used_at` int NOT NULL DEFAULT 0 COMMENT ''消费时间'',
          `create_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_state` (`state`),
          KEY `idx_site_purpose` (`site_id`,`purpose`,`create_at`),
          KEY `idx_expire_used` (`expires_at`,`used_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=''企业微信授权与绑定意图'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.state
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'state'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `state` varchar(160) NOT NULL DEFAULT '''' COMMENT ''一次性授权状态码'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.site_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'site_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.uid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'uid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `uid` int NOT NULL DEFAULT 0 COMMENT ''发起员工UID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.provider_suite_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'provider_suite_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商应用渠道ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.purpose
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'purpose'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `purpose` varchar(30) NOT NULL DEFAULT ''install'' COMMENT ''install/member_bind'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.pre_auth_code
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'pre_auth_code'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `pre_auth_code` varchar(255) NOT NULL DEFAULT '''' COMMENT ''预授权码'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.return_url
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'return_url'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `return_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''授权完成后的安全回跳地址'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.meta_json
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'meta_json'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `meta_json` longtext NULL COMMENT ''授权上下文'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.expires_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'expires_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `expires_at` int NOT NULL DEFAULT 0 COMMENT ''状态码过期时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.used_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'used_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `used_at` int NOT NULL DEFAULT 0 COMMENT ''消费时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_authorization_intent.create_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND COLUMN_NAME = 'create_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD COLUMN `create_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_authorization_intent.PRIMARY
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND INDEX_NAME = 'PRIMARY'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD PRIMARY KEY (`id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_authorization_intent.uk_state
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND INDEX_NAME = 'uk_state'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD UNIQUE KEY `uk_state` (`state`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_authorization_intent.idx_site_purpose
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND INDEX_NAME = 'idx_site_purpose'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD KEY `idx_site_purpose` (`site_id`,`purpose`,`create_at`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_authorization_intent.idx_expire_used
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_authorization_intent') AND INDEX_NAME = 'idx_expire_used'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_authorization_intent` ADD KEY `idx_expire_used` (`expires_at`,`used_at`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- wecom_callback_event
SET @wecom_sql = IF(@wecom_ready, CONCAT('CREATE TABLE IF NOT EXISTS `', @wecom_prefix, 'wecom_callback_event` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `event_key` varchar(160) NOT NULL DEFAULT '''' COMMENT ''回调事件幂等键'',
          `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商应用渠道ID'',
          `auth_corpid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''授权企业CorpID'',
          `info_type` varchar(60) NOT NULL DEFAULT '''' COMMENT ''企业微信回调类型'',
          `event_time` int NOT NULL DEFAULT 0 COMMENT ''企业微信事件时间'',
          `payload_json` longtext NULL COMMENT ''解密后的事件快照'',
          `payload_hash` char(64) NOT NULL DEFAULT '''' COMMENT ''事件内容SHA-256'',
          `status` varchar(20) NOT NULL DEFAULT ''pending'' COMMENT ''pending/processing/processed/failed'',
          `error_message` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''处理错误'',
          `processed_at` int NOT NULL DEFAULT 0 COMMENT ''处理完成时间'',
          `create_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_event_key` (`event_key`),
          KEY `idx_suite_status` (`provider_suite_id`,`status`,`create_at`),
          KEY `idx_corp_type_time` (`auth_corpid`,`info_type`,`event_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=''企业微信服务商回调收件箱'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.event_key
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'event_key'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `event_key` varchar(160) NOT NULL DEFAULT '''' COMMENT ''回调事件幂等键'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.provider_suite_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'provider_suite_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商应用渠道ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.auth_corpid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'auth_corpid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `auth_corpid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''授权企业CorpID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.info_type
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'info_type'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `info_type` varchar(60) NOT NULL DEFAULT '''' COMMENT ''企业微信回调类型'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.event_time
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'event_time'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `event_time` int NOT NULL DEFAULT 0 COMMENT ''企业微信事件时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.payload_json
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'payload_json'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `payload_json` longtext NULL COMMENT ''解密后的事件快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.payload_hash
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'payload_hash'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `payload_hash` char(64) NOT NULL DEFAULT '''' COMMENT ''事件内容SHA-256'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT ''pending'' COMMENT ''pending/processing/processed/failed'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.error_message
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'error_message'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `error_message` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''处理错误'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.processed_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'processed_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `processed_at` int NOT NULL DEFAULT 0 COMMENT ''处理完成时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_callback_event.create_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND COLUMN_NAME = 'create_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD COLUMN `create_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_callback_event.PRIMARY
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND INDEX_NAME = 'PRIMARY'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD PRIMARY KEY (`id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_callback_event.uk_event_key
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND INDEX_NAME = 'uk_event_key'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD UNIQUE KEY `uk_event_key` (`event_key`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_callback_event.idx_suite_status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND INDEX_NAME = 'idx_suite_status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD KEY `idx_suite_status` (`provider_suite_id`,`status`,`create_at`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_callback_event.idx_corp_type_time
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_callback_event') AND INDEX_NAME = 'idx_corp_type_time'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_callback_event` ADD KEY `idx_corp_type_time` (`auth_corpid`,`info_type`,`event_time`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- wecom_staff_binding
SET @wecom_sql = IF(@wecom_ready, CONCAT('CREATE TABLE IF NOT EXISTS `', @wecom_prefix, 'wecom_staff_binding` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'',
          `corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商授权企业记录ID'',
          `uid` int NOT NULL DEFAULT 0 COMMENT ''系统员工UID'',
          `wecom_userid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''企业微信成员UserID'',
          `open_userid` varchar(160) NOT NULL DEFAULT '''' COMMENT ''服务商域成员OpenUserID'',
          `id_scope` varchar(30) NOT NULL DEFAULT ''legacy'' COMMENT ''legacy/userid/open_userid'',
          `bind_source` varchar(30) NOT NULL DEFAULT ''manual'' COMMENT ''manual/oauth/miniapp/admin'',
          `verified_at` int NOT NULL DEFAULT 0 COMMENT ''最近验证时间'',
          `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''1启用0停用'',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_site_uid` (`site_id`,`uid`),
          KEY `idx_site_wecom_userid` (`site_id`,`wecom_userid`),
          KEY `idx_site_status` (`site_id`,`status`),
          KEY `idx_auth_open_userid` (`corp_authorization_id`,`open_userid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=''企业微信员工绑定'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.site_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'site_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.corp_authorization_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'corp_authorization_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商授权企业记录ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.uid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'uid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `uid` int NOT NULL DEFAULT 0 COMMENT ''系统员工UID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.wecom_userid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'wecom_userid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `wecom_userid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''企业微信成员UserID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.open_userid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'open_userid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `open_userid` varchar(160) NOT NULL DEFAULT '''' COMMENT ''服务商域成员OpenUserID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.id_scope
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'id_scope'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `id_scope` varchar(30) NOT NULL DEFAULT ''legacy'' COMMENT ''legacy/userid/open_userid'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.bind_source
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'bind_source'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `bind_source` varchar(30) NOT NULL DEFAULT ''manual'' COMMENT ''manual/oauth/miniapp/admin'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.verified_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'verified_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `verified_at` int NOT NULL DEFAULT 0 COMMENT ''最近验证时间'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''1启用0停用'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.create_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'create_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `create_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_staff_binding.update_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND COLUMN_NAME = 'update_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD COLUMN `update_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_staff_binding.PRIMARY
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND INDEX_NAME = 'PRIMARY'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD PRIMARY KEY (`id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_staff_binding.uk_site_uid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND INDEX_NAME = 'uk_site_uid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD UNIQUE KEY `uk_site_uid` (`site_id`,`uid`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_staff_binding.idx_site_wecom_userid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND INDEX_NAME = 'idx_site_wecom_userid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD KEY `idx_site_wecom_userid` (`site_id`,`wecom_userid`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_staff_binding.idx_site_status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND INDEX_NAME = 'idx_site_status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD KEY `idx_site_status` (`site_id`,`status`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_staff_binding.idx_auth_open_userid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_staff_binding') AND INDEX_NAME = 'idx_auth_open_userid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_staff_binding` ADD KEY `idx_auth_open_userid` (`corp_authorization_id`,`open_userid`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- wecom_message_log
SET @wecom_sql = IF(@wecom_ready, CONCAT('CREATE TABLE IF NOT EXISTS `', @wecom_prefix, 'wecom_message_log` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'',
          `corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商授权企业记录ID'',
          `channel_code` varchar(60) NOT NULL DEFAULT '''' COMMENT ''SaaS部署渠道编码快照'',
          `auth_corpid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''授权企业CorpID快照'',
          `agent_id` int NOT NULL DEFAULT 0 COMMENT ''授权企业应用AgentID快照'',
          `event_id` varchar(100) NOT NULL DEFAULT '''' COMMENT ''业务事件唯一标识'',
          `scene` varchar(50) NOT NULL DEFAULT '''' COMMENT ''通知场景'',
          `source_plugin` varchar(50) NOT NULL DEFAULT '''' COMMENT ''来源插件'',
          `source_type` varchar(50) NOT NULL DEFAULT '''' COMMENT ''来源类型'',
          `source_id` int NOT NULL DEFAULT 0 COMMENT ''来源业务ID'',
          `receiver_uid` int NOT NULL DEFAULT 0 COMMENT ''接收员工UID'',
          `receiver_name` varchar(60) NOT NULL DEFAULT '''' COMMENT ''接收员工名称快照'',
          `wecom_userid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''企业微信成员UserID快照'',
          `title` varchar(200) NOT NULL DEFAULT '''' COMMENT ''消息标题'',
          `content` text NULL COMMENT ''消息内容'',
          `target_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''任务跳转地址'',
          `payload_json` longtext NULL COMMENT ''业务事件快照'',
          `response_json` longtext NULL COMMENT ''企业微信响应'',
          `provider_msgid` varchar(160) NOT NULL DEFAULT '''' COMMENT ''企业微信消息ID'',
          `status` varchar(20) NOT NULL DEFAULT ''pending'' COMMENT ''pending/success/failed/skipped'',
          `retry_count` tinyint unsigned NOT NULL DEFAULT 0,
          `next_retry_at` int NOT NULL DEFAULT 0,
          `sent_at` int NOT NULL DEFAULT 0,
          `error_message` varchar(500) NOT NULL DEFAULT '''',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_site_event` (`site_id`,`event_id`),
          KEY `idx_retry` (`status`,`next_retry_at`,`retry_count`),
          KEY `idx_site_receiver` (`site_id`,`receiver_uid`,`create_at`),
          KEY `idx_auth_status` (`corp_authorization_id`,`status`,`create_at`),
          KEY `idx_channel_time` (`channel_code`,`create_at`),
          KEY `idx_provider_msgid` (`provider_msgid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=''企业微信消息发件箱与发送日志'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.site_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'site_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `site_id` int NOT NULL DEFAULT 0 COMMENT ''站点ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.corp_authorization_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'corp_authorization_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT ''服务商授权企业记录ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.channel_code
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'channel_code'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `channel_code` varchar(60) NOT NULL DEFAULT '''' COMMENT ''SaaS部署渠道编码快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.auth_corpid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'auth_corpid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `auth_corpid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''授权企业CorpID快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.agent_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'agent_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `agent_id` int NOT NULL DEFAULT 0 COMMENT ''授权企业应用AgentID快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.event_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'event_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `event_id` varchar(100) NOT NULL DEFAULT '''' COMMENT ''业务事件唯一标识'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.scene
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'scene'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `scene` varchar(50) NOT NULL DEFAULT '''' COMMENT ''通知场景'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.source_plugin
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'source_plugin'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `source_plugin` varchar(50) NOT NULL DEFAULT '''' COMMENT ''来源插件'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.source_type
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'source_type'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `source_type` varchar(50) NOT NULL DEFAULT '''' COMMENT ''来源类型'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.source_id
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'source_id'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `source_id` int NOT NULL DEFAULT 0 COMMENT ''来源业务ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.receiver_uid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'receiver_uid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `receiver_uid` int NOT NULL DEFAULT 0 COMMENT ''接收员工UID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.receiver_name
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'receiver_name'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `receiver_name` varchar(60) NOT NULL DEFAULT '''' COMMENT ''接收员工名称快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.wecom_userid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'wecom_userid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `wecom_userid` varchar(100) NOT NULL DEFAULT '''' COMMENT ''企业微信成员UserID快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.title
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'title'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `title` varchar(200) NOT NULL DEFAULT '''' COMMENT ''消息标题'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.content
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'content'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `content` text NULL COMMENT ''消息内容'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.target_url
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'target_url'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `target_url` varchar(1000) NOT NULL DEFAULT '''' COMMENT ''任务跳转地址'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.payload_json
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'payload_json'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `payload_json` longtext NULL COMMENT ''业务事件快照'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.response_json
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'response_json'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `response_json` longtext NULL COMMENT ''企业微信响应'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.provider_msgid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'provider_msgid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `provider_msgid` varchar(160) NOT NULL DEFAULT '''' COMMENT ''企业微信消息ID'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT ''pending'' COMMENT ''pending/success/failed/skipped'''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.retry_count
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'retry_count'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `retry_count` tinyint unsigned NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.next_retry_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'next_retry_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `next_retry_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.sent_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'sent_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `sent_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.error_message
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'error_message'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `error_message` varchar(500) NOT NULL DEFAULT '''''), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.create_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'create_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `create_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失列 wecom_message_log.update_at
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND COLUMN_NAME = 'update_at'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD COLUMN `update_at` int NOT NULL DEFAULT 0'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_message_log.PRIMARY
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND INDEX_NAME = 'PRIMARY'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD PRIMARY KEY (`id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_message_log.uk_site_event
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND INDEX_NAME = 'uk_site_event'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD UNIQUE KEY `uk_site_event` (`site_id`,`event_id`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_message_log.idx_retry
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND INDEX_NAME = 'idx_retry'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD KEY `idx_retry` (`status`,`next_retry_at`,`retry_count`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_message_log.idx_site_receiver
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND INDEX_NAME = 'idx_site_receiver'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD KEY `idx_site_receiver` (`site_id`,`receiver_uid`,`create_at`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_message_log.idx_auth_status
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND INDEX_NAME = 'idx_auth_status'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD KEY `idx_auth_status` (`corp_authorization_id`,`status`,`create_at`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_message_log.idx_channel_time
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND INDEX_NAME = 'idx_channel_time'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD KEY `idx_channel_time` (`channel_code`,`create_at`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

-- 缺失索引 wecom_message_log.idx_provider_msgid
SET @wecom_sql = IF(@wecom_ready AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @wecom_schema AND TABLE_NAME = CONCAT(@wecom_prefix, 'wecom_message_log') AND INDEX_NAME = 'idx_provider_msgid'), CONCAT('ALTER TABLE `', @wecom_prefix, 'wecom_message_log` ADD KEY `idx_provider_msgid` (`provider_msgid`)'), 'SELECT ''SKIPPED (already exists or preflight not ready)'' AS wecom_step');
PREPARE wecom_stmt FROM @wecom_sql;
EXECUTE wecom_stmt;
DEALLOCATE PREPARE wecom_stmt;

SELECT @wecom_schema AS target_database, @wecom_prefix AS table_prefix, IF(@wecom_ready, 'SQL ended; run manual_upgrade.php --check and inspect any earlier errors', 'NOT APPLIED: preflight failed') AS next_action;
