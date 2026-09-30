<?php
declare(strict_types=1);

/**
 * 真实 MySQL 仓储及连接锁回归。只允许本机库，只创建同连接 TEMPORARY TABLE。
 * 不调用 App::initialize（不启动业务服务），不运行安装器，不改真实表，不外呼服务商。
 */
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
require_once $root . '/vendor/topthink/framework/src/helper.php';

use addon\hsx_express\app\service\core\ConfigService;
use addon\hsx_express\app\service\core\Kuaidi100Client;
use addon\hsx_express\app\service\core\LogisticsService;
use addon\hsx_express\app\service\core\TaskRepository;
use addon\hsx_express\app\support\Cipher;
use addon\hsx_express\app\support\OperationLock;
use think\facade\Db;

$app = new \think\App($root . '/');
$app->env->load($root . '/.env');
$database = require $root . '/config/database.php';
$source = $database['connections']['mysql'];
if (!in_array((string)$source['hostname'], ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "BLOCKED: this verifier only accepts loopback MySQL; no connection attempted.\n"); exit(2);
}
if (!preg_match('/^[a-zA-Z0-9_]*$/', (string)$source['prefix']) || !preg_match('/^[a-zA-Z0-9_\-]+$/', (string)$source['database'])) {
    fwrite(STDERR, "BLOCKED: unexpected database/prefix format; no connection attempted.\n"); exit(2);
}
// 强制单连接、禁用重连。临时表只在这一条PDO上存在，连接丢失必须中止，不能写到真实同名表。
$source = array_replace($source, ['deploy' => 0, 'rw_separate' => false, 'break_reconnect' => false, 'fields_cache' => false,
    'trigger_sql' => false, 'params' => [PDO::ATTR_PERSISTENT => false], 'dsn' => '']);
$manager = new \think\DbManager();
$manager->setConfig(['default' => 'mysql', 'connections' => ['mysql' => $source]]);
$app->instance('think\\DbManager', $manager);
$app->env->set('app.auth_key', 'ephemeral-test-key-' . bin2hex(random_bytes(16)));
$prefix = $source['prefix'];
$table = $prefix . 'hsx_express_task';
$pdo = null; $temporaryCreated = false; $checks = 0; $testFailed = false;
$ok = static function ($value, string $message) use (&$checks): void { if (!$value) throw new RuntimeException('FAIL: ' . $message); ++$checks; };
$throws = static function (callable $fn, string $message) use ($ok): void { try { $fn(); } catch (Throwable $e) { $ok(true, $message); return; } $ok(false, $message); };

try {
    $connection = Db::connect();
    $connection->query('SELECT 1', [], true);
    $pdo = $connection->getPdo();
    if (!$pdo instanceof PDO) throw new RuntimeException('Cannot acquire the test connection');
    $sql = trim((string)file_get_contents(dirname(__DIR__) . '/sql/install.sql'));
    if (substr_count($sql, ';') !== 1 || !str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_express_task`')) throw new RuntimeException('Unexpected install SQL; verifier refuses to run');
    $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', str_replace('{{prefix}}', $prefix, $sql));
    // 无 IF NOT EXISTS：若此连接意外已有同名临时表，必须失败，不能复用未知测试数据。
    $pdo->exec($sql);
    $temporaryCreated = true;
    $definition = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
    $ok(strpos((string)$definition[1], 'CREATE TEMPORARY TABLE') === 0, 'all writes are shadowed by a session temporary table');
    $site = 987650001;
    $cfg = array_replace(ConfigService::defaults(), ['enabled' => 1, 'key' => 'database-test-key', 'secret' => 'database-test-secret', 'carrier' => 'jd', 'exp_type' => '京东标快',
        'partner_id' => 'not-a-real-carrier-account', 'template_id' => 'not-a-real-template', 'use_ack' => 1, 'callback_base_url' => 'https://example.com']);
    $calls = 0; $mode = 'success';
    $client = new Kuaidi100Client(static function ($url, $form) use (&$calls, &$mode, $ok, $pdo, $connection) {
        ++$calls;
        $ok($connection->getPdo() === $pdo, 'task repository and operation lock retained the temporary-table connection');
        if ($mode === 'timeout') throw new RuntimeException('fake transport timeout');
        if ($mode === 'reject') return ['success' => false, 'code' => 30001, 'message' => 'fixture parameter rejected'];
        if ($form['method'] === 'order') return ['success' => true, 'code' => $mode === 'cached' ? 30011 : 200, 'data' => ['taskId' => 'mock-' . $calls, 'kuaidinum' => 'MOCK' . $calls, 'label' => 'https://api.kuaidi100.com/label/mock', 'kdComOrderNum' => 'mock-carrier-' . $calls]];
        return ['success' => true, 'code' => 200, 'data' => 'https://api.kuaidi100.com/label/reprint'];
    });
    $repository = new TaskRepository();
    $service = new LogisticsService($client, $repository, static fn() => $cfg, null, static fn() => [true]);
    $payload = ['business_type' => 'phone_shop', 'business_id' => '999:test-package', 'order_no' => 'SALE-DBTEST-999', 'order_id' => 999, 'order_goods_ids' => [1, 2],
        'sender' => ['name' => '测试寄件人', 'mobile' => '13800000000', 'address' => '河北省测试市测试区假地址1号'],
        'receiver' => ['name' => '测试收件人', 'mobile' => '13900000000', 'address' => '河北省测试市测试区假地址2号'], 'cargo' => '测试手机', 'weight' => 0.5, 'count' => 1];
    $task = $service->execute($site, 'create', $payload);
    $ok($task['state'] === 'ready' && (int)$task['task_id'] > 0 && $calls === 1, 'real repository persists successful create');
    $row = $repository->find($site, (int)$task['task_id']);
    $ok(json_decode(Cipher::decrypt($row['snapshot_cipher']), true)['config']['key'] === 'database-test-key', 'encrypted original-account snapshot round trips through MySQL');
    $ok(!str_contains($row['snapshot_cipher'], 'database-test-key'), 'no plaintext credentials in task row');
    $logs = json_decode($row['logs_json'], true);
    $ok(count($logs) === 1 && $logs[0]['operation'] === 'create', 'real repository writes operation history');
    $service->execute($site, 'create', $payload);
    $ok($calls === 1, 'idempotent create does not call fake carrier twice');
    $ok($service->execute($site + 1, 'query', ['task_id' => $task['task_id']]) === [], 'real query enforces tenant ownership');
    $throws(static fn() => $service->execute($site, 'create', array_replace($payload, ['business_id' => '999:overlap', 'order_goods_ids' => [2, 3]])), 'active overlapping parcel cannot allocate another waybill');
    $page = $repository->page($site, ['page' => 1, 'limit' => 15, 'keyword' => 'MOCK']);
    $ok($page['total'] === 1 && count($page['data']) === 1, 'real filtered task pagination');
    $numberPage = $repository->page($site, ['keyword' => 'SALE-DBTEST-999']);
    $ok($numberPage['total'] === 1, 'human business number searchable without a new column');
    $reprint = $service->execute($site, 'reprint', ['task_id' => $task['task_id']]);
    $ok((int)$reprint['reprint_count'] === 1 && $reprint['state'] === 'ready', 'real reprint counter and state persisted');
    $cancel = $service->execute($site, 'cancel', ['task_id' => $task['task_id'], 'reason' => '回归测试取消，不会真实下单']);
    $ok($cancel['state'] === 'cancelled', 'confirmed cancellation persisted');
    $second = $service->execute($site, 'create', $payload);
    $ok((int)$second['attempt'] === 2 && $second['task_id'] !== $task['task_id'], 'unique attempt allows new order only after cancellation');
    $ok($repository->find($site, (int)$task['task_id'])['state'] === 'cancelled', 'previous task retained, not overwritten');
    $ok($repository->findBusiness($site, 'phone_shop', $payload['business_id'])['id'] == $second['task_id'], 'business lookup returns latest attempt');
    $throws(static fn() => $repository->create(array_diff_key($row, ['id' => true])), 'unique index prevents duplicate same business attempt');
    $mode = 'timeout';
    $unknownPayload = array_replace($payload, ['business_id' => '1000:unknown', 'order_id' => 1000]);
    $unknown = $service->execute($site, 'create', $unknownPayload);
    $ok($unknown['state'] === 'unknown', 'timeout persisted as unknown');
    $before = $calls; $service->execute($site, 'create', $unknownPayload);
    $ok($calls === $before, 'unknown remains non-retryable after SQL reload');
    $mode = 'cached';
    $recovered = $service->execute($site, 'recover', ['task_id' => $unknown['task_id'], 'confirm' => 1]);
    $ok($recovered['task_id'] === $unknown['task_id'] && $recovered['state'] === 'ready', '30011 recover persists result on same original task');
    $recoverLogs = json_decode($repository->find($site, (int)$unknown['task_id'])['logs_json'], true);
    $ok(count($recoverLogs) === 3 && $recoverLogs[1]['operation'] === 'recover_requested' && $recoverLogs[2]['operation'] === 'recover', 'possible-charge recovery has explicit audit trail');
    $mode = 'reject';
    $rejectPayload = array_replace($payload, ['business_id' => '1001:reject', 'order_id' => 1001]);
    $failed = $service->execute($site, 'create', $rejectPayload);
    $ok($failed['state'] === 'failed', 'known provider rejection persisted');
    $mode = 'success';
    $fixed = $service->execute($site, 'create', $rejectPayload);
    $ok((int)$fixed['attempt'] === 2, 'known rejected attempt can be retried without overwriting failed history');
    // 同一数据库的第二个连接竞争命名锁；不创建表、不查询业务数据。
    $dsn = 'mysql:host=' . $source['hostname'] . ';port=' . $source['hostport'] . ';dbname=' . $source['database'] . ';charset=utf8mb4';
    $other = new PDO($dsn, $source['username'], $source['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => false]);
    $key = 'test-only-contention'; $name = 'hsx_waybill_' . sha1($site . '|' . $key);
    $lock = $other->prepare('SELECT GET_LOCK(?, 0)'); $lock->execute([$name]);
    $ok((int)$lock->fetchColumn() === 1, 'independent connection owns the test lock');
    try { $throws(static fn() => OperationLock::run($site, $key, static fn() => null), 'second connection cannot enter a busy operation'); }
    finally { $unlock = $other->prepare('SELECT RELEASE_LOCK(?)'); $unlock->execute([$name]); }
    $entered = OperationLock::run($site, $key, static fn() => 'entered');
    $ok($entered === 'entered', 'operation proceeds after the owner releases');
    $probe = $other->prepare('SELECT IS_FREE_LOCK(?)'); $probe->execute([$name]);
    $ok((int)$probe->fetchColumn() === 1, 'operation releases connection lock after completion');
    $total = $repository->page($site, [])['total'];
    $ok($total === 5, 'temporary table contains only fixture attempts');
    echo 'PASS ' . $checks . " MySQL temporary-table checks; {$calls} fake carrier calls; no real table changed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n"); $testFailed = true;
} finally {
    if ($temporaryCreated && $pdo instanceof PDO) $pdo->exec('DROP TEMPORARY TABLE IF EXISTS `' . $table . '`');
}
exit($testFailed ? 1 : 0);
