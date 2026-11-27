<?php
declare(strict_types=1);

use addon\hsx_erp\app\service\admin\ErpPurchaseService;
use addon\hsx_erp\app\service\admin\ErpSaleService;
use addon\hsx_erp\app\service\admin\ErpPurchaseReturnService;
use addon\hsx_erp\app\service\admin\ErpSaleReturnService;
use addon\hsx_erp\app\service\admin\ErpFinanceService;
use addon\hsx_erp\app\service\admin\ErpIntegrationService;
use addon\hsx_kingdee_jdy\app\listener\LedgerRecorded;
use addon\hsx_kingdee_jdy\app\listener\TaskValidate;
use addon\hsx_kingdee_jdy\app\service\ConfigService;
use addon\hsx_kingdee_jdy\app\service\ConnectionService;
use addon\hsx_kingdee_jdy\app\service\NoticeService;
use addon\hsx_kingdee_jdy\app\service\TaskService;
use addon\hsx_kingdee_jdy\app\gateway\Client;
use addon\hsx_kingdee_jdy\app\gateway\Transport;
use addon\hsx_kingdee_jdy\app\support\Problem;
use think\facade\Db;

if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'stream_socket_client'] as $fn) {
    if (function_exists($fn)) exit("Disable HTTP/socket functions before testing\n");
}
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
$app = new think\App($root);
$app->loadEnv();
require_once $app->getThinkPath() . 'helper.php';
require_once $root . '/app/common.php';
$database = require $root . '/config/database.php';
$original = $database['connections'][$database['default']];
$schema = 'hsx_jdy_test_' . bin2hex(random_bytes(8));
$created = false;
$checks = 0;
$stage = 'isolation';
$failed = false;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('ASSERT: ' . $label);
    $checks++;
};
$reject = static function (callable $fn, string $reason) use ($check): void {
    try { $fn(); } catch (Problem $e) { $check($e->reason === $reason, $reason); return; }
    $check(false, 'expected ' . $reason);
};
$quote = static function (string $id): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $id)) throw new RuntimeException('Unsafe identifier');
    return '`' . $id . '`';
};
try {
    if (($original['type'] ?? '') !== 'mysql' || !in_array($original['hostname'], ['localhost', '127.0.0.1'], true)
        || !empty($original['deploy']) || !ctype_digit((string)$original['hostport'])) throw new RuntimeException('Only localhost MySQL is allowed');
    $pdo = new PDO('mysql:host=127.0.0.1;port=' . $original['hostport'] . ';charset=utf8mb4', $original['username'], $original['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]);
    $pdo->exec('CREATE DATABASE ' . $quote($schema) . ' CHARACTER SET utf8mb4');
    $created = true;
    $pdo->exec('USE ' . $quote($schema));
    $prefix = $original['prefix'];
    $tables = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
    $tables->execute([$original['database']]);
    foreach ($tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $short = str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : '';
        if (!str_starts_with($short, 'erp_') && !in_array($short, ['site', 'site_group', 'sys_config', 'sys_user', 'sys_user_role', 'sys_schedule', 'member'], true)) continue;
        $pdo->exec('CREATE TABLE ' . $quote($table) . ' LIKE ' . $quote($original['database']) . '.' . $quote($table));
    }
    $sql = str_replace('{{prefix}}', $prefix, file_get_contents(dirname(__DIR__) . '/sql/install.sql'));
    $pdo->exec($sql);
    $pdo->exec($sql);
    $database['default'] = 'isolated';
    $database['connections'] = ['isolated' => array_replace($original, ['hostname' => '127.0.0.1', 'database' => $schema, 'trigger_sql' => false, 'fields_cache' => false, 'break_reconnect' => false])];
    $app->config->set($database, 'database');
    foreach (['cache', 'log'] as $key) {
        $app->config->set(['default' => 'file', $key === 'cache' ? 'stores' : 'channels' => ['file' => ['type' => 'File', 'path' => sys_get_temp_dir() . '/' . $schema . '/' . $key . '/']]], $key);
    }
    $app->instance('request', new app\Request());
    $manager = new think\DbManager(); $manager->setConfig($database); $app->instance('think\DbManager', $manager);
    $check(Db::query('SELECT DATABASE() AS db')[0]['db'] === $schema, 'ORM isolated');
    $site = 900000002;
    request()->siteId($site); request()->uid(1); request()->username('JDY fixture'); request()->appType('adminapi');
    Db::name('site')->insert(['site_id' => $site, 'site_name' => 'JDY isolated', 'app_type' => 'site', 'addons' => json_encode(['hsx_erp', 'hsx_kingdee_jdy']), 'group_id' => 0]);
    Db::name('sys_user')->insert(['uid' => 1, 'username' => 'jdy-fixture', 'real_name' => 'Local Tester', 'status' => 1]);
    Db::name('sys_user_role')->insert(['uid' => 1, 'site_id' => $site, 'status' => 1]);
    $configService = new ConfigService();
    $stage = 'plugin lifecycle';
    $addon = new \addon\hsx_kingdee_jdy\Addon();
    $check($addon->install(), 'addon installs with real framework schedule service');
    $check(Db::name('sys_schedule')->where('addon', 'hsx_kingdee_jdy')->count() === 1, 'plugin schedule registered');
    $check($addon->upgrade(), 'addon upgrade succeeds');
    $check(Db::name('sys_schedule')->where('addon', 'hsx_kingdee_jdy')->count() === 1, 'upgrade does not duplicate schedules');
    $stage = 'configuration';
    $check($configService->installed($site), 'JDY installed without Cloud Galaxy');
    $saved = $configService->save($site, 1, ['revision' => 0, 'client_id' => '123', 'client_secret' => 'fixture-client',
        'app_key' => 'abc', 'app_secret' => 'fixture-app', 'account_id' => '123456', 'domain' => 'https://tf.jdy.com', 'mode' => 'preview', 'assignee_uid' => 1]);
    $check($saved['client_secret_configured'] && $saved['app_secret_configured'] && !isset($saved['client_secret_cipher']), 'secret presence but not plaintext returned');
    $check(!str_contains(json_encode($saved), 'fixture-client'), 'public config sanitized');
    $raw = Db::name('sys_config')->where('site_id', $site)->where('config_key', ConfigService::KEY)->value('value');
    $check(!str_contains($raw, 'fixture-client') && !str_contains($raw, 'fixture-app'), 'secrets encrypted at rest');
    $reject(fn() => $configService->save($site, 1, ['revision' => 0]), 'CONFIG_STALE');
    $fake = new class implements Transport {
        public function send(string $method, string $path, array $query, array $headers, array $body): array {
            return str_contains($path, 'auth_token') ? ['errcode' => 0, 'data' => ['access_token' => 'fixture-token', 'expires' => (time() + 3600) * 1000]] : ['code' => 0, 'items' => [], 'records' => 0];
        }
    };
    $probe = (new ConnectionService())->probe($site, 1, (int)$saved['revision'], new Client($fake));
    $check($probe['status'] === 'read_verified' && !$probe['write_enabled'], 'mocked connection checks query not write');
    $reject(fn() => (new ConnectionService())->probe($site, 1, (int)$saved['revision'], new Client($fake)), 'PROBE_BUSY');
    $fresh = $configService->get($site);
    $check($fresh['probe_revision'] === $fresh['revision'], 'connection result revision bound');
    $notices = [];
    $app->event->listen('ErpDomainEvent', LedgerRecorded::class);
    $app->event->listen('HsxBusinessTaskAssigned', static function (array $event) use (&$notices): array { $notices[] = $event; return ['queued' => true, 'status' => 'pending']; });
    // Only synthetic config is adjusted so current-second ERP fixtures are in scope.
    $row = app\model\sys\SysConfig::where('site_id', $site)->where('config_key', ConfigService::KEY)->find();
    $value = $row->value; $value['collect_from'] = time() - 10; $row->save(['value' => $value]);
    $add = static fn(string $table, array $data): int => (int)Db::name($table)->insertGetId(['site_id' => $site] + $data);
    $supplier = $add('erp_party', ['party_no' => 'JDY-S', 'party_name' => 'Fixture Supplier', 'party_type' => 'supplier', 'status' => 1]);
    $customer = $add('erp_party', ['party_no' => 'JDY-C', 'party_name' => 'Fixture Customer', 'party_type' => 'customer', 'status' => 1]);
    $warehouse = $add('erp_warehouse', ['warehouse_name' => 'Fixture Warehouse', 'warehouse_type' => 'peer', 'status' => 1, 'allow_direct_sale' => 1]);
    $location = $add('erp_warehouse_location', ['warehouse_id' => $warehouse, 'location_name' => 'Fixture Bin', 'status' => 1]);
    $account = $add('erp_capital_account', ['account_name' => 'Fixture Cash', 'account_type' => 'bank', 'balance' => 20000, 'status' => 1]);
    $stage = 'ERP purchase sale cash and both returns';
    $purchase = ErpPurchaseService::forSite($site, 1, 'Fixture');
    $sale = ErpSaleService::forSite($site, 1, 'Fixture');
    $finance = ErpFinanceService::forSite($site, 1, 'Fixture');
    $input = ['party_id' => $supplier, 'party_name' => 'Fixture Supplier', 'request_id' => 'JDY-P1', 'settle_method' => '挂账',
        'items' => [['imei' => 'JDY-FIXTURE-IMEI', 'model' => 'Fixture Phone', 'spec' => '256GB', 'purchase_cost' => 4500, 'retail_price' => 5000,
            'warehouse_id' => $warehouse, 'location_id' => $location, 'image_urls' => '["/static/fixture.png"]']]];
    $po = $purchase->create($input);
    $check($purchase->create($input) === $po, 'purchase idempotent');
    $asset = Db::name('erp_asset')->where('site_id', $site)->where('purchase_order_id', $po)->find();
    $payable = Db::name('erp_payable')->where('site_id', $site)->where('source_type', 'purchase_asset')->where('source_id', $asset['id'])->find();
    $finance->confirmPaymentInTransaction((int)$payable['id'], 4500, ['capital_account_id' => $account, 'request_id' => 'JDY-PAY1']);
    $saleInput = ['party_id' => $customer, 'party_name' => 'Fixture Customer', 'request_id' => 'JDY-S1', 'settle_method' => '挂账', 'items' => [['asset_id' => $asset['id'], 'sale_price' => 5000]]];
    $so = $sale->create($saleInput);
    $check($sale->create($saleInput) === $so, 'sale idempotent');
    $receivable = Db::name('erp_receivable')->where('site_id', $site)->where('source_type', 'sale')->where('source_id', $so)->find();
    $finance->confirmReceivableItemsInTransaction($customer, [['receivable_id' => $receivable['id'], 'amount' => 5000]], ['capital_account_id' => $account, 'request_id' => 'JDY-REC1']);
    $returns = new ErpSaleReturnService();
    $ret = $returns->create(['sale_order_id' => $so, 'request_id' => 'JDY-RET1', 'refund_mode' => 'payable', 'items' => [['asset_id' => $asset['id'], 'return_price' => 5000, 'reason' => 'Fixture']]]);
    $returns->confirm($ret); $returns->confirm($ret);
    $refund = Db::name('erp_payable')->where('site_id', $site)->where('source_type', 'sale_return')->where('source_id', $ret)->find();
    $finance->confirmPaymentInTransaction((int)$refund['id'], 5000, ['capital_account_id' => $account, 'request_id' => 'JDY-REF1']);
    $pret = (new ErpPurchaseReturnService())->create(['purchase_order_id' => $po, 'request_id' => 'JDY-PRET1', 'refund_mode' => 'receivable', 'items' => [['asset_id' => $asset['id'], 'return_cost' => 4500, 'reason' => 'Fixture']]]);
    $supplierRefund = Db::name('erp_receivable')->where('site_id', $site)->where('source_type', 'purchase_return')->where('source_id', $pret)->find();
    $finance->confirmReceivableItemsInTransaction($supplier, [['receivable_id' => $supplierRefund['id'], 'amount' => 4500]], ['capital_account_id' => $account, 'request_id' => 'JDY-SREF1']);
    $check((float)Db::name('erp_capital_account')->where('site_id', $site)->where('id', $account)->value('balance') === 20000.0, 'internal cashbook closes');
    $before = Db::name('erp_outbox_event')->count();
    Db::startTrans(); $copy = $input; $copy['request_id'] = 'JDY-ROLLBACK'; $copy['items'][0]['imei'] = 'JDY-ROLLBACK'; $purchase->create($copy); Db::rollback();
    $check(Db::name('erp_outbox_event')->count() === $before, 'rolled back stock has no integration fact');
    $stage = 'event delivery and preflight';
    $integration = ErpIntegrationService::forSite($site, 1);
    foreach (Db::name('erp_outbox_event')->where('site_id', $site)->column('id') as $id) $integration->dispatchDomainEvent((int)$id);
    $tasks = Db::name('hsx_kingdee_jdy_task')->where('site_id', $site)->select()->toArray();
    $check(count($tasks) >= 10, 'ERP stock finance and return facts reached independent JDY consumer');
    $service = new TaskService();
    foreach ($tasks as $task) {
        $event = json_decode($task['snapshot_json'], true);
        $check($service->accept($event)['status'] === 'duplicate', 'event redelivery idempotent');
        $check($service->process($site, (int)$task['id']), 'preflight processed');
        $check(!$service->process($site, (int)$task['id']), 'finished preflight not claimed twice');
    }
    $check(Db::name('hsx_kingdee_jdy_task')->count() === count($tasks), 'no duplicate task');
    $sample = $tasks[0];
    $event = json_decode($sample['snapshot_json'], true); $event['payload']['ledger']['remark'] = 'conflict';
    $reject(fn() => $service->accept($event), 'FACT_CONFLICT');
    $check(!$service->process($site + 1, (int)$sample['id']), 'cross tenant processing denied');
    $stage = 'material mappings and accounting basis';
    foreach ([['supplier', $supplier, 'S1', ''], ['customer', $customer, 'C1', ''], ['warehouse', $warehouse, 'W1', ''], ['asset_material', $asset['id'], 'P1', '1']] as [$kind, $local, $external, $unit]) {
        $add('hsx_kingdee_jdy_mapping', ['connection_key' => $saved['connection_key'], 'kind' => $kind, 'local_id' => $local,
            'external_number' => $external, 'unit_id' => $unit, 'operator_uid' => 1, 'create_at' => time(), 'update_at' => time()]);
    }
    $flowTasks = [];
    foreach ($tasks as $task) {
        if ($task['kind'] !== 'asset') continue;
        $service->retry($site, (int)$task['id'], 1); $service->process($site, (int)$task['id']);
        $current = Db::name('hsx_kingdee_jdy_task')->where('id', $task['id'])->find();
        $event = json_decode($current['snapshot_json'], true); $plan = json_decode($current['plan_json'], true);
        $flowTasks[$plan['flow']] = $current;
        $check($event['payload']['source_context']['business_amount'] === ($plan['flow'] === 'purchase' || $plan['flow'] === 'purchase_return' ? '4500.00' : '5000.00'), 'immutable business price snapshot ' . $plan['flow']);
        if (in_array($plan['flow'], ['purchase', 'sale'], true)) {
            $check($current['status'] === 'prepared', 'local draft ready ' . $plan['flow']);
            $check($plan['draft']['items'][0]['entries'][0]['price'] == ($plan['flow'] === 'purchase' ? 4500 : 5000), 'draft uses transaction price');
        } else {
            $check($current['status'] === 'blocked' && $plan['draft'] === null, 'return awaits original remote bill, not invented linkage');
            $check((int)$event['payload']['source_context']['original_line_id'] > 0, 'return retains original ERP line');
        }
    }
    $check(count($flowTasks) === 4, 'four stock flows observed');
    $blockedId = (int)$flowTasks['sale_return']['id'];
    (new NoticeService())->send($site, $blockedId);
    (new NoticeService())->send($site, $blockedId);
    $check(count($notices) === 1, 'one notice per round');
    $check($notices[0]['source_plugin'] === 'hsx_kingdee_jdy', 'notice independent target');
    $validation = new TaskValidate();
    $check($validation->handle(['site_id' => $site, 'source_plugin' => 'hsx_kingdee_jdy', 'source_id' => $blockedId])['valid'], 'pending task link valid');
    $reject(fn() => $service->resolve($site + 1, $blockedId, 1, 'Fixture resolution', ''), 'TASK_STATE');
    $service->resolve($site, $blockedId, 1, 'Fixture manual resolution only', '');
    $check(!$validation->handle(['site_id' => $site, 'source_plugin' => 'hsx_kingdee_jdy', 'source_id' => $blockedId])['valid'], 'closed notification link invalidated');
    $reject(fn() => $service->retry($site, $blockedId, 1), 'TASK_STATE');
    $fresh = $configService->get($site);
    $reject(fn() => $configService->save($site, 1, array_replace($fresh, ['account_id' => '999'])), 'ACCOUNT_LOCKED');
    $disabled = $configService->save($site, 1, array_replace($fresh, ['mode' => 'disabled']));
    $check($disabled['client_secret_configured'] && $disabled['app_secret_configured'], 'empty edit preserves encrypted secrets');
    $check(!$service->process($site, (int)$sample['id']), 'disabled worker stops');
    $allLogs = json_encode(Db::name('hsx_kingdee_jdy_log')->select()->toArray());
    $check(!str_contains($allLogs, 'fixture-client') && !str_contains($allLogs, 'fixture-token'), 'audit logs contain no credentials');
    $stage = 'plugin uninstall';
    $retainedTasks = Db::name('hsx_kingdee_jdy_task')->count();
    $check($addon->uninstall(), 'addon uninstall succeeds');
    $check(Db::name('sys_schedule')->where('addon', 'hsx_kingdee_jdy')->count() === 0, 'uninstall removes its schedule');
    $check(Db::name('hsx_kingdee_jdy_task')->count() === $retainedTasks, 'uninstall preserves integration records');
} catch (\Throwable $e) {
    $failed = true;
    echo json_encode(['status' => 'failed', 'stage' => $stage, 'type' => get_class($e),
        'assertion' => str_starts_with($e->getMessage(), 'ASSERT:') ? $e->getMessage() : '', 'file' => basename($e->getFile()), 'line' => $e->getLine()], JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    if ($created) {
        try { $pdo->exec('DROP DATABASE ' . $quote($schema)); $created = false; }
        catch (\Throwable $e) { $failed = true; echo "Temporary schema cleanup failed: $schema\n"; }
    }
    echo json_encode(['status' => $failed ? 'failed' : 'passed', 'checks' => $checks, 'temporary_schema_removed' => !$created,
        'business_database_written' => false, 'external_http_enabled' => false, 'remote_write_verified' => false], JSON_PRETTY_PRINT) . PHP_EOL;
}
exit($failed ? 1 : 0);
