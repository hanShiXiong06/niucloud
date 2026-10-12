<?php
declare(strict_types=1);

// 使用只读导出的目录元数据验证整份恢复 SQL。只接受隔离临时 MySQL，绝不加载应用 .env。
$socket = getenv('HSX_CATALOG_TEST_SOCKET') ?: '';
$directory = realpath(dirname($socket)) ?: '';
if (!str_starts_with($directory, '/private/tmp/hsx-catalog-mysql.') || !is_file($directory . '/init2.log')) {
    throw new RuntimeException('Only the dedicated temporary catalog test MySQL socket is allowed');
}
$auditPath = $argv[1] ?? '';
if (!is_file($auditPath)) throw new RuntimeException('Pass the read-only phpMyAdmin JSON audit file');
$export = json_decode(file_get_contents($auditPath), true, 512, JSON_THROW_ON_ERROR);
$rows = [];
foreach ($export as $part) if (($part['type'] ?? '') === 'table') $rows = array_merge($rows, $part['data']);
$models = array_values(array_filter($rows, static fn($r) => $r['kind'] === 'model'));
$templates = array_values(array_filter($rows, static fn($r) => $r['kind'] === 'template'));
if (count($models) !== 42483 || count($templates) !== 15539) throw new RuntimeException('Unexpected audit snapshot');

$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database = 'hsx_catalog_repair_test_' . bin2hex(random_bytes(6));
$pdo->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
$pdo->exec('USE `' . $database . '`');
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++; echo 'PASS: ' . $message . PHP_EOL;
};
$count = static fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
try {
    $pdo->exec("CREATE TABLE saas_recycle_device_model_dict (id INT PRIMARY KEY, site_id INT NOT NULL, node_type VARCHAR(30), node_name VARCHAR(100), source VARCHAR(50), product_source_id VARCHAR(100), status INT DEFAULT 1) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE saas_recycle_check_template (id INT PRIMARY KEY, site_id INT, template_key VARCHAR(100), status INT, schema_json LONGTEXT) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE saas_recycle_check_field (id INT PRIMARY KEY AUTO_INCREMENT, site_id INT, template_id INT, INDEX idx_tpl(template_id)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE saas_recycle_template_binding (id INT PRIMARY KEY AUTO_INCREMENT,site_id INT,target_type VARCHAR(30),target_id INT,scene_key VARCHAR(80),check_template_id INT,print_template_id INT,inherit_enabled INT,status INT,sort INT,remark VARCHAR(500),create_at INT,update_at INT, UNIQUE KEY uk_site_target_scene(site_id,target_type,target_id,scene_key)) ENGINE=InnoDB");
    $pdo->beginTransaction();
    $insertModel = $pdo->prepare("INSERT INTO saas_recycle_device_model_dict VALUES (?,0,'model',?,?,?,1)");
    $insertBinding = $pdo->prepare("INSERT INTO saas_recycle_template_binding (site_id,target_type,target_id,scene_key,check_template_id,print_template_id,inherit_enabled,status,sort,remark,create_at,update_at) VALUES (0,'model_dict',?,'manual_device_label',?,0,1,1,0,'original',1,1)");
    foreach ($models as $m) {
        $insertModel->execute([$m['id'],$m['title'],$m['source_key'],$m['product_id']]);
        if ((int)$m['template_id'] > 0) $insertBinding->execute([$m['id'],$m['template_id']]);
    }
    $insertTemplate = $pdo->prepare('INSERT INTO saas_recycle_check_template VALUES (?,0,?,?,NULL)');
    $insertField = $pdo->prepare('INSERT INTO saas_recycle_check_field (site_id,template_id) VALUES (0,?)');
    foreach ($templates as $t) {
        $insertTemplate->execute([$t['id'],$t['source_key'],$t['template_id']]);
        // 本测试只验证 SQL 匹配/保护/幂等；模板内容用占位字段，不把它声称为真实页面验收。
        $insertField->execute([$t['id']]);
    }
    $pdo->commit();
    $sql = file_get_contents(dirname(__DIR__) . '/sql/repair_public_check_bindings_verified_20261004_saas.sql');
    $check(substr_count($sql,'USE niushops;')===1, 'production database selector is explicit and replaceable only in isolated test');
    $sql = str_replace('USE niushops;', '', $sql);
    $statements = array_values(array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))));
    $run = static function () use ($pdo, $statements): void {
        foreach ($statements as $statement) {
            if (preg_match('/^SELECT\b/i',$statement)) { $query=$pdo->query($statement); $query->fetchAll(); $query->closeCursor(); }
            else $pdo->exec($statement);
        }
    };
    $check($count('SELECT COUNT(*) FROM saas_recycle_template_binding')===24484, 'original public binding count matches read-only audit');
    $run();
    $check($count("SELECT COUNT(*) FROM saas_recycle_template_binding WHERE remark='repair_public_verified_20261004'")===16804, 'full SQL inserts exactly 16804 verified associations');
    $check($count('SELECT COUNT(*) FROM saas_recycle_template_binding')===41288, 'full public result count is 41288, not falsely all models');
    $check($count("SELECT COUNT(*) FROM saas_recycle_template_binding WHERE remark='original' AND create_at=1 AND update_at=1")===24484, 'all original records unchanged');
    $run();
    $check($count('SELECT COUNT(*) FROM saas_recycle_template_binding')===41288, 'second execution is idempotent');
    $row = $pdo->query("SELECT * FROM saas_recycle_template_binding WHERE remark='repair_public_verified_20261004' ORDER BY target_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $id=(int)$row['target_id']; $tpl=(int)$row['check_template_id'];
    $pdo->exec("DELETE FROM saas_recycle_template_binding WHERE site_id=0 AND target_id=$id");
    $pdo->exec("INSERT INTO saas_recycle_template_binding (site_id,target_type,target_id,scene_key,check_template_id,print_template_id,inherit_enabled,status,sort,remark,create_at,update_at) VALUES (100005,'model_dict',$id,'manual_device_label',0,8,1,1,0,'site print',1,1)");
    $pdo->exec("UPDATE saas_recycle_device_model_dict SET node_name=CONCAT(node_name,' changed') WHERE id=$id");
    $run();
    $check($count("SELECT COUNT(*) FROM saas_recycle_template_binding WHERE site_id=0 AND target_id=$id")===0, 'changed model identity is skipped');
    $pdo->exec("UPDATE saas_recycle_device_model_dict SET node_name=REPLACE(node_name,' changed','') WHERE id=$id");
    $pdo->exec("UPDATE saas_recycle_check_template SET template_key=CONCAT(template_key,'changed') WHERE id=$tpl");
    $run();
    $check($count("SELECT COUNT(*) FROM saas_recycle_template_binding WHERE site_id=0 AND target_id=$id")===0, 'changed template fingerprint is skipped');
    $pdo->exec("UPDATE saas_recycle_check_template SET template_key=REPLACE(template_key,'changed','') WHERE id=$tpl");
    $pdo->exec("DELETE FROM saas_recycle_check_field WHERE template_id=$tpl");
    $pdo->exec("UPDATE saas_recycle_check_template SET schema_json='{broken' WHERE id=$tpl");
    $run();
    $check($count("SELECT COUNT(*) FROM saas_recycle_template_binding WHERE site_id=0 AND target_id=$id")===0, 'empty or malformed template content is skipped without JSON errors');
    $pdo->exec("UPDATE saas_recycle_check_template SET schema_json='{}' WHERE id=$tpl");
    $run();
    $check($count("SELECT COUNT(*) FROM saas_recycle_template_binding WHERE site_id=0 AND target_id=$id")===0, 'empty compact schema is not treated as usable');
    $pdo->exec("UPDATE saas_recycle_check_template SET schema_json='{\"groups\":[{\"fields\":[{\"field_key\":\"capacity\"}]}]}' WHERE id=$tpl");
    $run();
    $check($count("SELECT COUNT(*) FROM saas_recycle_template_binding WHERE site_id=0 AND target_id=$id")===1, 'nonempty compact schema may be restored without normalized fields');
    $check($count("SELECT print_template_id FROM saas_recycle_template_binding WHERE site_id=100005 AND target_id=$id")===8, 'site-specific label binding is preserved');
    echo "PASS $checks SQL checks; isolated MySQL only\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('DROP DATABASE `' . $database . '`');
}
