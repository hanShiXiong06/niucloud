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
use addon\hsx_lead\app\auth\CaptchaRecognizerContract;
use addon\hsx_lead\app\service\{ProviderChannelService,Context,ConnectionService,AuthChallengeService,AutomaticAuthService,CaptchaConfigService,SyncService};
use addon\hsx_lead\app\service\cooperation\PartnerService;
use addon\hsx_lead\app\support\{Cipher,Data};
use core\exception\CommonException;
use think\facade\Db;

final class ChannelFixtureTransport implements TransportContract
{
    public array $calls = [];
    public function __construct(private array $responses = [])
    {
    }
    public function post(string $endpoint, #[\SensitiveParameter] array $headers, #[\SensitiveParameter] array $body): array
    {
        channelCheck(!Db::connect()->getPdo()->inTransaction(), 'mock ERP HTTP is outside database transactions');
        $this->calls[] = ['endpoint' => $endpoint, 'headers' => $headers, 'body' => $body];
        if (!$this->responses) throw new RuntimeException('Unexpected synthetic ERP request');
        $response = array_shift($this->responses);
        if (is_callable($response)) $response = $response($endpoint, $headers, $body);
        if ($response instanceof Throwable) throw $response;
        if (!is_array($response)) throw new RuntimeException('Invalid synthetic ERP response');
        return $response;
    }
}
final class ChannelNoRecognition implements CaptchaRecognizerContract
{
    public array $calls = [];
    public function create(string $apiKey, string $dataUri, int $length = 4): string
    {
        $this->calls[] = 'create';
        throw new RuntimeException('Disabled channel must not create recognition');
    }
    public function result(string $apiKey, string $taskId, int $length = 4): array
    {
        $this->calls[] = 'result';
        throw new RuntimeException('Disabled channel must not query recognition');
    }
    public function balance(string $apiKey): string
    {
        $this->calls[] = 'balance';
        throw new RuntimeException('No recognition balance query');
    }
}

$checks = 0;
$failed = false;
$pdo = null;
$schema = '';
function channelCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: '.$label);
    $GLOBALS['checks']++;
}
function channelDenied(callable $action, string $label): void
{
    try {
        $action();
    } catch (CommonException|ProviderException $error) {
        channelCheck(true, $label);
        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
function channelBlocked(callable $action, string $label): void
{
    try {
        $action();
    } catch (ProviderException $error) {
        channelCheck($error->reason() === 'channel_disabled', $label);
        return;
    } catch (CommonException $error) {
        channelCheck((bool) preg_match('/(?:渠道|ERP).*关闭/u', $error->getMessage()), $label);
        return;
    }
    throw new RuntimeException('Expected channel closure: '.$label);
}
function channelSet(ProviderChannelService $service, Context $context, array $overrides): array
{
    $config = $service->configuration();
    $items = array_map(static fn (array $item): array => ['key' => $item['key'], 'enabled' => $overrides[$item['key']] ?? $item['enabled']], $config['items']);
    return $service->save($context, ['revision' => $config['revision'], 'items' => $items]);
}
function channelLogin(string $token = 'synthetic-channel-fresh-token'): array
{
    return ['code' => 200, 'data' => ['tokenInfo' => ['tokenName' => 'recovery-token', 'tokenValue' => $token],
        'storeId' => '9007199254740993', 'storeInfo' => ['storeName' => 'Synthetic source store']]];
}
function channelRow(int $id): array
{
    return ['id' => (string) $id, 'storeId' => '9007199254740993', 'categoryName' => 'Synthetic iPhone',
        'retailPrice' => '2000.00', 'tradePrice' => '1900.00', 'saleStatus' => false, 'shelfStatus' => '1', 'shelfStatusName' => '已上架'];
}
function channelPage(int $page, array $rows, int $total): array
{
    return ['code' => 200, 'data' => ['records' => $rows, 'current' => $page, 'size' => 100, 'total' => $total, 'pages' => (int) ceil($total / 100)]];
}

try {
    [$app,$pdo,$schema,$site] = leadBoot();
    leadAssertIsolated($schema);
    $context = new Context($site, 1);
    $service = new ProviderChannelService($site);
    $other = new ProviderChannelService($site + 1);
    $initial = $service->configuration();
    $keys = array_column($initial['items'], 'key');
    $supportedCount = count(ProviderRegistry::supported());
    channelCheck(count($keys) === $supportedCount && count(array_filter($initial['items'], static fn (array $row): bool => $row['enabled'] === true)) === $supportedCount, 'unconfigured site preserves its supported channels without writing a policy');
    channelCheck(Data::query('connection', $site)->count() === 0 && Db::name('sys_config')->where('site_id', $site)->where('config_key', ProviderChannelService::KEY)->count() === 0, 'reading defaults does not create business data or silently configure channels');
    channelCheck(count($service->available()) === $supportedCount, 'public available catalog initially includes all supported adapters');
    channelCheck(is_string($initial['revision']) && $initial['revision'] !== '' && !str_contains(json_encode($initial), 'credentials_cipher'), 'configuration has an optimistic revision and no credential ciphertext');
    $allOff = array_fill_keys($keys, false);
    $allOn = array_fill_keys($keys, true);

    $profile = ['name' => 'Synthetic channel merchant', 'contact_name' => 'Synthetic operator', 'mobile' => '18800000001',
        'province_id' => 910000, 'city_id' => 910100, 'district_id' => 910101, 'address' => 'Synthetic road', 'provider' => 'wanji'];
    $owner = new PartnerService(new Context($site, 0, 101));
    $merchant = $owner->saveProfile($profile);
    $merchantId = (int) $merchant['id'];
    channelDenied(static fn () => $service->save(new Context($site, 0, 101), ['revision' => $initial['revision'], 'items' => []]), 'merchant identity cannot modify site channel policy');
    Db::name('sys_user')->insert(['uid' => 2, 'username' => 'channel-no-grant', 'real_name' => 'Synthetic unprivileged operator', 'status' => 1]);
    Db::name('sys_user_role')->insert(['uid' => 2, 'site_id' => $site, 'status' => 1, 'is_admin' => 0]);
    channelDenied(static fn () => $service->save(new Context($site, 2), ['revision' => $initial['revision'], 'items' => []]), 'ordinary operator without business-configuration permission cannot save policy');
    $invalid = ['revision' => $initial['revision'], 'items' => array_map(static fn (array $item): array => ['key' => $item['key'], 'enabled' => $item['enabled']], $initial['items'])];
    $missing = $invalid;
    array_pop($missing['items']);
    channelDenied(static fn () => $service->save($context, $missing), 'partial lists cannot accidentally reset omitted channels');
    $unknown = $invalid;
    $unknown['items'][0]['key'] = 'unsupported_erp';
    channelDenied(static fn () => $service->save($context, $unknown), 'unknown adapter cannot be configured');
    $duplicate = $invalid;
    $duplicate['items'][1]['key'] = $duplicate['items'][0]['key'];
    channelDenied(static fn () => $service->save($context, $duplicate), 'duplicate adapter keys are rejected');
    $coerced = $invalid;
    $coerced['items'][0]['enabled'] = 'false';
    channelDenied(static fn () => $service->save($context, $coerced), 'string false cannot become an enabled channel through PHP truthiness');
    $off = channelSet($service, $context, $allOff);
    channelCheck($service->available() === [] && count(array_filter($off['items'], static fn (array $row): bool => $row['enabled'])) === 0, 'administrator may explicitly close every channel');
    channelCheck(count($other->available()) === $supportedCount, 'channel policy is isolated from the other site');
    channelDenied(static fn () => $service->save($context, $invalid), 'stale settings revision cannot overwrite another administrator update');
    channelBlocked(static fn () => $service->assertEnabled('wanji'), 'closed channel cannot be used through normal admission');

    $edited = $owner->saveProfile(array_replace($profile, ['contact_name' => 'Updated synthetic contact']));
    channelCheck((int) $edited['id'] === $merchantId && Data::row('merchant', $site, $merchantId)['contact_name'] === 'Updated synthetic contact', 'existing merchant can retain a closed ERP while correcting ordinary profile details');
    // Older bound shops may have a formal connection but no remembered profile selection.
    Db::name('hsx_lead_partner')->where('site_id', $site)->where('merchant_id', $merchantId)->update(['provider_requested' => '']);
    Data::query('connection', $site)->insert(['site_id' => $site, 'merchant_id' => $merchantId, 'provider' => 'wanji', 'enabled' => 0,
        'store_id' => 'synthetic-legacy-store', 'sync_interval_minutes' => 60, 'config_version' => 1, 'next_sync_at' => time() + 3600, 'create_time' => time(), 'update_time' => time()]);
    $legacyEdited = $owner->saveProfile(array_replace($profile, ['contact_name' => 'Updated legacy contact']));
    channelCheck((int) $legacyEdited['id'] === $merchantId && Data::row('merchant', $site, $merchantId)['contact_name'] === 'Updated legacy contact', 'legacy shop with empty requested provider may preserve its actual closed connection while editing contacts');
    channelDenied(static fn () => (new PartnerService(new Context($site, 0, 102)))->saveProfile(array_replace($profile, ['mobile' => '18800000002'])), 'new merchant cannot select a closed ERP');
    channelDenied(static fn () => $owner->saveProfile(array_replace($profile, ['provider' => 'soujike'])), 'existing merchant cannot switch to a different closed ERP');
    channelCheck($owner->bootstrap()['providers'] === [], 'merchant bootstrap does not expose closed login choices');
    $blockedTransport = new ChannelFixtureTransport();
    $registry = new ProviderRegistry($blockedTransport);
    $connections = new ConnectionService($context, $registry);
    channelBlocked(static fn () => $connections->probe($merchantId, ['provider' => 'wanji', 'credentials' => ['username' => 'synthetic-channel-account', 'password' => 'synthetic-channel-password'], 'scope' => []]), 'closed normal login is rejected before an ERP request');
    channelBlocked(static fn () => $connections->probe($merchantId, ['provider' => 'wanjiguanjia', 'credentials' => ['username' => 'synthetic-channel-account', 'password' => 'synthetic-channel-password'], 'scope' => []]), 'provider alias cannot bypass a closed canonical channel');
    channelBlocked(static fn () => (new AuthChallengeService($context, $registry))->begin($merchantId, ['provider' => 'aiguanji', 'credentials' => ['username' => '18800000001']]), 'closed CAPTCHA channel cannot begin a challenge');
    channelBlocked(static fn () => (new AuthChallengeService($context, $registry))->authorize($merchantId, 'aiguanji', ['username' => '18800000001'], ['challenge_token' => str_repeat('b', 64), 'password' => 'synthetic-channel-password', 'code' => 'Ab12']), 'closed CAPTCHA channel cannot continue an authorization answer');
    channelBlocked(static fn () => (new AutomaticAuthService($context, $registry, new ChannelNoRecognition()))->start($merchantId, ['provider' => 'aiguanji', 'credentials' => ['username' => '18800000001'], 'scope' => [], 'password' => 'synthetic-channel-password', 'request_id' => str_repeat('a', 32)]), 'closed automatic CAPTCHA channel cannot create a recognition flow');
    channelCheck($blockedTransport->calls === [], 'all disabled authorization entry points made zero mock ERP requests');
    $recognizer = new ChannelNoRecognition();
    $captchaConfig = new CaptchaConfigService($context, $recognizer);
    $captchaConfig->save(['api_key' => str_repeat('c', 32), 'enabled' => true, 'consent' => true, 'daily_limit' => 20]);
    channelSet($service, $context, ['aiguanji' => true]);
    $automatic = new AutomaticAuthService($context, $registry, $recognizer, $captchaConfig);
    $automaticState = $automatic->start($merchantId, ['provider' => 'aiguanji', 'credentials' => ['username' => '18800000001'], 'scope' => [], 'password' => 'synthetic-channel-password', 'request_id' => str_repeat('d', 32)]);
    channelCheck($automaticState['status'] === 'processing', 'open CAPTCHA channel can create its local task without an ERP or paid request');
    channelSet($service, $context, ['aiguanji' => false]);
    $stopped = $automatic->advance($merchantId, $automaticState['task_token']);
    channelCheck($stopped['status'] === 'cancelled' && str_contains($stopped['message'], '渠道已关闭'), 'closing a channel stops an existing pending automatic-login task');
    channelCheck($blockedTransport->calls === [] && $recognizer->calls === [], 'closed pending automatic login does not read CAPTCHA or submit/query paid recognition');

    // A preview created while open must not activate after the administrator closes it.
    channelSet($service, $context, ['soujike' => true]);
    $previewTransport = new ChannelFixtureTransport([channelLogin(), channelPage(1, [], 0)]);
    $previewService = new ConnectionService($context, new ProviderRegistry($previewTransport));
    $proof = $previewService->probe($merchantId, ['provider' => 'soujike', 'credentials' => ['auth_mode' => 'password', 'username' => '18800000001', 'password' => 'synthetic-channel-password'], 'scope' => []]);
    $beforeConnections = Data::query('connection', $site)->select()->toArray();
    channelSet($service, $context, ['soujike' => false]);
    channelDenied(static fn () => $previewService->activate($merchantId, ['probe_token' => $proof['probe_token'], 'confirm_switch' => true]), 'closing a channel invalidates activation of an already-cached preview');
    channelCheck(Data::query('connection', $site)->select()->toArray() === $beforeConnections, 'rejected activation does not create or overwrite a connection');
    channelSet($service, $context, ['soujike' => true]);
    $lateProof = null;
    $lateTransport = new ChannelFixtureTransport([channelLogin(), static function () use ($service, $context): array {
        channelSet($service, $context, ['soujike' => false]);
        return channelPage(1, [], 0);
    }]);
    $lateService = new ConnectionService($context, new ProviderRegistry($lateTransport));
    channelBlocked(static function () use ($lateService, $merchantId, &$lateProof): void {$lateProof = $lateService->probe($merchantId, ['provider' => 'soujike', 'credentials' => ['auth_mode' => 'password', 'username' => '18800000001', 'password' => 'synthetic-channel-password'], 'scope' => []]);}, 'channel closing inside preview HTTP prevents a successful preview proof from being returned');
    channelCheck($lateProof === null && count($lateTransport->calls) === 2 && Data::query('connection', $site)->select()->toArray() === $beforeConnections, 'in-flight closed preview returns no usable proof and does not mutate connection state');

    $makeConnection = static function (string $provider = 'soujike', int $enabled = 1, int $due = 0) use ($site): array {
        $now = time();
        $m = (int) Data::query('merchant', $site)->insertGetId(['site_id' => $site, 'name' => 'Synthetic sync shop', 'status' => 1, 'create_time' => $now, 'update_time' => $now]);
        $id = (int) Data::query('connection', $site)->insertGetId(['site_id' => $site, 'merchant_id' => $m, 'provider' => $provider, 'store_id' => '9007199254740993',
            'enabled' => $enabled, 'sync_interval_minutes' => 60, 'config_version' => 1, 'next_sync_at' => $due ?: $now - 1, 'create_time' => $now, 'update_time' => $now]);
        $credentials = ['auth_mode' => 'password', 'username' => '18800000001', 'password' => 'synthetic-channel-password', 'access_token' => 'synthetic-channel-old-token', 'expires_at' => $now + 3600];
        Data::query('connection', $site)->where('id', $id)->update(['credentials_cipher' => Cipher::encrypt($credentials, $site, $id)]);
        return [$m, $id, $credentials];
    };
    channelSet($service, $context, ['soujike' => true]);
    [$syncMerchant,$connection,$credentials] = $makeConnection();
    $read = static fn (): array => Data::row('connection', $site, $connection);
    $saved = static fn (): array => Cipher::decrypt((string) Data::row('connection', $site, $connection)['credentials_cipher'], $site, $connection);
    $sync = static fn (ChannelFixtureTransport $transport): SyncService => new SyncService(new ProviderRegistry($transport));
    $baseline = $sync(new ChannelFixtureTransport([channelPage(1, [channelRow(1)], 1)]))->tick(1)[0];
    channelCheck($baseline['status'] === 'completed', 'enabled channel completes a scheduled baseline generation');
    $published = (int) $baseline['id'];

    // A paused generation must retain its page cursor and resume, not create a new run.
    $stagedTransport = new ChannelFixtureTransport([channelPage(1, array_map('channelRow', range(1001, 1100)), 101)]);
    $stagedSync = $sync($stagedTransport);
    $run = $stagedSync->start($site, $syncMerchant, 1);
    $partial = $stagedSync->advance($site, (int) $run['id']);
    channelCheck($partial['status'] === 'running' && (int) $partial['page_count'] === 1, 'manual request stages a real incomplete generation');
    $rawBefore = Data::row('sync_run', $site, (int) $run['id']);
    $connectionBefore = $read();
    channelSet($service, $context, ['soujike' => false]);
    $closedSync = $sync(new ChannelFixtureTransport());
    $paused = $closedSync->detail($site, (int) $run['id']);
    channelCheck($paused['status'] === 'paused' && $paused['channel_enabled'] === false, 'paused API state clearly differs from completed, failed and running');
    channelCheck($closedSync->advance($site, (int) $run['id'])['status'] === 'paused' && $closedSync->tick(1) === [], 'closed channel does not advance manually or through the scheduler');
    channelCheck(Data::row('sync_run', $site, (int) $run['id']) === $rawBefore, 'pausing leaves the pending cursor and staged record counts intact');
    channelCheck($read()['credentials_cipher'] === $connectionBefore['credentials_cipher'] && (int) $read()['enabled'] === 1 && (int) $read()['published_run_id'] === $published, 'closing neither erases credentials nor flips merchant automatic-sync preference or publication');
    channelBlocked(static fn () => $closedSync->start($site, $syncMerchant, 1), 'closed channel cannot start another manual task');
    $history = (new ConnectionService($context))->get($syncMerchant);
    channelCheck($history['connected'] && $history['channel_enabled'] === false && $history['auth_renewal']['ready'], 'connection history remains readable and password capability is not falsely erased by policy pause');
    $impact = array_values(array_filter($service->configuration()['items'], static fn (array $item): bool => $item['key'] === 'soujike'))[0];
    channelCheck($impact['connection_count'] === 1 && $impact['automatic_sync_count'] === 1, 'configuration reports the exact existing and opted-in connection impact without modifying either');
    $settingsService = new ConnectionService($context);
    $retained = $settingsService->syncSettings($syncMerchant, ['enabled' => true, 'interval_minutes' => 90]);
    channelCheck($retained['enabled'] === true && $retained['interval_minutes'] === 90 && $retained['channel_enabled'] === false && (int) $read()['active_run_id'] === (int) $run['id'], 'closed channel allows a merchant to retain an existing enabled preference while changing only the interval');
    $settingsService->syncSettings($syncMerchant, ['enabled' => true, 'interval_minutes' => 60]);
    channelSet($service, $context, ['soujike' => true]);
    $resumed = $sync(new ChannelFixtureTransport([channelPage(2, [channelRow(1101)], 101)]))->tick(1)[0];
    channelCheck($resumed['status'] === 'completed' && (int) $resumed['id'] === (int) $run['id'] && (int) $resumed['record_count'] === 101, 'reopening resumes the same page-two generation and publishes it once');
    channelCheck((int) Data::query('inventory', $site)->where('sync_run_id', $resumed['id'])->count() === 101, 'resume does not duplicate staged inventory');
    $published = (int) $resumed['id'];

    // Reopening is not permission to revive a merchant-disabled automatic connection.
    Data::query('connection', $site)->where('id', $connection)->update(['enabled' => 0, 'next_sync_at' => time() - 1]);
    $manualRun = $sync(new ChannelFixtureTransport())->start($site, $syncMerchant, 1);
    channelSet($service, $context, ['soujike' => false]);
    channelBlocked(static fn () => $settingsService->syncSettings($syncMerchant, ['enabled' => true, 'interval_minutes' => 60]), 'closed channel does not permit a merchant-disabled automatic connection to be switched on');
    channelSet($service, $context, ['soujike' => true]);
    channelCheck($sync(new ChannelFixtureTransport())->tick(1) === [] && (int) $read()['active_run_id'] === (int) $manualRun['id'], 'reopening does not automatically advance an active manual task on merchant-disabled sync');
    $finished = $sync(new ChannelFixtureTransport([channelPage(1, [], 0)]))->advance($site, (int) $manualRun['id']);
    channelCheck($finished['status'] === 'completed' && (int) $read()['enabled'] === 0, 'merchant-disabled automatic sync still permits an explicit manual continue while channel is open');
    $published = (int) $finished['id'];

    // The scheduler must filter closed adapters before LIMIT, not starve open merchants.
    for ($index = 0;$index < 7;$index++) $makeConnection('wanji', 1, 1);
    Data::query('connection', $site)->where('id', $connection)->update(['enabled' => 1, 'next_sync_at' => time() - 1]);
    $starve = $sync(new ChannelFixtureTransport([channelPage(1, [], 0)]))->tick(1);
    channelCheck(count($starve) === 1 && (int) $starve[0]['merchant_id'] === $syncMerchant && $starve[0]['status'] === 'completed', 'seven earlier closed connections cannot consume the first scheduler slot');
    $published = (int) $starve[0]['id'];
    channelCheck(Data::query('connection', $site)->where('provider', 'wanji')->where('last_sync_at', '>', 0)->count() === 0, 'closed channels were not touched merely to reach an open connection');

    // Policy can change while the HTTP response is in flight. It wins over publication.
    $duringStock = new ChannelFixtureTransport([static function () use ($service, $context): array {
        channelSet($service, $context, ['soujike' => false]);
        return channelPage(1, [channelRow(3001)], 1);
    }]);
    $stockSync = $sync($duringStock);
    $stockRun = $stockSync->start($site, $syncMerchant, 1);
    $beforeCipher = $read()['credentials_cipher'];
    $response = $stockSync->advance($site, (int) $stockRun['id']);
    channelCheck($response['status'] === 'paused' && (int) $read()['active_run_id'] === (int) $stockRun['id'], 'channel closing during stock HTTP leaves a paused resumable task');
    channelCheck((int) Data::row('sync_run', $site, (int) $stockRun['id'])['page_count'] === 0 && Data::query('inventory', $site)->where('sync_run_id', $stockRun['id'])->count() === 0 && (int) $read()['published_run_id'] === $published, 'in-flight response cannot stage or publish inventory after channel closure');
    channelCheck($read()['lease_token'] === '' && (int) $read()['lease_until'] === 0 && $read()['credentials_cipher'] === $beforeCipher, 'paused in-flight request releases only its lease without rewriting stored authorization');

    // The same guard applies to token renewal, not just stock publication.
    channelSet($service, $context, ['soujike' => true]);
    $expired = $credentials;
    $expired['expires_at'] = time() - 1;
    Data::query('connection', $site)->where('id', $connection)->update(['credentials_cipher' => Cipher::encrypt($expired, $site, $connection)]);
    $duringLogin = new ChannelFixtureTransport([static function () use ($service, $context): array {
        channelSet($service, $context, ['soujike' => false]);
        return channelLogin('synthetic-channel-uncommitted-token');
    }]);
    $loginResult = $sync($duringLogin)->advance($site, (int) $stockRun['id']);
    channelCheck($loginResult['status'] === 'paused' && count($duringLogin->calls) === 1, 'channel closing during login stops before inventory is requested');
    channelCheck($saved()['access_token'] === 'synthetic-channel-old-token' && (int) $read()['published_run_id'] === $published, 'in-flight login cannot save a token after closure');

    channelSet($service, $context, ['soujike' => true]);
    $duringFailure = new ChannelFixtureTransport([channelLogin(), static function () use ($service, $context): array {
        channelSet($service, $context, ['soujike' => false]);
        throw new ProviderException('http_error', 'Synthetic source error');
    }]);
    $failure = $sync($duringFailure)->advance($site, (int) $stockRun['id']);
    channelCheck($failure['status'] === 'paused' && Data::row('sync_run', $site, (int) $stockRun['id'])['status'] !== 'failed' && (int) $read()['active_run_id'] === (int) $stockRun['id'], 'policy closing during a failing HTTP request is paused, not misreported as a broken account');

    channelSet($service, $context, ['soujike' => true]);
    $final = $sync(new ChannelFixtureTransport([channelLogin(), channelPage(1, [channelRow(3001)], 1)]))->tick(1)[0];
    channelCheck($final['status'] === 'completed' && (int) $final['id'] === (int) $stockRun['id'], 'reopening resumes and completes the paused task rather than abandoning its history');
    $published = (int) $final['id'];

    // A much older staged first page cannot become fresh merely by resuming its last page.
    Data::query('connection', $site)->where('id', $connection)->update(['credentials_cipher' => Cipher::encrypt($credentials, $site, $connection), 'sync_interval_minutes' => 60]);
    $oldSync = $sync(new ChannelFixtureTransport([channelPage(1, array_map('channelRow', range(5001, 5100)), 101)]));
    $oldRun = $oldSync->start($site, $syncMerchant, 1);
    $oldSync->advance($site, (int) $oldRun['id']);
    channelCheck((int) $read()['published_run_id'] === $published, 'staging a later generation still preserves the last complete publication');
    channelSet($service, $context, ['soujike' => false]);
    Data::query('sync_run', $site)->where('id', $oldRun['id'])->update(['started_at' => time() - 121 * 60]);
    channelCheck($oldSync->detail($site, (int) $oldRun['id'])['status'] === 'paused' && (int) $read()['published_run_id'] === $published, 'long policy pause never refreshes or publishes its old staged inventory');
    channelSet($service, $context, ['soujike' => true]);
    $fresh = $sync(new ChannelFixtureTransport([channelPage(1, [channelRow(9001)], 1)]))->tick(1)[0];
    $oldHistory = Data::row('sync_run', $site, (int) $oldRun['id']);
    channelCheck($fresh['status'] === 'completed' && (int) $fresh['id'] !== (int) $oldRun['id'] && $oldHistory['status'] === 'cancelled' && $oldHistory['error_code'] === 'generation_expired', 'scheduled resume cancels an expired staged generation and starts a full new one');
    channelCheck(Data::query('inventory', $site)->where('sync_run_id', $oldRun['id'])->count() === 100, 'expired staged pages remain as historical evidence rather than being deleted');
    channelCheck((int) $read()['published_run_id'] === (int) $fresh['id'] && (int) $fresh['record_count'] === 1 && Data::query('inventory', $site)->where('sync_run_id', $fresh['id'])->column('source_id') === ['9001'], 'new publication contains only freshly read records, never the old staged first page');
    $published = (int) $fresh['id'];

    // Explicitly advancing an expired task cancels it without any ERP request.
    $expiredSync = $sync(new ChannelFixtureTransport([channelPage(1, array_map('channelRow', range(6001, 6100)), 101)]));
    $expiredRun = $expiredSync->start($site, $syncMerchant, 1);
    $expiredSync->advance($site, (int) $expiredRun['id']);
    Data::query('sync_run', $site)->where('id', $expiredRun['id'])->update(['started_at' => time() - 121 * 60]);
    $noOldRead = new ChannelFixtureTransport();
    $expiredResult = $sync($noOldRead)->advance($site, (int) $expiredRun['id']);
    channelCheck($expiredResult['status'] === 'cancelled' && $expiredResult['error_code'] === 'generation_expired' && $noOldRead->calls === [], 'manual advance of expired staged inventory stops before issuing another page request');
    channelCheck((int) $read()['published_run_id'] === $published && (int) $read()['active_run_id'] === 0 && Data::query('inventory', $site)->where('sync_run_id', $expiredRun['id'])->count() === 100, 'expired manual advance retains prior publication and old staged evidence while releasing the obsolete task');

    // Calling start directly also replaces an old active generation, not just scheduler tick.
    $directSync = $sync(new ChannelFixtureTransport([channelPage(1, array_map('channelRow', range(7001, 7100)), 101)]));
    $directOld = $directSync->start($site, $syncMerchant, 1);
    $directSync->advance($site, (int) $directOld['id']);
    Data::query('sync_run', $site)->where('id', $directOld['id'])->update(['started_at' => time() - 121 * 60]);
    $directNew = $sync(new ChannelFixtureTransport())->start($site, $syncMerchant, 1);
    channelCheck((int) $directNew['id'] !== (int) $directOld['id'] && Data::row('sync_run', $site, (int) $directOld['id'])['error_code'] === 'generation_expired' && (int) $directNew['page_count'] === 0, 'manual start creates an empty new generation when the existing staged one has expired');
    channelCheck((int) $read()['published_run_id'] === $published, 'starting replacement sync is not itself a new publication');
    $directDone = $sync(new ChannelFixtureTransport([channelPage(1, [channelRow(9101)], 1)]))->advance($site, (int) $directNew['id']);
    channelCheck($directDone['status'] === 'completed' && Data::query('inventory', $site)->where('sync_run_id', $directNew['id'])->column('source_id') === ['9101'], 'manual replacement publishes only its newly fetched device');
    $published = (int) $directDone['id'];

    // A shortened freshness window while page HTTP is in flight is rechecked at commit.
    Data::query('connection', $site)->where('id', $connection)->update(['sync_interval_minutes' => 120]);
    $commitSync = $sync(new ChannelFixtureTransport([channelPage(1, array_map('channelRow', range(8001, 8100)), 101)]));
    $commitRun = $commitSync->start($site, $syncMerchant, 1);
    $commitSync->advance($site, (int) $commitRun['id']);
    Data::query('sync_run', $site)->where('id', $commitRun['id'])->update(['started_at' => time() - 150 * 60]);
    $lastPage = new ChannelFixtureTransport([static function () use ($site, $connection): array {
        Data::query('connection', $site)->where('id', $connection)->update(['sync_interval_minutes' => 60]);
        return channelPage(2, [channelRow(8101)], 101);
    }]);
    $commitResult = $sync($lastPage)->advance($site, (int) $commitRun['id']);
    channelCheck($commitResult['status'] === 'cancelled' && $commitResult['error_code'] === 'generation_expired' && count($lastPage->calls) === 1, 'freshness is checked again when committing an in-flight final page');
    channelCheck((int) $read()['published_run_id'] === $published && Data::query('inventory', $site)->where('sync_run_id', $commitRun['id'])->count() === 100, 'expired commit cannot append its final page or replace the last good published batch');
    $stored = json_encode(Db::name('sys_config')->where('config_key', ProviderChannelService::KEY)->select()->toArray());
    $audit = json_encode(Data::query('audit', $site)->select()->toArray());
    channelCheck(!preg_match('/synthetic-channel-(?:password|old-token|fresh-token|uncommitted-token)/', (string) $stored.(string) $audit), 'channel configuration and audit contain no ERP passwords or tokens');
    channelCheck(count($other->available()) === $supportedCount, 'all shutdown and resume actions remain isolated to the configured site');
    Db::name('sys_config')->where('site_id', $site)->where('config_key', ProviderChannelService::KEY)->update(['value' => '{invalid-json']);
    channelCheck($service->available() === [] && count(array_filter($service->configuration()['items'], static fn (array $item): bool => $item['enabled'])) === 0, 'malformed persisted policy fails closed rather than silently enabling adapters');
    Data::query('connection', $site)->where('id', $connection)->update(['next_sync_at' => time() - 1]);
    channelCheck($sync(new ChannelFixtureTransport())->tick(1) === [], 'SQL policy gate safely skips malformed JSON before the scheduler limit');
    channelSet($service, $context, $allOn);
    channelCheck(count($service->available()) === $supportedCount, 'administrator can repair malformed policy through the normal explicit full save');
    echo json_encode(['passed' => true, 'checks' => $checks, 'php' => PHP_VERSION, 'scope' => 'site ERP channels, admission gates, paused sync safety, reopening preferences and in-flight closure', 'remote_calls' => 0, 'existing_business_rows_read' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL after '.$checks.' checks: '.get_class($error).': '.$error->getMessage().' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema !== '') leadCleanup($pdo, $schema);
}
exit($failed ? 1 : 0);
