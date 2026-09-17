<?php
declare(strict_types=1);

/**
 * 一次性本地维护，不是安装/升级脚本。默认只读；只允许本机 saas_ / ns_ / 100000。
 * --apply=<预览指纹>：备份、回滚演练、备份恢复演练，最后提交。
 * --restore=<备份目录>：仅在当前数据仍与重建后完全相同时恢复；绝不覆盖后续新业务。
 */
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__, 2);
if (realpath($root) !== '/Users/a123/Documents/1-work/niucloud/niucloud') {
    throw new RuntimeException('仅允许在已确认的本地工作区运行，禁止部署到服务器执行');
}
require $root . '/niucloud/vendor/autoload.php';
(new think\App())->initialize();
$dbConfig = config('database.connections.mysql');
if (!in_array($dbConfig['hostname'], ['localhost', '127.0.0.1', '::1'], true)
    || $dbConfig['database'] !== 'saas_' || $dbConfig['prefix'] !== 'ns_') {
    throw new RuntimeException('数据库不符合授权范围：本地 saas_ / ns_');
}
think\facade\Db::query('SELECT 1');
$pdo = think\facade\Db::connect()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
$pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
$site = 100000;
$json = static fn(mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$query = static function (string $sql, array $params = []) use ($pdo): array {
    $s = $pdo->prepare($sql); $s->execute($params);
    return $s->fetchAll(PDO::FETCH_ASSOC);
};
$execute = static function (string $sql, array $params = []) use ($pdo): int {
    $s = $pdo->prepare($sql); $s->execute($params); return $s->rowCount();
};
if (!$query('SELECT site_id FROM ns_site WHERE site_id = ?', [$site])) throw new RuntimeException('本地站点 100000 不存在');

$delete = [
    'erp_settlement_link', 'erp_settlement', 'erp_offset_link', 'erp_offset',
    'erp_money_ledger', 'erp_account_ledger', 'erp_payable', 'erp_receivable',
    'erp_sale_return_item', 'erp_sale_return', 'erp_sale_item', 'erp_sale_order',
    'erp_purchase_return_item', 'erp_purchase_return', 'erp_purchase_item', 'erp_purchase_order',
    'erp_stocktake_item', 'erp_stocktake', 'erp_opening_item', 'erp_opening_batch',
    'erp_quantity_stock_flow', 'erp_quantity_stock', 'erp_print_job',
    'erp_channel_listing', 'erp_asset_ledger', 'erp_asset',
];
$rules = [];
foreach ($delete as $table) $rules[$table] = ['table' => $table, 'where' => 'site_id = 100000', 'action' => 'delete'];
$rules['erp_capital_account'] = ['table' => 'erp_capital_account', 'where' => 'site_id = 100000', 'action' => 'balance'];
$rules['sys_config'] = ['table' => 'sys_config', 'where' => "site_id = 100000 AND config_key IN ('HSX_ERP_RULES', 'recycle_erp_integration')", 'action' => 'config'];
$rules['erp_operation_log'] = ['table' => 'erp_operation_log', 'where' => 'site_id = 100000', 'action' => 'audit'];
foreach (['performance_fact', 'performance_employee_daily'] as $table) {
    $rules[$table] = ['table' => $table, 'where' => "site_id = 100000 AND source_plugin = 'hsx_erp'", 'action' => 'delete'];
}
$rules['performance_anomaly'] = ['table' => 'performance_anomaly', 'where' => "site_id = 100000 AND LEFT(event_id, 8) = 'hsx_erp:'", 'action' => 'delete'];
$rules['performance_report'] = ['table' => 'performance_report', 'where' => "site_id = 100000 AND COALESCE(JSON_CONTAINS(providers_json, '\"hsx_erp\"'), 0) = 1", 'action' => 'delete'];
$schemas = [];
$primary = [];
$protected = [];
foreach ($query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()') as $meta) {
    $full = $meta['TABLE_NAME'];
    if (!str_starts_with($full, 'ns_')) continue;
    $table = substr($full, 3);
    if (!isset($rules[$table]) && !preg_match('/^(erp_|phone_shop_|recycle_|device_asset_|performance_)/', $table)) continue;
    $columns = $query("SHOW COLUMNS FROM `$full`");
    if (!in_array('site_id', array_column($columns, 'Field'), true)) continue;
    $pk = array_column(array_filter($columns, static fn(array $r): bool => $r['Key'] === 'PRI'), 'Field');
    if (!$pk) throw new RuntimeException('缺少稳定主键，停止：' . $table);
    $primary[$table] = implode(',', array_map(static fn(string $c): string => "`$c`", $pk));
    if (isset($rules[$table])) {
        if ($meta['ENGINE'] !== 'InnoDB') throw new RuntimeException('不是事务表：' . $table);
        $schemas[$table] = $query("SHOW CREATE TABLE `$full`")[0]['Create Table'];
        $protected[$table] = 'NOT (' . $rules[$table]['where'] . ')';
    } else {
        $protected[$table] = 'site_id = 100000';
    }
}
if (array_diff(array_keys($rules), array_keys($schemas))) throw new RuntimeException('维护清单有缺表，禁止部分清理');
ksort($protected);
$fingerprint = static function (string $table, string $where) use ($pdo, $primary, $json): array {
    $stmt = $pdo->query("SELECT * FROM `ns_$table` FORCE INDEX (PRIMARY) WHERE $where ORDER BY " . $primary[$table]);
    $hash = hash_init('sha256'); $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { hash_update($hash, $json($row) . "\n"); $count++; }
    return ['count' => $count, 'sha256' => hash_final($hash)];
};
$capture = static function () use ($rules, $query, $primary, $protected, $fingerprint): array {
    $rows = []; $preserved = [];
    foreach ($rules as $key => $rule) $rows[$key] = $query("SELECT * FROM `ns_{$rule['table']}` FORCE INDEX (PRIMARY) WHERE {$rule['where']} ORDER BY " . $primary[$rule['table']]);
    foreach ($protected as $table => $where) $preserved[$table] = $fingerprint($table, $where);
    return ['rows' => $rows, 'preserved' => $preserved];
};
$digest = static fn(array $state): string => hash('sha256', $json($state));
$guard = static function () use ($query): void {
    foreach (['phone_shop_goods', 'phone_shop_goods_sku', 'phone_shop_order', 'phone_shop_order_goods'] as $table) {
        if ((int)$query("SELECT COUNT(*) AS n FROM `ns_$table` WHERE site_id = 100000")[0]['n'] !== 0) {
            throw new RuntimeException('商城已出现商品/订单，本脚本不执行有库存迁移：' . $table);
        }
    }
    foreach (['erp_inbox_event' => ['processed', 'done'], 'erp_outbox_event' => ['done']] as $table => $terminal) {
        foreach ($query("SELECT DISTINCT status FROM `ns_$table` WHERE site_id = 100000") as $row) {
            if (!in_array($row['status'], $terminal, true)) throw new RuntimeException('有未结束的 ERP 事件，停止清理：' . $table);
        }
    }
    if ((int)$query('SELECT COUNT(*) AS n FROM ns_recycle_device WHERE site_id = 100000 AND downstream_erp_asset_id > 0')[0]['n'] !== 0) {
        throw new RuntimeException('发现回收仍持有 ERP 资产关联，需重新核对后处理');
    }
};
$lock = static function () use ($query, $rules): void {
    $query('SELECT site_id FROM ns_site WHERE site_id = 100000 FOR UPDATE');
    foreach ($rules as $rule) $query("SELECT * FROM `ns_{$rule['table']}` WHERE {$rule['where']} FOR UPDATE");
};
$saveFile = static function (string $file, array $data) use ($json): void {
    $bytes = $json($data);
    if (file_put_contents($file, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('写备份失败');
    chmod($file, 0600);
    if (file_get_contents($file) !== $bytes) throw new RuntimeException('备份回读校验失败');
};
$restoreRows = static function (array $state) use ($rules, $execute): void {
    foreach ($rules as $key => $rule) {
        $execute("DELETE FROM `ns_{$rule['table']}` WHERE {$rule['where']}");
        foreach ($state['rows'][$key] as $row) {
            $cols = implode(',', array_map(static fn(string $c): string => "`$c`", array_keys($row)));
            $marks = implode(',', array_fill(0, count($row), '?'));
            $execute("INSERT INTO `ns_{$rule['table']}` ($cols) VALUES ($marks)", array_values($row));
        }
    }
};
$apply = static function (array $before, string $backupName) use ($rules, $execute, $json): void {
    foreach ($rules as $rule) if ($rule['action'] === 'delete') $execute("DELETE FROM `ns_{$rule['table']}` WHERE {$rule['where']}");
    $execute('UPDATE ns_erp_capital_account SET balance = 0 WHERE site_id = 100000');
    $existing = array_column($before['rows']['sys_config'], null, 'config_key');
    $erp = json_decode($existing['HSX_ERP_RULES']['value'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    $erp = array_replace_recursive($erp, ['marketplace' => ['recycle_material_owner' => 'erp', 'channels' => ['phone_shop' => [
        'enabled' => 1, 'category_mode' => 'erp', 'spec_mode' => 'erp', 'publish_mode' => 'direct',
    ]]]]);
    $integration = isset($existing['recycle_erp_integration'])
        ? json_decode($existing['recycle_erp_integration']['value'], true, 512, JSON_THROW_ON_ERROR)
        : ['mode' => 'self_erp', 'initial_mode' => 'self_erp', 'history' => [], 'changed_at' => time()];
    if (($integration['mode'] ?? '') !== 'self_erp') throw new RuntimeException('付款责任模式发生改变，需重新确认');
    foreach (['HSX_ERP_RULES' => $erp, 'recycle_erp_integration' => $integration] as $key => $value) {
        if (isset($existing[$key])) {
            $execute('UPDATE ns_sys_config SET value = ?, update_time = ? WHERE site_id = 100000 AND config_key = ?', [$json($value), time(), $key]);
        } else {
            $execute('INSERT INTO ns_sys_config (site_id, config_key, value, create_time) VALUES (100000, ?, ?, ?)', [$key, $json($value), time()]);
        }
    }
    $execute('INSERT INTO ns_erp_operation_log (site_id,action,source_type,source_id,source_no,operator_name,remark,extra_json,create_at) VALUES (100000,?,?,?,?,?,?,?,?)', [
        'local_test_rebuild', 'maintenance', 100000, 'LOCAL100000', '本地维护',
        '仅本地 100000：备份后清理 ERP 测试业务和余额；商城为空未导入；保留回收历史及事件防重记录。',
        $json(['backup' => $backupName, 'deleted' => array_map('count', $before['rows']), 'imported_goods' => 0]), time(),
    ]);
};
$verifyAfter = static function (array $before, array $after) use ($rules): void {
    if ($before['preserved'] !== $after['preserved']) throw new RuntimeException('其他站点或保留数据发生变化，禁止提交');
    foreach ($rules as $key => $rule) {
        if ($rule['action'] === 'delete' && $after['rows'][$key] !== []) throw new RuntimeException('未清理完整：' . $key);
    }
    foreach ($after['rows']['erp_capital_account'] as $row) if ((float)$row['balance'] !== 0.0) throw new RuntimeException('余额未归零');
    $accountsBefore = $before['rows']['erp_capital_account'];
    foreach ($accountsBefore as &$row) $row['balance'] = '0.00';
    unset($row);
    if ($accountsBefore !== $after['rows']['erp_capital_account']) throw new RuntimeException('账户资料除余额外不应改变');
};

$options = getopt('', ['apply:', 'restore:']);
$baseDir = $root . '/niucloud/runtime/maintenance';
if ($options === []) {
    $guard(); $state = $capture();
    echo $json(['mode' => 'read_only', 'site_id' => $site, 'counts' => array_map('count', $state['rows']), 'protected_scopes' => count($state['preserved']), 'expected' => $digest($state)]) . PHP_EOL;
    exit;
}
if (count($options) !== 1) throw new RuntimeException('仅允许一个维护动作');
if ((int)$query("SELECT GET_LOCK('hsx_local_rebuild_100000', 0) AS acquired")[0]['acquired'] !== 1) throw new RuntimeException('已有维护进程');
try {
    if (isset($options['restore'])) {
        $dir = realpath($options['restore']);
        if (!$dir || !str_starts_with($dir, $baseDir . '/erp-rebuild-100000-')) throw new RuntimeException('备份路径不在本地私有维护目录');
        $backup = json_decode(file_get_contents($dir . '/backup.json'), true, 512, JSON_THROW_ON_ERROR);
        $result = json_decode(file_get_contents($dir . '/result.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($result['status'] ?? '') !== 'committed' || $backup['before_sha256'] !== $digest($backup['before'])) throw new RuntimeException('备份或执行结果校验失败');
        $pdo->beginTransaction(); $lock();
        if ($digest($capture()) !== $result['after_sha256']) throw new RuntimeException('重建后已有新业务/配置变化，禁止覆盖恢复');
        $restoreRows($backup['before']);
        if ($digest($capture()) !== $backup['before_sha256']) throw new RuntimeException('恢复校验失败');
        $pdo->commit();
        $saveFile($dir . '/restored.json', ['restored_at' => date(DATE_ATOM), 'status' => 'restored']);
        echo "RESTORED local site 100000\n";
    } else {
        $guard(); $before = $capture();
        if (!hash_equals($digest($before), (string)$options['apply'])) throw new RuntimeException('预览已变化，请重新预览，禁止盲目执行');
        if (array_filter($before['rows']['erp_operation_log'], static fn(array $r): bool => $r['action'] === 'local_test_rebuild')) throw new RuntimeException('已经重建过，禁止再次误清');
        $dir = $baseDir . '/erp-rebuild-100000-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        if (!mkdir($dir, 0700, true)) throw new RuntimeException('无法创建备份目录');
        $saveFile($dir . '/backup.json', ['site_id' => $site, 'database' => 'saas_', 'prefix' => 'ns_', 'created_at' => date(DATE_ATOM), 'schemas' => $schemas, 'before_sha256' => $digest($before), 'before' => $before]);
        // 第一次：清理后回滚，逐字段确认原数据还在。
        $pdo->beginTransaction(); $lock(); $guard();
        if ($digest($capture()) !== $digest($before)) throw new RuntimeException('备份后数据变化，停止');
        $apply($before, basename($dir)); $verifyAfter($before, $capture()); $pdo->rollBack();
        if ($digest($capture()) !== $digest($before)) throw new RuntimeException('回滚演练失败');
        // 第二次：清理后从备份恢复，检查备份可用，再整体回滚。
        $pdo->beginTransaction(); $lock(); $guard();
        $apply($before, basename($dir));
        $disk = json_decode(file_get_contents($dir . '/backup.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($digest($disk['before']) !== $disk['before_sha256']) throw new RuntimeException('磁盘备份指纹错误');
        $restoreRows($disk['before']);
        if ($digest($capture()) !== $digest($before)) throw new RuntimeException('备份恢复演练失败');
        $pdo->rollBack();
        // 最后：再次检查本地状态，验证后提交。
        $pdo->beginTransaction(); $lock(); $guard();
        if ($digest($capture()) !== $digest($before)) throw new RuntimeException('演练后数据变化，停止');
        $apply($before, basename($dir)); $after = $capture(); $verifyAfter($before, $after);
        $saveFile($dir . '/result.json', ['status' => 'ready_to_commit', 'before_sha256' => $digest($before), 'after_sha256' => $digest($after)]);
        $pdo->commit();
        $saveFile($dir . '/result.json', ['status' => 'committed', 'committed_at' => date(DATE_ATOM), 'rollback_rehearsal' => 'passed', 'restore_rehearsal' => 'passed', 'before_sha256' => $digest($before), 'after_sha256' => $digest($after), 'counts_after' => array_map('count', $after['rows']), 'protected_scopes' => count($after['preserved'])]);
        echo $json(['status' => 'committed', 'site_id' => $site, 'backup_dir' => $dir, 'counts_after' => array_map('count', $after['rows']), 'preserved_scopes_unchanged' => count($after['preserved']), 'mall_imported' => 0]) . PHP_EOL;
    }
    think\facade\Cache::tag(app\service\core\sys\CoreConfigService::$cache_tag_name . $site)->clear();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1);
} finally {
    $query("SELECT RELEASE_LOCK('hsx_local_rebuild_100000')");
}
