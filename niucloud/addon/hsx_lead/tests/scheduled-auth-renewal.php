<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\provider\{ProviderRegistry,ProviderException,SoujikeProvider,TransportContract};
use addon\hsx_lead\app\service\SyncService;
use addon\hsx_lead\app\support\{Cipher,Data};
use think\facade\Db;

final class ScheduledRenewalMock implements TransportContract
{
    public array $calls = [];
    public function __construct(private array $responses)
    {
    }
    public function post(string $endpoint, #[\SensitiveParameter] array $headers, #[\SensitiveParameter] array $body): array
    {
        renewalCheck(!Db::connect()->getPdo()->inTransaction(), 'source authentication and inventory HTTP stay outside transactions');
        $this->calls[] = ['endpoint' => $endpoint, 'headers' => $headers, 'body' => $body];
        if (!$this->responses) throw new RuntimeException('Unexpected mocked request');
        $response = array_shift($this->responses);
        if (is_callable($response)) $response = $response($endpoint, $headers, $body);
        if ($response instanceof Throwable) throw $response;
        if (!is_array($response)) throw new RuntimeException('Invalid mocked response');
        return $response;
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
function renewalCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: '.$label);
    $GLOBALS['checks']++;
}
function renewalLogin(string $token, string $store = '9007199254740993'): array
{
    return ['code' => 200, 'data' => ['tokenInfo' => ['tokenName' => 'recovery-token', 'tokenValue' => $token],
        'storeId' => $store, 'storeInfo' => ['storeName' => 'Synthetic source store']]];
}
function renewalRow(int $id): array
{
    return ['id' => (string) $id, 'storeId' => '9007199254740993', 'categoryName' => 'Synthetic iPhone',
        'retailPrice' => '2000.00', 'tradePrice' => '1900.00', 'saleStatus' => false, 'shelfStatus' => '1', 'shelfStatusName' => '已上架'];
}
function renewalPage(int $page, array $rows, int $total): array
{
    return ['code' => 200, 'data' => ['records' => $rows, 'current' => $page, 'size' => 100, 'total' => $total, 'pages' => (int) ceil($total / 100)]];
}
function renewalKinds(ScheduledRenewalMock $mock): array
{
    return array_map(static fn (array $call): string => $call['endpoint'] === SoujikeProvider::LOGIN_ENDPOINT ? 'login' : 'stock', $mock->calls);
}

try {
    [$app,$pdo,$schema,$site] = leadBoot();
    leadAssertIsolated($schema);
    $now = time();
    $merchant = (int) Data::query('merchant', $site)->insertGetId(['site_id' => $site, 'name' => 'Synthetic renewal merchant',
        'member_id' => 101, 'status' => 1, 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101, 'create_time' => $now, 'update_time' => $now]);
    $connection = (int) Data::query('connection', $site)->insertGetId(['site_id' => $site, 'merchant_id' => $merchant, 'provider' => 'soujike',
        'store_id' => '9007199254740993', 'enabled' => 1, 'sync_interval_minutes' => 60, 'config_version' => 1, 'next_sync_at' => $now - 1, 'create_time' => $now, 'update_time' => $now]);
    $base = ['auth_mode' => 'password', 'username' => '18800000001', 'password' => 'synthetic-renewal-password', 'access_token' => 'synthetic-old-token'];
    $definition = (new ProviderRegistry())->definition('soujike');
    $capability = $definition->renewalStatus($base);
    renewalCheck($capability['automatic'] && $capability['ready'] && $capability['status'] === 'automatic', 'complete password credentials expose a genuine automatic-renewal capability');
    $missing = $definition->renewalStatus(['auth_mode' => 'password', 'username' => '18800000001']);
    renewalCheck($missing['automatic'] && !$missing['ready'] && $missing['status'] === 'credentials_required', 'missing password is not advertised as ready for unattended renewal');
    $tokenOnly = $definition->renewalStatus(['auth_mode' => 'token', 'access_token' => 'synthetic-manual-token']);
    renewalCheck(!$tokenOnly['automatic'] && !$tokenOnly['ready'] && $tokenOnly['status'] === 'manual_token', 'temporary token-only capability honestly reports no automatic password renewal');
    renewalCheck(!str_contains(json_encode([$capability, $missing, $tokenOnly]), 'synthetic-'), 'public renewal metadata contains no credential values');
    $setCredentials = static function (array $credentials) use ($site, $connection): void {
        Data::query('connection', $site)->where('id', $connection)->update(['credentials_cipher' => Cipher::encrypt($credentials, $site, $connection),
            'enabled' => 1, 'next_sync_at' => time() - 1, 'active_run_id' => 0, 'lease_token' => '', 'lease_until' => 0, 'last_sync_status' => 'completed']);
    };
    $read = static fn (): array => Data::row('connection', $site, $connection);
    $saved = static fn (): array => Cipher::decrypt((string) Data::row('connection', $site, $connection)['credentials_cipher'], $site, $connection);
    $make = static fn (ScheduledRenewalMock $mock): SyncService => new SyncService(new ProviderRegistry($mock));

    // Seed one complete synthetic generation so failure cases can prove preservation.
    $setCredentials($base + ['expires_at' => time() + 3600]);
    $baselineMock = new ScheduledRenewalMock([renewalPage(1, [renewalRow(1)], 1)]);
    $baseline = $make($baselineMock)->tick(1)[0];
    renewalCheck($baseline['status'] === 'completed' && $baseline['trigger_type'] === 'scheduled', 'real scheduler starts and completes the initial synthetic generation');
    $previous = (int) $baseline['id'];

    // Expired before tick one; page two later returns 401 and must retry that same page.
    $setCredentials($base + ['expires_at' => time() - 1]);
    $rows = array_map('renewalRow', range(1001, 1100));
    $mock = new ScheduledRenewalMock([renewalLogin('synthetic-first-renewal'), renewalPage(1, $rows, 101),
        ['code' => 401, 'msg' => 'private-expired-token'], renewalLogin('synthetic-second-renewal'), renewalPage(2, [renewalRow(1101)], 101)]);
    $sync = $make($mock);
    $first = $sync->tick(1)[0];
    renewalCheck($first['status'] === 'running' && (int) $first['page_count'] === 1 && (int) $first['record_count'] === 100, 'scheduled expired token logs in and stages one page');
    renewalCheck(renewalKinds($mock) === ['login', 'stock'] && (int) $read()['published_run_id'] === $previous, 'partial first page never replaces the old complete publication');
    renewalCheck($saved()['password'] === $base['password'] && $saved()['access_token'] === 'synthetic-first-renewal', 'refreshed token and original password persist in the connection cipher');
    $second = $sync->tick(1)[0];
    renewalCheck($second['status'] === 'completed' && (int) $second['id'] === (int) $first['id'] && (int) $second['record_count'] === 101, 'later scheduler tick resumes the same generation and finishes exactly 101 records');
    renewalCheck(renewalKinds($mock) === ['login', 'stock', 'stock', 'login', 'stock'], 'mid-pagination 401 has one login and one retry, not a fresh sync');
    renewalCheck($mock->calls[2]['body']['current'] === 2 && $mock->calls[4]['body']['current'] === 2, '401 retries the failed page rather than restarting page one');
    renewalCheck($mock->calls[2]['headers']['recovery-token'] === 'synthetic-first-renewal' && $mock->calls[4]['headers']['recovery-token'] === 'synthetic-second-renewal', 'retry uses the new token rather than the expired one');
    renewalCheck($saved()['access_token'] === 'synthetic-second-renewal' && $saved()['password'] === $base['password'], 'second page renewal is encrypted and retains the password');
    renewalCheck((int) Data::query('inventory', $site)->where('sync_run_id', $second['id'])->count() === 101, 'same-page retry does not create duplicate inventory');
    renewalCheck($sync->tick(1) === [] && $mock->remaining() === 0, 'completed schedule waits for the configured next interval');
    $previous = (int) $second['id'];

    // Source omits an expiry timestamp: use saved password on the next complete run.
    $setCredentials($base + ['expires_at' => 0]);
    $unknown = new ScheduledRenewalMock([renewalLogin('synthetic-unknown-expiry'), renewalPage(1, [renewalRow(2001)], 1)]);
    $result = $make($unknown)->tick(1)[0];
    renewalCheck($result['status'] === 'completed' && renewalKinds($unknown) === ['login', 'stock'], 'unknown expiry performs one normal password login before the scheduled read');
    $previous = (int) $result['id'];

    // Repeated denial stops, and the next minute cannot immediately create another run.
    $setCredentials($base + ['expires_at' => time() + 3600]);
    $denied = new ScheduledRenewalMock([['code' => 401], renewalLogin('synthetic-denied-renewal'), ['code' => 401]]);
    $deniedSync = $make($denied);
    $result = $deniedSync->tick(1)[0];
    $runCount = Data::query('sync_run', $site)->count();
    renewalCheck($result['status'] === 'failed' && $result['error_code'] === 'auth_required' && renewalKinds($denied) === ['stock', 'login', 'stock'], 'second denial fails after exactly one renewal');
    renewalCheck((int) $read()['published_run_id'] === $previous && $deniedSync->tick(1) === [] && Data::query('sync_run', $site)->count() === $runCount, 'failed auth preserves old publication and backs off instead of creating a run every minute');

    $setCredentials($base + ['expires_at' => time() - 1]);
    $wrongPassword = new ScheduledRenewalMock([['code' => 401, 'msg' => 'private-password-error']]);
    $result = $make($wrongPassword)->tick(1)[0];
    renewalCheck($result['status'] === 'failed' && renewalKinds($wrongPassword) === ['login'] && (int) $read()['published_run_id'] === $previous, 'incorrect password is not retried indefinitely and cannot replace existing inventory');

    $setCredentials($base + ['expires_at' => time() - 1]);
    $wrongStore = new ScheduledRenewalMock([renewalLogin('synthetic-wrong-store-token', '123456789')]);
    $result = $make($wrongStore)->tick(1)[0];
    renewalCheck($result['status'] === 'failed' && renewalKinds($wrongStore) === ['login'] && (int) $read()['published_run_id'] === $previous, 'login to another store is rejected before stock collection');
    renewalCheck($saved()['access_token'] === 'synthetic-old-token', 'cross-store credentials are not written back');

    $setCredentials(['auth_mode' => 'token', 'access_token' => 'synthetic-manual-token', 'expires_at' => time() - 1]);
    $manual = new ScheduledRenewalMock([['code' => 401]]);
    $result = $make($manual)->tick(1)[0];
    renewalCheck($result['status'] === 'failed' && renewalKinds($manual) === ['stock'] && (int) $read()['published_run_id'] === $previous, 'Token-only connection cannot invent a password or silently pretend to renew');

    // A valid new authorization must survive a later unrelated inventory network error.
    $setCredentials($base + ['expires_at' => time() - 1]);
    $network = new ScheduledRenewalMock([renewalLogin('synthetic-auth-before-network-error'), new ProviderException('http_error', 'Synthetic inventory read unavailable')]);
    $result = $make($network)->tick(1)[0];
    renewalCheck($result['status'] === 'failed' && $result['error_code'] === 'http_error' && (int) $read()['published_run_id'] === $previous, 'inventory network failure does not publish a partial generation');
    renewalCheck($saved()['access_token'] === 'synthetic-auth-before-network-error' && $saved()['password'] === $base['password'], 'verified fresh authorization persists even when the subsequent inventory request fails');
    renewalCheck(!str_contains((string) $read()['credentials_cipher'], 'synthetic-'), 'persisted credentials are ciphertext');

    // Configuration can change while login HTTP is in flight. Old-worker auth must lose.
    $setCredentials($base + ['expires_at' => time() - 1]);
    $replacement = $base;
    $replacement['password'] = 'synthetic-replacement-password';
    $replacement['access_token'] = 'synthetic-replacement-token';
    $replacementCipher = '';
    $configRace = new ScheduledRenewalMock([static function () use ($site, $connection, $replacement, &$replacementCipher): array {
        $replacementCipher = Cipher::encrypt($replacement, $site, $connection);
        Data::query('connection', $site)->where('id', $connection)->inc('config_version')->update([
            'credentials_cipher' => $replacementCipher, 'active_run_id' => 0, 'lease_token' => '', 'lease_until' => 0, 'last_sync_status' => 'idle']);
        return renewalLogin('synthetic-obsolete-worker-token');
    }]);
    $race = $make($configRace)->tick(1)[0];
    renewalCheck($race['status'] !== 'completed' && renewalKinds($configRace) === ['login'], 'concurrent configuration change stops the old worker before stock reading');
    renewalCheck($read()['credentials_cipher'] === $replacementCipher && $saved()['password'] === $replacement['password'] && $saved()['access_token'] === $replacement['access_token'], 'obsolete login cannot overwrite the newer connection password or token');
    renewalCheck((int) $read()['published_run_id'] === $previous, 'obsolete login cannot replace the last complete inventory generation');

    $audit = json_encode(Data::query('audit', $site)->select()->toArray());
    $runs = json_encode(Data::query('sync_run', $site)->select()->toArray());
    renewalCheck(!preg_match('/synthetic-(?:old-token|renewal-password|first-renewal|second-renewal|auth-before-network-error|replacement-password|replacement-token|obsolete-worker-token)|private-/', (string) $audit.(string) $runs), 'operational logs never contain credentials or raw source errors');
    echo json_encode(['passed' => true, 'checks' => $checks, 'php' => PHP_VERSION, 'scope' => 'scheduled password authentication, pagination renewal, ciphertext persistence, bounded failure and publication safety',
        'remote_calls' => 0, 'existing_business_rows_read' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL '.get_class($error).': '.$error->getMessage().' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema !== '') leadCleanup($pdo, $schema);
}
exit($failed ? 1 : 0);
