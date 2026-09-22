<?php
declare(strict_types=1);

// Real ORM/MySQL regression against a disposable database on a dedicated local socket.
namespace core\base {
    class BaseAdminService
    {
        public int $site_id = 100000;
        public int $uid = 10;
        public function __construct() {}
    }
}
namespace core\exception {
    class CommonException extends \RuntimeException {}
}
namespace app\service\core\sys {
    class CoreConfigService
    {
        public function getConfigValue(int $siteId, string $key): array { return []; }
        public function setConfig(int $siteId, string $key, array $value): void
        {
            throw new \RuntimeException('Alias binding must not write sys_config');
        }
    }
}
namespace addon\hsx_recycle\app\service\core\device {
    class CoreRecycleDeviceModelDictService {}
}
namespace {
    use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
    use core\exception\CommonException;
    use think\DbManager;

    $socket = getenv('HSX_ALIAS_TEST_MYSQL_SOCKET') ?: '';
    if ($socket === '') {
        echo "SKIP: set HSX_ALIAS_TEST_MYSQL_SOCKET to a disposable hsx-recycle-alias-mysql.* instance\n";
        exit(0);
    }
    $socketDir = realpath(dirname($socket)) ?: '';
    if (!str_starts_with($socketDir, '/private/tmp/hsx-recycle-alias-mysql.')) {
        throw new \RuntimeException('Only a dedicated temporary local MySQL socket is allowed');
    }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    $check = static function (bool $ok, string $message): void {
        if (!$ok) throw new \RuntimeException($message);
    };
    $configure = static function (string $database) use ($socket): void {
        $db = new DbManager();
        $db->setConfig(['default' => 'mysql', 'connections' => ['mysql' => [
            'type' => 'mysql', 'socket' => $socket, 'database' => $database,
            'username' => 'root', 'password' => '', 'prefix' => 'test_', 'charset' => 'utf8mb4',
            'fields_strict' => true, 'trigger_sql' => false,
        ]]]);
    };

    if (($argv[1] ?? '') === '--worker') {
        $database = $argv[2] ?? '';
        if (!preg_match('/^hsx_alias_test_[a-f0-9]{12}$/D', $database)) throw new \RuntimeException('Invalid test database');
        $configure($database);
        $worker = (int)($argv[3] ?? 0);
        $service = new RecycleDeviceModelDictService();
        $service->uid = 100 + $worker;
        for ($i = 0; $i < 5; $i++) {
            $service->bindAliases(['concurrent', 'worker-' . $worker], $worker % 2 === 0 ? 3 : 4);
        }
        exit(0);
    }

    $pdo = new \PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $database = 'hsx_alias_test_' . bin2hex(random_bytes(6));
    $pdo->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
    $workers = [];
    try {
        $pdo->exec('USE `' . $database . '`');
        $plugin = dirname(__DIR__);
        $installSql = file_get_contents($plugin . '/sql/install.sql');
        $upgradeSql = file_get_contents($plugin . '/sql/update_0.0.4.sql');
        $createTable = static function (string $sql, string $table): string {
            if (!preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `\{\{prefix\}\}' . preg_quote($table, '/') . '`\s*\(.*?;/s', $sql, $matches)) {
                throw new \RuntimeException('Missing schema for ' . $table);
            }
            return str_replace('{{prefix}}', 'test_', $matches[0]);
        };
        $aliasDdl = $createTable($upgradeSql, 'recycle_device_model_alias');
        $check($aliasDdl === $createTable($installSql, 'recycle_device_model_alias'), 'Install and upgrade schema must match');
        $pdo->exec($createTable($installSql, 'recycle_device_model_dict'));
        $pdo->exec($aliasDdl);
        $pdo->exec("INSERT INTO test_recycle_device_model_dict (id,site_id,pid,node_name,model_full_name) VALUES
            (1,100000,0,'Phone','Phone'), (2,100000,1,'Meizu','Phone/Meizu'),
            (3,100000,2,'16th','Phone/Meizu/16th'), (4,100000,2,'16th Plus','Phone/Meizu/16th Plus'),
            (5,100001,0,'Other tenant','Other tenant')");
        $catalogBefore = $pdo->query('SELECT * FROM test_recycle_device_model_dict ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $configure($database);
        $service = new RecycleDeviceModelDictService();
        $service->bindAliases(['16th', '16 TH'], 3);
        $row = $pdo->query('SELECT * FROM test_recycle_device_model_alias')->fetch(\PDO::FETCH_ASSOC);
        $check((int)$row['category_id'] === 3 && $row['normalized_alias'] === '16th', 'Real table receives one normalized alias');
        $service->uid = 20;
        $service->bindAliases(['16-TH'], 4);
        $corrected = $pdo->query('SELECT * FROM test_recycle_device_model_alias')->fetch(\PDO::FETCH_ASSOC);
        $check($corrected['id'] === $row['id'] && $corrected['create_at'] === $row['create_at'], 'Upsert preserves row ID and creation time');
        $check((int)$corrected['operator_uid'] === 20 && (int)$corrected['category_id'] === 4, 'Upsert records the corrected model/operator');
        $check($service->resolveAliases(['16th'])['node']['category_path'] === [1, 2, 4], 'Real model lookup returns the current leaf path');
        $pdo->exec($aliasDdl);
        $check((int)$pdo->query('SELECT COUNT(*) FROM test_recycle_device_model_alias')->fetchColumn() === 1, 'Repeated upgrade does not erase bindings');

        $service->site_id = 100001;
        $check($service->resolveAliases(['16th']) === ['matched' => false], 'Other tenant cannot read the binding');
        $service->bindAliases(['16th'], 5);
        $check($service->resolveAliases(['16th'])['node']['id'] === 5, 'Composite unique key permits tenant-owned correction');
        $service->site_id = 100000;
        $service->bindAliases(['é'], 3);
        $service->bindAliases(['e'], 4);
        $check($service->resolveAliases(['é'])['node']['id'] === 3, 'Binary alias key does not fold accents');
        $check($service->resolveAliases(['e'])['node']['id'] === 4, 'Accent-distinct binding remains separate');
        $longAlias = str_repeat('型', 120);
        $service->bindAliases([$longAlias], 3);
        $check($service->resolveAliases([$longAlias])['matched'], 'Unicode alias fits both storage fields');

        $pdo->exec('CREATE TABLE old_config (`value` TEXT) ENGINE=InnoDB');
        $largeMappings = [];
        for ($i = 0; $i < 1100; $i++) {
            $largeMappings['bulkmodel' . $i] = ['alias' => 'bulk-model-' . $i, 'category_id' => 3,
                'category_path' => [1, 2, 3], 'node_name' => 'Meizu 16th', 'model_full_name' => 'Phone/Meizu/16th', 'update_time' => time()];
        }
        $oldPayload = json_encode(['mappings' => $largeMappings], JSON_THROW_ON_ERROR);
        $check(strlen($oldPayload) > 65535, 'Fixture must exceed TEXT capacity');
        try {
            $pdo->prepare('INSERT INTO old_config (`value`) VALUES (?)')->execute([$oldPayload]);
            throw new \RuntimeException('TEXT overflow was not reproduced');
        } catch (\PDOException $error) {
            $check((int)$error->errorInfo[1] === 1406, 'Expected the original 1406 capacity error');
        }
        for ($i = 0; $i < 1100; $i += 10) {
            $service->bindAliases(array_map(static fn(int $n): string => 'bulk-model-' . $n, range($i, $i + 9)), 3);
        }
        $check($service->resolveAliases(['bulk-model-0'])['matched'] && $service->resolveAliases(['bulk-model-1099'])['matched'], 'Large binding collections preserve old and new aliases');
        $check((int)$pdo->query("SELECT COUNT(*) FROM test_recycle_device_model_alias WHERE normalized_alias LIKE 'bulkmodel%'")->fetchColumn() === 1100, 'All 1100 bindings are stored as separate rows');

        $pdo->exec("ALTER TABLE test_recycle_device_model_alias ADD CONSTRAINT test_batch_failure CHECK (`alias` <> 'force-fail')");
        try {
            $service->bindAliases(['16th', 'batch-ok', 'force-fail'], 3);
            throw new \RuntimeException('Injected write failure was not propagated');
        } catch (\think\db\exception\PDOException $error) {
            $check((int)($error->getData()['PDO Error Info']['Driver Error Code'] ?? 0) === 3819, 'Expected test CHECK violation');
        }
        $check($service->resolveAliases(['16th'])['node']['id'] === 4, 'Failed batch rolls back an earlier update');
        $check($service->resolveAliases(['batch-ok']) === ['matched' => false], 'Failed batch rolls back an earlier insert');

        for ($i = 0; $i < 8; $i++) {
            $process = proc_open([PHP_BINARY, '-d', 'error_reporting=24575', __FILE__, '--worker', $database, (string)$i],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new \RuntimeException('Could not start concurrent test worker');
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $check(proc_close($process) === 0, 'Concurrent worker failed: ' . $output);
        }
        $workers = [];
        $check((int)$pdo->query("SELECT COUNT(*) FROM test_recycle_device_model_alias WHERE normalized_alias='concurrent'")->fetchColumn() === 1, 'Concurrent binds create exactly one shared row');
        for ($i = 0; $i < 8; $i++) {
            $check($service->resolveAliases(['worker-' . $i])['node']['id'] === ($i % 2 === 0 ? 3 : 4), 'Concurrent writes must not lose unrelated aliases');
        }
        $check($pdo->query('SELECT * FROM test_recycle_device_model_dict ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC) === $catalogBefore, 'Binding never changes the catalog');

        $pdo->exec('DROP TABLE test_recycle_device_model_alias');
        foreach (['bind', 'resolve'] as $operation) {
            try {
                $operation === 'bind' ? $service->bindAliases(['missing'], 3) : $service->resolveAliases(['16th']);
                throw new \RuntimeException('Missing schema was not reported');
            } catch (CommonException $error) {
                $check(str_contains($error->getMessage(), 'update_0.0.4.sql'), 'Real missing-table error must name the upgrade file');
            }
        }
        echo "PASS MySQL/ORM: 1406 reproduced; schema idempotence, upsert, Unicode/tenant isolation, 1100 bindings, atomic rollback, 8 concurrent workers, missing-table guidance\n";
    } finally {
        foreach ($workers as [$process, $pipes]) {
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
        }
        $pdo->exec('DROP DATABASE `' . $database . '`');
    }
}
