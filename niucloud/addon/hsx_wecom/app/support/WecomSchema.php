<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\support;

use think\facade\Db;

/**
 * 企业微信插件增量结构迁移。
 *
 * 只允许由插件 install/upgrade 生命周期调用，业务请求不得执行 DDL。
 */
final class WecomSchema
{
    public static function migrate(): void
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $suiteTable = $prefix . 'wecom_provider_suite';
        $authorizationTable = $prefix . 'wecom_corp_authorization';
        $intentTable = $prefix . 'wecom_authorization_intent';
        $callbackTable = $prefix . 'wecom_callback_event';
        $staffBindingTable = $prefix . 'wecom_staff_binding';
        $messageLogTable = $prefix . 'wecom_message_log';

        self::createTables(
            $suiteTable,
            $authorizationTable,
            $intentTable,
            $callbackTable,
            $staffBindingTable,
            $messageLogTable
        );

        $columns = [
            $suiteTable => [
                'channel_code' => "`channel_code` varchar(60) NOT NULL DEFAULT '' COMMENT 'SaaS部署渠道唯一编码' AFTER `id`",
                'provider_corp_id' => "`provider_corp_id` varchar(100) NOT NULL DEFAULT '' COMMENT '服务商企业CorpID' AFTER `channel_code`",
                'suite_id' => "`suite_id` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方应用SuiteID' AFTER `provider_corp_id`",
                'suite_secret_cipher' => "`suite_secret_cipher` longtext NULL COMMENT '加密保存的SuiteSecret' AFTER `suite_id`",
                'callback_token' => "`callback_token` varchar(255) NOT NULL DEFAULT '' COMMENT '回调Token' AFTER `suite_secret_cipher`",
                'encoding_aes_key_cipher' => "`encoding_aes_key_cipher` longtext NULL COMMENT '加密保存的EncodingAESKey' AFTER `callback_token`",
                'admin_miniapp_appid' => "`admin_miniapp_appid` varchar(100) NOT NULL DEFAULT '' COMMENT '管理端小程序AppID' AFTER `encoding_aes_key_cipher`",
                'admin_miniapp_name' => "`admin_miniapp_name` varchar(100) NOT NULL DEFAULT '' COMMENT '管理端小程序名称' AFTER `admin_miniapp_appid`",
                'web_base_url' => "`web_base_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '当前SaaS网页根地址' AFTER `admin_miniapp_name`",
                'event_callback_url' => "`event_callback_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '服务商事件回调地址' AFTER `web_base_url`",
                'auth_callback_url' => "`auth_callback_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '企业授权完成回调地址' AFTER `event_callback_url`",
                'suite_ticket' => "`suite_ticket` varchar(1000) NOT NULL DEFAULT '' COMMENT '企业微信推送的SuiteTicket' AFTER `auth_callback_url`",
                'suite_ticket_at' => "`suite_ticket_at` int NOT NULL DEFAULT 0 COMMENT 'SuiteTicket最近接收时间' AFTER `suite_ticket`",
                'status' => "`status` varchar(20) NOT NULL DEFAULT 'disabled' COMMENT 'enabled/disabled' AFTER `suite_ticket_at`",
                'last_error' => "`last_error` varchar(1000) NOT NULL DEFAULT '' COMMENT '最近错误' AFTER `status`",
                'create_at' => "`create_at` int NOT NULL DEFAULT 0 AFTER `last_error`",
                'update_at' => "`update_at` int NOT NULL DEFAULT 0 AFTER `create_at`",
            ],
            $authorizationTable => [
                'site_id' => "`site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID' AFTER `id`",
                'provider_suite_id' => "`provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商应用渠道ID' AFTER `site_id`",
                'auth_corpid' => "`auth_corpid` varchar(100) NOT NULL DEFAULT '' COMMENT '授权企业CorpID（服务商域）' AFTER `provider_suite_id`",
                'permanent_code_cipher' => "`permanent_code_cipher` longtext NULL COMMENT '加密保存的永久授权码' AFTER `auth_corpid`",
                'agent_id' => "`agent_id` int NOT NULL DEFAULT 0 COMMENT '授权企业应用AgentID' AFTER `permanent_code_cipher`",
                'corp_name' => "`corp_name` varchar(200) NOT NULL DEFAULT '' COMMENT '授权企业名称快照' AFTER `agent_id`",
                'auth_info_json' => "`auth_info_json` longtext NULL COMMENT '授权详情快照' AFTER `corp_name`",
                'status' => "`status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/authorized/changed/cancelled/error' AFTER `auth_info_json`",
                'last_error' => "`last_error` varchar(1000) NOT NULL DEFAULT '' COMMENT '最近错误' AFTER `status`",
                'authorized_at' => "`authorized_at` int NOT NULL DEFAULT 0 COMMENT '首次授权时间' AFTER `last_error`",
                'changed_at' => "`changed_at` int NOT NULL DEFAULT 0 COMMENT '授权变更时间' AFTER `authorized_at`",
                'cancelled_at' => "`cancelled_at` int NOT NULL DEFAULT 0 COMMENT '取消授权时间' AFTER `changed_at`",
                'create_at' => "`create_at` int NOT NULL DEFAULT 0 AFTER `cancelled_at`",
                'update_at' => "`update_at` int NOT NULL DEFAULT 0 AFTER `create_at`",
            ],
            $intentTable => [
                'state' => "`state` varchar(160) NOT NULL DEFAULT '' COMMENT '一次性授权状态码' AFTER `id`",
                'site_id' => "`site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID' AFTER `state`",
                'uid' => "`uid` int NOT NULL DEFAULT 0 COMMENT '发起员工UID' AFTER `site_id`",
                'provider_suite_id' => "`provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商应用渠道ID' AFTER `uid`",
                'purpose' => "`purpose` varchar(30) NOT NULL DEFAULT 'install' COMMENT 'install/member_bind' AFTER `provider_suite_id`",
                'pre_auth_code' => "`pre_auth_code` varchar(255) NOT NULL DEFAULT '' COMMENT '预授权码' AFTER `purpose`",
                'return_url' => "`return_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '授权完成后的安全回跳地址' AFTER `pre_auth_code`",
                'meta_json' => "`meta_json` longtext NULL COMMENT '授权上下文' AFTER `return_url`",
                'expires_at' => "`expires_at` int NOT NULL DEFAULT 0 COMMENT '状态码过期时间' AFTER `meta_json`",
                'used_at' => "`used_at` int NOT NULL DEFAULT 0 COMMENT '消费时间' AFTER `expires_at`",
                'create_at' => "`create_at` int NOT NULL DEFAULT 0 AFTER `used_at`",
            ],
            $callbackTable => [
                'event_key' => "`event_key` varchar(160) NOT NULL DEFAULT '' COMMENT '回调事件幂等键' AFTER `id`",
                'provider_suite_id' => "`provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商应用渠道ID' AFTER `event_key`",
                'auth_corpid' => "`auth_corpid` varchar(100) NOT NULL DEFAULT '' COMMENT '授权企业CorpID' AFTER `provider_suite_id`",
                'info_type' => "`info_type` varchar(60) NOT NULL DEFAULT '' COMMENT '企业微信回调类型' AFTER `auth_corpid`",
                'event_time' => "`event_time` int NOT NULL DEFAULT 0 COMMENT '企业微信事件时间' AFTER `info_type`",
                'payload_json' => "`payload_json` longtext NULL COMMENT '解密后的事件快照' AFTER `event_time`",
                'payload_hash' => "`payload_hash` char(64) NOT NULL DEFAULT '' COMMENT '事件内容SHA-256' AFTER `payload_json`",
                'status' => "`status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/processed/failed' AFTER `payload_hash`",
                'error_message' => "`error_message` varchar(1000) NOT NULL DEFAULT '' COMMENT '处理错误' AFTER `status`",
                'processed_at' => "`processed_at` int NOT NULL DEFAULT 0 COMMENT '处理完成时间' AFTER `error_message`",
                'create_at' => "`create_at` int NOT NULL DEFAULT 0 AFTER `processed_at`",
            ],
            $staffBindingTable => [
                'corp_authorization_id' => "`corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商授权企业记录ID' AFTER `site_id`",
                'open_userid' => "`open_userid` varchar(160) NOT NULL DEFAULT '' COMMENT '服务商域成员OpenUserID' AFTER `wecom_userid`",
                'id_scope' => "`id_scope` varchar(30) NOT NULL DEFAULT 'legacy' COMMENT 'legacy/userid/open_userid' AFTER `open_userid`",
                'bind_source' => "`bind_source` varchar(30) NOT NULL DEFAULT 'manual' COMMENT 'manual/oauth/miniapp/admin' AFTER `id_scope`",
                'verified_at' => "`verified_at` int NOT NULL DEFAULT 0 COMMENT '最近验证时间' AFTER `bind_source`",
            ],
            $messageLogTable => [
                'corp_authorization_id' => "`corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商授权企业记录ID' AFTER `site_id`",
                'channel_code' => "`channel_code` varchar(60) NOT NULL DEFAULT '' COMMENT 'SaaS部署渠道编码快照' AFTER `corp_authorization_id`",
                'auth_corpid' => "`auth_corpid` varchar(100) NOT NULL DEFAULT '' COMMENT '授权企业CorpID快照' AFTER `channel_code`",
                'agent_id' => "`agent_id` int NOT NULL DEFAULT 0 COMMENT '授权企业应用AgentID快照' AFTER `auth_corpid`",
                'provider_msgid' => "`provider_msgid` varchar(160) NOT NULL DEFAULT '' COMMENT '企业微信消息ID' AFTER `response_json`",
            ],
        ];

        foreach ($columns as $table => $definitions) {
            if (!self::hasTable($table)) continue;
            foreach ($definitions as $column => $definition) {
                if (!self::hasColumn($table, $column)) {
                    Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
                }
            }
        }

        self::ensureIndexes($suiteTable, $authorizationTable, $intentTable, $callbackTable, $staffBindingTable, $messageLogTable);
    }

    private static function createTables(
        string $suiteTable,
        string $authorizationTable,
        string $intentTable,
        string $callbackTable,
        string $staffBindingTable,
        string $messageLogTable
    ): void {
        Db::execute("CREATE TABLE IF NOT EXISTS `{$suiteTable}` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `channel_code` varchar(60) NOT NULL DEFAULT '' COMMENT 'SaaS部署渠道唯一编码',
          `provider_corp_id` varchar(100) NOT NULL DEFAULT '' COMMENT '服务商企业CorpID',
          `suite_id` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方应用SuiteID',
          `suite_secret_cipher` longtext NULL COMMENT '加密保存的SuiteSecret',
          `callback_token` varchar(255) NOT NULL DEFAULT '' COMMENT '回调Token',
          `encoding_aes_key_cipher` longtext NULL COMMENT '加密保存的EncodingAESKey',
          `admin_miniapp_appid` varchar(100) NOT NULL DEFAULT '' COMMENT '管理端小程序AppID',
          `admin_miniapp_name` varchar(100) NOT NULL DEFAULT '' COMMENT '管理端小程序名称',
          `web_base_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '当前SaaS网页根地址',
          `event_callback_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '服务商事件回调地址',
          `auth_callback_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '企业授权完成回调地址',
          `suite_ticket` varchar(1000) NOT NULL DEFAULT '' COMMENT '企业微信推送的SuiteTicket',
          `suite_ticket_at` int NOT NULL DEFAULT 0 COMMENT 'SuiteTicket最近接收时间',
          `status` varchar(20) NOT NULL DEFAULT 'disabled' COMMENT 'enabled/disabled',
          `last_error` varchar(1000) NOT NULL DEFAULT '' COMMENT '最近错误',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_channel_code` (`channel_code`),
          UNIQUE KEY `uk_suite_id` (`suite_id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='企业微信服务商应用渠道'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$authorizationTable}` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
          `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商应用渠道ID',
          `auth_corpid` varchar(100) NOT NULL DEFAULT '' COMMENT '授权企业CorpID（服务商域）',
          `permanent_code_cipher` longtext NULL COMMENT '加密保存的永久授权码',
          `agent_id` int NOT NULL DEFAULT 0 COMMENT '授权企业应用AgentID',
          `corp_name` varchar(200) NOT NULL DEFAULT '' COMMENT '授权企业名称快照',
          `auth_info_json` longtext NULL COMMENT '授权详情快照',
          `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/authorized/changed/cancelled/error',
          `last_error` varchar(1000) NOT NULL DEFAULT '' COMMENT '最近错误',
          `authorized_at` int NOT NULL DEFAULT 0 COMMENT '首次授权时间',
          `changed_at` int NOT NULL DEFAULT 0 COMMENT '授权变更时间',
          `cancelled_at` int NOT NULL DEFAULT 0 COMMENT '取消授权时间',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_site_suite` (`site_id`,`provider_suite_id`),
          UNIQUE KEY `uk_suite_corp` (`provider_suite_id`,`auth_corpid`),
          KEY `idx_site_status` (`site_id`,`status`),
          KEY `idx_auth_corpid` (`auth_corpid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='企业微信客户企业授权'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$intentTable}` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `state` varchar(160) NOT NULL DEFAULT '' COMMENT '一次性授权状态码',
          `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
          `uid` int NOT NULL DEFAULT 0 COMMENT '发起员工UID',
          `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商应用渠道ID',
          `purpose` varchar(30) NOT NULL DEFAULT 'install' COMMENT 'install/member_bind',
          `pre_auth_code` varchar(255) NOT NULL DEFAULT '' COMMENT '预授权码',
          `return_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '授权完成后的安全回跳地址',
          `meta_json` longtext NULL COMMENT '授权上下文',
          `expires_at` int NOT NULL DEFAULT 0 COMMENT '状态码过期时间',
          `used_at` int NOT NULL DEFAULT 0 COMMENT '消费时间',
          `create_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_state` (`state`),
          KEY `idx_site_purpose` (`site_id`,`purpose`,`create_at`),
          KEY `idx_expire_used` (`expires_at`,`used_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='企业微信授权与绑定意图'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$callbackTable}` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `event_key` varchar(160) NOT NULL DEFAULT '' COMMENT '回调事件幂等键',
          `provider_suite_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商应用渠道ID',
          `auth_corpid` varchar(100) NOT NULL DEFAULT '' COMMENT '授权企业CorpID',
          `info_type` varchar(60) NOT NULL DEFAULT '' COMMENT '企业微信回调类型',
          `event_time` int NOT NULL DEFAULT 0 COMMENT '企业微信事件时间',
          `payload_json` longtext NULL COMMENT '解密后的事件快照',
          `payload_hash` char(64) NOT NULL DEFAULT '' COMMENT '事件内容SHA-256',
          `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/processed/failed',
          `error_message` varchar(1000) NOT NULL DEFAULT '' COMMENT '处理错误',
          `processed_at` int NOT NULL DEFAULT 0 COMMENT '处理完成时间',
          `create_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_event_key` (`event_key`),
          KEY `idx_suite_status` (`provider_suite_id`,`status`,`create_at`),
          KEY `idx_corp_type_time` (`auth_corpid`,`info_type`,`event_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='企业微信服务商回调收件箱'");

        // 迁移可独立完成首次安装，避免某些部署未自动执行插件 SQL 时缺少基础表。
        Db::execute("CREATE TABLE IF NOT EXISTS `{$staffBindingTable}` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
          `corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商授权企业记录ID',
          `uid` int NOT NULL DEFAULT 0 COMMENT '系统员工UID',
          `wecom_userid` varchar(100) NOT NULL DEFAULT '' COMMENT '企业微信成员UserID',
          `open_userid` varchar(160) NOT NULL DEFAULT '' COMMENT '服务商域成员OpenUserID',
          `id_scope` varchar(30) NOT NULL DEFAULT 'legacy' COMMENT 'legacy/userid/open_userid',
          `bind_source` varchar(30) NOT NULL DEFAULT 'manual' COMMENT 'manual/oauth/miniapp/admin',
          `verified_at` int NOT NULL DEFAULT 0 COMMENT '最近验证时间',
          `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用0停用',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_site_uid` (`site_id`,`uid`),
          KEY `idx_site_wecom_userid` (`site_id`,`wecom_userid`),
          KEY `idx_site_status` (`site_id`,`status`),
          KEY `idx_auth_open_userid` (`corp_authorization_id`,`open_userid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='企业微信员工绑定'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$messageLogTable}` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
          `corp_authorization_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '服务商授权企业记录ID',
          `channel_code` varchar(60) NOT NULL DEFAULT '' COMMENT 'SaaS部署渠道编码快照',
          `auth_corpid` varchar(100) NOT NULL DEFAULT '' COMMENT '授权企业CorpID快照',
          `agent_id` int NOT NULL DEFAULT 0 COMMENT '授权企业应用AgentID快照',
          `event_id` varchar(100) NOT NULL DEFAULT '' COMMENT '业务事件唯一标识',
          `scene` varchar(50) NOT NULL DEFAULT '' COMMENT '通知场景',
          `source_plugin` varchar(50) NOT NULL DEFAULT '' COMMENT '来源插件',
          `source_type` varchar(50) NOT NULL DEFAULT '' COMMENT '来源类型',
          `source_id` int NOT NULL DEFAULT 0 COMMENT '来源业务ID',
          `receiver_uid` int NOT NULL DEFAULT 0 COMMENT '接收员工UID',
          `receiver_name` varchar(60) NOT NULL DEFAULT '' COMMENT '接收员工名称快照',
          `wecom_userid` varchar(100) NOT NULL DEFAULT '' COMMENT '企业微信成员UserID快照',
          `title` varchar(200) NOT NULL DEFAULT '' COMMENT '消息标题',
          `content` text NULL COMMENT '消息内容',
          `target_url` varchar(1000) NOT NULL DEFAULT '' COMMENT '任务跳转地址',
          `payload_json` longtext NULL COMMENT '业务事件快照',
          `response_json` longtext NULL COMMENT '企业微信响应',
          `provider_msgid` varchar(160) NOT NULL DEFAULT '' COMMENT '企业微信消息ID',
          `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/success/failed/skipped',
          `retry_count` tinyint unsigned NOT NULL DEFAULT 0,
          `next_retry_at` int NOT NULL DEFAULT 0,
          `sent_at` int NOT NULL DEFAULT 0,
          `error_message` varchar(500) NOT NULL DEFAULT '',
          `create_at` int NOT NULL DEFAULT 0,
          `update_at` int NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_site_event` (`site_id`,`event_id`),
          KEY `idx_retry` (`status`,`next_retry_at`,`retry_count`),
          KEY `idx_site_receiver` (`site_id`,`receiver_uid`,`create_at`),
          KEY `idx_auth_status` (`corp_authorization_id`,`status`,`create_at`),
          KEY `idx_channel_time` (`channel_code`,`create_at`),
          KEY `idx_provider_msgid` (`provider_msgid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='企业微信消息发件箱与发送日志'");
    }

    private static function ensureIndexes(
        string $suiteTable,
        string $authorizationTable,
        string $intentTable,
        string $callbackTable,
        string $staffBindingTable,
        string $messageLogTable
    ): void {
        $indexes = [
            $suiteTable => [
                'uk_channel_code' => 'UNIQUE KEY `uk_channel_code` (`channel_code`)',
                'uk_suite_id' => 'UNIQUE KEY `uk_suite_id` (`suite_id`)',
                'idx_status' => 'KEY `idx_status` (`status`)',
            ],
            $authorizationTable => [
                'uk_site_suite' => 'UNIQUE KEY `uk_site_suite` (`site_id`,`provider_suite_id`)',
                'uk_suite_corp' => 'UNIQUE KEY `uk_suite_corp` (`provider_suite_id`,`auth_corpid`)',
                'idx_site_status' => 'KEY `idx_site_status` (`site_id`,`status`)',
                'idx_auth_corpid' => 'KEY `idx_auth_corpid` (`auth_corpid`)',
            ],
            $intentTable => [
                'uk_state' => 'UNIQUE KEY `uk_state` (`state`)',
                'idx_site_purpose' => 'KEY `idx_site_purpose` (`site_id`,`purpose`,`create_at`)',
                'idx_expire_used' => 'KEY `idx_expire_used` (`expires_at`,`used_at`)',
            ],
            $callbackTable => [
                'uk_event_key' => 'UNIQUE KEY `uk_event_key` (`event_key`)',
                'idx_suite_status' => 'KEY `idx_suite_status` (`provider_suite_id`,`status`,`create_at`)',
                'idx_corp_type_time' => 'KEY `idx_corp_type_time` (`auth_corpid`,`info_type`,`event_time`)',
            ],
            $staffBindingTable => [
                'idx_auth_open_userid' => 'KEY `idx_auth_open_userid` (`corp_authorization_id`,`open_userid`)',
            ],
            $messageLogTable => [
                'idx_auth_status' => 'KEY `idx_auth_status` (`corp_authorization_id`,`status`,`create_at`)',
                'idx_channel_time' => 'KEY `idx_channel_time` (`channel_code`,`create_at`)',
                'idx_provider_msgid' => 'KEY `idx_provider_msgid` (`provider_msgid`)',
            ],
        ];

        foreach ($indexes as $table => $definitions) {
            if (!self::hasTable($table)) continue;
            foreach ($definitions as $index => $definition) {
                if (!self::hasIndex($table, $index)) {
                    Db::execute("ALTER TABLE `{$table}` ADD {$definition}");
                }
            }
        }
    }

    private static function hasTable(string $table): bool
    {
        return Db::query("SHOW TABLES LIKE '" . addslashes($table) . "'") !== [];
    }

    private static function hasColumn(string $table, string $column): bool
    {
        return Db::query("SHOW COLUMNS FROM `{$table}` LIKE '" . addslashes($column) . "'") !== [];
    }

    private static function hasIndex(string $table, string $index): bool
    {
        return Db::query("SHOW INDEX FROM `{$table}` WHERE `Key_name` = '" . addslashes($index) . "'") !== [];
    }
}
