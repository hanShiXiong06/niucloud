<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration;

use think\facade\Db;

/**
 * 外部 ERP 集成框架插件
 *
 * 管理本站外部 ERP 对接与回执。按已安装适配器声明的能力开放原单操作。
 */
class Addon
{
    public function install()
    {
        $this->installTables();
        // Uninstall deliberately preserves records, so reinstall may encounter the earlier schema too.
        $this->ensureBridgeSchema();
        return true;
    }

    public function uninstall()
    {
        // 不删除数据表，保留集成历史记录
        return true;
    }

    public function upgrade()
    {
        $this->installTables();
        $this->ensureBridgeSchema();
        return true;
    }

    /** MySQL permits multiple NULL keys. Pre-bridge tasks are neither updated nor deleted. */
    private function ensureBridgeSchema(): void
    {
        $prefix = $this->prefix();
        $tasks = $prefix . 'erp_integration_sync_task';
        $connections = $prefix . 'erp_integration_connection';
        foreach ([
            $connections => ['bridge_started_at'=>'int(11) NOT NULL DEFAULT 0'],
            // Opaque IDs: keep byte-wise uniqueness even if the framework rewrites SQL collations.
            $tasks => ['origin_event_id'=>'varbinary(128) DEFAULT NULL',
                'fact_key'=>'varbinary(160) DEFAULT NULL',
                'target_key'=>'varbinary(64) DEFAULT NULL','receipt_json'=>'text'],
        ] as $table => $spec) {
            $columns = array_column(Db::query("SHOW COLUMNS FROM `$table`"), 'Field');
            foreach ($spec as $column=>$type) if (!in_array($column,$columns,true)) {
                Db::execute("ALTER TABLE `$table` ADD COLUMN `$column` $type");
            }
        }
        $status = Db::query("SHOW COLUMNS FROM `$tasks` WHERE Field = 'status'")[0] ?? [];
        if (preg_match('/^varchar\((\d+)\)$/i', (string)($status['Type'] ?? ''), $m) && (int)$m[1] < 32) {
            Db::execute("ALTER TABLE `$tasks` MODIFY COLUMN `status` varchar(32) NOT NULL DEFAULT 'pending'");
        }
        $snapshot = Db::query("SHOW COLUMNS FROM `$tasks` WHERE Field = 'payload'")[0] ?? [];
        if (in_array(strtolower((string)($snapshot['Type'] ?? '')), ['tinytext','text'],true)) {
            Db::execute("ALTER TABLE `$tasks` MODIFY COLUMN `payload` mediumtext");
        }
        $indexes = Db::query("SHOW INDEX FROM `$tasks`");
        foreach (['uk_bridge_fact'=>'fact_key','uk_bridge_event'=>'origin_event_id'] as $name=>$key) {
            $existing = array_values(array_filter($indexes, static fn($i)=>$i['Key_name']===$name));
            if ($existing) {
                usort($existing, static fn($a,$b)=>(int)$a['Seq_in_index'] <=>(int)$b['Seq_in_index']);
                if ((int)$existing[0]['Non_unique']!==0 || array_column($existing,'Column_name')!==['site_id','target_key',$key])
                    throw new \RuntimeException('原单集成唯一索引定义不一致，未删除现有索引；请管理员核对');
                continue;
            }
            if (Db::query("SELECT 1 FROM `$tasks` WHERE target_key IS NOT NULL AND `$key` IS NOT NULL GROUP BY site_id,target_key,`$key` HAVING COUNT(*)>1 LIMIT 1"))
                throw new \RuntimeException('发现目标账套原单重复，升级中止。请人工核对；未修改旧记录');
            Db::execute("ALTER TABLE `$tasks` ADD UNIQUE KEY `$name` (`site_id`,`target_key`,`$key`)");
        }
    }

    private function installTables(): void
    {
        $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';
        if (!is_file($sqlFile)) {
            return;
        }

        $prefix = $this->prefix();
        $sql = str_replace('{{prefix}}', $prefix, (string)file_get_contents($sqlFile));

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (!empty($statement)) {
                Db::execute($statement);
            }
        }
    }

    private function prefix(): string
    {
        $prefix = (string)Db::connect()->getConfig('prefix');
        if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix)) throw new \RuntimeException('数据库表前缀不合法，原单集成安装或升级中止');
        return $prefix;
    }
}
