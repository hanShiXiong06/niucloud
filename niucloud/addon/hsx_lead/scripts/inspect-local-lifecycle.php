<?php
declare(strict_types=1);

/** Read-only preflight. Does not boot HTTP/events, create fixtures, issue tokens or invoke payments. */
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || $argv[1] !== '--read-only-site-100005') exit("Usage: php inspect-local-lifecycle.php --read-only-site-100005\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $fn) if (function_exists($fn)) exit("Disable external HTTP/socket functions before inspection\n");
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
error_reporting(E_ALL & ~E_DEPRECATED);
$pdo = null;
try {
    $root = dirname(__DIR__, 3);
    require $root.'/vendor/autoload.php';
    $app = new think\App($root);
    $app->loadEnv();
    require $app->getThinkPath().'helper.php';
    $database = require $root.'/config/database.php';
    $config = $database['connections'][$database['default']] ?? [];
    if (($config['type'] ?? '') !== 'mysql' || !in_array($config['hostname'] ?? '', ['localhost', '127.0.0.1'], true) || !empty($config['deploy']) || !preg_match('/^[0-9]{1,5}$/D', (string) ($config['hostport'] ?? '')))throw new RuntimeException('LOCALHOST_ONLY');
    $prefix = $config['prefix'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_]*$/D', $prefix))throw new RuntimeException('INVALID_PREFIX');
    $pdo = new PDO('mysql:host=127.0.0.1;port='.$config['hostport'].';dbname='.$config['database'].';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $select = static function (string $sql, array $params = []) use ($pdo): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };
    $site = 100005;
    $out = ['mode' => 'read_only', 'site_id' => $site, 'database_writes' => 0, 'external_calls' => 0];
    $out['site'] = $select('SELECT site_id,site_name,status,group_id,app,addons,expire_time FROM `'.$prefix.'site` WHERE site_id=?', [$site]);
    if (count($out['site']) !== 1)throw new RuntimeException('SITE_NOT_FOUND');
    $rows = $select('SELECT config_key,value FROM `'.$prefix.'sys_config` WHERE site_id=? AND config_key IN (?,?,?,?,?)', [$site, 'HSX_LEAD_COOPERATION', 'HSX_LEAD_PROMOTION', 'HSX_LEAD_PROVIDER_CHANNELS', 'LOGIN_CONFIG', 'H5_CONFIG']);
    $values = [];
    foreach ($rows as $row)$values[$row['config_key']] = json_decode($row['value'], true) ?: [];
    $cooperation = $values['HSX_LEAD_COOPERATION'] ?? [];
    $out['cooperation'] = array_intersect_key($cooperation, array_flip(['enabled', 'fee_confirmed', 'publishing_required', 'commission_minimum', 'fee_percent', 'fee_minimum']));
    $out['businesses'] = array_map(static fn (array $row): array => array_intersect_key($row, array_flip(['key', 'name', 'enabled', 'dimension', 'commission_minimum', 'selection_mode', 'selection_limit'])) + ['fields_count' => count($row['fields'] ?? []), 'options_count' => count($row['options'] ?? [])], $cooperation['businesses'] ?? []);
    $out['promotion_enabled'] = (bool) ($values['HSX_LEAD_PROMOTION']['enabled'] ?? false);
    $out['pay_channels'] = $select('SELECT channel,type,status FROM `'.$prefix.'pay_channel` WHERE site_id=? ORDER BY channel,type', [$site]);
    $out['notice_channels'] = $select('SELECT `key`,is_weapp,is_wechat,is_sms FROM `'.$prefix.'sys_notice` WHERE site_id=? AND `key` LIKE ?', [$site, 'hsx_lead%']);
    foreach (['member', 'hsx_lead_merchant', 'hsx_lead_collaboration', 'hsx_lead_coop_order', 'hsx_lead_connection'] as $table)$out['counts'][$table] = (int) $select('SELECT COUNT(*) AS n FROM `'.$prefix.$table.'` WHERE site_id=?', [$site])[0]['n'];
    $out['merchants'] = $select('SELECT m.id,m.member_id,m.name,m.status,m.province_id,m.city_id,m.district_id,p.blocked_at,p.membership_level,p.membership_until FROM `'.$prefix.'hsx_lead_merchant` m LEFT JOIN `'.$prefix.'hsx_lead_partner` p ON p.site_id=m.site_id AND p.merchant_id=m.id WHERE m.site_id=? ORDER BY m.id DESC LIMIT 30', [$site]);
    $out['existing_synthetic_members'] = $select('SELECT member_id,username,nickname,status,balance,commission,pid,(mobile<>\'\') AS has_mobile,(wx_openid<>\'\') AS has_wechat_identity,(weapp_openid<>\'\') AS has_weapp_identity FROM `'.$prefix.'member` WHERE site_id=? AND username LIKE ? ORDER BY member_id DESC LIMIT 20', [$site, 'codex_lead_%']);
    $out['erp_connections'] = $select('SELECT COUNT(*) AS total,COALESCE(SUM(enabled=1),0) AS enabled FROM `'.$prefix.'hsx_lead_connection` WHERE site_id=?', [$site]);
    $out['schedules'] = $select('SELECT addon,`key`,status FROM `'.$prefix.'sys_schedule` WHERE addon=?', ['hsx_lead']);
    $out['site_config_keys'] = array_column($select('SELECT config_key FROM `'.$prefix.'sys_config` WHERE site_id=? ORDER BY config_key', [$site]), 'config_key');
    $pdo->rollBack();
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),PHP_EOL;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR, 'Read-only preflight failed ('.get_class($error).'): '.($error instanceof PDOException ? 'LOCAL_DATABASE_READ_FAILED' : $error->getMessage()).PHP_EOL);
    exit(1);
}
