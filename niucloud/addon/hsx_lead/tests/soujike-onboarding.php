<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_lead\app\provider\{ProviderRegistry, SoujikeProvider, TransportContract, ProviderException};
use addon\hsx_lead\app\service\{Context, MerchantService, ConnectionService, SyncService};
use addon\hsx_lead\app\support\{Data, Cipher};
use core\exception\CommonException;
use think\facade\{Db, Cache};

final class SoujikeOnboardingMock implements TransportContract
{
    public array $calls = [];
    public function __construct(private array $responses) {}
    public function post(string $endpoint, #[\SensitiveParameter] array $headers, #[\SensitiveParameter] array $body): array
    {
        sjkOnCheck(!Db::connect()->getPdo()->inTransaction(), 'ERP request occurs outside a database transaction');
        $this->calls[] = compact('endpoint', 'headers', 'body');
        if (!$this->responses) throw new RuntimeException('Unexpected synthetic request');
        $next = array_shift($this->responses);
        if (is_callable($next)) $next = $next($endpoint, $headers, $body);
        if ($next instanceof Throwable) throw $next;
        if (!is_array($next)) throw new RuntimeException('Invalid synthetic response');
        return $next;
    }
}
$checks = 0; $failed = false; $pdo = null; $schema = '';
function sjkOnCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $label);
    $GLOBALS['checks']++;
}
function sjkOnDenied(callable $call, string $label): void
{
    try { $call(); } catch (CommonException|ProviderException $error) { sjkOnCheck(true, $label); return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
function sjkOnLogin(string $store = '9007199254740993', string $token = 'synthetic-sjk-token'): array
{
    return ['code' => 200, 'data' => ['tokenInfo' => ['tokenName' => 'recovery-token', 'tokenValue' => $token],
        'storeId' => $store, 'storeInfo' => ['storeName' => 'Synthetic authenticated store', 'balance' => 'private-balance'],
        'id' => 'private-account-id', 'realName' => 'private-account-name']];
}
function sjkOnStock(array $rows = []): array
{
    return ['code' => 200, 'data' => ['records' => $rows, 'current' => 1, 'size' => 100, 'total' => count($rows)]];
}
function sjkOnEndpoints(SoujikeOnboardingMock $mock, string $endpoint): int
{
    return count(array_filter($mock->calls, static fn (array $call): bool => $call['endpoint'] === $endpoint));
}

try {
    [$app, $pdo, $schema, $site] = leadBoot();
    $installedTables = Db::query('SHOW TABLES');
    $context = new Context($site, 1);
    $definition = (new ProviderRegistry())->definition('soujike');
    $metadata = $definition->metadata();
    sjkOnCheck($metadata['auth_mode'] === 'password' && !empty($metadata['auth_modes']), 'normal account/password is the metadata default while testing mode is explicitly available');
    $password = ' synthetic-sjk-password +&= 中文 ';
    $credentialInput = ['auth_mode' => 'password', 'username' => '18800000001', 'password' => $password];
    $passwordCredentials = $definition->credentials($credentialInput);
    sjkOnCheck($passwordCredentials['auth_mode'] === 'password' && $passwordCredentials['password'] === $password, 'normal password remains byte-exact and mode is retained');
    sjkOnCheck($definition->renewable($passwordCredentials), 'password connection advertises only its actual renewable capability');
    $manualCredentials = $definition->credentials(['auth_mode' => 'token', 'access_token' => 'synthetic-manual-token']);
    sjkOnCheck(!$definition->renewable($manualCredentials), 'manual fallback never claims password renewal');
    sjkOnCheck($definition->scope([], $passwordCredentials)['store_id'] === '', 'password mode can discover its source store without a required manual ID');
    sjkOnDenied(static fn () => $definition->scope([], $manualCredentials), 'manual mode must explicitly provide its source store');
    sjkOnCheck($definition->credentials(['auth_mode' => 'password', 'username' => '18800000001', 'password' => ''], $passwordCredentials)['password'] === $password, 'same password account can keep its saved encrypted password');
    sjkOnDenied(static fn () => $definition->credentials(['auth_mode' => 'password', 'username' => '18800000002', 'password' => ''], $passwordCredentials), 'different account cannot inherit original password');
    sjkOnDenied(static fn () => $definition->credentials(['auth_mode' => 'password', 'username' => '18800000001', 'password' => ''], $manualCredentials), 'token mode cannot supply password by cross-mode reuse');
    sjkOnDenied(static fn () => $definition->credentials(['auth_mode' => 'token', 'access_token' => ''], $passwordCredentials + ['access_token' => 'internal-derived-token']), 'derived password token cannot be silently reused when changing auth mode');
    sjkOnCheck($definition->identityFingerprint($passwordCredentials) !== $definition->identityFingerprint($manualCredentials), 'mode changes are part of identity switching impact');

    $merchant = (new MerchantService($context))->save(0, ['name' => 'Synthetic Soujike merchant', 'contact_name' => 'Synthetic operator',
        'mobile' => '18800000001', 'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101, 'member_id' => 101, 'status' => 1]);
    $id = (int)$merchant['id'];
    $input = ['provider' => 'soujike', 'credentials' => $credentialInput, 'scope' => []];
    $mock = new SoujikeOnboardingMock([sjkOnLogin(), sjkOnStock()]);
    $service = new ConnectionService($context, new ProviderRegistry($mock));
    $proof = $service->probe($id, $input);
    sjkOnCheck(count($mock->calls) === 2 && $mock->calls[0]['endpoint'] === SoujikeProvider::LOGIN_ENDPOINT
        && $mock->calls[1]['endpoint'] === SoujikeProvider::STOCK_ENDPOINT, 'probe performs reference login and known inventory read, no member/card/customer API');
    sjkOnCheck($mock->calls[0]['body'] === ['openId' => '', 'phoneNumber' => '18800000001', 'passwords' => $password], 'probe preserves exact normal login request');
    sjkOnCheck($proof['preview']['empty_inventory'] && $proof['preview']['can_activate'], 'authenticated store with complete empty inventory can be activated without fake records');
    sjkOnCheck($proof['preview']['identity']['store_id'] === '9007199254740993' && $proof['preview']['identity']['tenant_id'] === null,
        'source store is derived exactly and account ID is never relabelled as tenant');
    sjkOnCheck($proof['preview']['identity']['basis'] === 'authenticated_profile_and_first_page', 'proof explains actual login/store and first-page basis');
    sjkOnCheck(Data::query('connection', $site)->count() === 0 && Data::query('inventory', $site)->count() === 0, 'probe neither activates connection nor publishes inventory');
    $proofKey = 'hsx_lead.probe.' . hash('sha256', $proof['probe_token']);
    $cache = Cache::get($proofKey);
    sjkOnCheck(is_string($cache) && !str_contains($cache, $password) && !str_contains($cache, 'synthetic-sjk-token'), 'temporary proof is encrypted');
    sjkOnCheck(!preg_match('/synthetic-sjk-token|private-|synthetic-sjk-password/', json_encode($proof)), 'public preview excludes secrets and private response extras');
    $active = $service->activate($id, ['probe_token' => $proof['probe_token'], 'confirm_switch' => true]);
    sjkOnCheck($active['scope'] === ['tenant_id' => '', 'store_id' => '9007199254740993'] && $active['requires_initial_sync'], 'activation binds authenticated source with unknown tenant and requires full synchronization');
    sjkOnCheck(($active['credentials']['username'] ?? '') === '18800000001' && !array_key_exists('password', $active['credentials']) && !array_key_exists('access_token', $active['credentials'])
        && !str_contains(json_encode($active), $password) && !str_contains(json_encode($active), 'synthetic-sjk-token'), 'GET returns public account/configured flags, never password or runtime token');
    $connectionId = (int)$active['connection_id'];
    $connection = Data::row('connection', $site, $connectionId);
    $saved = Cipher::decrypt($connection['credentials_cipher'], $site, $connectionId);
    sjkOnCheck($saved['auth_mode'] === 'password' && $saved['password'] === $password && $saved['access_token'] === 'synthetic-sjk-token', 'formal mode/password/runtime authorization are connection-bound encrypted JSON, not new schema fields');

    $reuseMock = new SoujikeOnboardingMock([sjkOnLogin(), sjkOnStock()]);
    $reuseService = new ConnectionService($context, new ProviderRegistry($reuseMock));
    $reuse = $reuseService->probe($id, ['provider' => 'soujike', 'credentials' => ['auth_mode' => 'password', 'username' => '18800000001', 'password' => ''], 'scope' => []]);
    sjkOnCheck($reuseMock->calls[0]['body']['passwords'] === $password && !$reuse['impact']['switching'], 'blank same-account password is actually reverified without falsely switching source');
    $reuseService->activate($id, ['probe_token' => $reuse['probe_token'], 'confirm_switch' => true]);

    $row = ['id' => '9223372036854775808', 'storeId' => '9007199254740993', 'shelfStatus' => '1', 'shelfStatusName' => '已上架', 'saleStatus' => false, 'categoryName' => 'iPhone 13', 'capacity' => '128GB', 'color' => '白色',
        'retailPrice' => '2000.01', 'tradePrice' => '1900.00', 'imei' => 'synthetic-sjk-device', 'firstResIsVideo' => false,
        'remark' => 'private-source-note', 'cost' => 'private-cost'];
    $syncMock = new SoujikeOnboardingMock([sjkOnLogin(), sjkOnStock([$row])]);
    $sync = new SyncService(new ProviderRegistry($syncMock));
    $run = $sync->start($site, $id, 1);
    $completed = $sync->advance($site, (int)$run['run_id']);
    sjkOnCheck($completed['status'] === 'completed' && count($syncMock->calls) === 2, 'first complete generation automatically authorizes and publishes one synthetic source item');
    $published = (int)Data::row('connection', $site, $connectionId)['published_run_id'];
    $stock = Data::query('inventory', $site)->where('sync_run_id', $published)->find();
    sjkOnCheck($stock['source_id'] === '9223372036854775808' && $stock['source_store_id'] === '9007199254740993' && $stock['tenant_id'] === '', 'inventory source keys preserve IDs and unknown tenant');
    sjkOnCheck($stock['price'] === '2000.01' && $stock['trade_price'] === '1900.00' && !str_contains($stock['snapshot_json'], 'private-'), 'published prices are exact and private source data is not stored');

    $before = Data::row('connection', $site, $connectionId);
    $wrongScope = new SoujikeOnboardingMock([sjkOnLogin()]);
    sjkOnDenied(static fn () => (new ConnectionService($context, new ProviderRegistry($wrongScope)))->probe($id, array_replace($input, ['scope' => ['store_id' => 'another-store']])), 'manually wrong source store cannot replace authenticated store');
    sjkOnCheck(count($wrongScope->calls) === 1 && $before === Data::row('connection', $site, $connectionId), 'scope mismatch never requests inventory or changes old publication');
    $wrongAccount = new SoujikeOnboardingMock([]);
    sjkOnDenied(static fn () => (new ConnectionService($context, new ProviderRegistry($wrongAccount)))->probe($id, ['provider' => 'soujike',
        'credentials' => ['auth_mode' => 'password', 'username' => '18800000002', 'password' => ''], 'scope' => []]), 'account switch cannot borrow saved password');
    sjkOnCheck($wrongAccount->calls === [] && $before === Data::row('connection', $site, $connectionId), 'local authorization rejection leaves published data untouched');
    $failedLogin = new SoujikeOnboardingMock([['code' => 401, 'msg' => 'private-password-error']]);
    sjkOnDenied(static fn () => (new ConnectionService($context, new ProviderRegistry($failedLogin)))->probe($id, $input), 'normal login failure remains an explicit failure, not old-token fallback');
    sjkOnCheck(count($failedLogin->calls) === 1 && $before === Data::row('connection', $site, $connectionId), 'failed probe does not overwrite live credentials or complete snapshot');

    $changedStore = new SoujikeOnboardingMock([sjkOnLogin('different-store')]);
    $changedSync = new SyncService(new ProviderRegistry($changedStore));
    $changedRun = $changedSync->start($site, $id, 1);
    $failedRun = $changedSync->advance($site, (int)$changedRun['run_id']);
    sjkOnCheck($failedRun['status'] === 'failed' && count($changedStore->calls) === 1, 'login-derived store changes stop a scheduled read before stock request');
    sjkOnCheck((int)Data::row('connection', $site, $connectionId)['published_run_id'] === $published, 'changed store failure preserves the previous complete generation');

    $refreshMock = null;
    $response = static function (string $endpoint) use (&$refreshMock): array {
        if ($endpoint === SoujikeProvider::STOCK_ENDPOINT) return ['code' => 401, 'msg' => 'private-expired-token'];
        $previous = $refreshMock->calls[count($refreshMock->calls) - 2]['endpoint'] ?? '';
        return sjkOnLogin($previous === SoujikeProvider::STOCK_ENDPOINT ? 'different-store' : '9007199254740993', 'synthetic-refresh-token');
    };
    $refreshMock = new SoujikeOnboardingMock([$response, $response, $response]);
    $refreshSync = new SyncService(new ProviderRegistry($refreshMock));
    $refreshRun = $refreshSync->start($site, $id, 1);
    $refreshFailed = $refreshSync->advance($site, (int)$refreshRun['run_id']);
    sjkOnCheck($refreshFailed['status'] === 'failed' && sjkOnEndpoints($refreshMock, SoujikeProvider::STOCK_ENDPOINT) === 1
        && end($refreshMock->calls)['endpoint'] === SoujikeProvider::LOGIN_ENDPOINT, '401 renewal validates the new authenticated store before any retry stock read');
    sjkOnCheck(sjkOnEndpoints($refreshMock, SoujikeProvider::LOGIN_ENDPOINT) <= 2 && (int)Data::row('connection', $site, $connectionId)['published_run_id'] === $published, 'renewal is bounded and changed-store failure keeps old publication');

    $renewMock = null;
    $renewResponse = static function (string $endpoint) use (&$renewMock, $row): array {
        if ($endpoint === SoujikeProvider::LOGIN_ENDPOINT) return sjkOnLogin('9007199254740993', 'synthetic-restored-auth');
        return sjkOnEndpoints($renewMock, SoujikeProvider::STOCK_ENDPOINT) === 1 ? ['code' => 401, 'msg' => 'private-expired-token'] : sjkOnStock([$row]);
    };
    $renewMock = new SoujikeOnboardingMock([$renewResponse, $renewResponse, $renewResponse, $renewResponse]);
    $renewSync = new SyncService(new ProviderRegistry($renewMock));
    $renewRun = $renewSync->start($site, $id, 1);
    $renewed = $renewSync->advance($site, (int)$renewRun['run_id']);
    sjkOnCheck($renewed['status'] === 'completed' && sjkOnEndpoints($renewMock, SoujikeProvider::STOCK_ENDPOINT) === 2
        && sjkOnEndpoints($renewMock, SoujikeProvider::LOGIN_ENDPOINT) <= 2, 'same-store 401 refresh performs one bounded retry and can publish a complete result');
    sjkOnCheck(end($renewMock->calls)['headers'] === ['recovery-token' => 'synthetic-restored-auth'], 'retry stock request uses newly authorized token, never the expired token');
    $published = (int)Data::row('connection', $site, $connectionId)['published_run_id'];
    sjkOnCheck($published === (int)$renewRun['run_id'], 'only successful complete renewed generation becomes the published pointer');

    $beforeManualEmpty = Data::row('connection', $site, $connectionId);
    $manualEmptyMock = new SoujikeOnboardingMock([sjkOnStock()]);
    $manualEmptyService = new ConnectionService($context, new ProviderRegistry($manualEmptyMock));
    $manualEmpty = $manualEmptyService->probe($id, ['provider' => 'soujike', 'credentials' => ['auth_mode' => 'token', 'access_token' => 'synthetic-manual-token'], 'scope' => ['store_id' => '9007199254740993']]);
    sjkOnCheck($manualEmpty['preview']['empty_inventory'] && !$manualEmpty['preview']['can_activate'], 'manual token plus empty page is not upgraded into authenticated store proof');
    sjkOnDenied(static fn () => $manualEmptyService->activate($id, ['probe_token' => $manualEmpty['probe_token'], 'confirm_switch' => true]), 'empty unverifiable token cannot replace existing password connection');
    sjkOnCheck($beforeManualEmpty === Data::row('connection', $site, $connectionId), 'empty-token probe and rejection preserve actual connection and publication');

    $manualMock = new SoujikeOnboardingMock([sjkOnStock([$row])]);
    $manualService = new ConnectionService($context, new ProviderRegistry($manualMock));
    $manualProof = $manualService->probe($id, ['provider' => 'soujike', 'credentials' => ['auth_mode' => 'token', 'access_token' => 'synthetic-manual-token'], 'scope' => ['store_id' => '9007199254740993']]);
    sjkOnCheck(count($manualMock->calls) === 1 && $manualMock->calls[0]['endpoint'] === SoujikeProvider::STOCK_ENDPOINT, 'current token test path continues without password request');
    sjkOnCheck($manualProof['impact']['switching'], 'explicit auth-mode switch warns that old source snapshot will leave matching');
    $manualActive = $manualService->activate($id, ['probe_token' => $manualProof['probe_token'], 'confirm_switch' => true]);
    sjkOnCheck($manualActive['published_run_id'] === 0 && Data::query('inventory', $site)->where('sync_run_id', $published)->count() === 1, 'confirmed mode switch hides old publication without deleting history');
    $manualSaved = Cipher::decrypt(Data::row('connection', $site, $connectionId)['credentials_cipher'], $site, $connectionId);
    sjkOnCheck($manualSaved['auth_mode'] === 'token' && !isset($manualSaved['password']) && !isset($manualSaved['username']), 'manual mode drops old password/account instead of keeping hidden credential baggage');
    $manualSaved['expires_at'] = time() - 1;
    Data::query('connection', $site)->where('id', $connectionId)->update(['credentials_cipher' => Cipher::encrypt($manualSaved, $site, $connectionId)]);
    sjkOnCheck($manualService->get($id)['authorization_attention'], 'known expiry of manual token still requires human attention');
    $manualFailMock = new SoujikeOnboardingMock([['code' => 401, 'msg' => 'private-token-expired']]);
    $manualSync = new SyncService(new ProviderRegistry($manualFailMock));
    $manualRun = $manualSync->start($site, $id, 1);
    $manualFailed = $manualSync->advance($site, (int)$manualRun['run_id']);
    sjkOnCheck($manualFailed['status'] === 'failed' && count($manualFailMock->calls) === 1
        && $manualFailMock->calls[0]['endpoint'] === SoujikeProvider::STOCK_ENDPOINT, 'expired token never loops login or turns into password mode');

    $audit = json_encode(Data::query('audit', $site)->select()->toArray());
    sjkOnCheck(!preg_match('/synthetic-sjk-password|synthetic-sjk-token|synthetic-refresh-token|synthetic-restored-auth|synthetic-manual-token|private-/', $audit), 'audits contain only safe operational facts, not credentials or provider bodies');
    sjkOnCheck(!preg_match('/synthetic-sjk-password|synthetic-sjk-token|synthetic-refresh-token|synthetic-restored-auth|synthetic-manual-token|private-/', json_encode($manualService->get($id))), 'final connection view does not disclose current or earlier secrets');
    sjkOnCheck(Db::query('SHOW TABLES') === $installedTables, 'normal password authorization adds no table beyond the actual installed schema');
    leadAssertIsolated($schema);
    echo json_encode(['passed' => true, 'checks' => $checks, 'php' => PHP_VERSION,
        'scope' => 'Soujike password/token onboarding, encrypted mode, authenticated-store binding, renewal and publication preservation',
        'database' => 'owned random localhost schema', 'remote_calls' => 0, 'existing_business_rows_read' => 0]), PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    $message = $error instanceof PDOException ? 'isolated MySQL failure SQLSTATE ' . $error->getCode() : $error->getMessage();
    fwrite(STDERR, 'FAIL ' . get_class($error) . ': ' . $message . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema !== '') {
        try { leadCleanup($pdo, $schema); } catch (Throwable $error) { $failed = true; fwrite(STDERR, "FAIL owned random schema cleanup requires attention\n"); }
    }
}
exit($failed ? 1 : 0);
