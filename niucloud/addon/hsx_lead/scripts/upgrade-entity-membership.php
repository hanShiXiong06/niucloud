<?php
declare(strict_types=1);
/** Explicit additive DDL. Never run implicitly from an API, login or plugin page. */
if (PHP_SAPI !== 'cli') exit(1);
$mode = $argv[1] ?? '';
if (count($argv) !== 2 || !in_array($mode, ['--check', '--apply'], true)) exit("php addon/hsx_lead/scripts/upgrade-entity-membership.php --check|--apply\n");
define('HSX_LEAD_UPGRADE_LIBRARY_ONLY', true);
require __DIR__.'/upgrade.php';
use think\facade\Db;
try {
    $backend = dirname(__DIR__, 3);
    \addon\hsx_lead\scripts\bootstrap($backend);
    $db = Db::getConfig();
    $prefix = (string) ($db['connections'][$db['default']]['prefix'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix)) throw new RuntimeException('表前缀格式无效');
    $ddl = [];
    $before = [];
    foreach (['hsx_lead_merchant', 'hsx_lead_partner'] as $table) {
        $columns = Db::query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$prefix.$table]);
        if (!$columns) throw new RuntimeException('请先正常安装插件，缺少基础表：'.$table);
        $before[$table] = $columns;
        $names = array_column($columns, 'COLUMN_NAME');
        $fields = $table === 'hsx_lead_merchant' ? ['subject_code' => 'char(10) NULL DEFAULT NULL'] : ['membership_level' => "varchar(16) NOT NULL DEFAULT ''", 'membership_until' => 'int unsigned NOT NULL DEFAULT 0'];
        foreach ($fields as $name => $definition) if (!in_array($name, $names, true)) $ddl[] = 'ALTER TABLE `'.$prefix.$table.'` ADD COLUMN `'.$name.'` '.$definition;
    }
    $indexes = Db::query('SHOW INDEX FROM `'.$prefix.'hsx_lead_merchant`');
    $before['merchant_indexes'] = $indexes;
    if (!in_array('uk_site_subject_code', array_column($indexes, 'Key_name'), true)) $ddl[] = 'ALTER TABLE `'.$prefix.'hsx_lead_merchant` ADD UNIQUE KEY `uk_site_subject_code` (`site_id`,`subject_code`)';
    $exists = Db::query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$prefix.'hsx_lead_merchant_member']);
    if (!$exists) {
        $sql = (string) file_get_contents(dirname(__DIR__).'/sql/install.sql');
        if (!preg_match('/CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}hsx_lead_merchant_member`\s*\(.*?\) ENGINE=InnoDB[^;]*;/s', $sql, $match)) throw new RuntimeException('成员表声明缺失，请完整上传插件');
        $ddl[] = str_replace('{{prefix}}', $prefix, $match[0]);
    }
    if ($mode === '--check') {
        echo json_encode(['mode' => 'check', 'pending_statements' => count($ddl), 'adds' => ['merchant.subject_code', 'partner.membership_level', 'partner.membership_until', 'merchant_member'], 'data_writes' => false, 'framework_changes' => false], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),PHP_EOL;
        exit(0);
    }
    if (!$ddl) {
        echo "[通过] 主体会员结构已齐全，未重复执行DDL。\n";
        exit(0);
    }
    $path = $backend.'/runtime/hsx_lead_upgrade/entity-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
    if (!mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('无法创建结构备份目录，未执行DDL');
    $backup = $path.'/schema-before.json';
    if (file_put_contents($backup, json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('结构备份失败，未执行DDL');
    chmod($backup, 0600);
    // MySQL DDL commits individually. Idempotent re-run checks each field; never claim rollback.
    foreach ($ddl as $statement) Db::execute($statement);
    echo '[完成] 新增成员关系表和主体会员字段；未转换个人会员等级、未更改历史付款或业务。结构备份：'.$backup.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[未完整执行] '.($e instanceof RuntimeException ? $e->getMessage() : '数据库结构操作失败，详情请查看服务器私有日志；勿卸载或清库')."\nDDL逐条提交，可修正后重新执行--check；不要假定已回滚。\n");
    exit(1);
}
