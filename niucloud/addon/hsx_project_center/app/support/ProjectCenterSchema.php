<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\support;

use think\facade\Db;

/**
 * 项目中心增量结构迁移。
 *
 * 只在插件 install/upgrade 生命周期执行，业务请求不执行 DDL。
 */
final class ProjectCenterSchema
{
    public static function migrate(): void
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $projectTable = $prefix . 'project_center_project';
        $groupAliasTable = $prefix . 'project_center_group_no_alias';
        $groupChangeLogTable = $prefix . 'project_center_group_no_change_log';
        $applicationTable = $prefix . 'project_center_application';
        $refundTable = $prefix . 'project_center_refund';

        self::createDistributionTables($prefix);

        if (self::hasTable($projectTable) && !self::hasColumn($projectTable, 'intro_page_id')) {
            Db::execute("ALTER TABLE `{$projectTable}` ADD COLUMN `intro_page_id` int NOT NULL DEFAULT 0 COMMENT '项目介绍DIY页面' AFTER `sort`");
        }

        if (!self::hasTable($groupAliasTable)) {
            Db::execute("CREATE TABLE `{$groupAliasTable}` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `site_id` int NOT NULL DEFAULT 0,
              `project_id` bigint unsigned NOT NULL DEFAULT 0,
              `group_id` bigint unsigned NOT NULL DEFAULT 0,
              `old_group_no` varchar(20) NOT NULL DEFAULT '',
              `old_group_no_full` varchar(40) NOT NULL DEFAULT '',
              `new_group_no` varchar(20) NOT NULL DEFAULT '',
              `new_group_no_full` varchar(40) NOT NULL DEFAULT '',
              `reason` varchar(500) NOT NULL DEFAULT '',
              `source` varchar(24) NOT NULL DEFAULT 'group_ledger',
              `application_id` bigint unsigned NOT NULL DEFAULT 0,
              `operator_id` int NOT NULL DEFAULT 0,
              `operator_name` varchar(100) NOT NULL DEFAULT '',
              `create_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_site_old_group_no` (`site_id`,`old_group_no_full`),
              KEY `idx_group` (`site_id`,`group_id`,`id`),
              KEY `idx_project` (`site_id`,`project_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-客户群历史编号别名'");
        }

        if (!self::hasTable($groupChangeLogTable)) {
            Db::execute("CREATE TABLE `{$groupChangeLogTable}` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `site_id` int NOT NULL DEFAULT 0,
              `project_id` bigint unsigned NOT NULL DEFAULT 0,
              `group_id` bigint unsigned NOT NULL DEFAULT 0,
              `old_group_no` varchar(20) NOT NULL DEFAULT '',
              `old_group_no_full` varchar(40) NOT NULL DEFAULT '',
              `new_group_no` varchar(20) NOT NULL DEFAULT '',
              `new_group_no_full` varchar(40) NOT NULL DEFAULT '',
              `reason` varchar(500) NOT NULL DEFAULT '',
              `source` varchar(24) NOT NULL DEFAULT 'group_ledger',
              `application_id` bigint unsigned NOT NULL DEFAULT 0,
              `operator_id` int NOT NULL DEFAULT 0,
              `operator_name` varchar(100) NOT NULL DEFAULT '',
              `create_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `idx_group_time` (`site_id`,`group_id`,`id`),
              KEY `idx_application` (`site_id`,`application_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-客户群编号更正日志'");
        }

        if (!self::hasTable($refundTable)) {
            Db::execute("CREATE TABLE `{$refundTable}` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `site_id` int NOT NULL DEFAULT 0,
              `refund_no` varchar(40) NOT NULL DEFAULT '',
              `project_id` bigint unsigned NOT NULL DEFAULT 0,
              `group_id` bigint unsigned NOT NULL DEFAULT 0,
              `application_id` bigint unsigned NOT NULL DEFAULT 0,
              `member_id` int NOT NULL DEFAULT 0,
              `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
              `status` varchar(24) NOT NULL DEFAULT 'pending',
              `origin_group_status` varchar(24) NOT NULL DEFAULT '',
              `origin_application_status` varchar(24) NOT NULL DEFAULT '',
              `reason` varchar(1000) NOT NULL DEFAULT '',
              `proof` varchar(1000) NOT NULL DEFAULT '',
              `remark` varchar(1000) NOT NULL DEFAULT '',
              `requested_at` int NOT NULL DEFAULT 0,
              `refunded_at` int NOT NULL DEFAULT 0,
              `cancelled_at` int NOT NULL DEFAULT 0,
              `request_operator_id` int NOT NULL DEFAULT 0,
              `request_operator_name` varchar(100) NOT NULL DEFAULT '',
              `complete_operator_id` int NOT NULL DEFAULT 0,
              `complete_operator_name` varchar(100) NOT NULL DEFAULT '',
              `create_at` int NOT NULL DEFAULT 0,
              `update_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_site_refund_no` (`site_id`,`refund_no`),
              UNIQUE KEY `uk_site_group` (`site_id`,`group_id`),
              KEY `idx_status_time` (`site_id`,`status`,`requested_at`),
              KEY `idx_application` (`site_id`,`application_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-退款闭环台账'");
        }

        if (!self::hasTable($applicationTable)) return;
        foreach ([
            'payment_confirmed_at' => "int NOT NULL DEFAULT 0 COMMENT '审核员核对群内流水时间' AFTER `payment_declared_at`",
            'payment_confirmed_uid' => "int NOT NULL DEFAULT 0 COMMENT '核对流水的管理员' AFTER `payment_confirmed_at`",
            'payment_confirmed_name' => "varchar(100) NOT NULL DEFAULT '' COMMENT '核对流水的管理员名称' AFTER `payment_confirmed_uid`",
            'eligibility_snapshot' => "longtext NULL COMMENT '付款前地区参与资格查询快照' AFTER `payment_confirmed_name`",
        ] as $column => $definition) {
            if (!self::hasColumn($applicationTable, $column)) {
                Db::execute("ALTER TABLE `{$applicationTable}` ADD COLUMN `{$column}` {$definition}");
            }
        }
        if (!self::hasColumn($applicationTable, 'idempotency_key')) {
            // 允许 NULL，使加列与历史数据回填之间不会因默认空字符串产生冲突。
            Db::execute("ALTER TABLE `{$applicationTable}` ADD COLUMN `idempotency_key` varchar(160) NULL DEFAULT NULL COMMENT '工单业务幂等键' AFTER `member_id`");
        }

        self::backfillIdempotencyKeys($applicationTable);
        if (!self::hasIndex($applicationTable, 'uk_site_idempotency')) {
            Db::execute("ALTER TABLE `{$applicationTable}` ADD UNIQUE KEY `uk_site_idempotency` (`site_id`,`idempotency_key`)");
        }
    }

    private static function createDistributionTables(string $prefix): void
    {
        $tables = [
            $prefix . 'project_center_invite' => "CREATE TABLE `%s` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
              `project_id` bigint unsigned NOT NULL DEFAULT 0, `inviter_member_id` int NOT NULL DEFAULT 0,
              `token` varchar(64) NOT NULL DEFAULT '', `status` tinyint unsigned NOT NULL DEFAULT 1,
              `use_count` int unsigned NOT NULL DEFAULT 0, `last_used_at` int NOT NULL DEFAULT 0,
              `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`), UNIQUE KEY `uk_site_project_inviter` (`site_id`,`project_id`,`inviter_member_id`),
              UNIQUE KEY `uk_site_token` (`site_id`,`token`), KEY `idx_project_status` (`site_id`,`project_id`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-分销邀请凭证'",
            $prefix . 'project_center_relation_log' => "CREATE TABLE `%s` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
              `project_id` bigint unsigned NOT NULL DEFAULT 0, `member_id` int NOT NULL DEFAULT 0,
              `inviter_member_id` int NOT NULL DEFAULT 0, `invite_id` bigint unsigned NOT NULL DEFAULT 0,
              `action` varchar(24) NOT NULL DEFAULT 'bind', `source` varchar(24) NOT NULL DEFAULT 'project_share',
              `remark` varchar(500) NOT NULL DEFAULT '', `operator_type` varchar(20) NOT NULL DEFAULT 'member',
              `operator_id` int NOT NULL DEFAULT 0, `operator_name` varchar(100) NOT NULL DEFAULT '', `create_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`), KEY `idx_member_time` (`site_id`,`member_id`,`id`),
              KEY `idx_inviter_time` (`site_id`,`inviter_member_id`,`id`), KEY `idx_project` (`site_id`,`project_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-推荐关系审计日志'",
            $prefix . 'project_center_distribution_order' => "CREATE TABLE `%s` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
              `order_no` varchar(40) NOT NULL DEFAULT '', `project_id` bigint unsigned NOT NULL DEFAULT 0,
              `application_id` bigint unsigned NOT NULL DEFAULT 0, `buyer_member_id` int NOT NULL DEFAULT 0,
              `base_amount` decimal(12,2) NOT NULL DEFAULT 0.00, `rule_snapshot` longtext NULL,
              `status` varchar(24) NOT NULL DEFAULT 'pending', `settle_at` int NOT NULL DEFAULT 0,
              `settled_at` int NOT NULL DEFAULT 0, `frozen_at` int NOT NULL DEFAULT 0,
              `cancelled_at` int NOT NULL DEFAULT 0, `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
              `error_message` varchar(1000) NOT NULL DEFAULT '', `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`), UNIQUE KEY `uk_site_application` (`site_id`,`application_id`),
              UNIQUE KEY `uk_site_order_no` (`site_id`,`order_no`), KEY `idx_settle` (`site_id`,`status`,`settle_at`),
              KEY `idx_project` (`site_id`,`project_id`,`id`), KEY `idx_buyer` (`site_id`,`buyer_member_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-分销业务佣金单'",
            $prefix . 'project_center_distribution_detail' => "CREATE TABLE `%s` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
              `order_id` bigint unsigned NOT NULL DEFAULT 0, `application_id` bigint unsigned NOT NULL DEFAULT 0,
              `project_id` bigint unsigned NOT NULL DEFAULT 0, `beneficiary_member_id` int NOT NULL DEFAULT 0,
              `relation_level` tinyint unsigned NOT NULL DEFAULT 1, `beneficiary_level_id` int NOT NULL DEFAULT 0,
              `beneficiary_level_name` varchar(100) NOT NULL DEFAULT '', `level_snapshot` longtext NULL,
              `base_commission` decimal(12,2) NOT NULL DEFAULT 0.00, `coefficient` decimal(8,2) NOT NULL DEFAULT 100.00,
              `commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00, `debt_offset_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
              `settled_amount` decimal(12,2) NOT NULL DEFAULT 0.00, `reversed_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
              `debt_amount` decimal(12,2) NOT NULL DEFAULT 0.00, `status` varchar(24) NOT NULL DEFAULT 'pending',
              `account_log_id` bigint unsigned NOT NULL DEFAULT 0, `reverse_log_id` bigint unsigned NOT NULL DEFAULT 0,
              `settle_at` int NOT NULL DEFAULT 0, `settled_at` int NOT NULL DEFAULT 0,
              `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`), UNIQUE KEY `uk_order_relation_level` (`order_id`,`relation_level`),
              KEY `idx_member_status` (`site_id`,`beneficiary_member_id`,`status`,`settle_at`),
              KEY `idx_application` (`site_id`,`application_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-一级二级佣金明细'",
            $prefix . 'project_center_distribution_debt' => "CREATE TABLE `%s` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
              `member_id` int NOT NULL DEFAULT 0, `detail_id` bigint unsigned NOT NULL DEFAULT 0,
              `amount` decimal(12,2) NOT NULL DEFAULT 0.00, `offset_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
              `status` varchar(20) NOT NULL DEFAULT 'pending', `reason` varchar(500) NOT NULL DEFAULT '',
              `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`), UNIQUE KEY `uk_detail` (`detail_id`),
              KEY `idx_member_status` (`site_id`,`member_id`,`status`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='项目合作中心-退款佣金欠款'",
        ];
        foreach ($tables as $table => $sql) {
            if (!self::hasTable($table)) Db::execute(sprintf($sql, $table));
        }
    }

    /**
     * 同一群或同一“项目+直达会员”历史上可能已经有多条工单。
     * 最新一条获得正式幂等键，旧行保留唯一 legacy 键，既不丢历史也能建立唯一索引。
     */
    private static function backfillIdempotencyKeys(string $table): void
    {
        $rows = Db::table($table)
            ->field('id,site_id,project_id,group_id,member_id,idempotency_key')
            ->order('id desc')->select()->toArray();
        $seen = [];
        $targets = [];
        foreach ($rows as $row) {
            $siteId = (int)$row['site_id'];
            $projectId = (int)$row['project_id'];
            $groupId = (int)$row['group_id'];
            $memberId = (int)$row['member_id'];
            $canonical = $groupId > 0
                ? 'group:' . $projectId . ':' . $groupId
                : 'direct:' . $projectId . ':' . $memberId;
            $scopeKey = $siteId . '|' . $canonical;
            $target = isset($seen[$scopeKey]) ? 'legacy:' . (int)$row['id'] : $canonical;
            $seen[$scopeKey] = true;
            $targets[(int)$row['id']] = $target;
        }

        // 两阶段改名：即便此前已经存在唯一索引，历史行暂占了另一行的目标键，
        // 也先切到逐行唯一的临时键，再写正式键，保证 upgrade 可重复执行。
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if ((string)($row['idempotency_key'] ?? '') === ($targets[$id] ?? '')) continue;
            Db::table($table)->where('id', '=', $id)->update(['idempotency_key' => 'migrating:' . $id]);
        }
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $target = (string)($targets[$id] ?? 'legacy:' . $id);
            if ((string)($row['idempotency_key'] ?? '') === $target) continue;
            Db::table($table)->where('id', '=', $id)->update(['idempotency_key' => $target]);
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
