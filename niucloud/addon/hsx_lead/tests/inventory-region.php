<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_lead\app\service\{Context, MerchantService, ReadService};
use core\exception\CommonException;
use think\facade\Db;

$checks = 0; $failed = false; $pdo = null; $schema = '';
function regionCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $label);
    $GLOBALS['checks']++;
}
function regionDenied(callable $action, string $label): void
{
    try { $action(); } catch (CommonException $error) { regionCheck(true, $label); return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
try {
    [$app, $pdo, $schema, $site] = leadBoot();
    $now = time();
    Db::name('sys_area')->insertAll([
        ['id' => 910200, 'pid' => 910000, 'name' => 'Fixture City Two', 'level' => 2, 'status' => 1],
        ['id' => 910201, 'pid' => 910200, 'name' => 'Fixture District Two', 'level' => 3, 'status' => 1],
        ['id' => 920000, 'pid' => 0, 'name' => 'Other Province', 'level' => 1, 'status' => 1],
        ['id' => 920100, 'pid' => 920000, 'name' => 'Other City', 'level' => 2, 'status' => 1],
        ['id' => 920101, 'pid' => 920100, 'name' => 'Other District', 'level' => 3, 'status' => 1],
    ]);
    $insert = static fn(string $table, array $row): int => (int)Db::name('hsx_lead_' . $table)->insertGetId(['site_id' => $site, 'create_time' => $now, 'update_time' => $now] + $row);
    $merchant = static fn(string $name, int $province, int $city, int $district, int $member = 0): int => $insert('merchant',
        ['name' => $name, 'contact_name' => 'Operator ' . $name, 'mobile' => '18800000001', 'province_id' => $province, 'city_id' => $city, 'district_id' => $district, 'member_id' => $member]);
    $a = $merchant('Region A', 910000, 910100, 910101, 101);
    $b = $merchant('Region B', 910000, 910100, 910102);
    $c = $merchant('Region C', 910000, 910200, 910201);
    $d = $merchant('Region D', 920000, 920100, 920101, 102);
    foreach ([$a, $b, $c, $d] as $merchantId) {
        $connection = $insert('connection', ['merchant_id' => $merchantId, 'provider' => 'soujike', 'last_sync_at' => $now, 'last_sync_status' => 'success']);
        $run = $insert('sync_run', ['merchant_id' => $merchantId, 'connection_id' => $connection, 'status' => 'success']);
        Db::name('hsx_lead_connection')->where('id', $connection)->update(['published_run_id' => $run]);
        $insert('inventory', ['merchant_id' => $merchantId, 'connection_id' => $connection, 'sync_run_id' => $run,
            'source_key' => 'REGION-' . $merchantId, 'source_store_id' => 'UNRELATED-ERP-REGION-123', 'model' => 'Synthetic Phone',
            // Region tests use a known sellable fixture, independent of a
            // provider whose source stock/status dictionary may be unknown.
            'price' => '1000.00', 'fetched_at' => $now, 'availability' => 'available', 'quantity' => '1.000',
            'source_status_json' => '{"_inventory":{"listing_status":"listed","stock_status":"in_stock","quantity_basis":"source_quantity"}}']);
    }
    $context = new Context($site, 1);
    $service = new ReadService($context);
    $all = $service->inventory(['limit' => 100]);
    regionCheck($all['total'] === 4, 'all published regional snapshots visible');
    $byMerchant = array_column($all['data'], null, 'merchant_id');
    regionCheck($byMerchant[$a]['province_id'] === 910000 && $byMerchant[$a]['city_id'] === 910100 && $byMerchant[$a]['district_id'] === 910101, 'projection includes local merchant area IDs');
    regionCheck($byMerchant[$a]['region_text'] === 'Fixture Province / Fixture City / Fixture District', 'projection displays dictionary names instead of missing region');
    regionCheck(!str_contains($byMerchant[$a]['region_text'], 'UNRELATED'), 'source ERP identifiers never become Niucloud region labels');
    regionCheck($service->inventory(['province_id' => 910000])['total'] === 3, 'province includes all its cities and districts');
    regionCheck($service->inventory(['province_id' => 910000, 'city_id' => 910100])['total'] === 2, 'city includes all its districts');
    regionCheck($service->inventory(['province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101])['total'] === 1, 'district narrows exact merchant address');
    regionCheck($service->inventory(['province_id' => 910000, 'merchant_id' => $d])['total'] === 0, 'merchant ID cannot escape applied region');
    regionCheck($service->inventory(['province_id' => '', 'city_id' => '', 'district_id' => ''])['total'] === 4, 'clearing regions returns complete published scope');
    foreach ([['city_id' => 910100], ['province_id' => 910000, 'district_id' => 910101],
        ['province_id' => 920000, 'city_id' => 910100], ['province_id' => 910000, 'city_id' => 910200, 'district_id' => 910101],
        ['province_id' => '910000 OR 1=1'], ['province_id' => []], ['province_id' => 910000.5], ['province_id' => true], ['province_id' => -1], ['province_id' => 999999]] as $bad) {
        regionDenied(fn() => $service->inventory($bad), 'invalid/hierarchically mismatched area filter is rejected');
    }
    $member = new ReadService(new Context($site, 0, 101));
    regionCheck($member->inventory(['province_id' => 910000], $a)['total'] === 1, 'own member inventory keeps regional projection');
    regionCheck($member->inventory(['province_id' => 920000, 'merchant_id' => $d], $a)['total'] === 0, 'member filter cannot select a different merchant');
    regionDenied(fn() => $member->inventory([], $d), 'member cannot read another merchant via region API');

    $merchants = new MerchantService($context);
    $picker = $merchants->lists(['picker' => 1, 'province_id' => 910000, 'limit' => 2]);
    regionCheck($picker['total'] === 3 && count($picker['data']) === 2, 'merchant picker filters before paging');
    regionCheck($picker['data'][0]['region_text'] !== '', 'picker batches actual local area names');
    regionCheck(!preg_match('/credentials|config_revision|connection_id|service_areas_json|ready_state/', json_encode($picker)), 'lean picker does not decrypt or return ERP authorization/readiness metadata');
    regionCheck($merchants->lists(['picker' => 1, 'keyword' => 'Operator Region A'])['total'] === 1, 'picker searches contact names on server');
    regionCheck($merchants->lists(['picker' => 1, 'keyword' => '18800000001'])['total'] === 4, 'picker searches telephone on server');
    regionCheck($merchants->lists(['picker' => 1, 'keyword' => 'Region C', 'province_id' => 910000, 'city_id' => 910100])['total'] === 0, 'merchant search is intersected with selected area');
    regionDenied(fn() => $merchants->lists(['picker' => 1, 'city_id' => 910100]), 'picker applies identical region validation');
    regionDenied(fn() => (new MerchantService(new Context($site, 0, 101)))->lists(['picker' => 1]), 'member cannot enumerate manager merchant picker');
    for ($i = 0; $i < 115; $i++) $merchant('Paged ' . $i, 910000, 910100, 910101);
    $first = $merchants->lists(['picker' => 1, 'keyword' => 'Paged', 'limit' => 100, 'page' => 1]);
    $second = $merchants->lists(['picker' => 1, 'keyword' => 'Paged', 'limit' => 100, 'page' => 2]);
    regionCheck($first['total'] === 115 && count($first['data']) === 100 && count($second['data']) === 15, 'picker does not silently truncate merchants after the first 100');
    regionCheck(count(array_unique(array_column(array_merge($first['data'], $second['data']), 'id'))) === 115, 'picker pages preserve every matching merchant exactly once');
    $other = (int)Db::name('hsx_lead_merchant')->insertGetId(['site_id' => $site + 1, 'name' => 'Paged Foreign', 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101]);
    regionCheck($merchants->lists(['picker' => 1, 'keyword' => 'Paged'])['total'] === 115, 'picker cannot enumerate another tenant');
    // Test the second site's own inventory as its actual authorized operator,
    // rather than relying on an arbitrary positive uid to bypass membership.
    Db::name('sys_user_role')->insert(['uid'=>1,'site_id'=>$site+1,'status'=>1,'is_admin'=>1]);
    regionCheck((new ReadService(new Context($site + 1, 1)))->inventory(['province_id' => 910000])['total'] === 0, 'regional joins cannot cross tenant publication scope');
    Db::name('hsx_lead_merchant')->where('id', $a)->update(['province_id' => 920000, 'city_id' => 920100, 'district_id' => 920101]);
    $updated = array_column($service->inventory(['limit' => 100])['data'], null, 'merchant_id');
    regionCheck($updated[$a]['region_text'] === 'Other Province / Other City / Other District', 'merchant address update is reflected without ERP resync or historical backfill');
    regionCheck($service->inventory(['province_id' => 910000])['total'] === 2, 'region filtering follows the current merchant address');
    leadAssertIsolated($schema);
    echo json_encode(['passed' => true, 'checks' => $checks, 'scope' => 'inventory merchant address projection, hierarchical filters, paginated searchable picker and tenant boundaries',
        'database' => 'owned random localhost schema', 'remote_calls' => 0, 'existing_business_rows_read' => 0]), PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    $message = $error instanceof PDOException ? 'isolated MySQL failure SQLSTATE ' . $error->getCode() : $error->getMessage();
    fwrite(STDERR, 'FAIL ' . get_class($error) . ': ' . $message . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema !== '') {
        try { leadCleanup($pdo, $schema); } catch (Throwable $error) { $failed = true; fwrite(STDERR, "FAIL owned test database cleanup requires attention\n"); }
    }
}
exit($failed ? 1 : 0);
