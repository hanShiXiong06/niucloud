<?php
declare(strict_types=1);

/** Fresh localhost schema only. No existing business schema/table/row is queried. */
function leadBoot(): array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI test execution only');
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    $app = new think\App($root);
    $app->loadEnv();
    require_once $app->getThinkPath() . 'helper.php';
    require_once $root . '/app/common.php';
    $database = require $root . '/config/database.php';
    $original = $database['connections'][$database['default']] ?? [];
    if (($original['type'] ?? '') !== 'mysql'
        || !in_array($original['hostname'] ?? '', ['localhost', '127.0.0.1'], true)
        || !empty($original['deploy'])
        || !preg_match('/^[0-9]{1,5}$/D', (string)($original['hostport'] ?? ''))) {
        throw new RuntimeException('Only configured localhost MySQL is permitted');
    }
    $schema = 'hsx_lead_test_' . bin2hex(random_bytes(8));
    $prefix = 'lt_';
    $pdo = null;
    $created = false;
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;port=' . $original['hostport'] . ';charset=utf8mb4',
            $original['username'], $original['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4');
        $created = true;
        $GLOBALS['hsx_lead_test_owned_schema'] = $schema;
        $pdo->exec('USE `' . $schema . '`');
        // Only official checked-in CREATE DDL; no source-database CREATE LIKE or seed rows.
        $official = file_get_contents($root . '/app/install/source/database.sql');
        foreach (['addon', 'site', 'site_group', 'sys_config', 'sys_area', 'sys_user',
            'sys_user_role', 'sys_role', 'sys_menu', 'sys_schedule', 'sys_schedule_log', 'member', 'member_level', 'member_account_log', 'member_cash_out', 'sys_notice', 'sys_notice_log'] as $table) {
            $pattern = '/CREATE TABLE\s+`' . preg_quote($table, '/') . '`\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;/si';
            if (!preg_match($pattern, $official, $matches)) throw new RuntimeException('Missing official fixture DDL: ' . $table);
            $ddl = preg_replace('/CREATE TABLE\s+`' . preg_quote($table, '/') . '`/i', 'CREATE TABLE `' . $prefix . $table . '`', $matches[0], 1);
            $pdo->exec($ddl);
        }
        $sql = str_replace('{{prefix}}', $prefix, file_get_contents(dirname(__DIR__) . '/sql/install.sql'));
        for ($pass = 0; $pass < 2; $pass++) {
            foreach (parse_sql($sql) as $statement) if (trim($statement) !== '') $pdo->exec($statement);
        }
        $database['default'] = 'mysql';
        $database['connections'] = ['mysql' => array_replace($original, [
            'hostname' => '127.0.0.1', 'database' => $schema, 'prefix' => $prefix,
            'trigger_sql' => false, 'fields_cache' => false, 'break_reconnect' => false,
        ])];
        $app->config->set($database, 'database');
        $app->config->set(['default' => 'file', 'stores' => ['file' => [
            'type' => 'File', 'path' => sys_get_temp_dir() . '/' . $schema . '/cache/',
        ]]], 'cache');
        $app->config->set(['default' => 'file', 'channels' => ['file' => [
            'type' => 'File', 'path' => sys_get_temp_dir() . '/' . $schema . '/log/',
        ]]], 'log');
        $app->env->set('app.auth_key', bin2hex(random_bytes(32)));
        $app->env->set('queue.state', false);
        $app->instance('request', new app\Request());
        $manager = new think\DbManager();
        $manager->setConfig($database);
        $app->instance('think\DbManager', $manager);
        leadAssertIsolated($schema);
        $site = 900000031;
        request()->siteId($site); request()->uid(1);
        request()->username('Isolated lead tester'); request()->appType('site');
        think\facade\Db::name('addon')->insert(['key' => 'hsx_lead', 'title' => 'Fixture', 'version' => '0.0.1', 'type' => 'app', 'status' => 1]);
        foreach ([$site, $site + 1] as $fixtureSite) {
            think\facade\Db::name('site')->insert(['site_id' => $fixtureSite, 'site_name' => 'Isolated fixture', 'app_type' => 'site', 'app' => '["hsx_lead"]', 'addons' => '[]', 'group_id' => 0]);
        }
        think\facade\Db::name('sys_user')->insert(['uid' => 1, 'username' => 'lead-fixture', 'real_name' => 'Test operator', 'status' => 1]);
        think\facade\Db::name('sys_user_role')->insert(['uid' => 1, 'site_id' => $site, 'status' => 1, 'is_admin' => 1]);
        foreach ([[$site, 101], [$site, 102], [$site + 1, 103]] as [$fixtureSite, $memberId]) {
            think\facade\Db::name('member')->insert(['member_id' => $memberId, 'site_id' => $fixtureSite, 'nickname' => 'Fixture ' . $memberId, 'mobile' => '19900000' . $memberId]);
        }
        think\facade\Db::name('sys_area')->insertAll([
            ['id' => 910000, 'pid' => 0, 'name' => 'Fixture Province', 'level' => 1, 'status' => 1],
            ['id' => 910100, 'pid' => 910000, 'name' => 'Fixture City', 'level' => 2, 'status' => 1],
            ['id' => 910101, 'pid' => 910100, 'name' => 'Fixture District', 'level' => 3, 'status' => 1],
            ['id' => 910102, 'pid' => 910100, 'name' => 'Other District', 'level' => 3, 'status' => 1],
        ]);
        return [$app, $pdo, $schema, $site];
    } catch (Throwable $exception) {
        if ($created && $pdo instanceof PDO) leadCleanup($pdo, $schema);
        throw $exception;
    }
}
function leadAssertIsolated(string $schema): void
{
    if (!preg_match('/^hsx_lead_test_[a-f0-9]{16}$/D', $schema)
        || ($GLOBALS['hsx_lead_test_owned_schema'] ?? '') !== $schema
        || (think\facade\Db::query('SELECT DATABASE() AS db')[0]['db'] ?? '') !== $schema) {
        throw new RuntimeException('Database isolation check failed');
    }
}
/** Only a schema generated and owned by this process may be removed. */
function leadCleanup(PDO $pdo, string $schema): void
{
    if (!preg_match('/^hsx_lead_test_[a-f0-9]{16}$/D', $schema)
        || ($GLOBALS['hsx_lead_test_owned_schema'] ?? '') !== $schema) {
        throw new RuntimeException('Refusing cleanup of an unowned schema');
    }
    $pdo->exec('DROP DATABASE `' . $schema . '`');
    unset($GLOBALS['hsx_lead_test_owned_schema']);
}
