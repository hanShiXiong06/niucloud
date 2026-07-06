<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpOperationLog;
use core\base\BaseAdminService;
use think\facade\Db;

class ErpOperationLogService extends BaseAdminService
{
    private static bool $schemaEnsured = false;

    public function record(string $action, string $sourceType, int $sourceId, string $sourceNo = '', string $remark = '', array $extra = []): int
    {
        $this->ensureSchema();
        $now = time();
        $row = ErpOperationLog::create([
            'site_id' => $this->site_id,
            'action' => $action,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_no' => $sourceNo,
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'remark' => $remark,
            'extra_json' => !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : '',
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;
        $table = (new ErpOperationLog())->getTable();
        Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `site_id` int NOT NULL DEFAULT 0,
            `action` varchar(60) NOT NULL DEFAULT '',
            `source_type` varchar(40) NOT NULL DEFAULT '',
            `source_id` int NOT NULL DEFAULT 0,
            `source_no` varchar(40) NOT NULL DEFAULT '',
            `operator_uid` int NOT NULL DEFAULT 0,
            `operator_name` varchar(60) NOT NULL DEFAULT '',
            `remark` varchar(500) NOT NULL DEFAULT '',
            `extra_json` longtext,
            `create_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_source` (`site_id`,`source_type`,`source_id`),
            KEY `idx_action` (`site_id`,`action`,`create_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-操作日志'");
    }
}
