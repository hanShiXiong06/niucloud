<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);
if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true))exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $f) {
    if (function_exists($f))exit("Disable external network functions\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_lead\app\service\{Context,ReadService};
use addon\hsx_lead\app\support\Data;
use think\facade\Db;
$checks = 0;
$failed = false;
$pdo = null;
$schema = '';
function searchCheck(bool $condition, string $name): void
{
    if (!$condition)throw new RuntimeException($name);
    $GLOBALS['checks']++;
}
try {
    [$app,$pdo,$schema,$site] = leadBoot();
    $now = time();
    $insert = static fn (string $table, array $row): int => (int) Db::name('hsx_lead_'.$table)->insertGetId(['site_id' => $site, 'create_time' => $now, 'update_time' => $now] + $row);
    $merchant = $insert('merchant', ['name' => 'Search source merchant', 'member_id' => 101, 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101]);
    $connection = $insert('connection', ['merchant_id' => $merchant, 'provider' => 'soujike', 'last_sync_status' => 'completed', 'last_sync_at' => $now]);
    $run = $insert('sync_run', ['merchant_id' => $merchant, 'connection_id' => $connection, 'status' => 'completed']);
    Db::name('hsx_lead_connection')->where('id', $connection)->update(['published_run_id' => $run]);
    $base = ['merchant_id' => $merchant, 'connection_id' => $connection, 'sync_run_id' => $run, 'fetched_at' => $now, 'model' => 'SearchNeedle 13', 'memory' => '256GB', 'price' => '2000.00', 'imei' => 'SJKSEARCH001', 'availability' => 'unknown', 'quantity' => null, 'source_status_json' => '{}', 'snapshot_json' => '{"secret":"PRIVATE_SEARCH_SNAPSHOT"}'];
    $unknown = $insert('inventory', $base + ['source_key' => 'unknown']);
    $known = $insert('inventory', array_replace($base, ['source_key' => 'known', 'model' => 'KnownPhone 16', 'imei' => 'KNOWN001', 'availability' => 'available', 'saleable_quantity' => '1.000', 'source_status_json' => Data::json(['_inventory' => ['listing_status' => 'listed', 'stock_status' => 'in_stock', 'quantity_basis' => 'listed_stock_device']])]));
    $literal = $insert('inventory', array_replace($base, ['source_key' => 'literal', 'model' => 'Literal%_Phone', 'imei' => 'LITERAL001']));
    $read = new ReadService(new Context($site, 1));
    searchCheck($read->inventory(['keyword' => 'SearchNeedle'])['total'] === 0, 'Unknown source stock does not silently enter a normal saleable search');
    $result = $read->inventory(['inventory_scope' => 'all', 'keyword' => 'SearchNeedle', 'merchant_id' => $merchant]);
    searchCheck($result['total'] === 1 && (int) $result['data'][0]['id'] === $unknown, 'Explicit all-source search finds a stored unknown merchant device');
    searchCheck($result['inventory_scope'] === 'all', 'API describes the widened diagnostic range explicitly');
    searchCheck($result['data'][0]['sale_status'] === 'unknown' && $result['data'][0]['is_sellable'] === false, 'Searching a device does not upgrade unknown evidence to available');
    searchCheck($result['data'][0]['quantity'] === null, 'Search does not manufacture a physical quantity');
    searchCheck(!str_contains(json_encode($result), 'PRIVATE_SEARCH_SNAPSHOT'), 'Search does not leak raw source snapshots');
    searchCheck($read->inventory(['inventory_scope' => 'all', 'keyword' => 'SJKSEARCH001'])['total'] === 1, 'IMEI search works on the diagnostic range');
    searchCheck($read->inventory(['inventory_scope' => 'all', 'keyword' => 'Literal%_'])['total'] === 1, 'Literal percent and underscore cannot widen search');
    searchCheck($read->inventory(['inventory_scope' => 'all', 'keyword' => 'SearchNeedle', 'merchant_id' => $merchant + 99])['total'] === 0, 'Widening states does not remove the chosen merchant constraint');
    searchCheck($read->inventory(['inventory_scope' => 'all', 'keyword' => 'SearchNeedle', 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910102])['total'] === 0, 'Widening states does not remove region constraints');
    searchCheck($read->inventory(['inventory_scope' => 'all', 'keyword' => 'SearchNeedle', 'time_field' => 'fetched_at', 'start_time' => $now + 60, 'end_time' => $now + 120])['total'] === 0, 'Widening states does not remove the time range');
    searchCheck($read->inventory(['keyword' => 'KnownPhone'])['total'] === 1, 'Confirmed saleable inventory still works with the default keyword search');
    searchCheck($read->inventory()['total'] === 1, 'Resetting parameters restores the safe default instead of all snapshots');
    $member = new ReadService(new Context($site, 0, 101));
    searchCheck($member->inventory(['inventory_scope' => 'all', 'keyword' => 'SearchNeedle'], $merchant)['total'] === 0, 'Merchant user cannot opt into the administrator diagnostic search');
    $otherRun = $insert('sync_run', ['merchant_id' => $merchant, 'connection_id' => $connection, 'status' => 'running']);
    $insert('inventory', array_replace($base, ['source_key' => 'staged', 'sync_run_id' => $otherRun]));
    searchCheck($read->inventory(['inventory_scope' => 'all', 'keyword' => 'SearchNeedle'])['total'] === 1, 'All-source search still cannot expose a partial generation');
    Db::name('hsx_lead_inventory')->insert(array_replace($base, ['site_id' => $site + 1, 'source_key' => 'foreign', 'create_time' => $now, 'update_time' => $now]));
    searchCheck($read->inventory(['inventory_scope' => 'all', 'keyword' => 'SearchNeedle'])['total'] === 1, 'All-source search cannot leak another tenant');
    leadAssertIsolated($schema);
    echo json_encode(['passed' => true, 'checks' => $checks, 'scope' => 'explicit administrator source search, unchanged saleability and merchant/region/time boundaries', 'remote_calls' => 0]).PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL '.($error instanceof PDOException ? 'isolated database error' : $error->getMessage()).PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema !== '') {
        try {
            leadCleanup($pdo, $schema);
        } catch (Throwable $e) {
            $failed = true;
            fwrite(STDERR, "FAIL test cleanup\n");
        }
    }
}
exit($failed ? 1 : 0);
