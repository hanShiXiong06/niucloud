<?php
declare(strict_types=1);

/** Real local schema + real Soujike adapter/SyncService; all ERP replies are synthetic. */
if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__.'/bootstrap.php';
require __DIR__.'/membership-fixtures.php';
require_once dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\provider\{ProviderRegistry,SoujikeProvider,TransportContract};
use addon\hsx_lead\app\service\{Context,ReadService,SyncService};
use addon\hsx_lead\app\service\cooperation\{CooperationService,PartnerService,Store,ConfigService,EntityMembershipService};
use addon\hsx_lead\app\support\{Cipher,Data};
use think\facade\Db;

final class SoujikePageSyncTransport implements TransportContract
{
    public array $calls = [];
    public function __construct(private array $responses)
    {
    }
    public function post(string $endpoint, array $headers, array $body): array
    {
        sjkPageSyncCheck(!Db::connect()->getPdo()->inTransaction(), 'ERP page reads do not hold database transactions');
        sjkPageSyncCheck($endpoint === SoujikeProvider::STOCK_ENDPOINT, 'sync only invokes the known SKU page endpoint');
        $this->calls[] = ['endpoint' => $endpoint, 'body' => $body];
        if (!$this->responses)throw new RuntimeException('Unexpected synthetic ERP request');
        $next = array_shift($this->responses);
        if ($next instanceof Throwable)throw $next;
        if (!is_array($next))throw new RuntimeException('Invalid synthetic page response');
        return $next;
    }
    public function remaining(): int
    {
        return count($this->responses);
    }
}

$checks = 0;
$failed = false;
$pdo = null;
$schema = '';
function sjkPageSyncCheck(bool $condition, string $label): void
{
    if (!$condition)throw new RuntimeException('Assertion failed: '.$label);
    $GLOBALS['checks']++;
}
function sjkPageSyncRow(string $id, string $model, string $shelf = '1', bool $sold = false): array
{
    return ['id' => $id, 'storeId' => '36183', 'shopId' => null, 'categoryName' => $model, 'productName' => $model,
        'brandName' => '苹果', 'capacity' => '256GB', 'color' => '白色', 'retailPrice' => '2100.01', 'tradePrice' => '2000.00',
        'imei' => 'synthetic-'.$id, 'shelfStatus' => $shelf, 'shelfStatusName' => $shelf === '1' ? '已上架' : ($shelf === '2' ? '已下架' : null),
        'saleStatus' => $sold, 'firstResIsVideo' => false, 'skuImageList' => ['https://static.example.invalid/sjk-synthetic.png'],
        'freeWarehouseCount' => 0, 'transferringCount' => 0,
        'inCostPrice' => 'PRIVATE_COST', 'totalCost' => 'PRIVATE_TOTAL', 'customName' => 'PRIVATE_CUSTOMER',
        'customPhone' => 'PRIVATE_PHONE', 'inlineRemark' => 'PRIVATE_INTERNAL', 'sellCredentialList' => ['PRIVATE_DOCUMENT']];
}
function sjkPageSyncPage(array $rows, int $page = 1, ?int $total = null): array
{
    $total = $total ?? count($rows);
    return ['code' => 200, 'data' => ['records' => $rows, 'current' => $page, 'size' => 100, 'total' => $total, 'pages' => (int) ceil($total / 100)]];
}

try {
    [$app,$pdo,$schema,$site] = leadBoot();
    leadAssertIsolated($schema);
    $now = time();
    leadMembershipSeed($site);
    $settings = new ConfigService(new Context($site, 1));
    $config = $settings->get();
    $config['enabled'] = true;
    $config['fee_confirmed'] = true;
    $settings->save($config);
    $insert = static fn (string $table, array $data): int => (int) Db::name('hsx_lead_'.$table)->insertGetId(['site_id' => $site, 'create_time' => $now, 'update_time' => $now] + $data);
    $merchant = $insert('merchant', ['name' => 'Synthetic Soujike receiver', 'member_id' => 101, 'status' => 1, 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101]);
    $publisher = $insert('merchant', ['name' => 'Synthetic publisher', 'member_id' => 102, 'status' => 1, 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101]);
    // Admit both subjects using the same dynamic catalog and verified synthetic
    // framework receipts as real member checkout, never cached partner flags.
    leadMembershipGrant($site, 101, 'ML701');
    leadMembershipGrant($site, 102, 'ML701');
    sjkPageSyncCheck(EntityMembershipService::state($site, $merchant)['active'] && EntityMembershipService::state($site, $publisher)['active'], 'both source-matching fixtures have independently verified subject membership');
    $connection = $insert('connection', ['merchant_id' => $merchant, 'provider' => 'soujike', 'store_id' => '36183', 'enabled' => 1, 'config_version' => 1, 'sync_interval_minutes' => 60]);
    Data::query('connection', $site)->where('id', $connection)->update(['credentials_cipher' => Cipher::encrypt(['auth_mode' => 'token', 'access_token' => 'synthetic-page-token'], $site, $connection)]);
    $adminContext = new Context($site, 1);
    $admin = new ReadService($adminContext);
    $member = new ReadService(new Context($site, 0, 101));
    $all = static fn (): array => $admin->inventory(['merchant_id' => $merchant, 'inventory_scope' => 'all', 'limit' => 100]);
    $current = static fn (): array => $admin->inventory(['merchant_id' => $merchant, 'limit' => 100]);
    $published = static fn (): int => (int) Data::row('connection', $site, $connection)['published_run_id'];
    $leads = [];
    foreach (['iPhone 16', 'iPhone 15', 'iPhone 17', 'iPhone 18', 'iPhone 19', 'iPhone 14', 'iPhone 13'] as $index => $model) {
        $leads[$model] = $insert('collaboration', ['lead_no' => 'SJK_PAGE_SYNTHETIC_'.$index, 'request_id' => 'SJK_PAGE_REQUEST_'.$index, 'publisher_id' => $publisher, 'owner_uid' => 1,
            'customer_name' => 'Synthetic customer', 'mobile' => '13900000102', 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101,
            'business_type' => 'purchase', 'models_json' => Data::json([['id' => 'synthetic-model-'.$index, 'title' => $model]]), 'status' => 'selecting']);
    }
    // A fresh request/service avoids testing a deliberately request-scoped inventory cache across generations.
    $candidate = static function (string $model) use ($site, $leads, $merchant): array {
        return array_values(array_filter((new CooperationService(new Context($site, 1)))->stores(['scope' => 'district'], $leads[$model])['data'], static fn (array $row): bool => (int) $row['id'] === $merchant));
    };

    $initial = [sjkPageSyncRow('active-16', 'iPhone 16'), sjkPageSyncRow('active-15', 'iPhone 15'),
        sjkPageSyncRow('off-17', 'iPhone 17', '2'), sjkPageSyncRow('sold-18', 'iPhone 18', '2', true), sjkPageSyncRow('unknown-19', 'iPhone 19', '0')];
    $firstTransport = new SoujikePageSyncTransport([sjkPageSyncPage($initial)]);
    $sync = new SyncService(new ProviderRegistry($firstTransport));
    $first = $sync->start($site, $merchant, 1);
    $firstId = (int) $first['run_id'];
    $done = $sync->advance($site, $firstId);
    sjkPageSyncCheck($done['status'] === 'completed' && $published() === $firstId, 'a complete verified page becomes the published generation');
    sjkPageSyncCheck($firstTransport->remaining() === 0 && count($firstTransport->calls) === 1, 'token connection reads one page without another source login');
    sjkPageSyncCheck(array_key_exists('saleStatus', $firstTransport->calls[0]['body']) && $firstTransport->calls[0]['body']['saleStatus'] === null, 'complete sync does not silently omit sold device states');
    sjkPageSyncCheck(array_key_exists('isNew', $firstTransport->calls[0]['body']) && $firstTransport->calls[0]['body']['isNew'] === null, 'complete sync includes both new and used source records');
    sjkPageSyncCheck($all()['total'] === 5 && $current()['total'] === 2, 'all view retains source states while default view includes only listed unsold devices');
    $rows = array_column($current()['data'], null, 'model');
    sjkPageSyncCheck(isset($rows['iPhone 16'],$rows['iPhone 15']), 'both explicitly listed unsold fixtures are searchable through the default list');
    foreach ($rows as $row) {
        sjkPageSyncCheck($row['quantity'] === null && $row['saleable_quantity'] === '1.000', 'single-device evidence does not fabricate an aggregate source quantity');
        sjkPageSyncCheck($row['is_sellable'] === true && $row['sale_status_label'] === '在售', 'confirmed Soujike rows are not labelled pending verification');
    }
    $allRows = array_column($all()['data'], null, 'model');
    sjkPageSyncCheck($allRows['iPhone 17']['sale_status'] === 'off_shelf' && $allRows['iPhone 17']['sale_status_label'] === '已下架', 'unsold down-listed device is labelled off shelf, not a proven sale');
    sjkPageSyncCheck($allRows['iPhone 18']['sale_state'] === 'sold' && $allRows['iPhone 18']['sale_status'] === 'sold_out' && $allRows['iPhone 18']['sale_status_label'] === '已售出', 'sold device is explicitly labelled sold even when also down-listed');
    sjkPageSyncCheck($allRows['iPhone 19']['sale_state'] === 'unsold' && $allRows['iPhone 19']['sale_status'] === 'unknown', 'unknown shelf code remains unverified rather than silently becoming on sale');
    $offRows = $admin->inventory(['merchant_id' => $merchant, 'inventory_scope' => 'off_shelf', 'limit' => 100]);
    $soldRows = $admin->inventory(['merchant_id' => $merchant, 'inventory_scope' => 'sold_out', 'limit' => 100]);
    sjkPageSyncCheck($offRows['total'] === 1 && $offRows['data'][0]['model'] === 'iPhone 17', 'off-shelf filter excludes sold device states');
    sjkPageSyncCheck($soldRows['total'] === 1 && $soldRows['data'][0]['model'] === 'iPhone 18', 'sold-out filter includes proven sold device even with unlisted shelf state');
    sjkPageSyncCheck($member->inventory([], $merchant)['total'] === 2, 'merchant inventory uses the same two available source devices');
    sjkPageSyncCheck(count((new PartnerService($adminContext))->inventory($merchant)) === 2, 'cooperation inventory/counting accepts verified per-device evidence without a quantity field');
    sjkPageSyncCheck(count($candidate('iPhone 16')) === 1 && count($candidate('iPhone 15')) === 1, 'new merchant cooperation can match both available models');
    foreach (['iPhone 17', 'iPhone 18', 'iPhone 19'] as $model)sjkPageSyncCheck($candidate($model) === [], 'unlisted sold or unknown model is not proposed as delivery stock: '.$model);
    $saved = Data::query('inventory', $site)->where('sync_run_id', $firstId)->where('source_id', 'active-16')->find();
    sjkPageSyncCheck($saved['source_store_id'] === '36183' && $saved['price'] === '2100.01' && $saved['trade_price'] === '2000.00', 'published store and prices preserve verified identity/exact decimals');
    sjkPageSyncCheck(!str_contains($saved['snapshot_json'], 'PRIVATE_') && !str_contains($saved['source_status_json'], 'PRIVATE_'), 'richer source response does not persist customer cost internal-note or credential evidence');

    // Stage a full first page, then publish only after the final page arrives.
    $nextRows = [sjkPageSyncRow('active-16', 'iPhone 16', '2'), sjkPageSyncRow('off-17', 'iPhone 17', '2'),
        sjkPageSyncRow('sold-18', 'iPhone 18', '2', true), sjkPageSyncRow('unknown-19', 'iPhone 19', '0')];
    for ($index = 0;$index < 96;$index++)$nextRows[] = sjkPageSyncRow('fresh-'.$index, 'iPhone 14');
    $secondTransport = new SoujikePageSyncTransport([sjkPageSyncPage($nextRows, 1, 101), sjkPageSyncPage([sjkPageSyncRow('fresh-final', 'iPhone 13')], 2, 101)]);
    $sync2 = new SyncService(new ProviderRegistry($secondTransport));
    $second = $sync2->start($site, $merchant, 1);
    $secondId = (int) $second['run_id'];
    $partial = $sync2->advance($site, $secondId);
    sjkPageSyncCheck($partial['status'] === 'running' && $published() === $firstId, 'first of two pages does not replace the complete inventory generation');
    sjkPageSyncCheck($all()['total'] === 5 && $current()['total'] === 2 && count($candidate('iPhone 16')) === 1, 'partial down-list and fresh rows neither hide old complete stock nor leak staged data');
    $completed = $sync2->advance($site, $secondId);
    sjkPageSyncCheck($completed['status'] === 'completed' && $published() === $secondId && $secondTransport->remaining() === 0, 'last page atomically switches publication to the complete new generation');
    sjkPageSyncCheck($secondTransport->calls[1]['body']['current'] === 2, 'cursor advances to the actual second source page');
    sjkPageSyncCheck($all()['total'] === 101 && $current()['total'] === 97, 'new totals count only current verified source rows');
    sjkPageSyncCheck($candidate('iPhone 16') === [] && $candidate('iPhone 15') === [], 'down-listed and disappeared old models immediately leave cooperation matching after complete publication');
    sjkPageSyncCheck(count($candidate('iPhone 14')) === 1 && count($candidate('iPhone 13')) === 1, 'both pages of new available stock enter cooperation matching');
    sjkPageSyncCheck(Data::query('inventory', $site)->where('sync_run_id', $firstId)->where('source_id', 'active-15')->count() === 1, 'source disappearance preserves historical snapshot instead of deleting or falsely marking a sale');
    sjkPageSyncCheck($member->inventory([], $merchant)['total'] === 97, 'merchant read uses the new complete generation, not the union of historical snapshots');

    // A later failed page must not publish a seemingly complete smaller source list.
    $failingTransport = new SoujikePageSyncTransport([sjkPageSyncPage($nextRows, 1, 101), ['code' => 500, 'msg' => 'synthetic upstream read failure']]);
    $badSync = new SyncService(new ProviderRegistry($failingTransport));
    $badRun = $badSync->start($site, $merchant, 1);
    $badId = (int) $badRun['run_id'];
    $badSync->advance($site, $badId);
    $failedRun = $badSync->advance($site, $badId);
    sjkPageSyncCheck($failedRun['status'] === 'failed' && $published() === $secondId, 'failed final page preserves the previous complete publication pointer');
    sjkPageSyncCheck($all()['total'] === 101 && Data::query('inventory', $site)->where('sync_run_id', $badId)->count() === 100, 'failed staged rows remain hidden even in the all-state current view');
    sjkPageSyncCheck($current()['total'] === 0 && $candidate('iPhone 14') === [], 'failed refresh marks old data stale rather than offering it as newly confirmed stock');

    // Recover through a normal complete sync, then test raw store boundary failures.
    $recoveryTransport = new SoujikePageSyncTransport([sjkPageSyncPage([sjkPageSyncRow('recovered', 'iPhone 16')])]);
    $recoverySync = new SyncService(new ProviderRegistry($recoveryTransport));
    $recovery = $recoverySync->start($site, $merchant, 1);
    $recoveredId = (int) $recovery['run_id'];
    $recoverySync->advance($site, $recoveredId);
    sjkPageSyncCheck($published() === $recoveredId && $current()['total'] === 1 && count($candidate('iPhone 16')) === 1, 'a successful full retry restores fresh matching without manual stock edits');
    foreach (['foreign-store', 'missing-store'] as $kind) {
        $wrong = sjkPageSyncRow('wrong-'.$kind, 'iPhone 16');
        if ($kind === 'foreign-store')$wrong['storeId'] = 'different-store';
        else unset($wrong['storeId']);
        $transport = new SoujikePageSyncTransport([sjkPageSyncPage([sjkPageSyncRow('valid-before-'.$kind, 'iPhone 16'), $wrong])]);
        $service = new SyncService(new ProviderRegistry($transport));
        $run = $service->start($site, $merchant, 1);
        $runId = (int) $run['run_id'];
        $result = $service->advance($site, $runId);
        sjkPageSyncCheck($result['status'] === 'failed' && $published() === $recoveredId, 'raw foreign or missing source store cannot replace published data: '.$kind);
        sjkPageSyncCheck(Data::query('inventory', $site)->where('sync_run_id', $runId)->count() === 0 && $all()['total'] === 1, 'a malformed store page cannot stage even an earlier valid row: '.$kind);
    }
    sjkPageSyncCheck(!str_contains(json_encode(Data::query('audit', $site)->select()->toArray()), 'PRIVATE_'), 'audit only stores safe operational facts from the richer page interface');
    leadAssertIsolated($schema);
    echo json_encode(['passed' => true, 'checks' => $checks, 'scope' => 'real Soujike page adapter, full generation publication, member stock and cooperation model matching with synthetic ERP pages', 'remote_calls' => 0, 'existing_business_rows_read' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL Soujike page sync: '.$error->getMessage().' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema !== '') {
        try {
            leadCleanup($pdo, $schema);
        } catch (Throwable $error) {
            $failed = true;
            fwrite(STDERR, "FAIL isolated schema cleanup requires attention\n");
        }
    }
}
exit($failed ? 1 : 0);
